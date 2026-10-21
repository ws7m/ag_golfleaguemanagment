<?php

namespace App\Controllers\Admin;

use App\Libraries\CourseCsv;
use App\Libraries\CourseHoles;
use App\Models\CourseModel;
use App\Models\HoleModel;

class Courses extends CrudController
{
    protected string $modelClass = CourseModel::class;
    protected string $title      = 'Courses';
    protected string $singular   = 'Course';
    protected string $baseRoute  = 'admin/courses';
    protected array $listColumns = ['name', 'city', 'phone', 'active'];
    protected array $orderBy     = ['name' => 'ASC'];
    protected ?string $extraFormView = 'admin/courses/import';

    // Largest CSV file accepted for import.
    private const MAX_IMPORT_BYTES = 1024 * 1024;

    protected function fields(): array
    {
        return [
            'name'        => ['label' => 'Name', 'required' => true],
            'description' => ['label' => 'Description', 'type' => 'textarea'],
            'address'     => ['label' => 'Address'],
            'city'        => ['label' => 'City'],
            'state'       => ['label' => 'State'],
            'zip'         => ['label' => 'Zip'],
            'phone'       => ['label' => 'Phone'],
            'email'       => ['label' => 'Email', 'type' => 'email'],
            'image'       => ['label' => 'Image', 'help' => 'Picture uploads come later; for now a file name or link.'],
            'rating'      => ['label' => 'Rating', 'type' => 'decimal', 'compact' => true],
            'front_slope' => ['label' => 'Front slope', 'type' => 'number', 'compact' => true],
            'back_slope'  => ['label' => 'Back slope', 'type' => 'number', 'compact' => true],
            'active'      => ['label' => 'Active', 'type' => 'checkbox', 'default' => 1],
        ];
    }

    protected function rowActions(): array
    {
        return ['Holes' => 'admin/courses/{id}/holes', 'Export CSV' => 'admin/courses/{id}/export'];
    }

    /**
     * Downloads the course details and its holes as a CSV file.
     */
    public function export(int $id)
    {
        $course = $this->findOr404($id);
        $holes  = (new HoleModel())->where('course_id', $id)->findAll();
        $name   = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($course['name'])), '-') ?: 'course';

        return $this->response
            ->setHeader('Content-Type', 'text/csv; charset=utf-8')
            ->setHeader('Content-Disposition', "attachment; filename=\"course-{$name}.csv\"")
            ->setBody(CourseCsv::export($course, $holes));
    }

    /**
     * Replaces the course details and holes with those in an uploaded CSV
     * file (in the format export() writes). Only fields and holes in the file
     * are changed. Nothing is saved if anything in the file is invalid.
     */
    public function import(int $id)
    {
        $this->findOr404($id);
        $back = redirect()->to("{$this->baseRoute}/{$id}/edit");
        $file = $this->request->getFile('csv');

        if ($file === null || ! $file->isValid()) {
            return $back->with('error', 'Choose a CSV file to import.');
        }
        if (! in_array(strtolower($file->getClientExtension()), ['csv', 'txt'], true)) {
            return $back->with('error', 'The file must be a .csv file.');
        }
        if ($file->getSize() > self::MAX_IMPORT_BYTES) {
            return $back->with('error', 'The file is too large (1 MB at most).');
        }

        $parsed = CourseCsv::parse((string) file_get_contents($file->getTempName()));
        $errors = $parsed['errors'];

        // Course details. A blank Active keeps the current setting.
        $course = $parsed['course'];
        if (array_key_exists('active', $course) && $course['active'] === null) {
            unset($course['active']);
        }
        $courses = new CourseModel();
        if ($course !== [] && ! $courses->validate(['id' => $id] + $course + ['name' => $courses->find($id)['name']])) {
            $errors = [...$errors, ...array_values($courses->errors())];
        }

        // Holes: start from what is saved, then apply the file's values.
        $existing = [];
        foreach ((new HoleModel())->where('course_id', $id)->findAll() as $hole) {
            $existing[(int) $hole['number']] = $hole;
        }
        $rows = [];
        for ($number = 1; $number <= 18; $number++) {
            if (! isset($parsed['holes'][$number]) && ! isset($existing[$number])) {
                continue;
            }
            $values        = array_merge($existing[$number] ?? [], $parsed['holes'][$number] ?? []);
            $rows[$number] = CourseHoles::row($id, $number, CourseCsv::holeFields(), $values);
        }
        foreach (CourseHoles::errors($rows) as $number => $error) {
            $errors[] = "Hole {$number}: {$error}";
        }

        if ($errors !== []) {
            return $back->with('error', 'Nothing was imported. ' . implode(' ', $errors));
        }

        $db = db_connect();
        $db->transStart();
        if ($course !== []) {
            $courses->update($id, ['id' => $id] + $course);
        }
        CourseHoles::save($id, $rows);
        $db->transComplete();

        $holeCount = count($parsed['holes']);

        return $back->with('message', "Imported the course details and {$holeCount} holes from {$file->getClientName()}.");
    }
}
