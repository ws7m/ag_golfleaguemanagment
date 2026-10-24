<?php

namespace App\Controllers\Admin;

use App\Libraries\CourseCsv;
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
    protected ?string $extraIndexView = 'admin/courses/import_new';
    protected array $headActions      = ['Import course' => '#import-course'];

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
            'website'     => ['label' => 'Website', 'type' => 'url', 'help' => 'Full address, e.g. https://www.southridgegolf.com'],
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
        [$csv, $name, $error] = $this->uploadedCsv();
        if ($error !== null) {
            return $back->with('error', $error);
        }

        $result = CourseCsv::import($csv, $id);
        if ($result['errors'] !== []) {
            return $back->with('error', 'Nothing was imported. ' . implode(' ', $result['errors']));
        }

        return $back->with('message', "Imported the course details and {$result['holes']} holes from {$name}.");
    }

    /**
     * Adds a new course, with its holes, from an uploaded CSV file (for
     * example one exported from another copy of this site). Nothing is saved
     * if anything in the file is invalid.
     */
    public function importNew()
    {
        $back = redirect()->to($this->baseRoute);
        [$csv, $name, $error] = $this->uploadedCsv();
        if ($error !== null) {
            return $back->with('error', $error);
        }

        $result = CourseCsv::import($csv);
        if ($result['errors'] !== []) {
            return $back->with('error', 'Nothing was imported. ' . implode(' ', $result['errors']));
        }
        $course = (new CourseModel())->find($result['id']);

        return redirect()->to("{$this->baseRoute}/{$result['id']}/edit")
            ->with('message', "Added {$course['name']} with {$result['holes']} holes from {$name}. Check the details below and the holes.");
    }

    /**
     * The uploaded CSV file's text and name, or an error if there is no
     * suitable file.
     *
     * @return array{0: string, 1: string, 2: ?string} [text, file name, error]
     */
    private function uploadedCsv(): array
    {
        $file = $this->request->getFile('csv');
        if ($file === null || ! $file->isValid()) {
            return ['', '', 'Choose a CSV file to import.'];
        }
        if (! in_array(strtolower($file->getClientExtension()), ['csv', 'txt'], true)) {
            return ['', '', 'The file must be a .csv file.'];
        }
        if ($file->getSize() > self::MAX_IMPORT_BYTES) {
            return ['', '', 'The file is too large (1 MB at most).'];
        }

        return [(string) file_get_contents($file->getTempName()), $file->getClientName(), null];
    }
}
