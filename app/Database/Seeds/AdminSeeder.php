<?php

namespace App\Database\Seeds;

use App\Models\PlayerModel;
use App\Models\SettingModel;
use CodeIgniter\Database\Seeder;
use RuntimeException;

/**
 * Creates the default admin player, or updates their password if they exist.
 *
 * The password is read from the environment so it is never stored in the repo:
 *
 *   ADMIN_EMAIL=you@example.com ADMIN_PASSWORD='...' \
 *   ADMIN_FIRST_NAME=First ADMIN_LAST_NAME=Last php spark db:seed AdminSeeder
 *
 * Also creates the settings row, with the admin as the contact, if it is missing.
 */
class AdminSeeder extends Seeder
{
    public function run()
    {
        $email     = trim((string) getenv('ADMIN_EMAIL'));
        $password  = (string) getenv('ADMIN_PASSWORD');
        $firstName = trim((string) getenv('ADMIN_FIRST_NAME')) ?: 'Admin';
        $lastName  = trim((string) getenv('ADMIN_LAST_NAME')) ?: 'User';

        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Set ADMIN_EMAIL to a valid email address.');
        }
        if (strlen($password) < 8) {
            throw new RuntimeException('Set ADMIN_PASSWORD to at least 8 characters.');
        }

        $players  = new PlayerModel();
        $existing = $players->where('email', $email)->where('is_admin', 1)->first();

        if ($existing === null) {
            $id = $players->insert([
                'first_name' => $firstName,
                'last_name'  => $lastName,
                'email'      => $email,
                'is_admin'   => 1,
                'active'     => 1,
            ]);
            if ($id === false) {
                throw new RuntimeException(implode(' ', $players->errors()));
            }
            $action = 'Created';
        } else {
            $id     = (int) $existing['id'];
            $action = 'Updated';
        }

        $players->setAdminPassword((int) $id, $password);

        $settings = new SettingModel();
        if ($settings->countAllResults() === 0) {
            $settings->insert([
                'admin_contact_name'  => "{$firstName} {$lastName}",
                'admin_contact_email' => $email,
            ]);
        }

        $player = $players->find($id);
        echo "{$action} admin {$player['first_name']} {$player['last_name']} <{$email}>, "
            . "member number {$player['member_number']}." . PHP_EOL;
    }
}
