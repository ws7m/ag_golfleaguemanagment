<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * The team members a player names on their score entry page for a team
 * game (player ids). "Link Teams" on the admin scores page turns them into
 * team numbers.
 */
class AddTeamMembersToScores extends Migration
{
    private const COLUMNS = ['team_member1_id', 'team_member2_id', 'team_member3_id', 'team_member4_id'];

    public function up()
    {
        $fields = [];
        foreach (self::COLUMNS as $column) {
            $fields[$column] = ['type' => 'INTEGER', 'unsigned' => true, 'null' => true];
        }
        $this->forge->addColumn('scores', $fields);
    }

    public function down()
    {
        // SQLite's own DROP COLUMN; Forge's dropColumn() rebuilds the table.
        foreach (self::COLUMNS as $column) {
            $this->db->query('ALTER TABLE ' . $this->db->prefixTable('scores') . " DROP COLUMN {$column}");
        }
    }
}
