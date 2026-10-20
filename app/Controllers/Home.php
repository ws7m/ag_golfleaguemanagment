<?php

namespace App\Controllers;

use App\Libraries\Lookup;
use App\Models\LeagueModel;
use App\Models\SettingModel;

/**
 * Public league page for guests (design.md section 5).
 */
class Home extends BaseController
{
    public function index(): string
    {
        $leagueId = Lookup::currentLeagueId();

        return view('home', [
            'title'    => APP_NAME,
            'league'   => $leagueId ? (new LeagueModel())->find($leagueId) : null,
            'settings' => (new SettingModel())->first(),
        ]);
    }
}
