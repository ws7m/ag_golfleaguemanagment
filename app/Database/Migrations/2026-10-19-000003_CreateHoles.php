<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Holes 1-18 of a course, with yardage, par and hole handicap for each tee.
 */
class CreateHoles extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'             => ['type' => 'INTEGER', 'unsigned' => true, 'auto_increment' => true],
            'course_id'      => ['type' => 'INTEGER', 'unsigned' => true],
            'number'         => ['type' => 'INTEGER'],
            'nine'           => ['type' => 'VARCHAR', 'constraint' => 5], // front or back
            'blue_yardage'   => ['type' => 'INTEGER', 'null' => true],
            'blue_par'       => ['type' => 'INTEGER', 'null' => true],
            'blue_handicap'  => ['type' => 'INTEGER', 'null' => true],
            'white_yardage'  => ['type' => 'INTEGER', 'null' => true],
            'white_par'      => ['type' => 'INTEGER', 'null' => true],
            'white_handicap' => ['type' => 'INTEGER', 'null' => true],
            'red_yardage'    => ['type' => 'INTEGER', 'null' => true],
            'red_par'        => ['type' => 'INTEGER', 'null' => true],
            'red_handicap'   => ['type' => 'INTEGER', 'null' => true],
            'image'          => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'created_at'     => ['type' => 'DATETIME', 'null' => true],
            'updated_at'     => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['course_id', 'number']);
        $this->forge->addForeignKey('course_id', 'courses', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('holes');
    }

    public function down()
    {
        $this->forge->dropTable('holes');
    }
}
