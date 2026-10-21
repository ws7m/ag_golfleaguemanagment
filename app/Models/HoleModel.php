<?php

namespace App\Models;

use CodeIgniter\Model;

class HoleModel extends Model
{
    protected $table         = 'holes';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'course_id', 'number', 'nine',
        'blue_yardage', 'blue_par', 'white_yardage', 'white_par', 'red_yardage', 'red_par',
        'handicap', 'net', 'adj', 'image',
    ];

    // Yardage and par are per tee; the hole handicap (1-18, each used once per
    // course), Net and Adj are one value per hole.
    protected $validationRules = [
        'course_id' => 'required|is_natural_no_zero',
        'number'    => 'required|integer|greater_than_equal_to[1]|less_than_equal_to[18]',
        'nine'      => 'required|in_list[front,back]',
        'blue_par'  => 'permit_empty|integer|in_list[3,4,5,6]',
        'white_par' => 'permit_empty|integer|in_list[3,4,5,6]',
        'red_par'   => 'permit_empty|integer|in_list[3,4,5,6]',
        'handicap'  => 'permit_empty|integer|greater_than_equal_to[1]|less_than_equal_to[18]',
        'net'       => 'permit_empty|integer',
        'adj'       => 'permit_empty|integer',
    ];

    protected $validationMessages = [
        'handicap' => [
            'integer'               => 'Hcp must be a whole number from 1 to 18.',
            'greater_than_equal_to' => 'Hcp must be from 1 to 18.',
            'less_than_equal_to'    => 'Hcp must be from 1 to 18.',
        ],
    ];
}
