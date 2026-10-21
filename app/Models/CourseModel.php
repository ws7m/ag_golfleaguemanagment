<?php

namespace App\Models;

use CodeIgniter\Model;

class CourseModel extends Model
{
    protected $table         = 'courses';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = ['name', 'description', 'address', 'city', 'state', 'zip', 'phone', 'email', 'website', 'image', 'rating', 'front_slope', 'back_slope', 'active'];

    protected $validationRules = [
        'name'        => 'required|max_length[100]',
        'email'       => 'permit_empty|valid_email',
        'website'     => 'permit_empty|valid_url_strict|max_length[255]',
        'rating'      => 'permit_empty|decimal',
        'front_slope' => 'permit_empty|integer|greater_than_equal_to[55]|less_than_equal_to[155]',
        'back_slope'  => 'permit_empty|integer|greater_than_equal_to[55]|less_than_equal_to[155]',
        'active'      => 'permit_empty|in_list[0,1]',
    ];
}
