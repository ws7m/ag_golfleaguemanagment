<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Email server (SMTP) details for sending league email, such as the tee
 * time lists. The password is stored encrypted with the app's key.
 */
class AddEmailServerToSettings extends Migration
{
    private const COLUMNS = ['smtp_host', 'smtp_port', 'smtp_crypto', 'smtp_user', 'smtp_pass_encrypted', 'mail_from_email', 'mail_from_name'];

    public function up()
    {
        $this->forge->addColumn('settings', [
            'smtp_host'           => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'smtp_port'           => ['type' => 'INTEGER', 'null' => true],
            // tls (STARTTLS, usually port 587), ssl (usually port 465) or none
            'smtp_crypto'         => ['type' => 'VARCHAR', 'constraint' => 10, 'null' => true],
            'smtp_user'           => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'smtp_pass_encrypted' => ['type' => 'TEXT', 'null' => true],
            'mail_from_email'     => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'mail_from_name'      => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
        ]);
    }

    public function down()
    {
        // SQLite's own DROP COLUMN; Forge's dropColumn() rebuilds the table.
        foreach (self::COLUMNS as $column) {
            $this->db->query('ALTER TABLE ' . $this->db->prefixTable('settings') . " DROP COLUMN {$column}");
        }
    }
}
