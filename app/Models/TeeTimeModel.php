<?php

namespace App\Models;

use CodeIgniter\Model;

class TeeTimeModel extends Model
{
    protected $table         = 'tee_times';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = ['league_id', 'course_id', 'nine', 'tee_time'];

    protected $validationRules = [
        'league_id' => 'required|is_natural_no_zero',
        'course_id' => 'required|is_natural_no_zero',
        'nine'      => 'required|in_list[front,back]',
        // Array form because the pattern contains a | character.
        'tee_time'  => ['required', 'regex_match[/^([01][0-9]|2[0-3]):[0-5][0-9]$/]'],
    ];
}
