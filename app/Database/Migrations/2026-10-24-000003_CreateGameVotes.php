<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Players' votes for the game they'd like to play in a scheduled round.
 *
 * game_votes keeps the count for each game in each round. game_voters
 * records who has voted in a round (and for what), so each player votes
 * only once per round.
 */
class CreateGameVotes extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'       => ['type' => 'INTEGER', 'unsigned' => true, 'auto_increment' => true],
            'round_id' => ['type' => 'INTEGER', 'unsigned' => true],
            'game_id'  => ['type' => 'INTEGER', 'unsigned' => true],
            'count'    => ['type' => 'INTEGER', 'default' => 0],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['round_id', 'game_id']);
        $this->forge->addForeignKey('round_id', 'rounds', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('game_id', 'games', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('game_votes');

        $this->forge->addField([
            'id'         => ['type' => 'INTEGER', 'unsigned' => true, 'auto_increment' => true],
            'round_id'   => ['type' => 'INTEGER', 'unsigned' => true],
            'player_id'  => ['type' => 'INTEGER', 'unsigned' => true],
            'game_id'    => ['type' => 'INTEGER', 'unsigned' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['round_id', 'player_id']);
        $this->forge->addForeignKey('round_id', 'rounds', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('player_id', 'players', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('game_id', 'games', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('game_voters');
    }

    public function down()
    {
        $this->forge->dropTable('game_voters');
        $this->forge->dropTable('game_votes');
    }
}
