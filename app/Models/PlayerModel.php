<?php

namespace App\Models;

use CodeIgniter\Model;

class PlayerModel extends Model
{
    protected $table         = 'players';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'member_number', 'league_id', 'first_name', 'last_name', 'gender', 'email', 'phone',
        'text_msg_ok', 'tee', 'handicap', 'flight', 'desired_tee_time', 'desired_group_members',
        'is_admin', 'admin_password_hash', 'active',
    ];

    protected $validationRules = [
        'member_number'    => 'permit_empty|integer|greater_than_equal_to[10000]|less_than_equal_to[99999]|is_unique[players.member_number,id,{id}]',
        'league_id'        => 'permit_empty|is_natural_no_zero',
        'first_name'       => 'required|max_length[50]',
        'last_name'        => 'required|max_length[50]',
        'gender'           => 'permit_empty|in_list[M,F]',
        'email'            => 'permit_empty|valid_email',
        'text_msg_ok'      => 'permit_empty|in_list[0,1]',
        'tee'              => 'permit_empty|in_list[red,white,blue]',
        'handicap'         => 'permit_empty|integer',
        'flight'           => 'permit_empty|in_list[1,2,3]',
        'desired_tee_time' => ['permit_empty', 'regex_match[/^([01][0-9]|2[0-3]):[0-5][0-9]$/]'],
        'is_admin'         => 'permit_empty|in_list[0,1]',
        'active'           => 'permit_empty|in_list[0,1]',
    ];

    // Give every new player a member number if one wasn't supplied.
    protected $beforeInsert = ['assignMemberNumber'];

    // Never send the admin password hash out with player data by accident.
    protected $afterFind = ['hidePasswordHash'];

    /**
     * Returns a random 5-digit member number that no player has yet.
     */
    public function generateMemberNumber(): int
    {
        do {
            $number = random_int(10000, 99999);
        } while ($this->builder()->where('member_number', $number)->countAllResults() > 0);

        return $number;
    }

    /**
     * Finds an active admin by email and checks their password.
     * Returns the player (without the hash) or null.
     */
    public function verifyAdmin(string $email, string $password): ?array
    {
        $row = $this->builder()
            ->where('email', $email)
            ->where('is_admin', 1)
            ->where('active', 1)
            ->get()
            ->getRowArray();

        if ($row === null || $row['admin_password_hash'] === null
            || ! password_verify($password, $row['admin_password_hash'])) {
            return null;
        }

        unset($row['admin_password_hash']);

        return $row;
    }

    /**
     * Sets an admin's password, stored as a hash.
     */
    public function setAdminPassword(int $playerId, string $password): bool
    {
        return $this->builder()
            ->where('id', $playerId)
            ->update(['admin_password_hash' => password_hash($password, PASSWORD_DEFAULT)]);
    }

    protected function assignMemberNumber(array $data): array
    {
        if (empty($data['data']['member_number'])) {
            $data['data']['member_number'] = $this->generateMemberNumber();
        }

        return $data;
    }

    protected function hidePasswordHash(array $data): array
    {
        if ($data['data'] === null) {
            return $data;
        }

        if ($data['singleton']) {
            unset($data['data']['admin_password_hash']);
        } else {
            foreach ($data['data'] as &$row) {
                unset($row['admin_password_hash']);
            }
        }

        return $data;
    }
}
