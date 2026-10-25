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
        }

        // First login (or a password an admin set): choose a new password.
        if ($player['must_change_password']) {
            return redirect()->to('password');
        }

        return redirect()->to($player['is_admin'] ? 'admin' : 'player');
    }

    // Shortest password accepted.
    public const MIN_PASSWORD_LENGTH = 8;

    /**
     * The change password page, for anyone logged in. Players (and admins)
     * are sent here after logging in for the first time, until they choose
     * their own password.
     */
    public function passwordForm()
    {
        $id = $this->accountId();
        if ($id === null) {
            return redirect()->to('login');
        }

        return view('auth/password', [
            'title'  => 'Change password',
            'forced' => (new PlayerModel())->mustChangePassword($id),
            'errors' => [],
        ]);
    }

    public function changePassword()
    {
        $id = $this->accountId();
        if ($id === null) {
            return redirect()->to('login');
        }
        $players  = new PlayerModel();
        $player   = $players->find($id);
        $forced   = $players->mustChangePassword($id);
        $current  = (string) $this->request->getPost('current_password');
        $password = (string) $this->request->getPost('password');
        $confirm  = (string) $this->request->getPost('password_confirm');

        // They just logged in with the starting password, so it isn't asked
        // for again on the first change.
        $errors = $this->passwordErrors($players, $player, $forced ? null : $current, $password, $confirm);

        if ($errors !== []) {
            return view('auth/password', ['title' => 'Change password', 'forced' => $forced, 'errors' => $errors]);
        }

        $players->setPassword($id, $password);

        return redirect()->to(session('admin_id') ? 'admin' : 'player')->with('message', 'Your new password is saved.');
    }

    /**
     * Problems with a new password, or [] if it can be saved. $current is
     * checked against the saved password unless it is null.
     *
     * @return list<string>
     */
    private function passwordErrors(PlayerModel $players, array $player, ?string $current, string $password, string $confirm): array
    {
        $id     = (int) $player['id'];
        $errors = [];
        if ($current !== null && ! $players->passwordMatches($id, $current)) {
            $errors[] = 'Your current password is not correct.';
        }
        if (strlen($password) < self::MIN_PASSWORD_LENGTH) {
            $errors[] = 'The new password must be at least ' . self::MIN_PASSWORD_LENGTH . ' characters.';
        } elseif ($password !== $confirm) {
            $errors[] = "The two new passwords don't match.";
        } elseif ($players->passwordMatches($id, $password)) {
            $errors[] = 'Choose a password different from the one you have now.';
        } elseif (strtolower($password) === PlayerModel::defaultPassword((string) $player['last_name'])
            || strtolower($password) === strtolower((string) $player['login'])) {
            $errors[] = 'Choose a password that isn\'t your last name or login.';
        }

        return $errors;
    }

    /** What a player can change about themselves on the Update Info page. */
    private const INFO_FIELDS = [
        'first_name', 'last_name', 'gender', 'email', 'phone', 'text_msg_ok', 'desired_tee_time', 'desired_group_members',
    ];

    /**
     * The Update Info page: anyone logged in can change their own name,
     * contact details, preferences and password.
     */
    public function infoForm()
    {
        $id = $this->accountId();
        if ($id === null) {
            return redirect()->to('login');
        }

        return view('auth/info', ['title' => 'Update info', 'player' => (new PlayerModel())->find($id), 'errors' => []]);
    }

    public function saveInfo()
    {
        $id = $this->accountId();
        if ($id === null) {
            return redirect()->to('login');
        }
        $players = new PlayerModel();
        $player  = $players->find($id);

        $data = [];
        foreach (self::INFO_FIELDS as $field) {
            $value        = trim((string) $this->request->getPost($field));
            $data[$field] = $value === '' ? null : $value;
        }
        $data['text_msg_ok'] = $this->request->getPost('text_msg_ok') ? 1 : 0;

        $errors = [];
        if (! $players->validate(['id' => $id] + $data)) {
            $errors = array_values($players->errors());
        }
        // A new password is optional here; changing it needs the current one.
        $password = (string) $this->request->getPost('password');
        if ($password !== '') {
            $errors = [...$errors, ...$this->passwordErrors(
                $players, $player, (string) $this->request->getPost('current_password'), $password, (string) $this->request->getPost('password_confirm')
            )];
        }

        if ($errors !== []) {
            return view('auth/info', ['title' => 'Update info', 'player' => $data + $player, 'errors' => $errors]);
        }

        $players->skipValidation(true)->update($id, $data);
        if ($password !== '') {
            $players->setPassword($id, $password);
        }

        // Show the new name at the top of the page.
        $name = "{$data['first_name']} {$data['last_name']}";
        if (session('player_id')) {
            session()->set('player_name', $name);
        }
        if (session('admin_id')) {
            session()->set('admin_name', $name);
        }

        return redirect()->to('info')->with('message', $password !== '' ? 'Your information and new password are saved.' : 'Your information is saved.');
    }

    /**
     * The logged-in admin's or player's id, or null.
     */
    private function accountId(): ?int
    {
        $id = (int) (session('admin_id') ?: session('player_id'));

        return $id > 0 ? $id : null;
    }

    public function logout()
    {
        session()->destroy();

        return redirect()->to('/');
    }
}
