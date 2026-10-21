<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddWebsiteToCourses extends Migration
{
    public function up()
    {
        $this->forge->addColumn('courses', [
            'website' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
        ]);
    }

    public function down()
    {
        // SQLite's own DROP COLUMN; Forge's dropColumn() rebuilds the table,
        // which breaks other tables' foreign keys (see PlayerLoginAndPassword).
        $this->db->query('ALTER TABLE ' . $this->db->prefixTable('courses') . ' DROP COLUMN website');
    }
}
