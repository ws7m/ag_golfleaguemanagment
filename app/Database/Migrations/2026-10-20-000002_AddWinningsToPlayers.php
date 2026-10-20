<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Winnings entered by the admin on the player's edit page (e.g. while payouts
 * are worked out outside the app). Added to winnings from scored rounds.
 */
class AddWinningsToPlayers extends Migration
{
    public function up()
    {
        $this->forge->addColumn('players', [
            'winnings_cents' => ['type' => 'INTEGER', 'null' => true, 'default' => 0],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('players', 'winnings_cents');
    }
}
