<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * A player's entry for one round: whether they are playing and paid, their
 * hole-by-hole strokes, and their results once the round is scored.
 */
class CreateScores extends Migration
{
    public function up()
    {
        $fields = [
            'id'        => ['type' => 'INTEGER', 'unsigned' => true, 'auto_increment' => true],
            'league_id' => ['type' => 'INTEGER', 'unsigned' => true],
            'player_id' => ['type' => 'INTEGER', 'unsigned' => true],
            'group_id'  => ['type' => 'INTEGER', 'unsigned' => true, 'null' => true], // group actually played in
            'round_id'  => ['type' => 'INTEGER', 'unsigned' => true],
            'nine'      => ['type' => 'VARCHAR', 'constraint' => 5, 'null' => true], // front or back
            'playing'   => ['type' => 'INTEGER', 'default' => 1],
            'paid'      => ['type' => 'INTEGER', 'default' => 0],
            'locked'    => ['type' => 'INTEGER', 'default' => 0],
        ];
        // Strokes per hole stay NULL until entered (never 0).
        for ($i = 1; $i <= 18; $i++) {
            $fields["hole{$i}"] = ['type' => 'INTEGER', 'null' => true];
        }
        $fields += [
            'gross'          => ['type' => 'INTEGER', 'null' => true],
            'handicap_used'  => ['type' => 'INTEGER', 'null' => true],
            'net'            => ['type' => 'INTEGER', 'null' => true],
            'place'          => ['type' => 'INTEGER', 'null' => true],
            'winnings_cents' => ['type' => 'INTEGER', 'null' => true],
            'image'          => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true], // scorecard picture
            'created_at'     => ['type' => 'DATETIME', 'null' => true],
            'updated_at'     => ['type' => 'DATETIME', 'null' => true],
        ];

        $this->forge->addField($fields);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['round_id', 'player_id']);
        $this->forge->addKey('player_id');
        $this->forge->addForeignKey('league_id', 'leagues', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('player_id', 'players', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('group_id', 'league_groups', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('round_id', 'rounds', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('scores');
    }

    public function down()
    {
        $this->forge->dropTable('scores');
    }
}
