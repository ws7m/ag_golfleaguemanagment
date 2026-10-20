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
    protected array $listColumns = ['member_number', 'last_name', 'first_name', 'group', 'flight', 'handicap', 'tee', 'winnings', 'is_admin', 'last_login_at', 'active'];
    protected array $filters     = ['league_id'];
    // By last name, then first name, ignoring capitals. Other sorts are
    // applied in listRows().
    protected array $orderBy     = ['LOWER(last_name)' => 'ASC', 'LOWER(first_name)' => 'ASC'];
    protected array $sortOptions = [
        'name'     => 'Last name',
        'group'    => 'Group',
        'winnings' => 'Winnings (most first)',
    ];

    protected function listFields(): array
    {
        return [
            'group'    => ['label' => 'Group'],
            'winnings' => ['label' => 'Winnings', 'type' => 'money'],
        ];
    }

    /**
     * Adds the group each player is on the roster of (or "None") and their
     * total winnings, then applies the chosen sort.
     *
     * Only active groups count. With the list showing a single league, only
     * that league's groups and round winnings are used; with all leagues,
     * group names include the league and round winnings are totalled across
     * leagues. Winnings entered on the player are always added.
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

        $byPlayer    = [];
        $firstGroup  = [];
        foreach ($groups->get()->getResultArray() as $group) {
            $label = 'Group ' . $group['group_number'];
            if (! isset($filterValues['league_id'])) {
                $label .= " ({$group['league']})";
            }
            foreach (LeagueGroupModel::playerIds($group) as $playerId) {
                $byPlayer[$playerId][] = $label;
                $firstGroup[$playerId] ??= (int) $group['group_number'];
            }
        }

        $winnings = $this->roundWinnings($filterValues['league_id'] ?? null);

        foreach ($rows as &$row) {
            $id                = (int) $row['id'];
            $row['group']      = isset($byPlayer[$id]) ? implode(', ', $byPlayer[$id]) : 'None';
            // Scored rounds plus winnings the admin entered on the player.
            $row['winnings']   = ($winnings[$id] ?? 0) + (int) ($row['winnings_cents'] ?? 0);
            $row['_group_num'] = $firstGroup[$id] ?? PHP_INT_MAX; // No group sorts last
            // Date only (e.g. "Oct 19") to keep the list narrow; the edit form shows the time.
            if (! empty($row['last_login_at'])) {
                $row['last_login_at'] = date('M j', strtotime($row['last_login_at']));
            }
        }
        unset($row);

        // Rows arrive sorted by name, so a stable sort keeps names in order
        // within each group or winnings amount.
        $sort = $this->sortChoice();
        if ($sort === 'group') {
            usort($rows, static fn ($a, $b) => $a['_group_num'] <=> $b['_group_num']);
        } elseif ($sort === 'winnings') {
            usort($rows, static fn ($a, $b) => $b['winnings'] <=> $a['winnings']);
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
            'winnings_cents'        => ['label' => 'Winnings', 'type' => 'money', 'compact' => 'winnings', 'help' => 'Entered by you. Added to winnings from scored rounds.'],
            'round_winnings'        => ['label' => 'From scored rounds', 'type' => 'money', 'readonly' => true, 'compact' => 'winnings'],
            'is_admin'              => ['label' => 'Admin', 'type' => 'checkbox'],
            'admin_password'        => ['label' => 'Admin password', 'type' => 'password', 'help' => 'Only for admins. At least 8 characters. Leave blank to keep the current password.'],
            'last_login_at'         => ['label' => 'Last login', 'readonly' => true],
            'active'                => ['label' => 'Active', 'type' => 'checkbox', 'default' => 1],
        ];
    }

    protected function formRecord(array $record, ?int $id): array
    {
        $record['round_winnings'] = $id === null ? 0 : $this->roundWinnings()[$id] ?? 0;

        return $record;
    }

    /**
     * Total winnings from scored rounds per player id, optionally for one league.
     *
     * @return array<int, int>
     */
    private function roundWinnings(?string $leagueId = null): array
    {
        $query = db_connect()->table('scores')
            ->select('player_id, SUM(winnings_cents) AS total')
            ->groupBy('player_id');
        if ($leagueId !== null) {
            $query->where('league_id', $leagueId);
        }

        return array_map('intval', array_column($query->get()->getResultArray(), 'total', 'player_id'));
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
