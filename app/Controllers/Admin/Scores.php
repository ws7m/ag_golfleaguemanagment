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
    protected array $listColumns = ['player_id', 'nine', 'playing', 'paid', 'team_number', 'holes', 'gross', 'handicap_used', 'net', 'place', 'winnings_cents'];
    // Clicking Yes/No for Playing or Paid on the list switches it.
    protected array $toggleColumns = [
        'playing' => 'admin/scores/{id}/toggle/playing',
        'paid'    => 'admin/scores/{id}/toggle/paid',
    ];
    // Small boxes for the nine holes played, saved with "Save hole scores".
    protected array $cellViews = [
        'team_number'   => 'admin/scores/team_cell',
        'holes'         => 'admin/scores/hole_cells',
        // A Handicap box; changing it updates Net on the row straight away.
        'handicap_used' => 'admin/scores/handicap_cell',
        'net'           => 'admin/scores/net_cell',
    ];

    // Teams of more players than this get a warning when saved.
    public const MAX_TEAM_SIZE = ScoreModel::TEAM_MEMBER_SLOTS;
    // The list always shows one round, chosen at the top.
    protected array $filters         = ['round_id'];
    protected array $requiredFilters = ['round_id'];
    // Newest round first; within a round, teams together (team 1, team 2, ...)
    // with individual scores (team 0) after them.
    protected array $orderBy = [
        'round_id'                                    => 'DESC',
        'CASE WHEN team_number = 0 THEN 1 ELSE 0 END' => 'ASC',
        'team_number'                                 => 'ASC',
        'player_id'                                   => 'ASC',
    ];
    protected ?string $extraIndexView = 'admin/scores/tools';
    // "Link Teams", for a round with a team game.
    protected ?string $belowFiltersView = 'admin/scores/link_teams';
    protected array $headActions      = ['Delete all scores' => '#delete-scores'];

    protected function fields(): array
    {
        $fields = [
            'round_id'  => ['label' => 'Round', 'type' => 'select', 'options' => 'rounds', 'listOptions' => 'roundsWithGame', 'required' => true],
            'player_id' => ['label' => 'Player', 'type' => 'select', 'options' => 'players', 'listOptions' => 'playerNames', 'required' => true],
            'group_id'  => ['label' => 'Group played in', 'type' => 'select', 'options' => 'groups', 'listOptions' => 'groupsShort'],
            'nine'      => ['label' => 'Nine played', 'listLabel' => 'Nine', 'type' => 'select', 'options' => ['front' => 'Front', 'back' => 'Back']],
            'playing'   => ['label' => 'Playing', 'type' => 'checkbox', 'default' => 1],
            'paid'      => ['label' => 'Paid', 'type' => 'checkbox'],
            'locked'    => ['label' => 'Locked', 'type' => 'checkbox'],
            'team_number' => ['label' => 'Team', 'type' => 'number', 'default' => 0,
                'help' => 'Players in this round with the same team number are a team. 0 = individual score.'],
        ];
        // The team the player chose on their score entry page (team games);
        // "Link Teams" turns these into team numbers.
        for ($i = 1; $i <= ScoreModel::TEAM_MEMBER_SLOTS; $i++) {
            $fields["team_member{$i}_id"] = ['label' => "Team member {$i}", 'type' => 'select', 'options' => 'playersFirstLast', 'compact' => 'team'];
        }
        for ($i = 1; $i <= 18; $i++) {
            $fields["hole{$i}"] = ['label' => "Hole {$i}", 'type' => 'number', 'compact' => 'holes'];
        }

        return $fields + [
            'gross'          => ['label' => 'Gross', 'type' => 'number', 'compact' => 'results', 'help' => 'Leave blank to total the nine played automatically.'],
            'handicap_used'  => ['label' => 'Handicap used', 'listLabel' => 'Hcp', 'type' => 'number', 'compact' => 'results'],
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

    /**
     * Without a round chosen, show the active round (the latest if more than
     * one), else the next round from today, else the most recent one.
     */
    protected function defaultFilter(string $name): ?string
    {
        $rounds = (new RoundModel())->orderBy('round_date', 'DESC')->orderBy('id', 'DESC')->findAll();
        $active = array_values(array_filter($rounds, static fn ($r) => $r['status'] === 'active'));
        $next   = array_values(array_filter($rounds, static fn ($r) => $r['round_date'] >= date('Y-m-d')));
        $round  = $active[0] ?? end($next) ?: ($rounds[0] ?? null);

        return $round ? (string) $round['id'] : null;
    }

    protected function listFields(): array
    {
        return ['holes' => ['label' => 'Holes']];
    }

    /**
     * Adds each player's handicap, to fill the Handicap box of scores that
     * don't have one saved yet.
     */
    protected function listRows(array $rows, array $filterValues): array
    {
        $ids      = array_unique(array_map('intval', array_column($rows, 'player_id')));
        $handicap = $ids === [] ? [] : array_column(
            db_connect()->table('players')->select('id, handicap')->whereIn('id', $ids)->get()->getResultArray(),
            'handicap',
            'id'
        );
        foreach ($rows as &$row) {
            $row['player_handicap'] = $handicap[$row['player_id']] ?? null;
        }

        return $rows;
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
            // The handicap typed in the list (blank = none), used for net.
            if (array_key_exists('handicap_used', $holes)) {
                $handicap = trim((string) $holes['handicap_used']);
                if ($handicap !== '' && ! preg_match('/^-?\d{1,2}$/', $handicap)) {
                    $errors[] = "Handicap must be a whole number, e.g. 12, or -2 for a plus handicap (score {$id}).";

                    continue;
                }
                $score['handicap_used'] = $handicap === '' ? null : (int) $handicap;
            }
            [$changes, $error] = ScoreModel::holeChanges($score, $holes);
            if ($error !== null) {
                $errors[] = rtrim($error, '.') . " (score {$id}).";

                continue;
            }
            if ($team !== null) {
                $changes['team_number'] = (int) $team;
            }
            if (array_key_exists('handicap_used', $holes)) {
                $changes['handicap_used'] = $score['handicap_used'];
            }
            $changes['updated_at'] = date('Y-m-d H:i:s');
            $db->table('scores')->where('id', (int) $id)->update($changes);
            $rounds[(int) $score['round_id']] = true;
            $saved++;
        }
        $db->transComplete();

        $back     = redirect()->to($this->listUrl());
        $warnings = self::teamSizeWarnings(array_keys($rounds));
        $then     = self::TEAM_ACTIONS[(string) $this->request->getPost('then')] ?? null;
        if ($errors !== []) {
            $skipped = $then === null ? '' : " {$then[1]} was not run; fix the scores above and try again.";

            return $back->with('error', trim(implode(' ', $errors) . " {$saved} other scores were saved.{$skipped} {$warnings}"));
        }

        // The team buttons save what was typed in the list first, so
        // nothing typed is lost, then do their job.
        if ($then !== null) {
            $this->savedNote = "{$saved} scores saved.";

            return $this->{$then[0]}();
        }

        return $back->with('message', trim("{$saved} scores saved. {$warnings}"));
    }

    /**
     * The team buttons, which send the typed scores with them: posted
     * "then" value => [method, button name].
     */
    private const TEAM_ACTIONS = [
        'link_teams'        => ['linkTeams', 'Link Teams'],
        'teams_from_groups' => ['teamsFromGroups', 'Teams = groups'],
        'map_team_scores'   => ['mapTeamScores', 'Map Team Scores'],
    ];

    /** "N scores saved." when a team button saved the typed scores first. */
    private string $savedNote = '';

    /**
     * The round a team button is for: posted on its own, or as the list's
     * round when it came with the typed scores.
     */
    private function postedRoundId(): int
    {
        return (int) ($this->request->getPost('round_id') ?: $this->request->getPost('return_round_id'));
    }

    /**
     * Puts players in a round who aren't on a team yet (team 0) on teams
     * from the team members they chose on their score entry page. A player
     * and everyone they named are one team, and teams that share a player
     * are joined. Players already on a team keep it, and their team numbers
     * aren't reused: new teams get the lowest free numbers, in the order
     * players entered. Naming someone already on a team, or someone with no
     * score in the round, doesn't link them; both are reported.
     */
    public function linkTeams()
    {
        $roundId = $this->postedRoundId();
        $back    = redirect()->to(site_url($this->baseRoute . "?round_id={$roundId}"));
        $round   = $roundId > 0 ? (new RoundModel())->find($roundId) : null;
        if ($round === null) {
            return $back->with('error', 'Choose a round first.');
        }

        $db     = db_connect();
        $scores = $db->table('scores')->where('round_id', $roundId)->orderBy('id')->get()->getResultArray();
        $names  = Lookup::playerNames();
        $name   = static fn (int $id) => $names[$id] ?? "player {$id}";

        // Players already on a team, and the numbers their teams use.
        $onTeam = [];
        $used   = [];
        foreach ($scores as $score) {
            if ((int) $score['team_number'] > 0) {
                $onTeam[(int) $score['player_id']] = true;
                $used[(int) $score['team_number']] = true;
            }
        }

        // Join the others into teams (union-find on player ids).
        $parent = [];
        foreach ($scores as $score) {
            if (! isset($onTeam[(int) $score['player_id']])) {
                $parent[(int) $score['player_id']] = (int) $score['player_id'];
            }
        }
        $find = static function (int $id) use (&$parent): int {
            while ($parent[$id] !== $id) {
                $id = $parent[$id] = $parent[$parent[$id]];
            }

            return $id;
        };
        $linked   = [];
        $missing  = [];
        $taken    = [];
        foreach ($scores as $score) {
            $playerId = (int) $score['player_id'];
            if (! isset($parent[$playerId])) {
                continue;
            }
            for ($i = 1; $i <= ScoreModel::TEAM_MEMBER_SLOTS; $i++) {
                $memberId = (int) ($score["team_member{$i}_id"] ?? 0);
                if ($memberId === 0 || $memberId === $playerId) {
                    continue;
                }
                if (isset($onTeam[$memberId])) {
                    $taken[] = $name($playerId) . ' named ' . $name($memberId);

                    continue;
                }
                if (! isset($parent[$memberId])) {
                    $missing[] = $name($playerId) . ' named ' . $name($memberId);

                    continue;
                }
                $parent[$find($memberId)] = $find($playerId);
                $linked[$playerId]        = $linked[$memberId] = true;
            }
        }

        // Number the new teams with the lowest free numbers, in the order
        // their first player was entered.
        $numbers = [];
        $next    = 1;
        $now     = date('Y-m-d H:i:s');
        $db->transStart();
        foreach ($scores as $score) {
            $playerId = (int) $score['player_id'];
            if (! isset($linked[$playerId])) {
                continue;
            }
            $root = $find($playerId);
            if (! isset($numbers[$root])) {
                while (isset($used[$next])) {
                    $next++;
                }
                $numbers[$root] = $next;
                $used[$next]    = true;
            }
            $db->table('scores')->where('id', (int) $score['id'])->update(['team_number' => $numbers[$root], 'updated_at' => $now]);
        }
        self::fillTeamMembers($roundId, array_values($numbers));
        $db->transComplete();

        $teams   = count($numbers);
        $message = "Linked {$teams} new " . ($teams === 1 ? 'team' : 'teams') . ' (' . count($linked) . ' players) from the team members players chose.';
        if ($onTeam !== []) {
            $kept     = count($onTeam);
            $message .= " {$kept} " . ($kept === 1 ? 'player was' : 'players were') . ' already on a team and kept it.';
        }
        if ($taken !== []) {
            $message .= ' Not linked, because they are already on a team: ' . implode('; ', $taken) . '.';
        }
        if ($missing !== []) {
            $message .= ' Not linked, because they have no score in this round: ' . implode('; ', $missing) . '.';
        }

        return $back->with('message', trim("{$this->savedNote} {$message} " . self::teamSizeWarnings([$roundId])));
    }

    /**
     * TEMPORARY test data helper, to be removed later.
     *
     * Fills in random but reasonable hole scores for every playing score in
     * a round that isn't locked: for each hole of the nine played (front if
     * not set) that hasn't been entered yet, par for the player's tees
     * (white if not set; 4 if the hole has no par) plus a random amount,
     * mostly par or bogey, sometimes a birdie or a double, rarely worse.
     * Holes already entered are kept. Gross and net are worked out again.
     */
    public function randomScores()
    {
        $roundId = (int) $this->request->getPost('round_id');
        $back    = redirect()->to(site_url($this->baseRoute . "?round_id={$roundId}"));
        $round   = $roundId > 0 ? (new RoundModel())->find($roundId) : null;
        if ($round === null) {
            return $back->with('error', 'Choose a round first.');
        }

        $db    = db_connect();
        $holes = $db->table('holes')->where('course_id', $round['course_id'])->get()->getResultArray();
        $pars  = [];
        foreach ($holes as $hole) {
            foreach (['blue', 'white', 'red'] as $tee) {
                $pars[$tee][(int) $hole['number']] = (int) $hole["{$tee}_par"];
            }
        }
        $scores = $db->table('scores s')
            ->select('s.*, p.tee')
            ->join('players p', 'p.id = s.player_id')
            ->where(['s.round_id' => $roundId, 's.playing' => 1, 's.locked' => 0])
            ->get()->getResultArray();

        // Strokes over par and how likely each is (out of 100).
        $overPar = [-1 => 8, 0 => 35, 1 => 37, 2 => 15, 3 => 5];
        $filled  = 0;
        $now     = date('Y-m-d H:i:s');
        $db->transStart();
        foreach ($scores as $score) {
            $tee     = in_array($score['tee'], ['blue', 'white', 'red'], true) ? $score['tee'] : 'white';
            $changes = ['nine' => $score['nine'] ?: 'front'];
            foreach (ScoreModel::holesForNine($changes['nine']) as $hole) {
                if ((int) $score["hole{$hole}"] > 0) {
                    continue;
                }
                $roll = random_int(1, 100);
                foreach ($overPar as $over => $chance) {
                    if (($roll -= $chance) <= 0) {
                        break;
                    }
                }
                $par                     = ($pars[$tee][$hole] ?? 0) ?: 4;
                $changes["hole{$hole}"] = max(1, $par + $over);
            }
            if (count($changes) === 1) {
                continue;
            }
            $changes['gross']      = ScoreModel::grossForNine($changes + $score);
            $changes['net']        = $changes['gross'] !== null && $score['handicap_used'] !== null ? $changes['gross'] - (int) $score['handicap_used'] : null;
            $changes['updated_at'] = $now;
            $db->table('scores')->where('id', (int) $score['id'])->update($changes);
            $filled++;
        }
        $db->transComplete();

        return $back->with('message', "Random scores filled in for {$filled} " . ($filled === 1 ? 'player' : 'players')
            . '. Holes already entered, locked scores and players not playing were left alone.');
    }

    /**
     * For each team in a round (team number above 0), sets every member's
     * score on each hole to the lowest score any member entered for that
     * hole. Holes no one on the team has entered (blank or 0) are left
     * alone, so a team that hasn't entered anything isn't changed. Gross
     * and net are worked out again for the scores that change.
     */
    public function mapTeamScores()
    {
        $roundId = $this->postedRoundId();
        $back    = redirect()->to(site_url($this->baseRoute . "?round_id={$roundId}"));
        if ($roundId <= 0 || (new RoundModel())->find($roundId) === null) {
            return $back->with('error', 'Choose a round first.');
        }

        $db    = db_connect();
        $teams = [];
        foreach ($db->table('scores')->where('round_id', $roundId)->where('team_number >', 0)->get()->getResultArray() as $score) {
            $teams[(int) $score['team_number']][] = $score;
        }
        ksort($teams);

        $now     = date('Y-m-d H:i:s');
        $mapped  = 0;
        $changed = 0;
        $db->transStart();
        foreach ($teams as $members) {
            // The lowest score entered on each hole by anyone on the team.
            $best = [];
            for ($hole = 1; $hole <= 18; $hole++) {
                $entered = array_filter(array_map(static fn ($s) => (int) ($s["hole{$hole}"] ?? 0), $members), static fn ($v) => $v > 0);
                if ($entered !== []) {
                    $best["hole{$hole}"] = min($entered);
                }
            }
            if ($best === []) {
                continue;
            }
            $mapped++;
            foreach ($members as $score) {
                $updates = array_filter($best, static fn ($value, $key) => (int) ($score[$key] ?? 0) !== $value, ARRAY_FILTER_USE_BOTH);
                if ($updates === []) {
                    continue;
                }
                $changed += count($updates);
                $updates['gross'] = ScoreModel::grossForNine($updates + $score);
                $updates['net']   = $updates['gross'] !== null && $score['handicap_used'] !== null
                    ? $updates['gross'] - (int) $score['handicap_used']
                    : null;
                $updates['updated_at'] = $now;
                $db->table('scores')->where('id', (int) $score['id'])->update($updates);
            }
        }
        $db->transComplete();

        if ($mapped === 0) {
            return $back->with('message', trim("{$this->savedNote} No team scores to map: no team in this round has entered a hole score yet."));
        }

        $teamWord = $mapped === 1 ? 'team' : 'teams';
        $holeWord = $changed === 1 ? 'score' : 'scores';

        return $back->with('message', trim("{$this->savedNote} Mapped team scores for {$mapped} {$teamWord}: each member now has"
            . " the team's lowest score on every hole entered ({$changed} hole {$holeWord} changed)."));
    }

    /**
     * For the round shown, puts players who aren't on a team yet (team 0)
     * on a team with the rest of their group, so teams start out as the
     * groups players tee off with. Players already on a team (e.g. from
     * Link Teams) keep their team, and their team numbers aren't reused:
     * a group whose number is taken gets the next free number instead.
     * Scores without a group are not changed.
     */
    public function teamsFromGroups()
    {
        $roundId = $this->postedRoundId();
        $back    = redirect()->to(site_url($this->baseRoute . ($roundId ? "?round_id={$roundId}" : '')));
        if ($roundId <= 0 || (new RoundModel())->find($roundId) === null) {
            return $back->with('error', 'Choose a round first.');
        }

        $db     = db_connect();
        $scores = $db->table('scores s')
            ->select('s.id, s.team_number, g.group_number')
            ->join('league_groups g', 'g.id = s.group_id', 'left')
            ->where('s.round_id', $roundId)
            ->orderBy('g.group_number')->orderBy('s.id')
            ->get()->getResultArray();

        // Team numbers already in use stay as they are.
        $used = [];
        foreach ($scores as $score) {
            if ((int) $score['team_number'] > 0) {
                $used[(int) $score['team_number']] = true;
            }
        }
        $kept = count(array_filter($scores, static fn ($s) => (int) $s['team_number'] > 0));

        // Each group gets its own number if it's free, else the next free one.
        $teamFor = [];
        $moved   = [];
        $count   = 0;
        $now     = date('Y-m-d H:i:s');
        $db->transStart();
        foreach ($scores as $score) {
            if ((int) $score['team_number'] > 0 || $score['group_number'] === null) {
                continue;
            }
            $group = (int) $score['group_number'];
            if (! isset($teamFor[$group])) {
                $team = $group;
                while (isset($used[$team])) {
                    $team++;
                }
                $teamFor[$group] = $team;
                $used[$team]     = true;
                if ($team !== $group) {
                    $moved[] = "group {$group} is team {$team}";
                }
            }
            $db->table('scores')->where('id', (int) $score['id'])->update(['team_number' => $teamFor[$group], 'updated_at' => $now]);
            $count++;
        }
        self::fillTeamMembers($roundId, array_values($teamFor));
        $db->transComplete();

        $message = "{$count} scores set to a team by group.";
        if ($moved !== []) {
            $message .= ' Numbers already in use were skipped, so ' . implode(', ', $moved) . '.';
        }
        if ($kept > 0) {
            $message .= " {$kept} " . ($kept === 1 ? 'player was' : 'players were') . ' already on a team and kept it.';
        }

        return $back->with('message', trim("{$this->savedNote} {$message} " . self::teamSizeWarnings([$roundId])));
    }

    /**
     * Sets the Team member boxes of everyone on the given teams in a round
     * to that team's players (by last name; at most the number of boxes),
     * so the scores show who is on each team.
     *
     * @param list<int> $teamNumbers
     */
    private static function fillTeamMembers(int $roundId, array $teamNumbers): void
    {
        if ($teamNumbers === []) {
            return;
        }
        $db   = db_connect();
        $rows = $db->table('scores s')
            ->select('s.id, s.team_number, s.player_id')
            ->join('players p', 'p.id = s.player_id')
            ->where('s.round_id', $roundId)
            ->whereIn('s.team_number', $teamNumbers)
            ->orderBy('p.last_name')->orderBy('p.first_name')
            ->get()->getResultArray();

        $teams = [];
        foreach ($rows as $row) {
            $teams[(int) $row['team_number']][] = $row;
        }
        foreach ($teams as $members) {
            $fields = [];
            for ($i = 1; $i <= ScoreModel::TEAM_MEMBER_SLOTS; $i++) {
                $fields["team_member{$i}_id"] = isset($members[$i - 1]) ? (int) $members[$i - 1]['player_id'] : null;
            }
            $db->table('scores')->whereIn('id', array_map('intval', array_column($members, 'id')))->update($fields);
        }
    }

    /**
     * A sentence warning about teams with only 1 player or more than 5, in
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
        $members = [];
        for ($i = 1; $i <= ScoreModel::TEAM_MEMBER_SLOTS; $i++) {
            if (! empty($data["team_member{$i}_id"])) {
                $members[] = (int) $data["team_member{$i}_id"];
            }
        }
        if (count($members) !== count(array_unique($members))) {
            return 'A player is chosen more than once as a team member.';
        }

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
