<?php

namespace App\Controllers\Admin;

use App\Libraries\Lookup;
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
    // Clicking a group's Front/Back on the list switches it to the other nine.
    protected array $toggleColumns = ['nine' => 'admin/groups/{id}/switch-nine'];
    protected array $orderBy     = ['group_number' => 'ASC'];
    protected ?string $extraIndexView = 'admin/groups/tools';
    protected array $headActions      = ['Switch front/back' => '#switch-nine', 'Create groups for players' => '#create-groups', 'Delete all groups' => '#delete-groups'];

    // Players in a group, for working out how many groups a league needs.
    public const PLAYERS_PER_GROUP = 4;

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

    /**
     * On the add/edit form, offer the league's front nine tee times for the
     * front tee time and back nine tee times for the back one (any of them,
     * even if another group has it), and only the league's registered
     * players who are not in another group (plus this group's own).
     */
    protected function formFields(array $record, ?int $id): array
    {
        $fields   = $this->resolvedFields();
        $leagueId = (int) ($record['league_id'] ?? 0) ?: Lookup::currentLeagueId();
        $taken    = $this->playersInOtherGroups((int) $leagueId, $id);

        $teeTimes = (new TeeTimeModel())->where('league_id', $leagueId)->orderBy('tee_time')->findAll();
        foreach (['front', 'back'] as $nine) {
            $field   = "{$nine}_tee_time_id";
            $options = [];
            foreach ($teeTimes as $teeTime) {
                if ($teeTime['nine'] === $nine) {
                    $options[(int) $teeTime['id']] = ucfirst($nine) . ' ' . $teeTime['tee_time'];
                }
            }
            $fields[$field]['options'] = $options;
        }

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
