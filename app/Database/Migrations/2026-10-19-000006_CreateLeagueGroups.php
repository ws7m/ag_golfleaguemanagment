<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Standing groups for a league: up to 6 regular players with a front-nine and
 * a back-nine tee time. Who actually played each round is on the score records.
 *
 * Named league_groups because GROUPS is an SQL keyword.
 */
class CreateLeagueGroups extends Migration
{
    public function up()
    {
        $fields = [
            'id'                 => ['type' => 'INTEGER', 'unsigned' => true, 'auto_increment' => true],
            'league_id'          => ['type' => 'INTEGER', 'unsigned' => true],
            'group_number'       => ['type' => 'INTEGER'],
            'front_tee_time_id'  => ['type' => 'INTEGER', 'unsigned' => true, 'null' => true],
            'back_tee_time_id'   => ['type' => 'INTEGER', 'unsigned' => true, 'null' => true],
        ];
        for ($i = 1; $i <= 6; $i++) {
            $fields["player{$i}_id"] = ['type' => 'INTEGER', 'unsigned' => true, 'null' => true];
        }
        $fields += [
            'active'     => ['type' => 'INTEGER', 'default' => 1],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ];

        $this->forge->addField($fields);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['league_id', 'group_number']);
        $this->forge->addForeignKey('league_id', 'leagues', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('front_tee_time_id', 'tee_times', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('back_tee_time_id', 'tee_times', 'id', 'RESTRICT', 'RESTRICT');
        for ($i = 1; $i <= 6; $i++) {
            $this->forge->addForeignKey("player{$i}_id", 'players', 'id', 'RESTRICT', 'RESTRICT');
        }
        $this->forge->createTable('league_groups');
    }

    public function down()
    {
        $this->forge->dropTable('league_groups');
    }
}
