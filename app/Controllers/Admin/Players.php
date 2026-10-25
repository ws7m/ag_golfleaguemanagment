<?php

namespace App\Controllers\Admin;

use App\Models\LeagueGroupModel;
use App\Models\PlayerModel;

class Players extends CrudController
{
    protected string $modelClass = PlayerModel::class;
    protected string $title      = 'Players';
    protected string $singular   = 'Player';
    protected string $baseRoute  = 'admin/players';
    protected array $listColumns = ['login', 'last_name', 'first_name', 'phone', 'email', 'group', 'flight', 'handicap', 'tee', 'winnings', 'is_admin', 'last_login_at', 'active'];
    protected array $filters     = ['league_id'];
    // The login links to the edit page instead of a separate Edit column.
    protected ?string $editLinkColumn = 'login';
    // Admin-only accounts (not league players) are listed on the Settings page.
    protected array $listWhere   = ['is_player' => 1];
    // By last name, then first name, ignoring capitals. Other sorts are
    // applied in listRows().
    protected array $orderBy     = ['LOWER(last_name)' => 'ASC', 'LOWER(first_name)' => 'ASC'];
    protected array $sortOptions = [
        'name'     => 'Last name',
        'group'    => 'Group',
        'winnings' => 'Winnings (most first)',
    ];

    protected ?string $extraIndexView = 'admin/players/tools';
    // "Add test players" is TEMPORARY: remove it with addTestPlayers(), its
    // route and the add_test view.
    protected array $headActions = ['Add test players' => '#add-test-players', 'Delete all players' => '#delete-players'];

    protected function listFields(): array
    {
        return [
            'group'    => ['label' => 'Group'],
            'winnings' => ['label' => 'Winnings', 'type' => 'money'],
        ];
    }

    /**
     * Adds the group each player is on the roster of (or "None") and their
     * total winnings, then applies the chosen sort.
     *
     * Only active groups count. With the list showing a single league, only
     * that league's groups and round winnings are used; with all leagues,
     * group names include the league and round winnings are totalled across
     * leagues. Winnings entered on the player are always added.
     */
    protected function listRows(array $rows, array $filterValues): array
    {
        $groups = db_connect()->table('league_groups g')
            ->select('g.*, l.name AS league')
            ->join('leagues l', 'l.id = g.league_id')
            ->where('g.active', 1)
            ->orderBy('g.group_number');
        if (isset($filterValues['league_id'])) {
            $groups->where('g.league_id', $filterValues['league_id']);
        }

        $byPlayer    = [];
        $firstGroup  = [];
        foreach ($groups->get()->getResultArray() as $group) {
            // Just the number when one league is shown, to keep the list narrow.
            $label = isset($filterValues['league_id'])
                ? (string) $group['group_number']
                : "{$group['group_number']} ({$group['league']})";
            foreach (LeagueGroupModel::playerIds($group) as $playerId) {
                $byPlayer[$playerId][] = $label;
                $firstGroup[$playerId] ??= (int) $group['group_number'];
            }
        }

        $winnings = $this->roundWinnings($filterValues['league_id'] ?? null);

        foreach ($rows as &$row) {
            $id                = (int) $row['id'];
            $row['group']      = isset($byPlayer[$id]) ? implode(', ', $byPlayer[$id]) : 'None';
            // Scored rounds plus winnings the admin entered on the player.
            $row['winnings']   = ($winnings[$id] ?? 0) + (int) ($row['winnings_cents'] ?? 0);
            $row['_group_num'] = $firstGroup[$id] ?? PHP_INT_MAX; // No group sorts last
            // Date only (e.g. "Oct 19") to keep the list narrow; the edit form shows the time.
            if (! empty($row['last_login_at'])) {
                $row['last_login_at'] = date('M j', strtotime($row['last_login_at']));
            }
        }
        unset($row);

        // Rows arrive sorted by name, so a stable sort keeps names in order
        // within each group or winnings amount.
        $sort = $this->sortChoice();
        if ($sort === 'group') {
            usort($rows, static fn ($a, $b) => $a['_group_num'] <=> $b['_group_num']);
        } elseif ($sort === 'winnings') {
            usort($rows, static fn ($a, $b) => $b['winnings'] <=> $a['winnings']);
        }

        return $rows;
    }

    protected function fields(): array
    {
        return [
            'first_name'            => ['label' => 'First name', 'listLabel' => 'First', 'required' => true],
            'last_name'             => ['label' => 'Last name', 'listLabel' => 'Last', 'required' => true],
            'login'                 => ['label' => 'Login', 'help' => 'Leave blank to set it from the last name and first initial, e.g. Mark Holbrook → holbrm.'],
            'password'              => ['label' => 'Password', 'type' => 'password', 'generate' => true, 'help' => 'At least 8 characters. Leave blank to keep the current password. A new player left blank starts with their last name in lower case, e.g. holbrook.'],
            'member_number'         => ['label' => 'Member number', 'type' => 'number', 'help' => 'Leave blank to assign a random 3-digit number (100–999).'],
            'league_id'             => ['label' => 'League', 'type' => 'select', 'options' => 'leagues', 'default' => 'currentLeague', 'help' => 'The league the player is registered in.'],
            'gender'                => ['label' => 'Male or female', 'type' => 'select', 'options' => ['M' => 'Male', 'F' => 'Female']],
            'email'                 => ['label' => 'Email', 'type' => 'email'],
            'phone'                 => ['label' => 'Phone'],
            'text_msg_ok'           => ['label' => 'Text messages OK', 'type' => 'checkbox'],
            'tee'                   => ['label' => 'Tee', 'type' => 'select', 'options' => ['blue' => 'Blue', 'white' => 'White', 'red' => 'Red']],
            'handicap'              => ['label' => 'Handicap', 'listLabel' => 'Hcp', 'type' => 'number', 'compact' => true],
            'flight'                => ['label' => 'Flight', 'type' => 'select', 'options' => [1 => '1', 2 => '2', 3 => '3'], 'compact' => true],
            'desired_tee_time'      => ['label' => 'Desired tee time', 'type' => 'time', 'compact' => true],
            'desired_group_members' => ['label' => 'Desired group members', 'type' => 'textarea'],
            'winnings_cents'        => ['label' => 'Winnings', 'type' => 'money', 'compact' => 'winnings', 'help' => 'Entered by you. Added to winnings from scored rounds.'],
            'round_winnings'        => ['label' => 'From scored rounds', 'type' => 'money', 'readonly' => true, 'compact' => 'winnings'],
            'is_admin'              => ['label' => 'Admin', 'type' => 'checkbox'],
            'is_player'             => ['label' => 'Plays in the league', 'type' => 'checkbox', 'default' => 1, 'help' => 'Untick for admin-only accounts. They are hidden from player lists and shown under Settings.'],
            'last_login_at'         => ['label' => 'Last login', 'listLabel' => 'Login on', 'readonly' => true],
            'active'                => ['label' => 'Active', 'type' => 'checkbox', 'default' => 1],
        ];
    }

    protected function formRecord(array $record, ?int $id): array
    {
        $record['round_winnings'] = $id === null ? 0 : $this->roundWinnings()[$id] ?? 0;

        return $record;
    }

    /**
     * Total winnings from scored rounds per player id, optionally for one league.
     *
     * @return array<int, int>
     */
    private function roundWinnings(?string $leagueId = null): array
    {
        $query = db_connect()->table('scores')
            ->select('player_id, SUM(winnings_cents) AS total')
            ->groupBy('player_id');
        if ($leagueId !== null) {
            $query->where('league_id', $leagueId);
        }

        return array_map('intval', array_column($query->get()->getResultArray(), 'total', 'player_id'));
    }

    /**
     * Deletes the players registered in a league, to start over (e.g. after
     * adding test players). Removes them from group rosters first. Admins,
     * the logged-in user, and players who already have scores are kept.
     */
    public function deleteAll()
    {
        $leagueId = (int) $this->request->getPost('league_id');
        $back     = redirect()->to($this->baseRoute . '?league_id=' . $leagueId);
        if ($leagueId <= 0) {
            return $back->with('error', 'Choose a league.');
        }

        $db        = db_connect();
        $players   = (new PlayerModel())->where('league_id', $leagueId)->where('is_player', 1)->findAll();
        $hasScores = array_map('intval', array_column(
            $db->table('scores')->select('player_id')->distinct()->get()->getResultArray(),
            'player_id'
        ));

        $delete = [];
        $kept   = ['admins' => 0, 'scores' => 0];
        foreach ($players as $player) {
            $id = (int) $player['id'];
            if ($player['is_admin'] || $id === (int) session('admin_id')) {
                $kept['admins']++;
            } elseif (in_array($id, $hasScores, true)) {
                $kept['scores']++;
            } else {
                $delete[] = $id;
            }
        }

        if ($delete !== []) {
            $db->transStart();
            for ($i = 1; $i <= 6; $i++) {
                $db->table('league_groups')->whereIn("player{$i}_id", $delete)->update(["player{$i}_id" => null]);
            }
            (new PlayerModel())->whereIn('id', $delete)->delete();
            $db->transComplete();
        }

        $message = count($delete) . ' players deleted.';
        if ($kept['admins'] > 0) {
            $message .= " {$kept['admins']} admins kept.";
        }
        if ($kept['scores'] > 0) {
            $message .= " {$kept['scores']} kept because they have scores.";
        }

        return $back->with('message', $message);
    }

    /**
     * TEMPORARY test data helper, to be removed later.
     *
     * Adds a number of players with random common first and last names, men
     * and women. Men play from the white tees and women from the red tees,
     * all in flight 2 with a handicap of 8. Each is registered in the chosen
     * league. The login is the first initial and last name (Jim Jones is
     * jjones; jjones2 if that's taken) and the password is the login plus
     * 12345 (jjones12345), which test players don't have to change.
     */
    public function addTestPlayers()
    {
        $leagueId = (int) $this->request->getPost('league_id');
        $count    = (int) $this->request->getPost('count');
        $back     = redirect()->to($this->baseRoute . '?league_id=' . $leagueId);
        if ($leagueId <= 0 || $count < 1 || $count > 200) {
            return $back->with('error', 'Choose a league and a number of players from 1 to 200.');
        }

        $players = new PlayerModel();
        $taken   = [];
        $logins  = [];
        foreach ($players->findAll() as $player) {
            $taken[strtolower("{$player['first_name']} {$player['last_name']}")] = true;
            $logins[(string) $player['login']]                                    = true;
        }

        $db    = db_connect();
        $added = [];
        $db->transStart();
        for ($n = 1; $n <= $count; $n++) {
            [$first, $last, $gender] = self::randomTestName($taken);
            $base  = strtolower($first[0] . $last);
            $login = $base;
            for ($i = 2; isset($logins[$login]); $i++) {
                $login = $base . $i;
            }
            $logins[$login] = true;
            $added[]        = $login;
            // Hashed once at low strength: a normal-strength hash takes ~0.2s,
            // too slow for 200 players in one request, and these are
            // throwaway test accounts. password_verify() works the same.
            $players->insert([
                'first_name'    => $first,
                'last_name'     => $last,
                'gender'        => $gender,
                'login'         => $login,
                'password_hash' => password_hash($login . '12345', PASSWORD_BCRYPT, ['cost' => 4]),
                'league_id'     => $leagueId,
                'tee'           => $gender === 'F' ? 'red' : 'white',
                'flight'        => 2,
                'handicap'      => 8,
                'is_player'     => 1,
                'active'        => 1,
                // Test accounts keep their starting password.
                'must_change_password' => 0,
            ]);
        }
        $db->transComplete();

        $examples = implode(', ', array_slice($added, 0, 3)) . ($count > 3 ? ', …' : '');

        return $back->with('message', "{$count} test players added ({$examples}). Each logs in with their login"
            . ' (first initial and last name) and the password login + 12345, e.g. ' . $added[0] . ' / ' . $added[0] . '12345.');
    }

    private const TEST_MEN = [
        'James', 'John', 'Robert', 'Michael', 'William', 'David', 'Richard', 'Joseph', 'Thomas', 'Charles',
        'Daniel', 'Matthew', 'Anthony', 'Mark', 'Donald', 'Steven', 'Paul', 'Andrew', 'Joshua', 'Kenneth',
        'Kevin', 'Brian', 'George', 'Timothy', 'Ronald', 'Jason', 'Edward', 'Jeffrey', 'Ryan', 'Jacob',
        'Gary', 'Eric', 'Stephen', 'Larry', 'Justin', 'Scott', 'Brandon', 'Frank', 'Gregory', 'Raymond',
    ];

    private const TEST_WOMEN = [
        'Mary', 'Patricia', 'Jennifer', 'Linda', 'Elizabeth', 'Barbara', 'Susan', 'Jessica', 'Sarah', 'Karen',
        'Lisa', 'Nancy', 'Betty', 'Sandra', 'Margaret', 'Ashley', 'Kimberly', 'Emily', 'Donna', 'Michelle',
        'Carol', 'Amanda', 'Melissa', 'Deborah', 'Stephanie', 'Rebecca', 'Sharon', 'Laura', 'Cynthia', 'Amy',
        'Kathleen', 'Angela', 'Shirley', 'Brenda', 'Emma', 'Anna', 'Pamela', 'Nicole', 'Samantha', 'Katherine',
    ];

    private const TEST_LAST_NAMES = [
        'Smith', 'Johnson', 'Williams', 'Brown', 'Jones', 'Garcia', 'Miller', 'Davis', 'Rodriguez', 'Martinez',
        'Hernandez', 'Lopez', 'Gonzalez', 'Wilson', 'Anderson', 'Thomas', 'Taylor', 'Moore', 'Jackson', 'Martin',
        'Lee', 'Perez', 'Thompson', 'White', 'Harris', 'Sanchez', 'Clark', 'Ramirez', 'Lewis', 'Robinson',
        'Walker', 'Young', 'Allen', 'King', 'Wright', 'Scott', 'Torres', 'Nguyen', 'Hill', 'Flores',
        'Green', 'Adams', 'Nelson', 'Baker', 'Hall', 'Rivera', 'Campbell', 'Mitchell', 'Carter', 'Roberts',
        'Turner', 'Phillips', 'Evans', 'Parker', 'Edwards', 'Collins', 'Stewart', 'Morris', 'Murphy', 'Cook',
        'Rogers', 'Morgan', 'Peterson', 'Cooper', 'Reed', 'Bailey', 'Bell', 'Kelly', 'Howard', 'Ward',
        'Cox', 'Richardson', 'Wood', 'Watson', 'Brooks', 'Bennett', 'Gray', 'James', 'Hughes', 'Price',
    ];

    /**
     * A random man's or woman's name (about half each) that isn't already
     * used, for a test player. Marks it as used.
     *
     * @param array<string, true> $taken "first last" in lower case
     *
     * @return array{string, string, string} first name, last name, gender (M/F)
     */
    private static function randomTestName(array &$taken): array
    {
        // 80 x 80 = 6,400 names, so a free one turns up quickly.
        do {
            $gender = random_int(0, 1) === 1 ? 'F' : 'M';
            $firsts = $gender === 'F' ? self::TEST_WOMEN : self::TEST_MEN;
            $first  = $firsts[random_int(0, count($firsts) - 1)];
            $last   = self::TEST_LAST_NAMES[random_int(0, count(self::TEST_LAST_NAMES) - 1)];
            $key    = strtolower("{$first} {$last}");
        } while (isset($taken[$key]));
        $taken[$key] = true;

        return [$first, $last, $gender];
    }

    /**
     * Logins are stored in lower case so they work with any capitals.
     */
    protected function collect(): array
    {
        $data = parent::collect();
        if ($data['login'] !== null) {
            $data['login'] = strtolower($data['login']);
        }

        return $data;
    }

    protected function checkBeforeSave(array $data, ?int $id): ?string
    {
        $password = (string) ($data['password'] ?? '');
        if ($password !== '' && strlen($password) < 8) {
            return 'The password must be at least 8 characters.';
        }

        // Stop admins from locking themselves out.
        if ($id !== null && $id === (int) session('admin_id')
            && (empty($data['is_admin']) || empty($data['active']))) {
            return 'You cannot remove your own admin access or make yourself inactive.';
        }

        return null;
    }

    protected function afterSave(int $id, array $data): void
    {
        if (! empty($data['password'])) {
            // A password set for someone else is temporary: they choose
            // their own at their next login. Your own is kept as it is.
            (new PlayerModel())->setPassword($id, $data['password'], $id !== (int) session('admin_id'));
        }
    }
}
