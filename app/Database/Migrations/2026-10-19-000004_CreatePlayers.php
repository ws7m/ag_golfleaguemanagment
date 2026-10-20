<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Players, including admins (players with is_admin set).
 */
class CreatePlayers extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'                    => ['type' => 'INTEGER', 'unsigned' => true, 'auto_increment' => true],
            // Random, non-sequential 3-digit number (100-999) the player enters to log in.
            'member_number'         => ['type' => 'INTEGER'],
            // Set to the current league when the player pays and registers.
            'league_id'             => ['type' => 'INTEGER', 'unsigned' => true, 'null' => true],
            'first_name'            => ['type' => 'VARCHAR', 'constraint' => 50],
            'last_name'             => ['type' => 'VARCHAR', 'constraint' => 50],
            'gender'                => ['type' => 'VARCHAR', 'constraint' => 1, 'null' => true], // M or F
            'email'                 => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'phone'                 => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'text_msg_ok'           => ['type' => 'INTEGER', 'default' => 0],
            'tee'                   => ['type' => 'VARCHAR', 'constraint' => 5, 'null' => true], // red, white or blue
            'handicap'              => ['type' => 'INTEGER', 'null' => true],
            'flight'                => ['type' => 'INTEGER', 'null' => true], // 1, 2 or 3
            'desired_tee_time'      => ['type' => 'VARCHAR', 'constraint' => 5, 'null' => true], // HH:MM
            'desired_group_members' => ['type' => 'TEXT', 'null' => true],
            'is_admin'              => ['type' => 'INTEGER', 'default' => 0],
            'admin_password_hash'   => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'active'                => ['type' => 'INTEGER', 'default' => 1],
            'created_at'            => ['type' => 'DATETIME', 'null' => true],
            'updated_at'            => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('member_number');
        $this->forge->addKey('email');
        $this->forge->addForeignKey('league_id', 'leagues', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('players');
    }

    public function down()
    {
        $this->forge->dropTable('players');
    }
}
