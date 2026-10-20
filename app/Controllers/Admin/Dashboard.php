<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\Lookup;
use App\Models\LeagueModel;

class Dashboard extends BaseController
{
    public function index(): string
    {
        $db       = db_connect();
        $leagueId = Lookup::currentLeagueId();
        $counts   = [];
        foreach (['leagues', 'courses', 'tee_times', 'players', 'league_groups', 'games', 'rounds', 'scores'] as $table) {
            $counts[$table] = $db->table($table)->countAllResults();
        }

        return view('admin/dashboard', [
            'title'  => 'Admin',
            'league' => $leagueId ? (new LeagueModel())->find($leagueId) : null,
            'counts' => $counts,
        ]);
    }
}
