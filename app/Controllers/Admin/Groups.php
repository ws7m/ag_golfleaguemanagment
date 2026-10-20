<?php

namespace App\Controllers\Admin;

use App\Models\LeagueGroupModel;

class Groups extends CrudController
{
    protected string $modelClass = LeagueGroupModel::class;
    protected string $title      = 'Groups';
    protected string $singular   = 'Group';
    protected string $baseRoute  = 'admin/groups';
    protected array $listColumns = ['group_number', 'front_tee_time_id', 'back_tee_time_id', 'player1_id', 'player2_id', 'player3_id', 'player4_id', 'active'];
    protected array $filters     = ['league_id'];
    protected array $orderBy     = ['group_number' => 'ASC'];

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

    protected function checkBeforeSave(array $data, ?int $id): ?string
    {
        $players = array_filter(array_map(static fn ($i) => $data["player{$i}_id"], range(1, 6)));
        if (count($players) !== count(array_unique($players))) {
            return 'The same player is listed more than once in this group.';
        }

        return null;
    }
}
