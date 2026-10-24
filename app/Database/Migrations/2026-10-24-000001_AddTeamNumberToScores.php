<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Links scores into teams for team games. Players in the same round with the
 * same team number are a team; 0 means an individual score.
 */
class AddTeamNumberToScores extends Migration
{
    public function up()
    {
        $this->forge->addColumn('scores', [
            'team_number' => ['type' => 'INTEGER', 'null' => false, 'default' => 0],
        ]);
    }

    public function down()
    {
        // SQLite's own DROP COLUMN; Forge's dropColumn() rebuilds the table,
        // which breaks other tables' foreign keys (see PlayerLoginAndPassword).
        $this->db->query('ALTER TABLE ' . $this->db->prefixTable('scores') . ' DROP COLUMN team_number');
    }
}
