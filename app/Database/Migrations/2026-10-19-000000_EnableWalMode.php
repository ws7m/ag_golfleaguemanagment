<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Switches the SQLite database to WAL (write-ahead log) mode.
 *
 * WAL lets players read pages while others are saving scores. The mode is
 * stored in the database file itself, so it only needs to be set once.
 */
class EnableWalMode extends Migration
{
    public function up()
    {
        if ($this->db->DBDriver === 'SQLite3' && $this->db->database !== ':memory:') {
            $this->db->query('PRAGMA journal_mode = WAL');
        }
    }

    public function down()
    {
        if ($this->db->DBDriver === 'SQLite3' && $this->db->database !== ':memory:') {
            $this->db->query('PRAGMA journal_mode = DELETE');
        }
    }
}
