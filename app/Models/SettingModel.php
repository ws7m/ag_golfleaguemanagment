<?php

namespace App\Models;

use CodeIgniter\Model;

class SettingModel extends Model
{
    protected $table         = 'settings';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = ['shared_login_id', 'shared_password_hash', 'admin_contact_name', 'admin_contact_email'];

    protected $validationRules = [
        'admin_contact_email' => 'permit_empty|valid_email',
    ];
}
