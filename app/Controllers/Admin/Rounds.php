<?php

namespace App\Controllers\Admin;

use App\Models\RoundModel;

class Rounds extends CrudController
{
    protected string $modelClass = RoundModel::class;
    protected string $title      = 'Rounds';
    protected string $singular   = 'Round';
    protected string $baseRoute  = 'admin/rounds';
    protected array $listColumns = ['round_date', 'name', 'course_id', 'game_id', 'status', 'entry_fee_cents'];
    protected array $filters     = ['league_id'];
    protected array $orderBy     = ['round_date' => 'ASC'];

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
            ]],
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
}
