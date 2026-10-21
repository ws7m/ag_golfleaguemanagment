<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\CourseHoles;
use App\Models\CourseModel;
use App\Models\HoleModel;
use CodeIgniter\Exceptions\PageNotFoundException;

/**
 * Edits all 18 holes of a course on one page.
 */
class Holes extends BaseController
{
    // Kept here for the holes view; the rules live in CourseHoles.
    public const TEES        = CourseHoles::TEES;
    public const STATS       = CourseHoles::STATS;
    public const HOLE_FIELDS = CourseHoles::HOLE_FIELDS;

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
        $posted = $this->request->getPost('holes') ?? [];
        $rows   = [];
        for ($number = 1; $number <= 18; $number++) {
            $rows[$number] = CourseHoles::row($courseId, $number, CourseHoles::keys(), $posted[$number] ?? []);
        }

        $errors = CourseHoles::errors($rows);
        if ($errors !== []) {
            return view('admin/courses/holes', [
                'title'  => 'Holes: ' . $course['name'],
                'course' => $course,
                'holes'  => $rows,
                'errors' => $errors,
            ]);
        }

        CourseHoles::save($courseId, $rows);

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
