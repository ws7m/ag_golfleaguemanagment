<?php

use App\Models\CourseModel;
use App\Models\HoleModel;
use App\Models\LeagueModel;
use App\Models\PlayerModel;
use App\Models\RoundModel;
use App\Models\TeeTimeModel;
use CodeIgniter\Config\Factories;
use CodeIgniter\Security\Exceptions\SecurityException;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * @internal
 */
final class AdminScreensTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;
    protected $namespace   = 'App';

    private int $adminId;

    protected function setUp(): void
    {
        parent::setUp();

        // Each test starts with a clean login throttle.
        cache()->clean();

        // Posts in these tests carry no CSRF token, so switch the check off
        // here (testPostWithoutCsrfTokenIsRefused checks it is on in the app).
        $filters = config(\Config\Filters::class);
        $filters->globals['before'] = array_values(array_diff($filters->globals['before'], ['csrf']));

        $players       = new PlayerModel();
        $this->adminId = (int) $players->insert([
            'first_name' => 'Pat', 'last_name' => 'Admin', 'email' => 'admin@example.com', 'is_admin' => 1,
        ]);
        $players->setAdminPassword($this->adminId, 'correct-horse');
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Factories::reset('config');
    }

    public function testPostWithoutCsrfTokenIsRefused(): void
    {
        Factories::reset('config');

        $this->expectException(SecurityException::class);
        $this->asAdmin()->post('admin/leagues', ['name' => 'No token']);
    }

    private function asAdmin(): self
    {
        return $this->withSession(['admin_id' => $this->adminId, 'admin_name' => 'Pat Admin']);
    }

    public function testAdminPagesRequireLogin(): void
    {
        $this->get('admin/leagues')->assertRedirectTo('/admin/login');
    }

    public function testLoggedInAdminWhoNoLongerExistsIsLoggedOut(): void
    {
        $this->withSession(['admin_id' => 9999, 'admin_name' => 'Gone'])
            ->get('admin')
            ->assertRedirectTo('/admin/login');
    }

    public function testAdminLoginWithCorrectAndWrongPassword(): void
    {
        $this->post('admin/login', ['email' => 'admin@example.com', 'password' => 'wrong-pass'])
            ->assertSessionMissing('admin_id');

        $result = $this->post('admin/login', ['email' => 'admin@example.com', 'password' => 'correct-horse']);
        $result->assertRedirectTo('/admin');
        $result->assertSessionHas('admin_id', $this->adminId);

        $admin = (new PlayerModel())->find($this->adminId);
        $this->assertNotNull($admin['last_login_at'], 'Admin login should record the login date.');
    }

    public function testEveryAdminScreenLoads(): void
    {
        foreach (['admin', 'admin/leagues', 'admin/leagues/new', 'admin/courses', 'admin/courses/new',
            'admin/tee-times', 'admin/tee-times/new', 'admin/players', 'admin/players/new',
            'admin/groups', 'admin/groups/new', 'admin/games', 'admin/games/new',
            'admin/rounds', 'admin/rounds/new', 'admin/scores', 'admin/scores/new', 'admin/settings'] as $url) {
            $this->asAdmin()->get($url)->assertOK();
        }
    }

    public function testAddAndEditLeague(): void
    {
        $this->asAdmin()->post('admin/leagues', ['name' => '2027 League', 'start_date' => '2027-05-01', 'active' => '1'])
            ->assertRedirectTo('/admin/leagues');
        $league = (new LeagueModel())->where('name', '2027 League')->first();
        $this->assertNotNull($league);

        $this->asAdmin()->post("admin/leagues/{$league['id']}", ['name' => '2027 Summer League', 'active' => '0'])
            ->assertRedirectTo('/admin/leagues');
        $this->seeInDatabase('leagues', ['id' => $league['id'], 'name' => '2027 Summer League', 'active' => 0]);
    }

    public function testInvalidLeagueShowsErrorsAndSavesNothing(): void
    {
        $result = $this->asAdmin()->post('admin/leagues', ['name' => '', 'email' => 'not-an-email']);
        $result->assertOK();
        $result->assertSee('flash-error');
        $this->dontSeeInDatabase('leagues', ['email' => 'not-an-email']);
    }

    public function testCourseInUseIsNotDeleted(): void
    {
        $courseId = (new CourseModel())->insert(['name' => 'Home Course']);
        (new HoleModel())->insert(['course_id' => $courseId, 'number' => 1, 'nine' => 'front']);

        $result = $this->asAdmin()->post("admin/courses/{$courseId}/delete");
        $result->assertRedirectTo('/admin/courses');
        $result->assertSessionHas('error');
        $this->seeInDatabase('courses', ['id' => $courseId]);
    }

    public function testUnusedCourseIsDeleted(): void
    {
        $courseId = (new CourseModel())->insert(['name' => 'Old Course']);

        $this->asAdmin()->post("admin/courses/{$courseId}/delete")->assertSessionHas('message');
        $this->dontSeeInDatabase('courses', ['id' => $courseId]);
    }

    public function testSaveAllEighteenHoles(): void
    {
        $courseId = (new CourseModel())->insert(['name' => 'Home Course']);
        $holes    = [];
        for ($n = 1; $n <= 18; $n++) {
            $holes[$n] = ['white_yardage' => 300 + $n, 'white_par' => 4, 'white_handicap' => $n];
        }

        $this->asAdmin()->post("admin/courses/{$courseId}/holes", ['holes' => $holes])
            ->assertRedirectTo("/admin/courses/{$courseId}/holes");
        $this->assertSame(18, (new HoleModel())->where('course_id', $courseId)->countAllResults());
        $this->seeInDatabase('holes', ['course_id' => $courseId, 'number' => 12, 'nine' => 'back', 'white_yardage' => 312]);
    }

    public function testShiftTeeTimes(): void
    {
        $leagueId = (new LeagueModel())->insert(['name' => 'League']);
        $courseId = (new CourseModel())->insert(['name' => 'Course']);
        $teeTimes = new TeeTimeModel();
        $first    = $teeTimes->insert(['league_id' => $leagueId, 'course_id' => $courseId, 'nine' => 'front', 'tee_time' => '15:10']);
        $teeTimes->insert(['league_id' => $leagueId, 'course_id' => $courseId, 'nine' => 'back', 'tee_time' => '15:12']);

        $this->asAdmin()->post('admin/tee-times/shift', ['league_id' => $leagueId, 'minutes' => '-20'])
            ->assertSessionHas('message');
        $this->seeInDatabase('tee_times', ['id' => $first, 'tee_time' => '14:50']);

        $this->asAdmin()->post('admin/tee-times/shift', ['league_id' => $leagueId, 'minutes' => '600'])
            ->assertSessionHas('error');
        $this->seeInDatabase('tee_times', ['id' => $first, 'tee_time' => '14:50']);
    }

    public function testEditAndSavePlayer(): void
    {
        $players  = new PlayerModel();
        $playerId = (int) $players->insert(['first_name' => 'Jo', 'last_name' => 'Golfer']);
        $number   = (int) $players->find($playerId)['member_number'];

        $result = $this->asAdmin()->post("admin/players/{$playerId}", [
            'first_name' => 'Joanne', 'last_name' => 'Golfer', 'member_number' => (string) $number,
            'flight' => '2', 'handicap' => '8', 'active' => '1',
        ]);
        $result->assertRedirectTo('/admin/players');
        $this->seeInDatabase('players', ['id' => $playerId, 'first_name' => 'Joanne', 'member_number' => $number, 'flight' => 2]);

        // Another player's member number is refused.
        $otherId = (int) $players->insert(['first_name' => 'Al', 'last_name' => 'Other']);
        $result  = $this->asAdmin()->post("admin/players/{$otherId}", [
            'first_name' => 'Al', 'last_name' => 'Other', 'member_number' => (string) $number, 'active' => '1',
        ]);
        $result->assertOK();
        $result->assertSee('flash-error');
    }

    public function testPlayerListIsByLastNameAndShowsGroup(): void
    {
        $leagueId = (new LeagueModel())->insert(['name' => 'League']);
        $players  = new PlayerModel();
        $zed      = (int) $players->insert(['first_name' => 'Zoe', 'last_name' => 'Zed', 'league_id' => $leagueId]);
        $players->insert(['first_name' => 'Dan', 'last_name' => 'deVries', 'league_id' => $leagueId]);
        $players->insert(['first_name' => 'Amy', 'last_name' => 'Abbott', 'league_id' => $leagueId]);
        db_connect()->table('league_groups')->insert(['league_id' => $leagueId, 'group_number' => 4, 'player1_id' => $zed, 'active' => 1]);

        $result = $this->asAdmin()->get('admin/players?league_id=' . $leagueId);
        $result->assertOK();
        $html = $result->getBody();

        // Sorted by last name ignoring capitals: Abbott, deVries, Zed.
        $this->assertLessThan(strpos($html, 'deVries'), strpos($html, 'Abbott'));
        $this->assertLessThan(strpos($html, 'Zed'), strpos($html, 'deVries'));

        // Zed is in group 4; the others show None.
        $this->assertMatchesRegularExpression('#Zed</td>\s*<td>Zoe</td>\s*<td>Group 4</td>#', $html);
        $this->assertMatchesRegularExpression('#Abbott</td>\s*<td>Amy</td>\s*<td>None</td>#', $html);
    }

    public function testGroupListShowsShortTeeTimesAndPlayerNames(): void
    {
        $leagueId = (new LeagueModel())->insert(['name' => 'Monday League']);
        $courseId = (new CourseModel())->insert(['name' => 'Course']);
        $teeTime  = (new TeeTimeModel())->insert(['league_id' => $leagueId, 'course_id' => $courseId, 'nine' => 'front', 'tee_time' => '15:10']);
        $player   = (new PlayerModel())->insert(['first_name' => 'Jordan', 'last_name' => 'Williams']);
        db_connect()->table('league_groups')->insert([
            'league_id' => $leagueId, 'group_number' => 2, 'front_tee_time_id' => $teeTime, 'player1_id' => $player, 'active' => 1,
        ]);

        $result = $this->asAdmin()->get('admin/groups?league_id=' . $leagueId);
        $result->assertSee('<td>Front 15:10</td>');
        $result->assertSee('<td>Williams, J</td>');
        $result->assertDontSee('Front 15:10 (Monday League)');

        // The edit form keeps the full labels.
        $form = $this->asAdmin()->get('admin/groups/new');
        $form->assertSee('Front 15:10 (Monday League)');
        $form->assertSee('Williams, Jordan');
    }

    public function testGenerateWeeklyRoundsToLeagueEndDate(): void
    {
        $leagueId = (new LeagueModel())->insert(['name' => 'League', 'start_date' => '2027-05-03', 'end_date' => '2027-05-31']);
        $courseId = (new CourseModel())->insert(['name' => 'Course']);
        // An existing round on week 2's date is kept and that week is skipped.
        (new RoundModel())->insert(['league_id' => $leagueId, 'course_id' => $courseId, 'round_date' => '2027-05-10', 'name' => 'Opening night']);

        $result = $this->asAdmin()->post('admin/rounds/generate', ['league_id' => $leagueId, 'course_id' => $courseId]);
        $result->assertSessionHas('message', '4 rounds created. 1 skipped because a round already exists on that date.');

        $rounds = (new RoundModel())->where('league_id', $leagueId)->orderBy('round_date')->findAll();
        $this->assertSame(
            ['2027-05-03', '2027-05-10', '2027-05-17', '2027-05-24', '2027-05-31'],
            array_column($rounds, 'round_date')
        );
        $this->assertSame(['Round 1', 'Opening night', 'Round 3', 'Round 4', 'Round 5'], array_column($rounds, 'name'));
    }

    public function testGenerateRoundsByCountAndRequiresStartDate(): void
    {
        $courseId = (new CourseModel())->insert(['name' => 'Course']);
        $noStart  = (new LeagueModel())->insert(['name' => 'No dates']);
        $this->asAdmin()->post('admin/rounds/generate', ['league_id' => $noStart, 'course_id' => $courseId])
            ->assertSessionHas('error');

        $leagueId = (new LeagueModel())->insert(['name' => 'League', 'start_date' => '2027-05-03']);
        $this->asAdmin()->post('admin/rounds/generate', ['league_id' => $leagueId, 'course_id' => $courseId, 'count' => '3'])
            ->assertSessionHas('message', '3 rounds created.');
        $this->seeInDatabase('rounds', ['league_id' => $leagueId, 'name' => 'Round 3', 'round_date' => '2027-05-17', 'status' => 'scheduled']);
    }

    public function testDeleteAllRoundsKeepsRoundsWithScores(): void
    {
        $leagueId = (new LeagueModel())->insert(['name' => 'League']);
        $otherId  = (new LeagueModel())->insert(['name' => 'Other league']);
        $courseId = (new CourseModel())->insert(['name' => 'Course']);
        $rounds   = new RoundModel();
        $scored   = $rounds->insert(['league_id' => $leagueId, 'course_id' => $courseId, 'round_date' => '2027-05-03']);
        $rounds->insert(['league_id' => $leagueId, 'course_id' => $courseId, 'round_date' => '2027-05-10']);
        $rounds->insert(['league_id' => $leagueId, 'course_id' => $courseId, 'round_date' => '2027-05-17']);
        $otherRound = $rounds->insert(['league_id' => $otherId, 'course_id' => $courseId, 'round_date' => '2027-05-10']);
        $playerId   = (new PlayerModel())->insert(['first_name' => 'Jo', 'last_name' => 'Golfer']);
        db_connect()->table('scores')->insert(['league_id' => $leagueId, 'player_id' => $playerId, 'round_id' => $scored]);

        $this->asAdmin()->post('admin/rounds/delete-all', ['league_id' => $leagueId])
            ->assertSessionHas('message', '2 rounds deleted from League. 1 kept because they already have scores.');

        $this->assertSame(1, (new RoundModel())->where('league_id', $leagueId)->countAllResults());
        $this->seeInDatabase('rounds', ['id' => $scored]);
        $this->seeInDatabase('rounds', ['id' => $otherRound]);
    }

    public function testPlayerListShowsWinningsAndSortsByGroupAndWinnings(): void
    {
        $leagueId = (new LeagueModel())->insert(['name' => 'League']);
        $courseId = (new CourseModel())->insert(['name' => 'Course']);
        $roundId  = (new RoundModel())->insert(['league_id' => $leagueId, 'course_id' => $courseId, 'round_date' => '2027-05-03']);
        $round2   = (new RoundModel())->insert(['league_id' => $leagueId, 'course_id' => $courseId, 'round_date' => '2027-05-10']);
        $players  = new PlayerModel();
        $adams    = (int) $players->insert(['first_name' => 'Al', 'last_name' => 'Adams', 'league_id' => $leagueId]);
        $baker    = (int) $players->insert(['first_name' => 'Bo', 'last_name' => 'Baker', 'league_id' => $leagueId]);
        $cole     = (int) $players->insert(['first_name' => 'Cy', 'last_name' => 'Cole', 'league_id' => $leagueId]);
        $db       = db_connect();
        $db->table('league_groups')->insert(['league_id' => $leagueId, 'group_number' => 2, 'player1_id' => $adams, 'active' => 1]);
        $db->table('league_groups')->insert(['league_id' => $leagueId, 'group_number' => 1, 'player1_id' => $cole, 'active' => 1]);
        $db->table('scores')->insert(['league_id' => $leagueId, 'player_id' => $baker, 'round_id' => $roundId, 'winnings_cents' => 1550]);
        $db->table('scores')->insert(['league_id' => $leagueId, 'player_id' => $baker, 'round_id' => $round2, 'winnings_cents' => 1000]);
        $db->table('scores')->insert(['league_id' => $leagueId, 'player_id' => $adams, 'round_id' => $roundId, 'winnings_cents' => 500]);

        $order = function (string $sort) use ($leagueId): array {
            $html = $this->asAdmin()->get("admin/players?league_id={$leagueId}&sort={$sort}")->getBody();
            preg_match_all('#<td>(Adams|Baker|Cole)</td>#', $html, $m);

            return $m[1];
        };

        $this->assertSame(['Adams', 'Baker', 'Cole'], $order('name'));
        $this->assertSame(['Cole', 'Adams', 'Baker'], $order('group'));    // group 1, group 2, none
        $this->assertSame(['Baker', 'Adams', 'Cole'], $order('winnings')); // $25.50, $5.00, $0.00

        $this->asAdmin()->get("admin/players?league_id={$leagueId}")->assertSee('$25.50');
    }

    public function testAdminCanSetPlayerWinningsAddedToRoundWinnings(): void
    {
        $leagueId = (new LeagueModel())->insert(['name' => 'League']);
        $courseId = (new CourseModel())->insert(['name' => 'Course']);
        $roundId  = (new RoundModel())->insert(['league_id' => $leagueId, 'course_id' => $courseId, 'round_date' => '2027-05-03']);
        $playerId = (int) (new PlayerModel())->insert(['first_name' => 'Jo', 'last_name' => 'Golfer', 'league_id' => $leagueId]);
        db_connect()->table('scores')->insert(['league_id' => $leagueId, 'player_id' => $playerId, 'round_id' => $roundId, 'winnings_cents' => 500]);

        $this->asAdmin()->get("admin/players/{$playerId}/edit")->assertSee('$5.00');

        $result = $this->asAdmin()->post("admin/players/{$playerId}", [
            'first_name' => 'Jo', 'last_name' => 'Golfer', 'league_id' => (string) $leagueId,
            'winnings_cents' => '12.50', 'active' => '1',
        ]);
        $result->assertRedirectTo('/admin/players');
        $this->seeInDatabase('players', ['id' => $playerId, 'winnings_cents' => 1250]);
        // The member number was left blank on the form, so a new one was assigned.
        $this->assertNotEmpty((new PlayerModel())->find($playerId)['member_number']);

        // The list total is $12.50 entered + $5.00 from rounds.
        $this->asAdmin()->get("admin/players?league_id={$leagueId}")->assertSee('$17.50');
    }

    public function testScoresListShowsGroupWithoutLeague(): void
    {
        $leagueId = (new LeagueModel())->insert(['name' => 'Monday League']);
        $courseId = (new CourseModel())->insert(['name' => 'Course']);
        $roundId  = (new RoundModel())->insert(['league_id' => $leagueId, 'course_id' => $courseId, 'round_date' => '2027-05-03']);
        $playerId = (new PlayerModel())->insert(['first_name' => 'Jo', 'last_name' => 'Golfer']);
        $db       = db_connect();
        $db->table('league_groups')->insert(['league_id' => $leagueId, 'group_number' => 3, 'active' => 1]);
        $groupId = $db->insertID();
        $db->table('scores')->insert(['league_id' => $leagueId, 'player_id' => $playerId, 'round_id' => $roundId, 'group_id' => $groupId]);

        $result = $this->asAdmin()->get("admin/scores?round_id={$roundId}");
        $result->assertSee('<td>Group 3</td>');
        $result->assertDontSee('Group 3 (Monday League)');
    }

    public function testAdminCannotRemoveOwnAdminAccess(): void
    {
        $result = $this->asAdmin()->post("admin/players/{$this->adminId}", [
            'first_name' => 'Pat', 'last_name' => 'Admin', 'email' => 'admin@example.com', 'is_admin' => '0', 'active' => '1',
        ]);
        $result->assertOK();
        $result->assertSee('cannot remove your own admin access');
        $this->seeInDatabase('players', ['id' => $this->adminId, 'is_admin' => 1]);
    }

    public function testScoreTotalsTheNinePlayed(): void
    {
        $leagueId = (new LeagueModel())->insert(['name' => 'League']);
        $courseId = (new CourseModel())->insert(['name' => 'Course']);
        $roundId  = (new RoundModel())->insert(['league_id' => $leagueId, 'course_id' => $courseId, 'round_date' => '2027-05-06']);
        $playerId = (new PlayerModel())->insert(['first_name' => 'Jo', 'last_name' => 'Golfer']);

        $post = ['round_id' => $roundId, 'player_id' => $playerId, 'nine' => 'front', 'playing' => '1', 'paid' => '1', 'handicap_used' => '6'];
        for ($n = 1; $n <= 9; $n++) {
            $post["hole{$n}"] = '5';
        }

        $this->asAdmin()->post('admin/scores', $post)->assertRedirectTo('/admin/scores');
        $this->seeInDatabase('scores', ['round_id' => $roundId, 'player_id' => $playerId, 'league_id' => $leagueId, 'gross' => 45, 'net' => 39, 'hole10' => null]);

        // A second score for the same player and round is refused.
        $this->asAdmin()->post('admin/scores', $post)->assertSee('already has a score');
    }

    public function testPlayerLoginWithSharedPasswordAndMemberNumber(): void
    {
        $this->asAdmin()->post('admin/settings', ['shared_login_id' => 'golfers', 'shared_password' => 'tee-off-2027'])
            ->assertSessionHas('message');
        $player = (new PlayerModel())->find((new PlayerModel())->insert(['first_name' => 'Jo', 'last_name' => 'Golfer']));

        $this->post('login', ['login_id' => 'golfers', 'password' => 'wrong-password'])->assertSessionMissing('shared_login_ok');

        $this->post('login', ['login_id' => 'golfers', 'password' => 'tee-off-2027'])->assertSessionHas('shared_login_ok', true);

        $result = $this->withSession(['shared_login_ok' => true])
            ->post('login/member', ['member_number' => (string) $player['member_number']]);
        $result->assertRedirectTo('/player');
        $result->assertSessionHas('player_id', (int) $player['id']);

        $this->assertNotNull((new PlayerModel())->find($player['id'])['last_login_at'], 'Player login should record the login date.');
    }
}
