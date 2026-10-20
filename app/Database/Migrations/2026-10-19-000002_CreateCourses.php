<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateCourses extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'          => ['type' => 'INTEGER', 'unsigned' => true, 'auto_increment' => true],
            'name'        => ['type' => 'VARCHAR', 'constraint' => 100],
            'description' => ['type' => 'TEXT', 'null' => true],
            'address'     => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'city'        => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'state'       => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'zip'         => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'phone'       => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'email'       => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'image'       => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            // Course rating and front/back nine slopes. Per-tee ratings come in Phase 5.
            'rating'      => ['type' => 'DECIMAL', 'constraint' => '4,1', 'null' => true],
            'front_slope' => ['type' => 'INTEGER', 'null' => true],
            'back_slope'  => ['type' => 'INTEGER', 'null' => true],
            'active'      => ['type' => 'INTEGER', 'default' => 1],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
            'updated_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('courses');
    }

    public function down()
    {
        $this->forge->dropTable('courses');
    }
}
