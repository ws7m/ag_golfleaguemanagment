<?php

namespace App\Models;

use CodeIgniter\Model;
use RuntimeException;

class PlayerModel extends Model
{
    protected $table         = 'players';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'member_number', 'login', 'league_id', 'first_name', 'last_name', 'gender', 'email', 'phone',
        'text_msg_ok', 'tee', 'handicap', 'flight', 'desired_tee_time', 'desired_group_members',
        'is_admin', 'is_player', 'password_hash', 'last_login_at', 'winnings_cents', 'active',
    ];

    protected $validationRules = [
        // Needed for the {id} placeholder in the unique rules below.
        'id'               => 'permit_empty|is_natural_no_zero',
        'member_number'    => 'permit_empty|integer|greater_than_equal_to[100]|less_than_equal_to[999]|is_unique[players.member_number,id,{id}]',
        'login'            => 'permit_empty|alpha_numeric|max_length[20]|is_unique[players.login,id,{id}]',
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
        'is_player'        => 'permit_empty|in_list[0,1]',
        'winnings_cents'   => 'permit_empty|is_natural',
        'active'           => 'permit_empty|in_list[0,1]',
    ];

    protected $validationMessages = [
        'login' => [
            'is_unique'     => 'That login is already used by another player.',
            'alpha_numeric' => 'The login can only contain letters and numbers.',
        ],
    ];

    // Fill in a member number and login when they are left blank.
    protected $beforeInsert = ['fillBlanksOnInsert'];
    protected $beforeUpdate = ['fillBlanksOnUpdate'];

    // Never send the password hash out with player data by accident.
    protected $afterFind = ['hidePasswordHash'];

    // Member numbers are 3 digits so they are easy to remember.
    public const MEMBER_NUMBER_MIN = 100;
    public const MEMBER_NUMBER_MAX = 999;

    /**
     * The default login for a name: up to 5 letters of the last name plus the
     * first initial, lower case. "Mark Holbrook" => "holbrm".
     */
    public static function loginBase(string $firstName, string $lastName): string
    {
        $letters = static fn (string $s) => strtolower((string) preg_replace('/[^A-Za-z]/', '', $s));
        $login   = substr($letters($lastName), 0, 5) . substr($letters($firstName), 0, 1);

        return $login !== '' ? $login : 'player';
    }

    /**
     * A login for this name that no other player has: the default login, or
     * the default with 2, 3, ... added if it is taken.
     */
    public function generateLogin(string $firstName, string $lastName, ?int $exceptId = null): string
    {
        $base  = self::loginBase($firstName, $lastName);
        $login = $base;
        for ($n = 2; $this->loginTaken($login, $exceptId); $n++) {
            $login = $base . $n;
        }

        return $login;
    }

    /**
     * Returns a random 3-digit member number that no player has yet.
     */
    public function generateMemberNumber(): int
    {
        $used = array_map('intval', array_column(
            $this->builder()->select('member_number')->get()->getResultArray(),
            'member_number'
        ));
        $free = array_values(array_diff(range(self::MEMBER_NUMBER_MIN, self::MEMBER_NUMBER_MAX), $used));

        if ($free === []) {
            throw new RuntimeException('All 900 member numbers (100-999) are in use.');
        }

        return $free[random_int(0, count($free) - 1)];
    }

    /**
     * Finds an active player or admin by login (any capitals) and checks their
     * password. Returns the player (without the hash) or null.
     */
    public function verifyLogin(string $login, string $password): ?array
    {
        $row = $this->builder()
            ->where('login', strtolower(trim($login)))
            ->where('active', 1)
            ->get()
            ->getRowArray();

        if ($row === null || empty($row['password_hash'])
            || ! password_verify($password, $row['password_hash'])) {
            return null;
        }

        unset($row['password_hash']);

        return $row;
    }

    /**
     * Records that a player (or admin) has just logged in.
     */
    public function recordLogin(int $playerId): void
    {
        $this->builder()
            ->where('id', $playerId)
            ->update(['last_login_at' => date('Y-m-d H:i:s')]);
    }

    /**
     * Sets a player's password, stored as a hash.
     */
    public function setPassword(int $playerId, string $password): bool
    {
        return $this->builder()
            ->where('id', $playerId)
            ->update(['password_hash' => password_hash($password, PASSWORD_DEFAULT)]);
    }

    private function loginTaken(string $login, ?int $exceptId): bool
    {
        $query = $this->builder()->where('login', $login);
        if ($exceptId !== null) {
            $query->where('id !=', $exceptId);
        }

        return $query->countAllResults() > 0;
    }

    protected function fillBlanksOnInsert(array $data): array
    {
        $row = &$data['data'];
        if (empty($row['member_number'])) {
            $row['member_number'] = $this->generateMemberNumber();
        }
        $row['login'] = empty($row['login'])
            ? $this->generateLogin((string) ($row['first_name'] ?? ''), (string) ($row['last_name'] ?? ''))
            : strtolower(trim($row['login']));

        return $data;
    }

    protected function fillBlanksOnUpdate(array $data): array
    {
        $row = &$data['data'];
        if (array_key_exists('member_number', $row) && empty($row['member_number'])) {
            $row['member_number'] = $this->generateMemberNumber();
        }

        if (array_key_exists('login', $row)) {
            $ids = (array) $data['id'];
            if (! empty($row['login'])) {
                $row['login'] = strtolower(trim($row['login']));
            } elseif (count($ids) === 1) {
                // Cleared on the edit form: set it again from the name.
                $id      = (int) reset($ids);
                $current = $this->builder()->where('id', $id)->get()->getRowArray() ?? [];
                $first   = (string) ($row['first_name'] ?? $current['first_name'] ?? '');
                $last    = (string) ($row['last_name'] ?? $current['last_name'] ?? '');
                $row['login'] = $this->generateLogin($first, $last, $id);
            }
        }

        return $data;
    }

    protected function hidePasswordHash(array $data): array
    {
        if ($data['data'] === null) {
            return $data;
        }

        if ($data['singleton']) {
            unset($data['data']['password_hash']);
        } else {
            foreach ($data['data'] as &$row) {
                unset($row['password_hash']);
            }
        }

        return $data;
    }
}
