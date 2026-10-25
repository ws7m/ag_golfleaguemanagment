<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Marks games played in teams (players' scores linked by team number)
 * rather than individually.
 */
class AddTeamGameToGames extends Migration
{
    public function up()
    {
        $this->forge->addColumn('games', [
            'team_game' => ['type' => 'INTEGER', 'null' => false, 'default' => 0],
        ]);
    }

    public function down()
    {
        // SQLite's own DROP COLUMN; Forge's dropColumn() rebuilds the table.
        $this->db->query('ALTER TABLE ' . $this->db->prefixTable('games') . ' DROP COLUMN team_game');
    }
}
