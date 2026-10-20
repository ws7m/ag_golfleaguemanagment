<?php

namespace App\Controllers\Player;

use App\Controllers\BaseController;
use App\Models\PlayerModel;

/**
 * Player home page. Player screens are built in Phase 4; for now this
 * confirms who is logged in.
 */
class Home extends BaseController
{
    public function index(): string
    {
        return view('player/home', [
            'title'  => 'My page',
            'player' => (new PlayerModel())->find(session('player_id')),
        ]);
    }
}
