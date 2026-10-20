<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateGames extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'           => ['type' => 'INTEGER', 'unsigned' => true, 'auto_increment' => true],
            'league_id'    => ['type' => 'INTEGER', 'unsigned' => true],
            'name'         => ['type' => 'VARCHAR', 'constraint' => 100],
            'description'  => ['type' => 'TEXT', 'null' => true],
            'scoring_info' => ['type' => 'TEXT', 'null' => true], // Format still to be defined
            'active'       => ['type' => 'INTEGER', 'default' => 1],
            'created_at'   => ['type' => 'DATETIME', 'null' => true],
            'updated_at'   => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('league_id', 'leagues', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('games');
    }

    public function down()
    {
        $this->forge->dropTable('games');
    }
}
