<?php

namespace App\Database\Seeds;

use App\Models\PlayerModel;
use App\Models\SettingModel;
use CodeIgniter\Database\Seeder;
use RuntimeException;

/**
 * Creates the initial admin account, or resets its password if it exists.
 * The initial admin is admin-only: not a league player, so it is not on the
 * player lists.
 *
 * The password is read from the environment so it is never stored in the repo:
 *
 *   ADMIN_LOGIN=admin1 ADMIN_PASSWORD='...' php spark db:seed AdminSeeder
 *
 * Optional: ADMIN_EMAIL, ADMIN_FIRST_NAME, ADMIN_LAST_NAME. If there is no
 * settings row yet, one is created with the admin as the contact.
 */
class AdminSeeder extends Seeder
{
    public function run()
    {
        $login     = strtolower(trim((string) getenv('ADMIN_LOGIN'))) ?: 'admin1';
        $password  = (string) getenv('ADMIN_PASSWORD');
        $email     = trim((string) getenv('ADMIN_EMAIL')) ?: null;
        $firstName = trim((string) getenv('ADMIN_FIRST_NAME')) ?: 'League';
        $lastName  = trim((string) getenv('ADMIN_LAST_NAME')) ?: 'Admin';

        if (! ctype_alnum($login)) {
            throw new RuntimeException('ADMIN_LOGIN can only contain letters and numbers.');
        }
        if (strlen($password) < 8) {
            throw new RuntimeException('Set ADMIN_PASSWORD to at least 8 characters.');
        }
        if ($email !== null && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('ADMIN_EMAIL is not a valid email address.');
        }

        $players  = new PlayerModel();
        $existing = $players->where('login', $login)->first();

        if ($existing === null) {
            $id = $players->insert([
                'login'      => $login,
                'first_name' => $firstName,
                'last_name'  => $lastName,
                'email'      => $email,
                'is_admin'   => 1,
                'is_player'  => 0,
                'active'     => 1,
            ]);
            if ($id === false) {
                throw new RuntimeException(implode(' ', $players->errors()));
            }
            $action = 'Created';
        } else {
            $id = (int) $existing['id'];
            $players->update($id, ['id' => $id, 'is_admin' => 1, 'is_player' => 0, 'active' => 1]);
            $action = 'Updated';
        }

        $players->setPassword((int) $id, $password);

        $settings = new SettingModel();
        if ($settings->countAllResults() === 0) {
            $settings->insert([
                'admin_contact_name'  => "{$firstName} {$lastName}",
                'admin_contact_email' => $email,
            ]);
        }

        echo "{$action} admin-only account \"{$login}\"." . PHP_EOL;
    }
}
