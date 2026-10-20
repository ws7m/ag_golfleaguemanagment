<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * A league is one season. A new league record is created each year.
 */
class CreateLeagues extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'          => ['type' => 'INTEGER', 'unsigned' => true, 'auto_increment' => true],
            'name'        => ['type' => 'VARCHAR', 'constraint' => 100],
            'description' => ['type' => 'TEXT', 'null' => true],
            'image'       => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'start_date'  => ['type' => 'DATE', 'null' => true],
            'end_date'    => ['type' => 'DATE', 'null' => true],
            'website'     => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'email'       => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'active'      => ['type' => 'INTEGER', 'default' => 1],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
            'updated_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('leagues');
    }

    public function down()
    {
        $this->forge->dropTable('leagues');
    }
}
