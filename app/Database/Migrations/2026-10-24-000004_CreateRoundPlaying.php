<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Whether a player says they are playing in a scheduled round, set from the
 * player's page. No row means playing. When the round is made active, each
 * player's score record is created with this choice; after that the choice
 * is kept on the score (scores.playing).
 */
class CreateRoundPlaying extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'         => ['type' => 'INTEGER', 'unsigned' => true, 'auto_increment' => true],
            'round_id'   => ['type' => 'INTEGER', 'unsigned' => true],
            'player_id'  => ['type' => 'INTEGER', 'unsigned' => true],
            'playing'    => ['type' => 'INTEGER', 'default' => 1],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['round_id', 'player_id']);
        $this->forge->addForeignKey('round_id', 'rounds', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('player_id', 'players', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('round_playing');
    }

    public function down()
    {
        $this->forge->dropTable('round_playing');
    }
}
