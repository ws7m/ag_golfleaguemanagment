<?php

namespace App\Models;

use CodeIgniter\Model;

class SettingModel extends Model
{
    protected $table         = 'settings';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'admin_contact_name', 'admin_contact_email',
        'smtp_host', 'smtp_port', 'smtp_crypto', 'smtp_user', 'smtp_pass_encrypted', 'mail_from_email', 'mail_from_name',
    ];

    protected $validationRules = [
        'admin_contact_email' => 'permit_empty|valid_email',
        'smtp_host'           => 'permit_empty|max_length[255]',
        'smtp_port'           => 'permit_empty|is_natural_no_zero|less_than_equal_to[65535]',
        'smtp_crypto'         => 'permit_empty|in_list[tls,ssl,none]',
        'smtp_user'           => 'permit_empty|max_length[255]',
        'mail_from_email'     => 'permit_empty|valid_email',
        'mail_from_name'      => 'permit_empty|max_length[100]',
    ];

    protected $validationMessages = [
        'smtp_port'       => ['is_natural_no_zero' => 'The email server port must be a number, e.g. 587.', 'less_than_equal_to' => 'The email server port must be a number, e.g. 587.'],
        'mail_from_email' => ['valid_email' => 'The "From" email address is not valid.'],
    ];
}
