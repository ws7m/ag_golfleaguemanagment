<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * A fifth team member on scores, for teams of five.
 */
class AddFifthTeamMemberToScores extends Migration
{
    public function up()
    {
        $this->forge->addColumn('scores', [
            'team_member5_id' => ['type' => 'INTEGER', 'unsigned' => true, 'null' => true],
        ]);
    }

    public function down()
    {
        // SQLite's own DROP COLUMN; Forge's dropColumn() rebuilds the table.
        $this->db->query('ALTER TABLE ' . $this->db->prefixTable('scores') . ' DROP COLUMN team_member5_id');
    }
}
