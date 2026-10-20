<?php

namespace App\Controllers\Admin;

use App\Models\GameModel;

class Games extends CrudController
{
    protected string $modelClass = GameModel::class;
    protected string $title      = 'Games';
    protected string $singular   = 'Game';
    protected string $baseRoute  = 'admin/games';
    protected array $listColumns = ['name', 'league_id', 'active'];
    protected array $filters     = ['league_id'];
    protected array $orderBy     = ['name' => 'ASC'];

    protected function fields(): array
    {
        return [
            'league_id'    => ['label' => 'League', 'type' => 'select', 'options' => 'leagues', 'default' => 'currentLeague', 'required' => true],
            'name'         => ['label' => 'Name', 'required' => true],
            'description'  => ['label' => 'Description', 'type' => 'textarea'],
            'scoring_info' => ['label' => 'Scoring info', 'type' => 'textarea', 'help' => 'How the game is scored. The format is still to be decided.'],
            'active'       => ['label' => 'Active', 'type' => 'checkbox', 'default' => 1],
        ];
    }
}
