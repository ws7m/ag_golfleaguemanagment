<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\CourseModel;
use App\Models\HoleModel;
use CodeIgniter\Exceptions\PageNotFoundException;

/**
 * Edits all 18 holes of a course on one page.
 */
class Holes extends BaseController
{
    public const TEES  = ['blue', 'white', 'red'];
    // Entered for each tee.
    public const STATS = ['yardage', 'par'];
    // Entered once per hole, after the tees.
    public const HOLE_FIELDS = ['handicap' => 'Hcp', 'net' => 'Net', 'adj' => 'Adj'];

    public function edit(int $courseId): string
    {
        $course = $this->courseOr404($courseId);
        $holes  = [];
        foreach ((new HoleModel())->where('course_id', $courseId)->findAll() as $hole) {
            $holes[(int) $hole['number']] = $hole;
        }

        return view('admin/courses/holes', [
            'title'  => 'Holes: ' . $course['name'],
            'course' => $course,
            'holes'  => $holes,
            'errors' => [],
        ]);
    }

    public function update(int $courseId)
    {
        $course = $this->courseOr404($courseId);
        $model  = new HoleModel();
        $posted = $this->request->getPost('holes') ?? [];
        $errors = [];
        $rows   = [];

        for ($number = 1; $number <= 18; $number++) {
            $row = ['course_id' => $courseId, 'number' => $number, 'nine' => $number <= 9 ? 'front' : 'back'];
            $keys = array_keys(self::HOLE_FIELDS);
            foreach (self::TEES as $tee) {
                foreach (self::STATS as $stat) {
                    $keys[] = "{$tee}_{$stat}";
                }
            }
            foreach ($keys as $key) {
                $value     = trim((string) ($posted[$number][$key] ?? ''));
                $row[$key] = $value === '' ? null : $value;
            }
            if (! $model->validate($row)) {
                $errors[$number] = implode(' ', $model->errors());
            }
            $rows[$number] = $row;
        }

        // Each hole handicap (1-18) may be used on only one hole.
        $holesByHandicap = [];
        foreach ($rows as $number => $row) {
            if ($row['handicap'] !== null && ! isset($errors[$number])) {
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

        if ($errors !== []) {
            return view('admin/courses/holes', [
                'title'  => 'Holes: ' . $course['name'],
                'course' => $course,
                'holes'  => $rows,
                'errors' => $errors,
            ]);
        }

        $existing = array_column($model->where('course_id', $courseId)->findAll(), 'id', 'number');
        $db       = db_connect();
        $db->transStart();
        // Clear the handicaps first, so swapping two holes' handicaps doesn't
        // briefly put the same number on both and trip the unique index.
        $db->table('holes')->where('course_id', $courseId)->update(['handicap' => null]);
        foreach ($rows as $number => $row) {
            if (isset($existing[$number])) {
                $model->update($existing[$number], $row);
            } else {
                $model->insert($row);
            }
        }
        $db->transComplete();

        return redirect()->to("admin/courses/{$courseId}/holes")->with('message', 'Holes saved.');
    }

    private function courseOr404(int $id): array
    {
        $course = (new CourseModel())->find($id);
        if ($course === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $course;
    }
}
