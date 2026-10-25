<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Players must choose their own password the first time they log in (and
 * after an admin sets one for them). Existing accounts that have never been
 * used are marked too, since they still have the starting password.
 */
class AddMustChangePasswordToPlayers extends Migration
{
    public function up()
    {
        $this->forge->addColumn('players', [
            'must_change_password' => ['type' => 'INTEGER', 'null' => false, 'default' => 0],
        ]);
        $this->db->table('players')->where('last_login_at', null)->update(['must_change_password' => 1]);
    }

    public function down()
    {
        // SQLite's own DROP COLUMN; Forge's dropColumn() rebuilds the table,
        // which breaks other tables' foreign keys (see PlayerLoginAndPassword).
        $this->db->query('ALTER TABLE ' . $this->db->prefixTable('players') . ' DROP COLUMN must_change_password');
    }
}
