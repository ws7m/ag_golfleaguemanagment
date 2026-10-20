<?php

namespace App\Models;

use CodeIgniter\Model;

class RoundModel extends Model
{
    protected $table         = 'rounds';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = ['league_id', 'course_id', 'game_id', 'round_date', 'name', 'description', 'image', 'status', 'entry_fee_cents', 'use_for_handicap', 'total_pot_cents'];

    protected $validationRules = [
        'league_id'        => 'required|is_natural_no_zero',
        'course_id'        => 'required|is_natural_no_zero',
        'game_id'          => 'permit_empty|is_natural_no_zero',
        'round_date'       => 'required|valid_date[Y-m-d]',
        'status'           => 'permit_empty|in_list[scheduled,active,closed,scored]',
        'entry_fee_cents'  => 'permit_empty|is_natural',
        'use_for_handicap' => 'permit_empty|in_list[0,1]',
    ];
}
