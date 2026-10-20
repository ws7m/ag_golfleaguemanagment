<?php

namespace App\Models;

use CodeIgniter\Model;

class HoleModel extends Model
{
    protected $table         = 'holes';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = ['course_id', 'number', 'nine', 'blue_yardage', 'blue_par', 'blue_handicap', 'white_yardage', 'white_par', 'white_handicap', 'red_yardage', 'red_par', 'red_handicap', 'image'];

    protected $validationRules = [
        'course_id'      => 'required|is_natural_no_zero',
        'number'         => 'required|integer|greater_than_equal_to[1]|less_than_equal_to[18]',
        'nine'           => 'required|in_list[front,back]',
        'blue_par'       => 'permit_empty|integer|in_list[3,4,5,6]',
        'white_par'      => 'permit_empty|integer|in_list[3,4,5,6]',
        'red_par'        => 'permit_empty|integer|in_list[3,4,5,6]',
        'blue_handicap'  => 'permit_empty|integer|greater_than_equal_to[1]|less_than_equal_to[18]',
        'white_handicap' => 'permit_empty|integer|greater_than_equal_to[1]|less_than_equal_to[18]',
        'red_handicap'   => 'permit_empty|integer|greater_than_equal_to[1]|less_than_equal_to[18]',
    ];
}
