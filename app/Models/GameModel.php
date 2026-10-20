<?php

namespace App\Models;

use CodeIgniter\Model;

class GameModel extends Model
{
    protected $table         = 'games';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = ['league_id', 'name', 'description', 'scoring_info', 'active'];

    protected $validationRules = [
        'league_id' => 'required|is_natural_no_zero',
        'name'      => 'required|max_length[100]',
        'active'    => 'permit_empty|in_list[0,1]',
    ];
}
