<?php

namespace App\Controllers\Admin;

use App\Models\LeagueGroupModel;
use App\Models\LeagueModel;
use App\Models\TeeTimeModel;

class Groups extends CrudController
{
    protected string $modelClass = LeagueGroupModel::class;
    protected string $title      = 'Groups';
    protected string $singular   = 'Group';
    protected string $baseRoute  = 'admin/groups';
    protected array $listColumns = ['group_number', 'front_tee_time_id', 'back_tee_time_id', 'player1_id', 'player2_id', 'player3_id', 'player4_id', 'active'];
    protected array $filters     = ['league_id'];
    protected array $orderBy     = ['group_number' => 'ASC'];
    protected ?string $extraIndexView = 'admin/groups/tools';
    protected array $headActions      = ['Create groups from tee times' => '#create-groups', 'Delete all groups' => '#delete-groups'];

    protected function fields(): array
    {
        $fields = [
            'league_id'         => ['label' => 'League', 'type' => 'select', 'options' => 'leagues', 'default' => 'currentLeague', 'required' => true],
            'group_number'      => ['label' => 'Group number', 'type' => 'number', 'required' => true],
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
     * Creates one group per front nine tee time in a league, in time order,
     * numbered after the league's existing groups. Each gets the closest back
     * nine tee time, preferring one no other group is using yet. Front nine
     * tee times that already have a group are skipped.
     */
    public function generate()
    {
        $leagueId = (int) $this->request->getPost('league_id');
        $back     = redirect()->to($this->baseRoute . '?league_id=' . $leagueId);
        $league   = $leagueId ? (new LeagueModel())->find($leagueId) : null;
        if ($league === null) {
            return $back->with('error', 'Choose a league.');
        }

        $teeTimes = (new TeeTimeModel())->where('league_id', $leagueId)->orderBy('tee_time')->findAll();
        $fronts   = array_values(array_filter($teeTimes, static fn ($t) => $t['nine'] === 'front'));
        $backs    = array_values(array_filter($teeTimes, static fn ($t) => $t['nine'] === 'back'));
        if ($fronts === []) {
            return $back->with('error', "{$league['name']} has no front nine tee times yet. Create them on the Tee times page first.");
        }

        $model      = new LeagueGroupModel();
        $existing   = $model->where('league_id', $leagueId)->findAll();
        $usedFront  = array_map('intval', array_filter(array_column($existing, 'front_tee_time_id')));
        $usedBack   = array_map('intval', array_filter(array_column($existing, 'back_tee_time_id')));
        $nextNumber = (int) max([0, ...array_column($existing, 'group_number')]) + 1;
        $created    = 0;

        $db = db_connect();
        $db->transStart();
        foreach ($fronts as $front) {
            if (in_array((int) $front['id'], $usedFront, true)) {
                continue;
            }
            $backId = self::closestTeeTime($front['tee_time'], $backs, $usedBack);
            if ($backId !== null) {
                $usedBack[] = $backId;
            }
            $model->insert([
                'league_id'         => $leagueId,
                'group_number'      => $nextNumber++,
                'front_tee_time_id' => (int) $front['id'],
                'back_tee_time_id'  => $backId,
                'active'            => 1,
            ]);
            $created++;
        }
        $db->transComplete();

        $skipped = count($fronts) - $created;
        $message = "{$created} groups created.";
        if ($skipped > 0) {
            $message .= " {$skipped} front nine tee times already had a group.";
        }
        if ($backs === []) {
            $message .= ' There are no back nine tee times, so set those on each group later.';
        }

        return $back->with('message', $message);
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

    /**
     * The id of the back nine tee time closest to $time ("HH:MM"), preferring
     * ones not in $used; earlier wins a tie. Null if there are none.
     *
     * @param list<array> $backs
     * @param list<int>   $used
     */
    private static function closestTeeTime(string $time, array $backs, array $used): ?int
    {
        $minutes = static fn (string $t) => (int) substr($t, 0, 2) * 60 + (int) substr($t, 3, 2);
        $target  = $minutes($time);
        $best    = null;
        $bestKey = null;
        foreach ($backs as $teeTime) {
            // Unused tee times sort before used ones, then by distance, then earlier.
            $key = [in_array((int) $teeTime['id'], $used, true) ? 1 : 0, abs($minutes($teeTime['tee_time']) - $target), $minutes($teeTime['tee_time'])];
            if ($bestKey === null || $key < $bestKey) {
                $best    = (int) $teeTime['id'];
                $bestKey = $key;
            }
        }

        return $best;
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
