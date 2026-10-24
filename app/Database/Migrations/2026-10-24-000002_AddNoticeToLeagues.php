<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * A notice for the league, shown on the player's page as "League Notice".
 */
class AddNoticeToLeagues extends Migration
{
    public function up()
    {
        $this->forge->addColumn('leagues', [
            'notice' => ['type' => 'TEXT', 'null' => true],
        ]);
    }

    public function down()
    {
        // SQLite's own DROP COLUMN; Forge's dropColumn() rebuilds the table,
        // which breaks other tables' foreign keys (see PlayerLoginAndPassword).
        $this->db->query('ALTER TABLE ' . $this->db->prefixTable('leagues') . ' DROP COLUMN notice');
    }
}
