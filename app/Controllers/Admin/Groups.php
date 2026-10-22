<?php

namespace App\Controllers\Admin;

use App\Models\LeagueGroupModel;
use App\Models\LeagueModel;
use App\Models\PlayerModel;
use App\Models\TeeTimeModel;

class Groups extends CrudController
{
    protected string $modelClass = LeagueGroupModel::class;
    protected string $title      = 'Groups';
    protected string $singular   = 'Group';
    protected string $baseRoute  = 'admin/groups';
    protected array $listColumns = ['group_number', 'nine', 'front_tee_time_id', 'back_tee_time_id', 'player1_id', 'player2_id', 'player3_id', 'player4_id', 'active'];
    protected array $filters     = ['league_id'];
    protected array $orderBy     = ['group_number' => 'ASC'];
    protected ?string $extraIndexView = 'admin/groups/tools';
    protected array $headActions      = ['Switch front/back' => '#switch-nine', 'Create groups for players' => '#create-groups', 'Delete all groups' => '#delete-groups'];

    protected function fields(): array
    {
        $fields = [
            'league_id'         => ['label' => 'League', 'type' => 'select', 'options' => 'leagues', 'default' => 'currentLeague', 'required' => true],
            'group_number'      => ['label' => 'Group number', 'type' => 'number', 'required' => true],
            'nine'              => ['label' => 'Playing', 'type' => 'select', 'options' => ['front' => 'Front nine', 'back' => 'Back nine'], 'listOptions' => ['front' => 'Front', 'back' => 'Back'], 'default' => 'front', 'required' => true,
                'help' => 'The nine this group is playing now. Use "Switch front/back for all groups" on the Groups page to swap every group between rounds.'],
            'front_tee_time_id' => ['label' => 'Front nine tee time', 'type' => 'select', 'options' => 'teeTimes', 'listOptions' => 'teeTimesShort'],
            'back_tee_time_id'  => ['label' => 'Back nine tee time', 'type' => 'select', 'options' => 'teeTimes', 'listOptions' => 'teeTimesShort'],
        ];
        for ($i = 1; $i <= 6; $i++) {
            $fields["player{$i}_id"] = ['label' => "Player {$i}", 'type' => 'select', 'options' => 'players', 'listOptions' => 'playersShort'];
        }
        $fields['active'] = ['label' => 'Active', 'type' => 'checkbox', 'default' => 1];

        return $fields;
    }

    // Players in a group, for working out how many groups a league needs.
    public const PLAYERS_PER_GROUP = 4;

    /**
     * Puts every registered player in the league into a group of 4.
     *
     * Players who are not yet on an active group's roster (in last name
     * order) first fill any open places in the league's existing active
     * groups, then go into new groups of 4 (the last may have fewer). New
     * groups are numbered after the existing ones, start on the front nine,
     * and get the league's front and back nine tee times in time order,
     * starting after the last front tee time already used by a group. When
     * the tee times run out, assignment starts again from the first one.
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
            ->where('league_id', $leagueId)
            ->where('active', 1)
            ->where('is_player', 1)
            ->orderBy('LOWER(last_name)', 'ASC', false)
            ->orderBy('LOWER(first_name)', 'ASC', false)
            ->findAll();
        if ($registered === []) {
            return $back->with('error', "{$league['name']} has no registered players yet.");
        }

        $model    = new LeagueGroupModel();
        $existing = $model->where('league_id', $leagueId)->orderBy('group_number')->findAll();
        $active   = array_values(array_filter($existing, static fn ($g) => (int) $g['active'] === 1));

        // Players not on any active group's roster yet.
        $grouped = [];
        foreach ($active as $group) {
            foreach (LeagueGroupModel::playerIds($group) as $playerId) {
                $grouped[$playerId] = true;
            }
        }
        $waiting = array_values(array_filter(
            array_map(static fn ($p) => (int) $p['id'], $registered),
            static fn ($id) => ! isset($grouped[$id])
        ));
        $summary = count($registered) . ' registered players.';
        if ($waiting === []) {
            return $back->with('message', "{$summary} All of them are already in groups, so nothing was changed.");
        }

        // Tee times in time order; new groups continue after the last front
        // tee time an existing group uses, and wrap around to the start.
        $teeTimes = (new TeeTimeModel())->where('league_id', $leagueId)->orderBy('tee_time')->findAll();
        $fronts   = array_values(array_filter($teeTimes, static fn ($t) => $t['nine'] === 'front'));
        $backs    = array_values(array_filter($teeTimes, static fn ($t) => $t['nine'] === 'back'));
        $usedFront = array_map('intval', array_filter(array_column($active, 'front_tee_time_id')));
        $slot      = 0;
        foreach ($fronts as $i => $teeTime) {
            if (in_array((int) $teeTime['id'], $usedFront, true)) {
                $slot = $i + 1;
            }
        }

        $filled  = 0;
        $created = [];
        $wrapped = false;
        $db      = db_connect();
        $db->transStart();

        // 1. Fill open places in existing active groups.
        foreach ($active as $group) {
            $changes = [];
            for ($i = 1; $i <= self::PLAYERS_PER_GROUP && $waiting !== []; $i++) {
                if (empty($group["player{$i}_id"])) {
                    $changes["player{$i}_id"] = array_shift($waiting);
                    $filled++;
                }
            }
            if ($changes !== []) {
                $model->update($group['id'], $changes);
            }
        }

        // 2. New groups of 4 for everyone left.
        $number = (int) max([0, ...array_column($existing, 'group_number')]) + 1;
        while ($waiting !== []) {
            $row = [
                'league_id'         => $leagueId,
                'group_number'      => $number,
                'nine'              => 'front',
                'front_tee_time_id' => $fronts === [] ? null : (int) $fronts[$slot % count($fronts)]['id'],
                'back_tee_time_id'  => $backs === [] ? null : (int) $backs[$slot % count($backs)]['id'],
                'active'            => 1,
            ];
            foreach (array_splice($waiting, 0, self::PLAYERS_PER_GROUP) as $i => $playerId) {
                $row['player' . ($i + 1) . '_id'] = $playerId;
            }
            $model->insert($row);
            $created[] = $number++;
            $wrapped   = $wrapped || ($fronts !== [] && $slot >= count($fronts)) || ($backs !== [] && $slot >= count($backs));
            $slot++;
        }

        $db->transComplete();

        $message = $summary;
        if ($filled > 0) {
            $message .= " {$filled} placed in open places in existing groups.";
        }
        if ($created !== []) {
            $range    = count($created) === 1 ? "group {$created[0]}" : "groups {$created[0]}–" . end($created);
            $message .= ' Created ' . count($created) . " groups of up to " . self::PLAYERS_PER_GROUP . " ({$range}).";
            if ($fronts === [] || $backs === []) {
                $message .= ' The league has no ' . ($fronts === [] ? 'front' : 'back') . ' nine tee times, so set those on the groups later.';
            } elseif ($wrapped) {
                $message .= ' There were more groups than tee times, so tee times were used again from the first one.';
            }
        }

        return $back->with('message', $message);
    }

    protected function rowPostActions(): array
    {
        return ['Switch nine' => 'admin/groups/{id}/switch-nine'];
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
        $players = array_filter(array_map(static fn ($i) => $data["player{$i}_id"], range(1, 6)));
        if (count($players) !== count(array_unique($players))) {
            return 'The same player is listed more than once in this group.';
        }

        return null;
    }
}
