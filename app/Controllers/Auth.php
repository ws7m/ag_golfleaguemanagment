<?php

namespace App\Controllers;

use App\Models\PlayerModel;

/**
 * One login for players and admins: each person's own login and password.
 * Admins go to the admin pages; players go to their player page.
 */
class Auth extends BaseController
{
    // Failed attempts allowed per visitor IP per minute.
    private const ATTEMPTS_PER_MINUTE = 5;

    public function loginForm()
    {
        if (session('admin_id')) {
            return redirect()->to('admin');
        }
        if (session('player_id')) {
            return redirect()->to('player');
        }

        return view('auth/login', ['title' => 'Log in']);
    }

    public function login()
    {
        $key = 'login-' . md5($this->request->getIPAddress());
        if (! service('throttler')->check($key, self::ATTEMPTS_PER_MINUTE, MINUTE)) {
            return redirect()->to('login')->with('error', 'Too many attempts. Please wait a minute and try again.');
        }

        $players = new PlayerModel();
        $player  = $players->verifyLogin(
            (string) $this->request->getPost('login'),
            (string) $this->request->getPost('password')
        );

        if ($player === null) {
            return redirect()->back()->withInput()->with('error', 'Login or password is not correct.');
        }

        $players->recordLogin((int) $player['id']);

        // Start fresh, so an earlier login in this browser doesn't carry over.
        session()->regenerate(true);
        session()->remove(['admin_id', 'admin_name', 'player_id', 'player_name']);
        $name = $player['first_name'] . ' ' . $player['last_name'];
        if ($player['is_player']) {
            session()->set(['player_id' => (int) $player['id'], 'player_name' => $name]);
        }
        if ($player['is_admin']) {
            session()->set(['admin_id' => (int) $player['id'], 'admin_name' => $name]);

            return redirect()->to('admin');
        }

        return redirect()->to('player');
    }

    public function logout()
    {
        session()->destroy();

        return redirect()->to('/');
    }
}
