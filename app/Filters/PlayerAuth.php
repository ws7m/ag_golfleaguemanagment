<?php

namespace App\Filters;

use App\Models\PlayerModel;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Sends anyone who has not completed the player login (shared login plus
 * member number) to the player login page.
 */
class PlayerAuth implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $playerId = session('player_id');
        $player   = $playerId ? (new PlayerModel())->find($playerId) : null;

        // Also covers a player deleted or made inactive since logging in.
        if ($player === null || ! $player['active']) {
            session()->remove(['player_id', 'player_name']);

            return redirect()->to('login');
        }

        // They have to choose their own password before anything else.
        if ($player['must_change_password']) {
            return redirect()->to('password');
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
