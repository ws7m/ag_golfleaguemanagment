<?php

namespace App\Controllers\Admin;

use App\Libraries\Lookup;
use App\Libraries\RoundEntries;
use App\Models\LeagueGroupModel;
use App\Models\LeagueModel;
use App\Models\PlayerModel;
use App\Models\RoundModel;
use DateTimeImmutable;
use Dompdf\Dompdf;

class Groups extends CrudController
{
    protected string $modelClass = LeagueGroupModel::class;
    protected string $title      = 'Groups';
    protected string $singular   = 'Group';
    protected string $baseRoute  = 'admin/groups';
    // "tee_time" is the tee time for the nine the group is playing now.
    protected array $listColumns = ['group_number', 'nine', 'tee_time', 'player1_id', 'player2_id', 'player3_id', 'player4_id', 'active'];
    protected array $filters     = ['league_id'];
    // Clicking a group's Front/Back on the list switches it to the other nine.
    protected array $toggleColumns = ['nine' => 'admin/groups/{id}/switch-nine'];
    // Groups playing the front nine first, then the back nine; within each,
    // by the tee time for that nine (groups without one last), then number.
    protected array $orderBy = [
        "CASE WHEN nine = 'back' THEN 1 ELSE 0 END"                                  => 'ASC',
        "CASE WHEN nine = 'back' THEN back_tee_time ELSE front_tee_time END IS NULL" => 'ASC',
        "CASE WHEN nine = 'back' THEN back_tee_time ELSE front_tee_time END"         => 'ASC',
        'group_number'                                                               => 'ASC',
    ];
    protected ?string $extraIndexView = 'admin/groups/tools';
    protected array $headActions      = ['Tee time list' => 'admin/groups/tee-sheet', 'Switch front/back' => '#switch-nine', 'Shift tee times' => '#shift-tee-times', 'Create groups for players' => '#create-groups', 'Delete all groups' => '#delete-groups'];

    // Players in a group, for working out how many groups a league needs.
    public const PLAYERS_PER_GROUP = 4;

    protected function fields(): array
    {
        $fields = [
            'league_id'         => ['label' => 'League', 'type' => 'select', 'options' => 'leagues', 'default' => 'currentLeague', 'required' => true],
            'group_number'      => ['label' => 'Group number', 'type' => 'number', 'required' => true],
            'nine'              => ['label' => 'Playing', 'type' => 'select', 'options' => ['front' => 'Front nine', 'back' => 'Back nine'], 'listOptions' => ['front' => 'Front', 'back' => 'Back'], 'default' => 'front', 'required' => true,
                'help' => 'The nine this group is playing now. Use "Switch front/back for all groups" on the Groups page to swap every group between rounds.'],
            'front_tee_time'    => ['label' => 'Front nine tee time', 'type' => 'time', 'compact' => 'tee'],
            'back_tee_time'     => ['label' => 'Back nine tee time', 'type' => 'time', 'compact' => 'tee'],
        ];
        for ($i = 1; $i <= 6; $i++) {
            $fields["player{$i}_id"] = ['label' => "Player {$i}", 'type' => 'select', 'options' => 'players', 'listOptions' => 'playersShort'];
        }
        $fields['active'] = ['label' => 'Active', 'type' => 'checkbox', 'default' => 1];

        return $fields;
    }

    protected function listFields(): array
    {
        return ['tee_time' => ['label' => 'Tee time']];
    }

    /**
     * The list shows only the tee time for the nine each group is playing.
     */
    protected function listRows(array $rows, array $filterValues): array
    {
        foreach ($rows as &$row) {
            $row['tee_time'] = $row['nine'] === 'back' ? $row['back_tee_time'] : $row['front_tee_time'];
        }

        return $rows;
    }

    /**
     * On the add/edit form, offer only the league's registered players who
     * are not in another group (plus this group's own).
     */
    protected function formFields(array $record, ?int $id): array
    {
        $fields   = $this->resolvedFields();
        $leagueId = (int) ($record['league_id'] ?? 0) ?: Lookup::currentLeagueId();
        $taken    = $this->playersInOtherGroups((int) $leagueId, $id);

        $current = [];
        for ($i = 1; $i <= 6; $i++) {
            $current[] = (int) ($record["player{$i}_id"] ?? 0);
        }
        $players = (new PlayerModel())
            ->groupStart()
                ->groupStart()->where('league_id', $leagueId)->where('is_player', 1)->where('active', 1)->groupEnd()
                ->orWhereIn('id', array_filter($current) ?: [0])
            ->groupEnd()
            ->orderBy('LOWER(last_name)', 'ASC', false)
            ->orderBy('LOWER(first_name)', 'ASC', false)
            ->findAll();
        $options = [];
        foreach ($players as $player) {
            $playerId = (int) $player['id'];
            if (in_array($playerId, $current, true) || ! isset($taken[$playerId])) {
                $options[$playerId] = "{$player['last_name']}, {$player['first_name']}";
            }
        }
        for ($i = 1; $i <= 6; $i++) {
            $fields["player{$i}_id"]['options'] = $options;
        }
        $fields['player1_id']['help'] = 'Only players registered in this league who are not in another group are listed.';

        return $fields;
    }

    /**
     * Players already on the league's other groups.
     *
     * @return array<int, int> player id => group number
     */
    private function playersInOtherGroups(int $leagueId, ?int $exceptGroupId): array
    {
        $query = (new LeagueGroupModel())->where('league_id', $leagueId);
        if ($exceptGroupId !== null) {
            $query->where('id !=', $exceptGroupId);
        }

        $taken = [];
        foreach ($query->findAll() as $group) {
            foreach (LeagueGroupModel::playerIds($group) as $playerId) {
                $taken[$playerId] = (int) $group['group_number'];
            }
        }

        return $taken;
    }

    /**
     * Creates enough empty groups for the league's registered players at 4
     * players to a group (8 players need 2 groups, 9 need 3). Groups the
     * league already has count towards that. New groups are numbered after
     * the existing ones and start on the front nine, with no tee times or
     * players.
     */
    public function generate()
    {
        $leagueId = (int) $this->request->getPost('league_id');
        $back     = redirect()->to($this->baseRoute . '?league_id=' . $leagueId);
        $league   = $leagueId ? (new LeagueModel())->find($leagueId) : null;
        if ($league === null) {
            return $back->with('error', 'Choose a league.');
        }

        $registered = (new PlayerModel())
            ->where('league_id', $leagueId)->where('is_player', 1)->where('active', 1)
            ->countAllResults();
        if ($registered === 0) {
            return $back->with('error', "{$league['name']} has no registered players yet.");
        }

        $model    = new LeagueGroupModel();
        $existing = $model->where('league_id', $leagueId)->findAll();
        $needed   = (int) ceil($registered / self::PLAYERS_PER_GROUP);
        $summary  = "{$registered} registered players need {$needed} groups of " . self::PLAYERS_PER_GROUP . '.';
        if (count($existing) >= $needed) {
            return $back->with('message', "{$summary} The league already has " . count($existing) . ' groups, so none were created.');
        }

        $number = (int) max([0, ...array_column($existing, 'group_number')]) + 1;
        $first  = $number;
        $db     = db_connect();
        $db->transStart();
        for ($i = count($existing); $i < $needed; $i++) {
            $model->insert(['league_id' => $leagueId, 'group_number' => $number++, 'nine' => 'front', 'active' => 1]);
        }
        $db->transComplete();

        $created = $number - $first;
        $range   = $created === 1 ? "group {$first}" : "groups {$first}–" . ($number - 1);

        return $back->with('message', "{$summary} Created {$created} ({$range}). Set their tee times and players on each group.");
    }

    /**
     * The tee time list page: pick the league and the round to show, preview
     * the front and back lists, and download each as a PDF.
     */
    public function teeSheet(): string
    {
        [$leagueId, $league, $round, $date, $rounds] = $this->teeSheetRequest();
        $notPlaying = $this->playersNotPlaying($round);

        return view('admin/groups/tee_sheet', [
            'title'    => 'Tee time list',
            'league'   => $league,
            'leagues'  => Lookup::leagues(),
            'round'    => $round,
            'rounds'   => $rounds,
            'date'     => $date,
            'sections' => [
                'front' => $this->teeSheetRows($leagueId, 'front', $notPlaying),
                'back'  => $this->teeSheetRows($leagueId, 'back', $notPlaying),
            ],
        ]);
    }

    /**
     * Downloads the front or back tee time list as a PDF named
     * TeeTimeList-<Front|Back>-<date>.pdf, for emailing.
     */
    public function teeSheetPdf()
    {
        [$leagueId, $league, $round, $date] = $this->teeSheetRequest();
        $nine = $this->request->getGet('nine') === 'back' ? 'back' : 'front';

        $html = view('admin/groups/tee_sheet_pdf', [
            'heading' => self::teeSheetHeading($league['name'] ?? '', $nine, $date),
            'rows'    => $this->teeSheetRows($leagueId, $nine, $this->playersNotPlaying($round)),
        ]);

        $pdf = new Dompdf();
        $pdf->loadHtml($html);
        $pdf->setPaper('letter', 'portrait');
        $pdf->render();

        return $this->response
            ->setHeader('Content-Type', 'application/pdf')
            ->setHeader('Content-Disposition', 'attachment; filename="' . self::teeSheetFilename($nine, $date) . '"')
            ->setBody($pdf->output());
    }

    /**
     * "Monday Mongrels - 2027 - Tee time list - Front - 5/4/2027"
     */
    public static function teeSheetHeading(string $leagueName, string $nine, string $date): string
    {
        $parts = [trim($leagueName), 'Tee time list', ucfirst($nine), trim($date)];

        return implode(' - ', array_filter($parts, static fn ($p) => $p !== ''));
    }

    /**
     * "TeeTimeList-Front-5-4-2027.pdf". Anything in the date other than
     * letters, numbers, - and _ becomes a dash, so "5/4/2027" or
     * "May 4, 2027" are safe in a file name.
     */
    public static function teeSheetFilename(string $nine, string $date): string
    {
        $safe = trim((string) preg_replace('/[^A-Za-z0-9_-]+/', '-', $date), '-');

        return 'TeeTimeList-' . ucfirst($nine) . '-' . ($safe !== '' ? $safe : 'undated') . '.pdf';
    }

    /**
     * League and round from the query string. Without a round (or with one
     * from another league) the league's next round from today is used, or
     * its last round if none are left. The round's date is shown as
     * month/day/year, e.g. 5/4/2027; with no rounds there is no date.
     *
     * @return array{0: int, 1: ?array, 2: ?array, 3: string, 4: array<int, string>}
     *         league id, league, round, display date, round choices (id => label)
     */
    private function teeSheetRequest(): array
    {
        $leagueId = (int) ($this->request->getGet('league_id') ?: Lookup::currentLeagueId());
        $rounds   = (new RoundModel())->where('league_id', $leagueId)->orderBy('round_date')->orderBy('id')->findAll();
        $byId     = array_column($rounds, null, 'id');

        $round = $byId[(int) $this->request->getGet('round_id')] ?? null;
        if ($round === null && $rounds !== []) {
            $today = date('Y-m-d');
            $next  = array_values(array_filter($rounds, static fn ($r) => $r['round_date'] >= $today));
            $round = $next[0] ?? end($rounds);
        }

        $choices = [];
        foreach ($rounds as $r) {
            $choices[(int) $r['id']] = trim(self::displayDate($r['round_date']) . ' ' . ($r['name'] ?? ''));
        }

        return [$leagueId, (new LeagueModel())->find($leagueId), $round, $round ? self::displayDate($round['round_date']) : '', $choices];
    }

    /**
     * "2027-05-04" as "5/4/2027".
     */
    private static function displayDate(string $isoDate): string
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $isoDate);

        return $date ? $date->format('n/j/Y') : $isoDate;
    }

    /**
     * Ids of the players who said they are not playing in a round: their
     * score record says not playing, or, without a score record, they
     * unticked Playing while the round was scheduled.
     *
     * @return list<int>
     */
    private function playersNotPlaying(?array $round): array
    {
        if ($round === null) {
            return [];
        }
        $db       = db_connect();
        $roundId  = (int) $round['id'];
        $scored   = $db->table('scores')->select('player_id, playing')->where('round_id', $roundId)->get()->getResultArray();
        $playing  = array_map('intval', array_column($scored, 'playing', 'player_id'));
        $playing += RoundEntries::playingChoices($roundId);

        return array_keys(array_filter($playing, static fn ($p) => $p === 0));
    }

    /**
     * The active groups playing one nine, in ascending tee time order (groups
     * without a time last, then by group number), each with its first four
     * player places as "First Last", or "Open" where no player is set or
     * the player is not playing in the round.
     *
     * @param list<int> $notPlaying Players shown as Open
     *
     * @return list<array{time: ?string, number: int, players: list<string>}>
     */
    private function teeSheetRows(int $leagueId, string $nine, array $notPlaying = []): array
    {
        $groups = (new LeagueGroupModel())
            ->where('league_id', $leagueId)
            ->where('active', 1)
            ->findAll();
        $groups = array_values(array_filter($groups, static fn ($g) => ($g['nine'] === 'back' ? 'back' : 'front') === $nine));

        $playerIds = [];
        foreach ($groups as $group) {
            $playerIds = [...$playerIds, ...LeagueGroupModel::playerIds($group)];
        }
        $names = [];
        if ($playerIds !== []) {
            foreach ((new PlayerModel())->whereIn('id', $playerIds)->findAll() as $player) {
                $names[(int) $player['id']] = "{$player['first_name']} {$player['last_name']}";
            }
        }

        $rows = [];
        foreach ($groups as $group) {
            $players = [];
            for ($i = 1; $i <= self::PLAYERS_PER_GROUP; $i++) {
                $playerId  = (int) ($group["player{$i}_id"] ?? 0);
                $players[] = in_array($playerId, $notPlaying, true) ? 'Open' : ($names[$playerId] ?? 'Open');
            }
            $rows[] = [
                'time'    => $group["{$nine}_tee_time"],
                'number'  => (int) $group['group_number'],
                'players' => $players,
            ];
        }
        usort($rows, static fn ($a, $b) => [$a['time'] === null, $a['time'], $a['number']] <=> [$b['time'] === null, $b['time'], $b['number']]);

        return $rows;
    }

    /**
     * Moves the front nine (or back nine) tee time of every group in a league
     * earlier or later by a number of minutes, e.g. for daylight changes.
     * Groups without that tee time are skipped. Nothing changes if any time
     * would move past midnight.
     */
    public function shiftTeeTimes()
    {
        $leagueId = (int) $this->request->getPost('league_id');
        $nine     = (string) $this->request->getPost('nine');
        $minutes  = (int) $this->request->getPost('minutes');
        $back     = redirect()->to($this->baseRoute . '?league_id=' . $leagueId);
        if ($leagueId <= 0 || ! in_array($nine, ['front', 'back'], true) || $minutes === 0) {
            return $back->with('error', 'Choose a league and a number of minutes other than 0.');
        }

        $field  = "{$nine}_tee_time";
        $model  = new LeagueGroupModel();
        $groups = $model->where('league_id', $leagueId)->where("{$field} IS NOT NULL")->findAll();
        $moved  = [];
        foreach ($groups as $group) {
            if (! preg_match('/^(\d{2}):(\d{2})$/', (string) $group[$field], $m)) {
                continue;
            }
            $total = (int) $m[1] * 60 + (int) $m[2] + $minutes;
            if ($total < 0 || $total >= 24 * 60) {
                return $back->with('error', "Shifting by {$minutes} minutes would move group {$group['group_number']}'s {$nine} nine tee time ({$group[$field]}) past midnight. Nothing was changed.");
            }
            $moved[$group['id']] = sprintf('%02d:%02d', intdiv($total, 60), $total % 60);
        }

        $db = db_connect();
        $db->transStart();
        foreach ($moved as $id => $time) {
            $model->update($id, [$field => $time]);
        }
        $db->transComplete();

        $direction = $minutes > 0 ? 'later' : 'earlier';

        return $back->with('message', count($moved) . " groups' {$nine} nine tee times moved " . abs($minutes) . " minutes {$direction}.");
    }

    /**
     * Switches one group to the other nine: front becomes back, and back
     * becomes front.
     */
    public function switchGroupNine(int $id)
    {
        $group = $this->findOr404($id);
        $nine  = $group['nine'] === 'front' ? 'back' : 'front';
        (new LeagueGroupModel())->update($id, ['nine' => $nine]);

        return redirect()->to($this->baseRoute . '?league_id=' . $group['league_id'])
            ->with('message', "Group {$group['group_number']} now plays the {$nine} nine.");
    }

    /**
     * Switches every group in a league to the other nine: front becomes back
     * and back becomes front.
     */
    public function switchNine()
    {
        $leagueId = (int) $this->request->getPost('league_id');
        $back     = redirect()->to($this->baseRoute . '?league_id=' . $leagueId);
        if ($leagueId <= 0) {
            return $back->with('error', 'Choose a league.');
        }

        $db    = db_connect();
        $table = $db->prefixTable('league_groups');
        $db->query(
            "UPDATE {$table} SET nine = CASE nine WHEN 'front' THEN 'back' ELSE 'front' END, updated_at = ? WHERE league_id = ?",
            [date('Y-m-d H:i:s'), $leagueId]
        );
        $count = $db->affectedRows();

        return $back->with('message', "{$count} groups switched to the other nine.");
    }

    /**
     * Deletes every group in a league so the admin can start over. Scores
     * that recorded one of these groups as "group played in" are cleared
     * first, since a group can't be deleted while a score points to it.
     */
    public function deleteAll()
    {
        $leagueId = (int) $this->request->getPost('league_id');
        $back     = redirect()->to($this->baseRoute . '?league_id=' . $leagueId);
        if ($leagueId <= 0) {
            return $back->with('error', 'Choose a league.');
        }

        $ids = array_map('intval', array_column(
            (new LeagueGroupModel())->select('id')->where('league_id', $leagueId)->findAll(),
            'id'
        ));
        if ($ids === []) {
            return $back->with('message', 'There were no groups to delete.');
        }

        $db = db_connect();
        $db->transStart();
        $db->table('scores')->whereIn('group_id', $ids)->update(['group_id' => null]);
        $clearedScores = $db->affectedRows();
        (new LeagueGroupModel())->whereIn('id', $ids)->delete();
        $db->transComplete();

        $message = count($ids) . ' groups deleted.';
        if ($clearedScores > 0) {
            $message .= " {$clearedScores} scores no longer show a group played in.";
        }

        return $back->with('message', $message);
    }

    protected function checkBeforeSave(array $data, ?int $id): ?string
    {
        // A player already on another group of the league can't be added to
        // this one too (players the group already had are allowed).
        $before = $id === null ? [] : (new LeagueGroupModel())->find($id);
        $taken  = $this->playersInOtherGroups((int) $data['league_id'], $id);
        $beforePlayers = $before === [] ? [] : LeagueGroupModel::playerIds($before);
        for ($i = 1; $i <= 6; $i++) {
            $playerId = (int) ($data["player{$i}_id"] ?? 0);
            if ($playerId && ! in_array($playerId, $beforePlayers, true) && isset($taken[$playerId])) {
                $player = (new PlayerModel())->find($playerId);

                return "{$player['first_name']} {$player['last_name']} is already in group {$taken[$playerId]}.";
            }
        }

        $players = array_filter(array_map(static fn ($i) => $data["player{$i}_id"], range(1, 6)));
        if (count($players) !== count(array_unique($players))) {
            return 'The same player is listed more than once in this group.';
        }

        return null;
    }
}
