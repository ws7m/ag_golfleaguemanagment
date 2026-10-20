<?php

namespace App\Database\Migrations;

use App\Models\PlayerModel;
use CodeIgniter\Database\Migration;

/**
 * Each player gets their own login and password, replacing the shared player
 * login in settings:
 *
 * - players.login: up to 5 letters of the last name plus the first initial
 *   (e.g. "holbrm"), filled in here for existing players.
 * - players.password_hash: for every player; existing admin passwords move here.
 * - players.is_player: 0 for admin-only accounts that are not league players.
 *
 * Columns are dropped with SQLite's own DROP COLUMN. Forge's dropColumn()
 * rebuilds the table, which makes SQLite repoint other tables' foreign keys
 * at its temporary table name and breaks them.
 */
class PlayerLoginAndPassword extends Migration
{
    public function up()
    {
        $players  = $this->db->prefixTable('players');
        $settings = $this->db->prefixTable('settings');

        $this->forge->addColumn('players', [
            'login'         => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'password_hash' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'is_player'     => ['type' => 'INTEGER', 'default' => 1],
        ]);

        $this->db->query("UPDATE {$players} SET password_hash = admin_password_hash");
        $this->db->query("ALTER TABLE {$players} DROP COLUMN admin_password_hash");

        // Give existing players a login, oldest first, numbering duplicates.
        $taken = [];
        foreach ($this->db->table('players')->orderBy('id')->get()->getResultArray() as $player) {
            $base  = PlayerModel::loginBase($player['first_name'], $player['last_name']);
            $login = $base;
            for ($n = 2; isset($taken[$login]); $n++) {
                $login = $base . $n;
            }
            $taken[$login] = true;
            $this->db->table('players')->where('id', $player['id'])->update(['login' => $login]);
        }

        $this->db->query("CREATE UNIQUE INDEX {$players}_login_unique ON {$players} (login)");

        $this->db->query("ALTER TABLE {$settings} DROP COLUMN shared_login_id");
        $this->db->query("ALTER TABLE {$settings} DROP COLUMN shared_password_hash");
    }

    public function down()
    {
        $players = $this->db->prefixTable('players');

        $this->forge->addColumn('settings', [
            'shared_login_id'      => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'shared_password_hash' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
        ]);

        $this->db->query("DROP INDEX IF EXISTS {$players}_login_unique");
        $this->forge->addColumn('players', [
            'admin_password_hash' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
        ]);
        $this->db->query("UPDATE {$players} SET admin_password_hash = password_hash WHERE is_admin = 1");
        foreach (['login', 'password_hash', 'is_player'] as $column) {
            $this->db->query("ALTER TABLE {$players} DROP COLUMN {$column}");
        }
    }
}
