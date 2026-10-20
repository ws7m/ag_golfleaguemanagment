<?php

namespace App\Controllers;

use App\Models\PlayerModel;
use App\Models\SettingModel;

/**
 * Admin login (email + password) and player login (shared login, then
 * member number). See design.md section 2.10.
 */
class Auth extends BaseController
{
    // Failed attempts allowed per visitor IP per minute.
    private const ATTEMPTS_PER_MINUTE = 5;

    public function adminLoginForm()
    {
        if (session('admin_id')) {
            return redirect()->to('admin');
        }

        return view('auth/admin_login', ['title' => 'Admin login']);
    }

    public function adminLogin()
    {
        if (! $this->allowAttempt('admin')) {
            return $this->tooManyAttempts('admin/login');
        }

        $email    = trim((string) $this->request->getPost('email'));
        $password = (string) $this->request->getPost('password');
        $admin    = (new PlayerModel())->verifyAdmin($email, $password);

        if ($admin === null) {
            return redirect()->back()->withInput()->with('error', 'Email or password is not correct.');
        }

        session()->regenerate(true);
        session()->set([
            'admin_id'   => (int) $admin['id'],
            'admin_name' => $admin['first_name'] . ' ' . $admin['last_name'],
        ]);

        return redirect()->to('admin');
    }

    public function playerLoginForm()
    {
        if (session('player_id')) {
            return redirect()->to('player');
        }

        $view = session('shared_login_ok') ? 'auth/player_member' : 'auth/player_login';

        return view($view, ['title' => 'Player login']);
    }

    public function playerLogin()
    {
        if (! $this->allowAttempt('player')) {
            return $this->tooManyAttempts('login');
        }

        $settings = (new SettingModel())->first();
        $loginId  = trim((string) $this->request->getPost('login_id'));
        $password = (string) $this->request->getPost('password');

        if (empty($settings['shared_login_id']) || empty($settings['shared_password_hash'])) {
            return redirect()->back()->with('error', 'Player login has not been set up yet. Please contact the league admin.');
        }

        if (! hash_equals((string) $settings['shared_login_id'], $loginId)
            || ! password_verify($password, $settings['shared_password_hash'])) {
            return redirect()->back()->withInput()->with('error', 'Login ID or password is not correct.');
        }

        session()->regenerate(true);
        session()->set('shared_login_ok', true);

        return redirect()->to('login');
    }

    public function playerMember()
    {
        if (! session('shared_login_ok')) {
            return redirect()->to('login');
        }
        if (! $this->allowAttempt('member')) {
            return $this->tooManyAttempts('login');
        }

        $number = trim((string) $this->request->getPost('member_number'));
        $player = ctype_digit($number)
            ? (new PlayerModel())->where('member_number', (int) $number)->where('active', 1)->first()
            : null;

        if ($player === null) {
            return redirect()->back()->with('error', 'That member number was not found.');
        }

        session()->set([
            'player_id'   => (int) $player['id'],
            'player_name' => $player['first_name'] . ' ' . $player['last_name'],
        ]);

        return redirect()->to('player');
    }

    /**
     * Lets a logged-in player switch to a different member number (e.g. to
     * enter scores for someone in their group) without logging out.
     */
    public function switchMember()
    {
        session()->remove(['player_id', 'player_name']);

        return redirect()->to('login');
    }

    public function logout()
    {
        session()->destroy();

        return redirect()->to('/');
    }

    private function allowAttempt(string $kind): bool
    {
        $key = 'login-' . $kind . '-' . md5($this->request->getIPAddress());

        return service('throttler')->check($key, self::ATTEMPTS_PER_MINUTE, MINUTE);
    }

    private function tooManyAttempts(string $to)
    {
        return redirect()->to($to)->with('error', 'Too many attempts. Please wait a minute and try again.');
    }
}
