<?php

namespace App\Libraries;

use App\Models\CourseModel;
use App\Models\HoleModel;

/**
 * Writes and reads a course and its holes as a CSV file.
 *
 * Layout:
 *
 *   field,value              <- course details: field name in column A,
 *   name,Southridge Golf...     value in column B
 *   ...
 *                             <- blank line
 *   number,blue_yardage,...  <- hole header: one field name per column
 *   1,317,4,...              <- holes 1 to 18
 */
class CourseCsv
{
    /** Course fields written to and read from the file, in order. */
    public const COURSE_FIELDS = [
        'name', 'description', 'address', 'city', 'state', 'zip', 'phone', 'email', 'website', 'image',
        'rating', 'front_slope', 'back_slope', 'active',
    ];

    /** Hole columns after "number". */
    public static function holeFields(): array
    {
        return [...CourseHoles::keys(), 'image'];
    }

    /**
     * The CSV text for a course and its holes. Starts with a UTF-8 byte order
     * mark so Excel shows accented characters correctly.
     *
     * @param array<int, array> $holes Rows from the holes table
     */
    public static function export(array $course, array $holes): string
    {
        $out = fopen('php://temp', 'r+b');
        fwrite($out, "\xEF\xBB\xBF");

        fputcsv($out, ['field', 'value'], ',', '"', '');
        foreach (self::COURSE_FIELDS as $field) {
            fputcsv($out, [$field, (string) ($course[$field] ?? '')], ',', '"', '');
        }

        fwrite($out, "\n");
        $columns = ['number', ...self::holeFields()];
        fputcsv($out, $columns, ',', '"', '');

        usort($holes, static fn ($a, $b) => (int) $a['number'] <=> (int) $b['number']);
        foreach ($holes as $hole) {
            fputcsv($out, array_map(static fn ($c) => (string) ($hole[$c] ?? ''), $columns), ',', '"', '');
        }

        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);

        return $csv;
    }

    /**
     * Saves a course and its holes from CSV text in the export() layout.
     *
     * With a course id, the fields and holes in the file replace those
     * saved; anything not in the file is kept (a blank Active keeps the
     * current setting). Without one, a new course is added: the file must
     * give its name, and the name must not already be used.
     *
     * Nothing is saved if anything in the file is invalid.
     *
     * @return array{id: ?int, holes: int, errors: list<string>} the course id and how many holes the file had
     */
    public static function import(string $csv, ?int $courseId = null): array
    {
        $parsed  = self::parse($csv);
        $errors  = $parsed['errors'];
        $courses = new CourseModel();
        $course  = $parsed['course'];
        $result  = static fn (array $errors) => ['id' => $courseId, 'holes' => count($parsed['holes']), 'errors' => $errors];

        if ($courseId === null) {
            $name = trim((string) ($course['name'] ?? ''));
            if ($name === '') {
                return $result([...$errors, 'The file has no course name (a "name" line in the course details).']);
            }
            $names = array_map('strtolower', array_column($courses->select('name')->findAll(), 'name'));
            if (in_array(strtolower($name), $names, true)) {
                return $result([...$errors, "A course named \"{$name}\" already exists. To update it, use Import on that course's edit page."]);
            }
            $course['active'] ??= 1;
            if (! $courses->validate($course)) {
                $errors = [...$errors, ...array_values($courses->errors())];
            }
        } else {
            if (array_key_exists('active', $course) && $course['active'] === null) {
                unset($course['active']);
            }
            if ($course !== [] && ! $courses->validate(['id' => $courseId] + $course + ['name' => $courses->find($courseId)['name']])) {
                $errors = [...$errors, ...array_values($courses->errors())];
            }
        }

        // Holes: start from what is saved (nothing for a new course), then
        // apply the file's values. The course id is filled in once known.
        $existing = [];
        if ($courseId !== null) {
            foreach ((new HoleModel())->where('course_id', $courseId)->findAll() as $hole) {
                $existing[(int) $hole['number']] = $hole;
            }
        }
        $rows = [];
        for ($number = 1; $number <= 18; $number++) {
            if (! isset($parsed['holes'][$number]) && ! isset($existing[$number])) {
                continue;
            }
            $values        = array_merge($existing[$number] ?? [], $parsed['holes'][$number] ?? []);
            // A new course has no id until it is saved; 1 stands in while checking.
            $rows[$number] = CourseHoles::row($courseId ?? 1, $number, self::holeFields(), $values);
        }
        foreach (CourseHoles::errors($rows) as $number => $error) {
            $errors[] = "Hole {$number}: {$error}";
        }
        if ($errors !== []) {
            return $result($errors);
        }

        $db = db_connect();
        $db->transStart();
        if ($courseId === null) {
            $courseId = (int) $courses->insert($course);
            foreach ($rows as &$row) {
                $row['course_id'] = $courseId;
            }
            unset($row);
        } elseif ($course !== []) {
            $courses->update($courseId, ['id' => $courseId] + $course);
        }
        CourseHoles::save($courseId, $rows);
        $db->transComplete();

        return ['id' => $courseId, 'holes' => count($parsed['holes']), 'errors' => []];
    }

    /**
     * Reads CSV text written by export() (or edited in a spreadsheet).
     *
     * @return array{course: array<string, ?string>, holes: array<int, array<string, string>>, errors: list<string>}
     */
    public static function parse(string $csv): array
    {
        $csv    = preg_replace('/^\xEF\xBB\xBF/', '', $csv);
        $course = [];
        $holes  = [];
        $errors = [];
        $header = null;

        $in = fopen('php://temp', 'r+b');
        fwrite($in, $csv);
        rewind($in);

        $line = 0;
        while (($cells = fgetcsv($in, null, ',', '"', '')) !== false) {
            $line++;
            $cells = array_map(static fn ($c) => trim((string) $c), $cells);
            if ($cells === [''] || implode('', $cells) === '') {
                continue; // blank line
            }

            $first = strtolower($cells[0]);

            // The hole header row starts the hole section.
            if ($first === 'number') {
                $header = array_map('strtolower', $cells);
                $unknown = array_diff($header, ['number', ...self::holeFields()]);
                if ($unknown !== []) {
                    $errors[] = 'Unknown hole column(s): ' . implode(', ', $unknown) . '.';
                }

                continue;
            }

            if ($header === null) {
                // Course section: field name, value.
                if ($first === 'field') {
                    continue;
                }
                if (! in_array($first, self::COURSE_FIELDS, true)) {
                    $errors[] = "Line {$line}: unknown course field \"{$cells[0]}\".";

                    continue;
                }
                $course[$first] = ($cells[1] ?? '') === '' ? null : $cells[1];

                continue;
            }

            // Hole section.
            $values = array_combine($header, array_pad(array_slice($cells, 0, count($header)), count($header), ''));
            $number = $values['number'];
            if (! ctype_digit($number) || (int) $number < 1 || (int) $number > 18) {
                $errors[] = "Line {$line}: hole number must be 1 to 18.";

                continue;
            }
            if (isset($holes[(int) $number])) {
                $errors[] = "Line {$line}: hole {$number} appears more than once.";

                continue;
            }
            unset($values['number']);
            $holes[(int) $number] = $values;
        }
        fclose($in);

        if ($course === [] && $holes === []) {
            $errors[] = 'No course details or holes were found in the file.';
        }

        ksort($holes);

        return ['course' => $course, 'holes' => $holes, 'errors' => $errors];
    }
}
