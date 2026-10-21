<?php

namespace App\Libraries;

use App\Models\HoleModel;

/**
 * Checks and saves all 18 holes of a course at once. Used by the holes page
 * and by course CSV import, so both follow the same rules.
 */
class CourseHoles
{
    public const TEES = ['blue', 'white', 'red'];

    // Entered for each tee.
    public const STATS = ['yardage', 'par'];

    // Entered once per hole, after the tees.
    public const HOLE_FIELDS = ['handicap' => 'Hcp', 'net' => 'Net', 'adj' => 'Adj'];

    /**
     * Hole columns in display order: each tee's yardage and par, then Hcp,
     * Net and Adj.
     *
     * @return list<string>
     */
    public static function keys(): array
    {
        $keys = [];
        foreach (self::TEES as $tee) {
            foreach (self::STATS as $stat) {
                $keys[] = "{$tee}_{$stat}";
            }
        }

        return [...$keys, ...array_keys(self::HOLE_FIELDS)];
    }

    /**
     * A hole row ready to save, from entered values (blank becomes NULL).
     *
     * @param list<string>          $keys   Which columns to take from $values
     * @param array<string, mixed> $values
     */
    public static function row(int $courseId, int $number, array $keys, array $values): array
    {
        $row = ['course_id' => $courseId, 'number' => $number, 'nine' => $number <= 9 ? 'front' : 'back'];
        foreach ($keys as $key) {
            $value     = trim((string) ($values[$key] ?? ''));
            $row[$key] = $value === '' ? null : $value;
        }

        return $row;
    }

    /**
     * Problems with a set of hole rows, keyed by hole number: field errors,
     * and any hole handicap (1-18) used on more than one hole.
     *
     * @param array<int, array> $rows Hole number => row
     *
     * @return array<int, string>
     */
    public static function errors(array $rows): array
    {
        $model  = new HoleModel();
        $errors = [];
        foreach ($rows as $number => $row) {
            if (! $model->validate($row)) {
                $errors[$number] = implode(' ', $model->errors());
            }
        }

        $holesByHandicap = [];
        foreach ($rows as $number => $row) {
            if (($row['handicap'] ?? null) !== null && ! isset($errors[$number])) {
                $holesByHandicap[(int) $row['handicap']][] = $number;
            }
        }
        foreach ($holesByHandicap as $handicap => $numbers) {
            if (count($numbers) > 1) {
                foreach ($numbers as $number) {
                    $others          = array_diff($numbers, [$number]);
                    $errors[$number] = "Hcp {$handicap} is also used on hole " . implode(' and ', $others) . '.';
                }
            }
        }
        ksort($errors);

        return $errors;
    }

    /**
     * Saves hole rows for a course, adding holes that don't exist yet. Call
     * errors() first. Rows must include the handicap of every hole that has
     * one, because handicaps are cleared before saving so two holes can swap
     * numbers without tripping the unique index.
     *
     * @param array<int, array> $rows Hole number => row
     */
    public static function save(int $courseId, array $rows): void
    {
        $model    = new HoleModel();
        $existing = array_column($model->where('course_id', $courseId)->findAll(), 'id', 'number');
        $db       = db_connect();

        $db->transStart();
        $db->table('holes')->where('course_id', $courseId)->update(['handicap' => null]);
        foreach ($rows as $number => $row) {
            if (isset($existing[$number])) {
                $model->update($existing[$number], $row);
            } else {
                $model->insert($row);
            }
        }
        $db->transComplete();
    }
}
