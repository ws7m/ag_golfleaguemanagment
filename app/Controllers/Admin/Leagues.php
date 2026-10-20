<?php

namespace App\Controllers\Admin;

use App\Models\LeagueModel;

class Leagues extends CrudController
{
    protected string $modelClass = LeagueModel::class;
    protected string $title      = 'Leagues';
    protected string $singular   = 'League';
    protected string $baseRoute  = 'admin/leagues';
    protected array $listColumns = ['name', 'start_date', 'end_date', 'active'];
    protected array $orderBy     = ['start_date' => 'DESC', 'id' => 'DESC'];

    protected function fields(): array
    {
        return [
            'name'        => ['label' => 'Name', 'required' => true],
            'description' => ['label' => 'Description', 'type' => 'textarea'],
            'start_date'  => ['label' => 'Starting date', 'type' => 'date'],
            'end_date'    => ['label' => 'Ending date', 'type' => 'date'],
            'website'     => ['label' => 'Website'],
            'email'       => ['label' => 'Email', 'type' => 'email'],
            'image'       => ['label' => 'Image', 'help' => 'Picture uploads come later; for now a file name or link.'],
            'active'      => ['label' => 'Active', 'type' => 'checkbox', 'default' => 1],
        ];
    }

    protected function rowActions(): array
    {
        return ['Copy' => 'admin/leagues/{id}/copy'];
    }

    /**
     * Starts a new league form filled in from an existing league.
     */
    public function copy(int $id): string
    {
        $record = $this->findOr404($id);
        unset($record['id'], $record['start_date'], $record['end_date']);
        $record['name']   = 'Copy of ' . $record['name'];
        $record['active'] = 1;

        return $this->form($record, null);
    }
}
