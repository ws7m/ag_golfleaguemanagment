<?php

namespace App\Models;

use CodeIgniter\Model;

class LeagueModel extends Model
{
    protected $table         = 'leagues';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = ['name', 'description', 'image', 'start_date', 'end_date', 'website', 'email', 'active'];

    protected $validationRules = [
        'name'       => 'required|max_length[100]',
        'start_date' => 'permit_empty|valid_date[Y-m-d]',
        'end_date'   => 'permit_empty|valid_date[Y-m-d]',
        'email'      => 'permit_empty|valid_email',
        'active'     => 'permit_empty|in_list[0,1]',
    ];
}
