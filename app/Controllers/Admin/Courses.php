<?php

namespace App\Controllers\Admin;

use App\Models\CourseModel;

class Courses extends CrudController
{
    protected string $modelClass = CourseModel::class;
    protected string $title      = 'Courses';
    protected string $singular   = 'Course';
    protected string $baseRoute  = 'admin/courses';
    protected array $listColumns = ['name', 'city', 'phone', 'active'];
    protected array $orderBy     = ['name' => 'ASC'];

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
        return ['Holes' => 'admin/courses/{id}/holes'];
    }
}
