<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateTeeTimes extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'         => ['type' => 'INTEGER', 'unsigned' => true, 'auto_increment' => true],
            'league_id'  => ['type' => 'INTEGER', 'unsigned' => true],
            'course_id'  => ['type' => 'INTEGER', 'unsigned' => true],
            'nine'       => ['type' => 'VARCHAR', 'constraint' => 5], // front or back
            'tee_time'   => ['type' => 'VARCHAR', 'constraint' => 5], // HH:MM, 24-hour
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('league_id', 'leagues', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('course_id', 'courses', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('tee_times');
    }

    public function down()
    {
        $this->forge->dropTable('tee_times');
    }
}
