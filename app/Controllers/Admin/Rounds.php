<?php

namespace App\Controllers\Admin;

use App\Libraries\RoundEntries;
use App\Models\LeagueModel;
use App\Models\RoundModel;
use DateTimeImmutable;

class Rounds extends CrudController
{
    protected string $modelClass = RoundModel::class;
    protected string $title      = 'Rounds';
    protected string $singular   = 'Round';
    protected string $baseRoute  = 'admin/rounds';
    protected array $listColumns = ['round_date', 'name', 'course_id', 'game_id', 'status', 'entry_fee_cents'];
    protected array $filters     = ['league_id'];
    protected array $orderBy     = ['round_date' => 'ASC'];
    protected ?string $extraIndexView = 'admin/rounds/generate';
    protected array $headActions      = ['Generate rounds' => '#generate', 'Delete all rounds' => '#delete-rounds'];
    // The status on the list is a dropdown that saves straight away.
    protected array $selectColumns    = ['status' => 'admin/rounds/{id}/status'];

    // Safety limit on how many rounds one click can create.
    private const MAX_GENERATED = 52;

    protected function fields(): array
    {
        return [
            'league_id'        => ['label' => 'League', 'type' => 'select', 'options' => 'leagues', 'default' => 'currentLeague', 'required' => true],
            'round_date'       => ['label' => 'Date', 'type' => 'date', 'required' => true],
            'name'             => ['label' => 'Name'],
            'description'      => ['label' => 'Description', 'type' => 'textarea'],
            'course_id'        => ['label' => 'Course', 'type' => 'select', 'options' => 'courses', 'required' => true],
            'game_id'          => ['label' => 'Game', 'type' => 'select', 'options' => 'games'],
            'status'           => ['label' => 'Status', 'type' => 'select', 'default' => 'scheduled', 'required' => true, 'options' => [
                'scheduled' => 'Scheduled', 'active' => 'Active (live scoring open)',
                'closed'    => 'Closed (no new scores)', 'scored' => 'Scored (results done)',
            ], 'listOptions' => ['scheduled' => 'Scheduled', 'active' => 'Active', 'closed' => 'Closed', 'scored' => 'Scored']],
            'entry_fee_cents'  => ['label' => 'Entry fee', 'type' => 'money', 'default' => 1000],
            'use_for_handicap' => ['label' => 'Use for handicap', 'type' => 'checkbox'],
            'total_pot_cents'  => ['label' => 'Total pot', 'type' => 'money', 'help' => 'Total of paid entry fees. Filled in when the round is scored.'],
            'image'            => ['label' => 'Image', 'help' => 'Picture uploads come later; for now a file name or link.'],
        ];
    }

    protected function rowActions(): array
    {
        return ['Scores' => 'admin/scores?round_id={id}'];
    }

    /**
     * Sets a round's status from the dropdown on the rounds list.
     */
    public function setStatus(int $id)
    {
        $round  = $this->findOr404($id);
        $status = (string) $this->request->getPost('value');
        $back   = redirect()->to($this->baseRoute . '?league_id=' . $round['league_id']);
        $labels = $this->fields()['status']['options'];
        if (! isset($labels[$status])) {
            return $back->with('error', 'Choose a status from the list.');
        }

        (new RoundModel())->update($id, ['status' => $status]);
        $name    = trim("{$round['round_date']} {$round['name']}");
        $message = "{$name} is now {$labels[$status]}.";

        if ($status === 'active') {
            $message .= ' ' . $this->addPlayersToRound($round);
        }

        return $back->with('message', $message);
    }

    /**
     * When a round is saved from its edit page as Active, enter the players
     * the same way as from the list.
     */
    protected function afterSave(int $id, array $data): void
    {
        if (($data['status'] ?? null) === 'active') {
            $this->saveNote = $this->addPlayersToRound((new RoundModel())->find($id));
        }
    }

    /**
     * Gives the league's players score records for the round (see
     * RoundEntries::addPlayers()).
     *
     * @return string A sentence saying how many were added
     */
    private function addPlayersToRound(array $round): string
    {
        [$added, $kept] = RoundEntries::addPlayers($round);

        return "{$added} score records added for players." . ($kept > 0 ? " {$kept} players already had one and were left alone." : '');
    }

    /**
     * Deletes every round in a league that has no scores yet, to undo
     * mistakes such as generating rounds with the wrong dates. Rounds that
     * already have scores are kept.
     */
    public function deleteAll()
    {
        $leagueId = (int) $this->request->getPost('league_id');
        $back     = redirect()->to($this->baseRoute . '?league_id=' . $leagueId);
        $league   = $leagueId ? (new LeagueModel())->find($leagueId) : null;

        if ($league === null) {
            return $back->with('error', 'Choose a league.');
        }

        $db        = db_connect();
        $hasScores = $db->table('scores')->select('round_id')->distinct()->get()->getResultArray();
        $hasScores = array_map('intval', array_column($hasScores, 'round_id'));

        $rounds  = (new RoundModel())->where('league_id', $leagueId)->findAll();
        $delete  = [];
        $kept    = 0;
        foreach ($rounds as $round) {
            if (in_array((int) $round['id'], $hasScores, true)) {
                $kept++;
            } else {
                $delete[] = (int) $round['id'];
            }
        }

        if ($delete !== []) {
            (new RoundModel())->whereIn('id', $delete)->delete();
        }

        $message = count($delete) . " rounds deleted from {$league['name']}.";
        if ($kept > 0) {
            $message .= " {$kept} kept because they already have scores.";
        }

        return $back->with('message', $message);
    }

    /**
     * Creates weekly rounds for a league: the first on the league's start
     * date, then every 7 days, named Round 1, Round 2, and so on. Runs to the
     * league's end date, or for the number of rounds entered. Weeks that
     * already have a round on that date are skipped.
     */
    public function generate()
    {
        $leagueId = (int) $this->request->getPost('league_id');
        $courseId = (int) $this->request->getPost('course_id');
        $gameId   = (int) $this->request->getPost('game_id') ?: null;
        $count    = (int) $this->request->getPost('count');
        $back     = redirect()->to($this->baseRoute . '?league_id=' . $leagueId);
        $league   = $leagueId ? (new LeagueModel())->find($leagueId) : null;

        if ($league === null || $courseId <= 0) {
            return $back->with('error', 'Choose a league and a course to generate rounds.');
        }
        if (empty($league['start_date'])) {
            return $back->with('error', "Set a starting date on {$league['name']} first.");
        }

        $start = new DateTimeImmutable($league['start_date']);
        if ($count <= 0) {
            if (empty($league['end_date'])) {
                return $back->with('error', "{$league['name']} has no ending date. Enter the number of rounds to create.");
            }
            $days  = (int) $start->diff(new DateTimeImmutable($league['end_date']))->format('%r%a');
            $count = $days < 0 ? 0 : intdiv($days, 7) + 1;
        }
        if ($count <= 0 || $count > self::MAX_GENERATED) {
            return $back->with('error', 'Rounds can be generated 1 to ' . self::MAX_GENERATED . ' at a time. Check the league dates or the number of rounds.');
        }

        $model    = new RoundModel();
        $existing = array_column($model->where('league_id', $leagueId)->findAll(), 'round_date');
        $created  = 0;
        $skipped  = 0;

        $db = db_connect();
        $db->transStart();
        for ($week = 0; $week < $count; $week++) {
            $date = $start->modify('+' . (7 * $week) . ' days')->format('Y-m-d');
            if (in_array($date, $existing, true)) {
                $skipped++;

                continue;
            }
            $model->insert([
                'league_id'  => $leagueId,
                'course_id'  => $courseId,
                'game_id'    => $gameId,
                'round_date' => $date,
                'name'       => 'Round ' . ($week + 1),
                'status'     => 'scheduled',
            ]);
            $created++;
        }
        $db->transComplete();

        $message = "{$created} rounds created.";
        if ($skipped > 0) {
            $message .= " {$skipped} skipped because a round already exists on that date.";
        }

        return $back->with('message', $message);
    }
}
