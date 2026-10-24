<?php

namespace App\Controllers\Player;

use App\Controllers\BaseController;
use App\Libraries\Lookup;
use App\Models\LeagueModel;
use App\Models\PlayerModel;

/**
 * Player home page. Player screens are built in Phase 4; for now this
 * confirms who is logged in.
 */
class Home extends BaseController
{
    public function index(): string
    {
        $player = (new PlayerModel())->find(session('player_id'));

        // The notice of the player's league, or of the current league.
        $leagueId = $player['league_id'] ?? null ?: Lookup::currentLeagueId();

        return view('player/home', [
            'title'  => 'My page',
            'player' => $player,
            'notice' => $leagueId ? (new LeagueModel())->find($leagueId)['notice'] ?? null : null,
        ]);
    }
}
