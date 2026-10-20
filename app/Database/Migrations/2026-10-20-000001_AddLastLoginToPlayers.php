<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Records when each player (or admin) last logged in.
 */
class AddLastLoginToPlayers extends Migration
{
    public function up()
    {
        $this->forge->addColumn('players', [
            'last_login_at' => ['type' => 'DATETIME', 'null' => true, 'after' => 'admin_password_hash'],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('players', 'last_login_at');
    }
}
