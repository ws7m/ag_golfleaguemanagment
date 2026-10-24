<?php

namespace App\Controllers\Admin;

use App\Libraries\Lookup;
use App\Models\RoundModel;
use App\Models\ScoreModel;

class Scores extends CrudController
{
    protected string $modelClass = ScoreModel::class;
    protected string $title      = 'Scores';
    protected string $singular   = 'Score';
    protected string $baseRoute  = 'admin/scores';
    protected array $listColumns = ['round_id', 'player_id', 'nine', 'playing', 'paid', 'team_number', 'holes', 'gross', 'net', 'place', 'winnings_cents'];
    // Clicking Yes/No for Playing or Paid on the list switches it.
    protected array $toggleColumns = [
        'playing' => 'admin/scores/{id}/toggle/playing',
        'paid'    => 'admin/scores/{id}/toggle/paid',
    ];
    // Small boxes for the nine holes played, saved with "Save hole scores".
    protected array $cellViews = ['team_number' => 'admin/scores/team_cell', 'holes' => 'admin/scores/hole_cells'];

    // Teams of more players than this get a warning when saved.
    public const MAX_TEAM_SIZE = 4;
    protected array $filters     = ['round_id'];
    // Newest round first; within a round, teams together (team 1, team 2, ...)
    // with individual scores (team 0) after them.
    protected array $orderBy = [
        'round_id'                                    => 'DESC',
        'CASE WHEN team_number = 0 THEN 1 ELSE 0 END' => 'ASC',
        'team_number'                                 => 'ASC',
        'player_id'                                   => 'ASC',
    ];
    protected ?string $extraIndexView = 'admin/scores/tools';
    protected array $headActions      = ['Delete all scores' => '#delete-scores'];

    protected function fields(): array
    {
        $fields = [
            'round_id'  => ['label' => 'Round', 'type' => 'select', 'options' => 'rounds', 'listOptions' => 'roundsShort', 'required' => true],
            'player_id' => ['label' => 'Player', 'type' => 'select', 'options' => 'players', 'listOptions' => 'playerNames', 'required' => true],
            'group_id'  => ['label' => 'Group played in', 'type' => 'select', 'options' => 'groups', 'listOptions' => 'groupsShort'],
            'nine'      => ['label' => 'Nine played', 'listLabel' => 'Nine', 'type' => 'select', 'options' => ['front' => 'Front', 'back' => 'Back']],
            'playing'   => ['label' => 'Playing', 'type' => 'checkbox', 'default' => 1],
            'paid'      => ['label' => 'Paid', 'type' => 'checkbox'],
            'locked'    => ['label' => 'Locked', 'type' => 'checkbox'],
            'team_number' => ['label' => 'Team', 'type' => 'number', 'default' => 0,
                'help' => 'Players in this round with the same team number are a team. 0 = individual score.'],
        ];
        for ($i = 1; $i <= 18; $i++) {
            $fields["hole{$i}"] = ['label' => "Hole {$i}", 'type' => 'number', 'compact' => 'holes'];
        }

        return $fields + [
            'gross'          => ['label' => 'Gross', 'type' => 'number', 'compact' => 'results', 'help' => 'Leave blank to total the nine played automatically.'],
            'handicap_used'  => ['label' => 'Handicap used', 'type' => 'number', 'compact' => 'results'],
            'net'            => ['label' => 'Net', 'type' => 'number', 'compact' => 'results'],
            'place'          => ['label' => 'Place', 'type' => 'number', 'compact' => 'results'],
            'winnings_cents' => ['label' => 'Winnings', 'listLabel' => 'Won', 'type' => 'money', 'compact' => 'results'],
            'image'          => ['label' => 'Scorecard picture', 'help' => 'Picture uploads come later; for now a file name or link.'],
        ];
    }

    /**
     * Deletes the scores for one round, or for every round in a league, so
     * the admin can start over. Scores are not used by anything else.
     */
    public function deleteAll()
    {
        $leagueId = (int) $this->request->getPost('league_id');
        $roundId  = (int) $this->request->getPost('round_id');
        $back     = redirect()->to($this->baseRoute . ($roundId ? '?round_id=' . $roundId : ''));

        $round = $roundId ? (new RoundModel())->find($roundId) : null;
        if ($roundId && $round === null) {
            return $back->with('error', 'That round was not found.');
        }
        if ($round === null && $leagueId <= 0) {
            return $back->with('error', 'Choose a league or a round.');
        }

        $query = db_connect()->table('scores');
        $round ? $query->where('round_id', $roundId) : $query->where('league_id', $leagueId);
        $query->delete();
        $count = db_connect()->affectedRows();

        $from = $round ? 'the ' . trim("{$round['round_date']} {$round['name']}") . ' round' : 'every round in the league';

        return $back->with('message', "{$count} scores deleted from {$from}.");
    }

    protected function listFields(): array
    {
        return ['holes' => ['label' => 'Holes']];
    }

    /**
     * Switches a score's Playing or Paid between yes and no.
     */
    public function toggle(int $id, string $field)
    {
        $score = $this->findOr404($id);
        if (! in_array($field, ['playing', 'paid'], true)) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }
        db_connect()->table('scores')->where('id', $id)->update([$field => $score[$field] ? 0 : 1, 'updated_at' => date('Y-m-d H:i:s')]);

        return redirect()->to($this->listUrl());
    }

    /**
     * Saves the team numbers and hole scores typed into the list: for each
     * score, its team number (blank means 0) and the holes of the nine it is
     * playing (front if not set yet; blank means 0, not entered yet). Gross
     * (and net, when a handicap is set) are worked out once every hole of
     * the nine is entered. Warns about teams of an unusual size.
     */
    public function saveScores()
    {
        $posted = $this->request->getPost('scores') ?? [];
        $model  = new ScoreModel();
        $errors = [];
        $saved  = 0;
        $rounds = [];

        $db = db_connect();
        $db->transStart();
        foreach ($posted as $id => $holes) {
            $score = $model->find((int) $id);
            if ($score === null || ! is_array($holes)) {
                continue;
            }
            $team = null;
            if (array_key_exists('team_number', $holes)) {
                $team = trim((string) $holes['team_number']);
                if ($team !== '' && (! ctype_digit($team) || (int) $team > 99)) {
                    $errors[] = "Team must be a number from 0 to 99 (score {$id}).";

                    continue;
                }
            }
            [$changes, $error] = ScoreModel::holeChanges($score, $holes);
            if ($error !== null) {
                $errors[] = rtrim($error, '.') . " (score {$id}).";

                continue;
            }
            if ($team !== null) {
                $changes['team_number'] = (int) $team;
            }
            $changes['updated_at'] = date('Y-m-d H:i:s');
            $db->table('scores')->where('id', (int) $id)->update($changes);
            $rounds[(int) $score['round_id']] = true;
            $saved++;
        }
        $db->transComplete();

        $back     = redirect()->to($this->listUrl());
        $warnings = self::teamSizeWarnings(array_keys($rounds));
        if ($errors !== []) {
            return $back->with('error', trim(implode(' ', $errors) . " {$saved} other scores were saved. {$warnings}"));
        }

        return $back->with('message', trim("{$saved} scores saved. {$warnings}"));
    }

    /**
     * Sets each score's team number to its group number, for the round
     * shown, so teams start out as the groups players tee off with. Scores
     * without a group are not changed.
     */
    public function teamsFromGroups()
    {
        $roundId = (int) $this->request->getPost('round_id');
        $back    = redirect()->to(site_url($this->baseRoute . ($roundId ? "?round_id={$roundId}" : '')));
        if ($roundId <= 0 || (new RoundModel())->find($roundId) === null) {
            return $back->with('error', 'Choose a round first.');
        }

        $db     = db_connect();
        $scores = $db->prefixTable('scores');
        $groups = $db->prefixTable('league_groups');
        $db->query(
            "UPDATE {$scores} SET team_number = (SELECT g.group_number FROM {$groups} g WHERE g.id = {$scores}.group_id), updated_at = ?
             WHERE round_id = ? AND group_id IS NOT NULL",
            [date('Y-m-d H:i:s'), $roundId]
        );
        $count = $db->affectedRows();

        return $back->with('message', trim("{$count} scores set to their group's number as their team. " . self::teamSizeWarnings([$roundId])));
    }

    /**
     * A sentence warning about teams with only 1 player or more than 4, in
     * the given rounds, or an empty string.
     *
     * @param list<int> $roundIds
     */
    public static function teamSizeWarnings(array $roundIds): string
    {
        if ($roundIds === []) {
            return '';
        }
        $rows = db_connect()->table('scores s')
            ->select('s.team_number, COUNT(*) AS players, r.round_date, r.name')
            ->join('rounds r', 'r.id = s.round_id')
            ->whereIn('s.round_id', $roundIds)
            ->where('s.team_number >', 0)
            ->groupBy('s.round_id, s.team_number, r.round_date, r.name')
            ->orderBy('r.round_date')->orderBy('s.team_number')
            ->get()->getResultArray();

        $problems = [];
        foreach ($rows as $row) {
            $players = (int) $row['players'];
            if ($players === 1 || $players > self::MAX_TEAM_SIZE) {
                $where      = count($roundIds) > 1 ? ' in ' . trim("{$row['round_date']} {$row['name']}") : '';
                $problems[] = "Team {$row['team_number']}{$where} has {$players} " . ($players === 1 ? 'player' : 'players');
            }
        }

        return $problems === [] ? '' : 'Check teams: ' . implode('; ', $problems) . '.';
    }

    /**
     * The scores list, keeping the round it was showing.
     */
    private function listUrl(): string
    {
        parse_str((string) $this->request->getPost('return_query'), $query);
        $roundId = (int) ($this->request->getGet('round_id') ?: $this->request->getPost('return_round_id') ?: ($query['round_id'] ?? 0));

        return site_url($this->baseRoute . ($roundId ? "?round_id={$roundId}" : ''));
    }

    public function new(): string
    {
        $record             = ['playing' => 1];
        $record['round_id'] = $this->request->getGet('round_id');

        return $this->form($record, null);
    }

    /**
     * Fills in the league from the round, and gross/net when they are blank.
     */
    protected function collect(): array
    {
        $data  = parent::collect();
        // A blank team means 0: an individual score.
        $data['team_number'] = (int) ($data['team_number'] ?? 0);
        $round = $data['round_id'] ? (new RoundModel())->find($data['round_id']) : null;
        $data['league_id'] = $round['league_id'] ?? Lookup::currentLeagueId();

        if ($data['gross'] === null) {
            $data['gross'] = ScoreModel::grossForNine($data);
        }
        if ($data['net'] === null && $data['gross'] !== null && $data['handicap_used'] !== null) {
            $data['net'] = (int) $data['gross'] - (int) $data['handicap_used'];
        }

        return $data;
    }

    protected function afterSave(int $id, array $data): void
    {
        $this->saveNote = self::teamSizeWarnings([(int) $data['round_id']]);
    }

    protected function checkBeforeSave(array $data, ?int $id): ?string
    {
        $existing = (new ScoreModel())
            ->where('round_id', $data['round_id'])
            ->where('player_id', $data['player_id']);
        if ($id !== null) {
            $existing->where('id !=', $id);
        }

        return $existing->countAllResults() > 0
            ? 'This player already has a score for this round.'
            : null;
    }
}
