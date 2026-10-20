<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * One round for each day the league plays.
 */
class CreateRounds extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'               => ['type' => 'INTEGER', 'unsigned' => true, 'auto_increment' => true],
            'league_id'        => ['type' => 'INTEGER', 'unsigned' => true],
            'course_id'        => ['type' => 'INTEGER', 'unsigned' => true],
            'game_id'          => ['type' => 'INTEGER', 'unsigned' => true, 'null' => true],
            'round_date'       => ['type' => 'DATE'],
            'name'             => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'description'      => ['type' => 'TEXT', 'null' => true],
            'image'            => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            // scheduled, active (live scoring open), closed (no new scores), scored (results done)
            'status'           => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'scheduled'],
            'entry_fee_cents'  => ['type' => 'INTEGER', 'default' => 1000],
            'use_for_handicap' => ['type' => 'INTEGER', 'default' => 0],
            // Total of the entry fees marked paid; set when the round is scored.
            'total_pot_cents'  => ['type' => 'INTEGER', 'null' => true],
            'created_at'       => ['type' => 'DATETIME', 'null' => true],
            'updated_at'       => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['league_id', 'round_date']);
        $this->forge->addForeignKey('league_id', 'leagues', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('course_id', 'courses', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('game_id', 'games', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('rounds');
    }

    public function down()
    {
        $this->forge->dropTable('rounds');
    }
}
