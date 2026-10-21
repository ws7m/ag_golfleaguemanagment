<?php

namespace App\Controllers\Admin;

use App\Models\LeagueGroupModel;
use App\Models\LeagueModel;
use App\Models\PlayerModel;

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
     * Makes sure a league has enough groups for its registered players, at 4
     * players to a group: 8 players need 2 groups, 9 need 3. Active groups
     * the league already has count towards that; new groups are numbered
     * after the existing ones, start on the front nine and have no players.
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
            ->countAllResults();
        if ($registered === 0) {
            return $back->with('error', "{$league['name']} has no registered players yet.");
        }

        $needed   = (int) ceil($registered / self::PLAYERS_PER_GROUP);
        $model    = new LeagueGroupModel();
        $existing = $model->where('league_id', $leagueId)->findAll();
        $active   = count(array_filter($existing, static fn ($g) => (int) $g['active'] === 1));
        $summary  = "{$registered} registered players need {$needed} groups of " . self::PLAYERS_PER_GROUP . '.';

        if ($active >= $needed) {
            return $back->with('message', "{$summary} The league already has {$active} active groups, so none were created.");
        }

        $next  = (int) max([0, ...array_column($existing, 'group_number')]) + 1;
        $first = $next;

        $db = db_connect();
        $db->transStart();
        for ($i = $active; $i < $needed; $i++) {
            $model->insert(['league_id' => $leagueId, 'group_number' => $next++, 'nine' => 'front', 'active' => 1]);
        }
        $db->transComplete();

        $created = $next - $first;
        $numbers = $created === 1 ? "group {$first}" : 'groups ' . $first . '–' . ($next - 1);

        return $back->with('message', "{$summary} Created {$created} ({$numbers}). Add players and tee times to them.");
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
