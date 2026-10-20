<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Site-wide settings. There is a single row.
 */
class CreateSettings extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'                   => ['type' => 'INTEGER', 'unsigned' => true, 'auto_increment' => true],
            'shared_login_id'      => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'shared_password_hash' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'admin_contact_name'   => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'admin_contact_email'  => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'created_at'           => ['type' => 'DATETIME', 'null' => true],
            'updated_at'           => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('settings');
    }

    public function down()
    {
        $this->forge->dropTable('settings');
    }
}
