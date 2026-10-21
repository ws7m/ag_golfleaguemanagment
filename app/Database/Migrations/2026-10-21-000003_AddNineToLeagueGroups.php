<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Which nine a standing group is playing now: front or back. Groups switch
 * nines from one round to the next.
 */
class AddNineToLeagueGroups extends Migration
{
    public function up()
    {
        $this->forge->addColumn('league_groups', [
            'nine' => ['type' => 'VARCHAR', 'constraint' => 5, 'default' => 'front'],
        ]);
    }

    public function down()
    {
        // SQLite's own DROP COLUMN; Forge's dropColumn() rebuilds the table,
        // which breaks other tables' foreign keys (see PlayerLoginAndPassword).
        $this->db->query('ALTER TABLE ' . $this->db->prefixTable('league_groups') . ' DROP COLUMN nine');
    }
}
