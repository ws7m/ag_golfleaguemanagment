<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Each hole has one hole handicap (stroke index, 1-18), not one per tee, and
 * gains Net and Adj values. The handicap is copied from the white tee (or
 * blue, then red, if white was blank). A unique index stops two holes on the
 * same course having the same handicap.
 *
 * Columns are dropped with SQLite's own DROP COLUMN (see the
 * PlayerLoginAndPassword migration for why not Forge's dropColumn()).
 */
class OneHandicapPerHoleAddNetAdj extends Migration
{
    private const TEE_COLUMNS = ['blue_handicap', 'white_handicap', 'red_handicap'];

    public function up()
    {
        $holes = $this->db->prefixTable('holes');

        $this->forge->addColumn('holes', [
            'handicap' => ['type' => 'INTEGER', 'null' => true],
            'net'      => ['type' => 'INTEGER', 'null' => true],
            'adj'      => ['type' => 'INTEGER', 'null' => true],
        ]);

        $this->db->query("UPDATE {$holes} SET handicap = COALESCE(white_handicap, blue_handicap, red_handicap)");

        // If old data had the same handicap on two holes of a course, keep it
        // on the lower-numbered hole only, so the unique index can be created.
        $this->db->query(
            "UPDATE {$holes} SET handicap = NULL WHERE handicap IS NOT NULL AND EXISTS (
                SELECT 1 FROM {$holes} other
                WHERE other.course_id = {$holes}.course_id
                  AND other.handicap = {$holes}.handicap
                  AND other.number < {$holes}.number)"
        );

        foreach (self::TEE_COLUMNS as $column) {
            $this->db->query("ALTER TABLE {$holes} DROP COLUMN {$column}");
        }

        $this->db->query("CREATE UNIQUE INDEX {$holes}_course_handicap_unique ON {$holes} (course_id, handicap)");
    }

    public function down()
    {
        $holes = $this->db->prefixTable('holes');

        $this->db->query("DROP INDEX IF EXISTS {$holes}_course_handicap_unique");

        $columns = [];
        foreach (self::TEE_COLUMNS as $column) {
            $columns[$column] = ['type' => 'INTEGER', 'null' => true];
        }
        $this->forge->addColumn('holes', $columns);
        $this->db->query("UPDATE {$holes} SET blue_handicap = handicap, white_handicap = handicap, red_handicap = handicap");

        foreach (['handicap', 'net', 'adj'] as $column) {
            $this->db->query("ALTER TABLE {$holes} DROP COLUMN {$column}");
        }
    }
}
