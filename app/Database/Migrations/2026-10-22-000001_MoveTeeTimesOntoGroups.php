<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Tee times are no longer a separate list. Each group stores its own front
 * nine and back nine tee time ("HH:MM"), copied here from the tee times it
 * pointed at, and the tee_times table is dropped.
 *
 * league_groups is rebuilt the way SQLite recommends (create the new table,
 * copy, drop the old one, rename the new one into place) with foreign keys
 * switched off. Forge's own rebuild renames the old table first, which makes
 * SQLite repoint scores.group_id at that temporary name and breaks it.
 */
class MoveTeeTimesOntoGroups extends Migration
{
    public function up()
    {
        $groups   = $this->db->prefixTable('league_groups');
        $teeTimes = $this->db->prefixTable('tee_times');

        $this->rebuildGroups(
            "`front_tee_time` VARCHAR(5) NULL, `back_tee_time` VARCHAR(5) NULL,",
            '',
            "(SELECT tee_time FROM {$teeTimes} t WHERE t.id = g.front_tee_time_id),
             (SELECT tee_time FROM {$teeTimes} t WHERE t.id = g.back_tee_time_id),",
            'front_tee_time, back_tee_time,'
        );

        $this->db->query("DROP TABLE {$teeTimes}");
    }

    public function down()
    {
        $groups   = $this->db->prefixTable('league_groups');
        $teeTimes = $this->db->prefixTable('tee_times');
        $leagues  = $this->db->prefixTable('leagues');
        $courses  = $this->db->prefixTable('courses');

        // The tee time list comes back empty; groups lose their times.
        $this->db->query(
            "CREATE TABLE {$teeTimes} (
                `id` INTEGER PRIMARY KEY AUTOINCREMENT,
                `league_id` INTEGER NOT NULL,
                `course_id` INTEGER NOT NULL,
                `nine` VARCHAR(5) NOT NULL,
                `tee_time` VARCHAR(5) NOT NULL,
                `created_at` DATETIME NULL,
                `updated_at` DATETIME NULL,
                FOREIGN KEY (`league_id`) REFERENCES {$leagues}(`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
                FOREIGN KEY (`course_id`) REFERENCES {$courses}(`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
            )"
        );

        $this->rebuildGroups(
            "`front_tee_time_id` INTEGER NULL, `back_tee_time_id` INTEGER NULL,",
            "FOREIGN KEY (`front_tee_time_id`) REFERENCES {$teeTimes}(`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
             FOREIGN KEY (`back_tee_time_id`) REFERENCES {$teeTimes}(`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,",
            'NULL, NULL,',
            'front_tee_time_id, back_tee_time_id,'
        );
    }

    /**
     * Rebuilds league_groups with different tee time columns.
     *
     * @param string $teeColumns  Column definitions for the tee time columns
     * @param string $teeKeys     Foreign keys for them (may be empty)
     * @param string $selectTees  What to copy into them from the old table "g"
     * @param string $insertTees  Their names, for the INSERT column list
     */
    private function rebuildGroups(string $teeColumns, string $teeKeys, string $selectTees, string $insertTees): void
    {
        $groups  = $this->db->prefixTable('league_groups');
        $new     = $groups . '_new';
        $leagues = $this->db->prefixTable('leagues');
        $players = $this->db->prefixTable('players');

        $playerColumns = [];
        $playerKeys    = [];
        for ($i = 1; $i <= 6; $i++) {
            $playerColumns[] = "`player{$i}_id` INTEGER NULL";
            $playerKeys[]    = "FOREIGN KEY (`player{$i}_id`) REFERENCES {$players}(`id`) ON DELETE RESTRICT ON UPDATE RESTRICT";
        }
        $playerList = implode(', ', array_map(static fn ($i) => "player{$i}_id", range(1, 6)));

        $this->db->query('PRAGMA foreign_keys = OFF');

        $this->db->query(
            "CREATE TABLE {$new} (
                `id` INTEGER PRIMARY KEY AUTOINCREMENT,
                `league_id` INTEGER NOT NULL,
                `group_number` INTEGER NOT NULL,
                `nine` VARCHAR(5) DEFAULT 'front',
                {$teeColumns}
                " . implode(",\n", $playerColumns) . ",
                `active` INTEGER NOT NULL DEFAULT 1,
                `created_at` DATETIME NULL,
                `updated_at` DATETIME NULL,
                FOREIGN KEY (`league_id`) REFERENCES {$leagues}(`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
                {$teeKeys}
                " . implode(",\n", $playerKeys) . '
            )'
        );

        $this->db->query(
            "INSERT INTO {$new} (id, league_id, group_number, nine, {$insertTees} {$playerList}, active, created_at, updated_at)
             SELECT g.id, g.league_id, g.group_number, g.nine, {$selectTees} {$playerList}, g.active, g.created_at, g.updated_at
             FROM {$groups} g"
        );

        $this->db->query("DROP TABLE {$groups}");
        $this->db->query("ALTER TABLE {$new} RENAME TO {$groups}");
        $this->db->query("CREATE UNIQUE INDEX {$groups}_league_id_group_number ON {$groups} (league_id, group_number)");

        $this->db->query('PRAGMA foreign_keys = ON');
    }
}
