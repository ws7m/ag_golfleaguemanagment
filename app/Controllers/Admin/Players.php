<?php

namespace App\Controllers\Admin;

use App\Models\LeagueGroupModel;
use App\Models\PlayerModel;

class Players extends CrudController
{
    protected string $modelClass = PlayerModel::class;
    protected string $title      = 'Players';
    protected string $singular   = 'Player';
    protected string $baseRoute  = 'admin/players';
    protected array $listColumns = ['member_number', 'last_name', 'first_name', 'group', 'flight', 'handicap', 'tee', 'is_admin', 'last_login_at', 'active'];
    protected array $filters     = ['league_id'];
    // By last name, then first name, ignoring capitals.
    protected array $orderBy = ['LOWER(last_name)' => 'ASC', 'LOWER(first_name)' => 'ASC'];

    protected function listFields(): array
    {
        return ['group' => ['label' => 'Group']];
    }

    /**
     * Adds the group each player is on the roster of, or "None".
     * Only active groups count. With the list showing a single league, only
     * that league's groups are used; with all leagues, the league is named.
     */
    protected function listRows(array $rows, array $filterValues): array
    {
        $groups = db_connect()->table('league_groups g')
            ->select('g.*, l.name AS league')
            ->join('leagues l', 'l.id = g.league_id')
            ->where('g.active', 1)
            ->orderBy('g.group_number');
        if (isset($filterValues['league_id'])) {
            $groups->where('g.league_id', $filterValues['league_id']);
        }

        $byPlayer = [];
        foreach ($groups->get()->getResultArray() as $group) {
            $label = 'Group ' . $group['group_number'];
            if (! isset($filterValues['league_id'])) {
                $label .= " ({$group['league']})";
            }
            foreach (LeagueGroupModel::playerIds($group) as $playerId) {
                $byPlayer[$playerId][] = $label;
            }
        }

        foreach ($rows as &$row) {
            $row['group'] = isset($byPlayer[(int) $row['id']]) ? implode(', ', $byPlayer[(int) $row['id']]) : 'None';
        }

        return $rows;
    }

    protected function fields(): array
    {
        return [
            'first_name'            => ['label' => 'First name', 'required' => true],
            'last_name'             => ['label' => 'Last name', 'required' => true],
            'member_number'         => ['label' => 'Member number', 'type' => 'number', 'help' => 'Leave blank to assign a random 3-digit number (100–999).'],
            'league_id'             => ['label' => 'League', 'type' => 'select', 'options' => 'leagues', 'default' => 'currentLeague', 'help' => 'The league the player is registered in.'],
            'gender'                => ['label' => 'Male or female', 'type' => 'select', 'options' => ['M' => 'Male', 'F' => 'Female']],
            'email'                 => ['label' => 'Email', 'type' => 'email'],
            'phone'                 => ['label' => 'Phone'],
            'text_msg_ok'           => ['label' => 'Text messages OK', 'type' => 'checkbox'],
            'tee'                   => ['label' => 'Tee', 'type' => 'select', 'options' => ['blue' => 'Blue', 'white' => 'White', 'red' => 'Red']],
            'handicap'              => ['label' => 'Handicap', 'type' => 'number', 'compact' => true],
            'flight'                => ['label' => 'Flight', 'type' => 'select', 'options' => [1 => '1', 2 => '2', 3 => '3'], 'compact' => true],
            'desired_tee_time'      => ['label' => 'Desired tee time', 'type' => 'time', 'compact' => true],
            'desired_group_members' => ['label' => 'Desired group members', 'type' => 'textarea'],
            'is_admin'              => ['label' => 'Admin', 'type' => 'checkbox'],
            'admin_password'        => ['label' => 'Admin password', 'type' => 'password', 'help' => 'Only for admins. At least 8 characters. Leave blank to keep the current password.'],
            'last_login_at'         => ['label' => 'Last login', 'readonly' => true],
            'active'                => ['label' => 'Active', 'type' => 'checkbox', 'default' => 1],
        ];
    }

    protected function checkBeforeSave(array $data, ?int $id): ?string
    {
        $password = (string) ($data['admin_password'] ?? '');
        if ($password !== '' && strlen($password) < 8) {
            return 'The admin password must be at least 8 characters.';
        }
        if ($password !== '' && empty($data['is_admin'])) {
            return 'Tick Admin to give this player an admin password.';
        }

        // Stop admins from locking themselves out.
        if ($id !== null && $id === (int) session('admin_id')
            && (empty($data['is_admin']) || empty($data['active']))) {
            return 'You cannot remove your own admin access or make yourself inactive.';
        }

        return null;
    }

    protected function afterSave(int $id, array $data): void
    {
        if (! empty($data['admin_password'])) {
            (new PlayerModel())->setAdminPassword($id, $data['admin_password']);
        }
    }
}
