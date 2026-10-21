<?php

namespace App\Libraries;

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
