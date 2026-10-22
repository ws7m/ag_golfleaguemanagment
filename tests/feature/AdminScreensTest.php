<?php

use App\Models\CourseModel;
use App\Models\HoleModel;
use App\Models\LeagueGroupModel;
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
            'login' => 'admin1', 'first_name' => 'Pat', 'last_name' => 'Admin', 'is_admin' => 1, 'is_player' => 0,
        ]);
        $players->setPassword($this->adminId, 'correct-horse');
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
        $this->get('admin/leagues')->assertRedirectTo('/login');
    }

    public function testLoggedInAdminWhoNoLongerExistsIsLoggedOut(): void
    {
        $this->withSession(['admin_id' => 9999, 'admin_name' => 'Gone'])
            ->get('admin')
            ->assertRedirectTo('/login');
    }

    public function testAdminLoginWithCorrectAndWrongPassword(): void
    {
        $this->post('login', ['login' => 'admin1', 'password' => 'wrong-pass'])
            ->assertSessionMissing('admin_id');

        // Logins work with any capitals.
        $result = $this->post('login', ['login' => 'Admin1', 'password' => 'correct-horse']);
        $result->assertRedirectTo('/admin');
        $result->assertSessionHas('admin_id', $this->adminId);
        // An admin-only account is not a player.
        $result->assertSessionMissing('player_id');

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
            $holes[$n] = ['white_yardage' => 300 + $n, 'white_par' => 4, 'handicap' => $n, 'net' => $n + 1, 'adj' => -1];
        }

        $this->asAdmin()->post("admin/courses/{$courseId}/holes", ['holes' => $holes])
            ->assertRedirectTo("/admin/courses/{$courseId}/holes");
        $this->assertSame(18, (new HoleModel())->where('course_id', $courseId)->countAllResults());
        $this->seeInDatabase('holes', ['course_id' => $courseId, 'number' => 12, 'nine' => 'back', 'white_yardage' => 312, 'handicap' => 12, 'net' => 13, 'adj' => -1]);

        // Front 9, back 9 and total rows add up yardage (301-318) and par (4 each).
        $html = $this->asAdmin()->get("admin/courses/{$courseId}/holes")->getBody();
        $this->assertStringContainsString('<td data-total="white_yardage" data-range="front">2745</td>', $html);
        $this->assertStringContainsString('<td data-total="white_yardage" data-range="back">2826</td>', $html);
        $this->assertStringContainsString('<td data-total="white_yardage" data-range="all">5571</td>', $html);
        $this->assertStringContainsString('<td data-total="white_par" data-range="front">36</td>', $html);
        $this->assertStringContainsString('<td data-total="white_par" data-range="all">72</td>', $html);
        // Tees with nothing entered stay blank.
        $this->assertStringContainsString('<td data-total="blue_yardage" data-range="all"></td>', $html);
    }

    public function testExportCourseAsCsv(): void
    {
        $courseId = (new CourseModel())->insert(['name' => 'Home Course', 'city' => 'Fort Collins', 'website' => 'https://golf.example.com', 'front_slope' => 113]);
        (new HoleModel())->insert(['course_id' => $courseId, 'number' => 1, 'nine' => 'front', 'white_par' => 4, 'handicap' => 5]);

        $this->asAdmin()->get('admin/courses')->assertSee("admin/courses/{$courseId}/export");

        $result = $this->asAdmin()->get("admin/courses/{$courseId}/export");
        $result->assertOK();
        $result->assertHeader('Content-Type', 'text/csv; charset=utf-8');
        $result->assertHeader('Content-Disposition', 'attachment; filename="course-home-course.csv"');
        $csv = $result->getBody();
        $this->assertStringContainsString('city,"Fort Collins"', $csv);
        $this->assertStringContainsString("website,https://golf.example.com\n", $csv);
        $this->assertStringContainsString("front_slope,113\n", $csv);
        $this->assertStringContainsString("\n1,,,,4,,,5,,,\n", $csv);

        // The import form is on the course's edit page.
        $this->asAdmin()->get("admin/courses/{$courseId}/edit")->assertSee("admin/courses/{$courseId}/import");
    }

    public function testCourseWebsiteIsSavedAndChecked(): void
    {
        $this->asAdmin()->get('admin/courses/new')->assertSee('name="website"');

        $result = $this->asAdmin()->post('admin/courses', ['name' => 'Bad Site', 'website' => 'not a web address', 'active' => '1']);
        $result->assertOK();
        $result->assertSee('flash-error');
        $this->dontSeeInDatabase('courses', ['name' => 'Bad Site']);

        $this->asAdmin()->post('admin/courses', ['name' => 'Good Site', 'website' => 'https://www.southridgegolf.com', 'active' => '1'])
            ->assertRedirectTo('/admin/courses');
        $this->seeInDatabase('courses', ['name' => 'Good Site', 'website' => 'https://www.southridgegolf.com']);
    }

    public function testHoleHandicapsMustBeOneToEighteenAndNotRepeat(): void
    {
        $courseId = (new CourseModel())->insert(['name' => 'Home Course']);
        $holes    = [];
        for ($n = 1; $n <= 18; $n++) {
            $holes[$n] = ['handicap' => $n];
        }

        // Out of range.
        $bad      = $holes;
        $bad[4]   = ['handicap' => 19];
        $result   = $this->asAdmin()->post("admin/courses/{$courseId}/holes", ['holes' => $bad]);
        $result->assertSee('Hole 4: Hcp must be from 1 to 18.');

        // The same handicap on two holes names both holes, and nothing is saved.
        $dupe    = $holes;
        $dupe[2] = ['handicap' => 7];
        $result  = $this->asAdmin()->post("admin/courses/{$courseId}/holes", ['holes' => $dupe]);
        $result->assertSee('Hole 2: Hcp 7 is also used on hole 7.');
        $result->assertSee('Hole 7: Hcp 7 is also used on hole 2.');
        $this->assertSame(0, (new HoleModel())->where('course_id', $courseId)->countAllResults());

        // Saved, then two holes swap handicaps in one save.
        $this->asAdmin()->post("admin/courses/{$courseId}/holes", ['holes' => $holes])->assertSessionHas('message');
        $swap    = $holes;
        $swap[1] = ['handicap' => 2];
        $swap[2] = ['handicap' => 1];
        $this->asAdmin()->post("admin/courses/{$courseId}/holes", ['holes' => $swap])->assertSessionHas('message');
        $this->seeInDatabase('holes', ['course_id' => $courseId, 'number' => 1, 'handicap' => 2]);
        $this->seeInDatabase('holes', ['course_id' => $courseId, 'number' => 2, 'handicap' => 1]);
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
        $zed      = (int) $players->insert(['first_name' => 'Zoe', 'last_name' => 'Zed', 'league_id' => $leagueId, 'phone' => '555-0101', 'email' => 'zoe@example.com']);
        $players->insert(['first_name' => 'Dan', 'last_name' => 'deVries', 'league_id' => $leagueId]);
        $players->insert(['first_name' => 'Amy', 'last_name' => 'Abbott', 'league_id' => $leagueId]);
        db_connect()->table('league_groups')->insert(['league_id' => $leagueId, 'group_number' => 4, 'player1_id' => $zed, 'active' => 1]);

        $result = $this->asAdmin()->get('admin/players?league_id=' . $leagueId);
        $result->assertOK();
        $html = $result->getBody();

        // Sorted by last name ignoring capitals: Abbott, deVries, Zed.
        $this->assertLessThan(strpos($html, 'deVries'), strpos($html, 'Abbott'));
        $this->assertLessThan(strpos($html, 'Zed'), strpos($html, 'deVries'));

        // Phone, then email as a link, then the group number; no group shows None.
        $this->assertMatchesRegularExpression('#Zed</td>\s*<td>Zoe</td>\s*<td>555-0101</td>\s*<td><a class="cell-email" href="mailto:zoe@example.com"[^>]*>zoe@example.com</a></td>\s*<td>4</td>#', $html);
        $this->assertMatchesRegularExpression('#Abbott</td>\s*<td>Amy</td>\s*<td></td>\s*<td></td>\s*<td>None</td>#', $html);
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
            'winnings_cents' => '12.50', 'active' => '1', 'is_player' => '1',
        ]);
        $result->assertRedirectTo('/admin/players');
        $this->seeInDatabase('players', ['id' => $playerId, 'winnings_cents' => 1250]);
        // The member number was left blank on the form, so a new one was assigned.
        $this->assertNotEmpty((new PlayerModel())->find($playerId)['member_number']);

        // The list total is $12.50 entered + $5.00 from rounds, right-aligned.
        $this->asAdmin()->get("admin/players?league_id={$leagueId}")->assertSee('<td class="num">$17.50</td>');
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

    public function testNewPlayerStartsWithLastNameAsPassword(): void
    {
        $this->asAdmin()->post('admin/players', ['first_name' => 'Tim', 'last_name' => 'Annable', 'active' => '1', 'is_player' => '1'])
            ->assertRedirectTo('/admin/players');

        $players = new PlayerModel();
        $this->assertNotNull($players->verifyLogin('annabt', 'annable'));

        // A password typed in when adding the player is used instead.
        $this->asAdmin()->post('admin/players', [
            'first_name' => 'Jo', 'last_name' => 'Golfer', 'password' => 'fairway-27', 'active' => '1', 'is_player' => '1',
        ]);
        $this->assertNotNull($players->verifyLogin('golfej', 'fairway-27'));
        $this->assertNull($players->verifyLogin('golfej', 'golfer'));
    }

    public function testCreateFrontAndBackNineTeeTimes(): void
    {
        $leagueId = (new LeagueModel())->insert(['name' => 'League']);
        $courseId = (new CourseModel())->insert(['name' => 'Course']);
        (new TeeTimeModel())->insert(['league_id' => $leagueId, 'course_id' => $courseId, 'nine' => 'front', 'tee_time' => '16:10']);

        $this->asAdmin()->post('admin/tee-times/generate', [
            'league_id' => $leagueId, 'course_id' => $courseId, 'nine' => 'front', 'start' => '16:00', 'increment' => '10', 'count' => '4',
        ])->assertSessionHas('message', '3 front nine tee times created. 1 skipped because they already exist.');

        $this->asAdmin()->post('admin/tee-times/generate', [
            'league_id' => $leagueId, 'course_id' => $courseId, 'nine' => 'back', 'start' => '16:02', 'increment' => '8', 'count' => '3',
        ])->assertSessionHas('message', '3 back nine tee times created.');

        $times = static fn (string $nine) => array_column(
            (new TeeTimeModel())->where('league_id', $leagueId)->where('nine', $nine)->orderBy('tee_time')->findAll(),
            'tee_time'
        );
        $this->assertSame(['16:00', '16:10', '16:20', '16:30'], $times('front'));
        $this->assertSame(['16:02', '16:10', '16:18'], $times('back'));

        // Running past midnight creates nothing.
        $this->asAdmin()->post('admin/tee-times/generate', [
            'league_id' => $leagueId, 'course_id' => $courseId, 'nine' => 'back', 'start' => '23:50', 'increment' => '10', 'count' => '3',
        ])->assertSessionHas('error');
        $this->assertCount(3, $times('back'));

        // The list doesn't repeat the league chosen at the top.
        $this->asAdmin()->get("admin/tee-times?league_id={$leagueId}")->assertDontSee('<th>League</th>');
    }

    public function testDeleteAllTeeTimesClearsThemFromGroups(): void
    {
        $leagueId = (new LeagueModel())->insert(['name' => 'League']);
        $otherId  = (new LeagueModel())->insert(['name' => 'Other']);
        $courseId = (new CourseModel())->insert(['name' => 'Course']);
        $teeTimes = new TeeTimeModel();
        $front    = $teeTimes->insert(['league_id' => $leagueId, 'course_id' => $courseId, 'nine' => 'front', 'tee_time' => '16:00']);
        $teeTimes->insert(['league_id' => $leagueId, 'course_id' => $courseId, 'nine' => 'back', 'tee_time' => '16:02']);
        $other = $teeTimes->insert(['league_id' => $otherId, 'course_id' => $courseId, 'nine' => 'front', 'tee_time' => '16:00']);
        $db    = db_connect();
        $db->table('league_groups')->insert(['league_id' => $leagueId, 'group_number' => 1, 'front_tee_time_id' => $front, 'active' => 1]);
        $groupId = $db->insertID();

        $this->asAdmin()->post('admin/tee-times/delete-all', ['league_id' => $leagueId])
            ->assertSessionHas('message', '2 tee times deleted. Groups that used them need their tee times set again.');

        $this->assertSame(0, (new TeeTimeModel())->where('league_id', $leagueId)->countAllResults());
        $this->seeInDatabase('tee_times', ['id' => $other]);
        $this->seeInDatabase('league_groups', ['id' => $groupId, 'front_tee_time_id' => null]);
    }

    public function testTeeTimeListShowsAssignedGroup(): void
    {
        $leagueId = (new LeagueModel())->insert(['name' => 'League']);
        $courseId = (new CourseModel())->insert(['name' => 'Course']);
        $teeTimes = new TeeTimeModel();
        $front    = $teeTimes->insert(['league_id' => $leagueId, 'course_id' => $courseId, 'nine' => 'front', 'tee_time' => '16:00']);
        $back     = $teeTimes->insert(['league_id' => $leagueId, 'course_id' => $courseId, 'nine' => 'back', 'tee_time' => '16:02']);
        $teeTimes->insert(['league_id' => $leagueId, 'course_id' => $courseId, 'nine' => 'front', 'tee_time' => '16:10']);
        db_connect()->table('league_groups')->insert([
            'league_id' => $leagueId, 'group_number' => 5, 'front_tee_time_id' => $front, 'back_tee_time_id' => $back, 'active' => 1,
        ]);

        $html = $this->asAdmin()->get("admin/tee-times?league_id={$leagueId}")->getBody();
        $this->assertMatchesRegularExpression('#<td>Front</td>\s*<td>Group 5</td>\s*<td>16:00</td>#', $html);
        $this->assertMatchesRegularExpression('#<td>Back</td>\s*<td>Group 5</td>\s*<td>16:02</td>#', $html);
        $this->assertMatchesRegularExpression('#<td>Front</td>\s*<td>None</td>\s*<td>16:10</td>#', $html);
    }

    /**
     * @return array{0: int, 1: list<int>, 2: list<int>} League id, front and back tee time ids in time order
     */
    private function leagueWithTeeTimes(int $count): array
    {
        $leagueId = (new LeagueModel())->insert(['name' => 'League']);
        $courseId = (new CourseModel())->insert(['name' => 'Course']);
        $teeTimes = new TeeTimeModel();
        $fronts   = [];
        $backs    = [];
        for ($i = 0; $i < $count; $i++) {
            $fronts[] = (int) $teeTimes->insert(['league_id' => $leagueId, 'course_id' => $courseId, 'nine' => 'front', 'tee_time' => sprintf('16:%02d', $i * 10)]);
            $backs[]  = (int) $teeTimes->insert(['league_id' => $leagueId, 'course_id' => $courseId, 'nine' => 'back', 'tee_time' => sprintf('16:%02d', $i * 10 + 2)]);
        }

        return [$leagueId, $fronts, $backs];
    }

    public function testCreateGroupsPutsEveryPlayerInAGroupOfFourWithTeeTimesInOrder(): void
    {
        [$leagueId, $fronts, $backs] = $this->leagueWithTeeTimes(2);
        $players = new PlayerModel();
        $ids     = [];
        foreach (['Ames', 'Baker', 'Cole', 'Dunn', 'Eads', 'Ford', 'Gray', 'Hale', 'Irwin', 'Jones'] as $last) {
            $ids[$last] = (int) $players->insert(['first_name' => 'Pat', 'last_name' => $last, 'league_id' => $leagueId]);
        }
        // Not placed: inactive, or an admin-only account.
        $players->insert(['first_name' => 'Gone', 'last_name' => 'Away', 'league_id' => $leagueId, 'active' => 0]);
        $players->insert(['first_name' => 'Admin', 'last_name' => 'Only', 'league_id' => $leagueId, 'is_player' => 0]);

        // 10 players need 3 groups; there are only 2 tee times, so group 3 starts again at the first.
        $this->asAdmin()->post('admin/groups/generate', ['league_id' => $leagueId])
            ->assertSessionHas('message', '10 registered players. Created 3 groups of up to 4 (groups 1–3). There were more groups than tee times, so tee times were used again from the first one.');

        $groups = db_connect()->table('league_groups')->where('league_id', $leagueId)->orderBy('group_number')->get()->getResultArray();
        $this->assertCount(3, $groups);
        $this->assertSame([$ids['Ames'], $ids['Baker'], $ids['Cole'], $ids['Dunn']], LeagueGroupModel::playerIds($groups[0]));
        $this->assertSame([$ids['Eads'], $ids['Ford'], $ids['Gray'], $ids['Hale']], LeagueGroupModel::playerIds($groups[1]));
        $this->assertSame([$ids['Irwin'], $ids['Jones']], LeagueGroupModel::playerIds($groups[2]));
        $this->assertSame([$fronts[0], $fronts[1], $fronts[0]], array_map('intval', array_column($groups, 'front_tee_time_id')));
        $this->assertSame([$backs[0], $backs[1], $backs[0]], array_map('intval', array_column($groups, 'back_tee_time_id')));
        $this->assertSame(['front', 'front', 'front'], array_column($groups, 'nine'));

        // Running it again changes nothing.
        $this->asAdmin()->post('admin/groups/generate', ['league_id' => $leagueId])
            ->assertSessionHas('message', '10 registered players. All of them are already in groups, so nothing was changed.');
    }

    public function testCreateGroupsFillsOpenPlacesFirstAndContinuesTeeTimes(): void
    {
        [$leagueId, $fronts, $backs] = $this->leagueWithTeeTimes(3);
        $players = new PlayerModel();
        $ids     = [];
        foreach (['Ames', 'Baker', 'Cole', 'Dunn', 'Eads', 'Ford'] as $last) {
            $ids[$last] = (int) $players->insert(['first_name' => 'Pat', 'last_name' => $last, 'league_id' => $leagueId]);
        }
        // Group 1 already has Ames and uses the first tee times.
        db_connect()->table('league_groups')->insert([
            'league_id' => $leagueId, 'group_number' => 1, 'front_tee_time_id' => $fronts[0], 'back_tee_time_id' => $backs[0],
            'player1_id' => $ids['Ames'], 'active' => 1,
        ]);

        $this->asAdmin()->post('admin/groups/generate', ['league_id' => $leagueId])
            ->assertSessionHas('message', '6 registered players. 3 placed in open places in existing groups. Created 1 groups of up to 4 (group 2).');

        // Baker, Cole, Dunn fill group 1; Eads and Ford start group 2 on the second tee times.
        $this->seeInDatabase('league_groups', ['group_number' => 1, 'player1_id' => $ids['Ames'], 'player2_id' => $ids['Baker'], 'player3_id' => $ids['Cole'], 'player4_id' => $ids['Dunn']]);
        $this->seeInDatabase('league_groups', [
            'group_number' => 2, 'player1_id' => $ids['Eads'], 'player2_id' => $ids['Ford'], 'player3_id' => null,
            'front_tee_time_id' => $fronts[1], 'back_tee_time_id' => $backs[1],
        ]);
    }

    public function testCreateGroupsNeedsRegisteredPlayers(): void
    {
        $emptyId = (new LeagueModel())->insert(['name' => 'Empty']);
        $this->asAdmin()->post('admin/groups/generate', ['league_id' => $emptyId])->assertSessionHas('error');
    }

    public function testDeleteAllGroupsClearsScoresThatUseThem(): void
    {
        $leagueId = (new LeagueModel())->insert(['name' => 'League']);
        $otherId  = (new LeagueModel())->insert(['name' => 'Other']);
        $courseId = (new CourseModel())->insert(['name' => 'Course']);
        $roundId  = (new RoundModel())->insert(['league_id' => $leagueId, 'course_id' => $courseId, 'round_date' => '2027-05-03']);
        $playerId = (new PlayerModel())->insert(['first_name' => 'Jo', 'last_name' => 'Golfer']);
        $db       = db_connect();
        $db->table('league_groups')->insert(['league_id' => $leagueId, 'group_number' => 1, 'player1_id' => $playerId, 'active' => 1]);
        $groupId = $db->insertID();
        $db->table('league_groups')->insert(['league_id' => $leagueId, 'group_number' => 2, 'active' => 1]);
        $db->table('league_groups')->insert(['league_id' => $otherId, 'group_number' => 1, 'active' => 1]);
        $otherGroup = $db->insertID();
        $db->table('scores')->insert(['league_id' => $leagueId, 'player_id' => $playerId, 'round_id' => $roundId, 'group_id' => $groupId]);

        $this->asAdmin()->post('admin/groups/delete-all', ['league_id' => $leagueId])
            ->assertSessionHas('message', '2 groups deleted. 1 scores no longer show a group played in.');

        $this->assertSame(0, $db->table('league_groups')->where('league_id', $leagueId)->countAllResults());
        $this->seeInDatabase('league_groups', ['id' => $otherGroup]);
        $this->seeInDatabase('scores', ['player_id' => $playerId, 'group_id' => null]);
        $this->seeInDatabase('players', ['id' => $playerId]);
    }

    public function testGroupNineCanBeSetAndSwitchedForAllGroups(): void
    {
        $leagueId = (new LeagueModel())->insert(['name' => 'League']);
        $otherId  = (new LeagueModel())->insert(['name' => 'Other']);

        $this->asAdmin()->post('admin/groups', ['league_id' => $leagueId, 'group_number' => '1', 'nine' => 'back', 'active' => '1'])
            ->assertRedirectTo('/admin/groups');
        $this->asAdmin()->post('admin/groups', ['league_id' => $leagueId, 'group_number' => '2', 'nine' => 'front', 'active' => '1']);
        $this->asAdmin()->post('admin/groups', ['league_id' => $otherId, 'group_number' => '1', 'nine' => 'front', 'active' => '1']);
        $this->seeInDatabase('league_groups', ['league_id' => $leagueId, 'group_number' => 1, 'nine' => 'back']);

        $this->asAdmin()->post('admin/groups', ['league_id' => $leagueId, 'group_number' => '3', 'nine' => 'middle', 'active' => '1'])
            ->assertSee('flash-error');

        $this->asAdmin()->post('admin/groups/switch-nine', ['league_id' => $leagueId])
            ->assertSessionHas('message', '2 groups switched to the other nine.');
        $this->seeInDatabase('league_groups', ['league_id' => $leagueId, 'group_number' => 1, 'nine' => 'front']);
        $this->seeInDatabase('league_groups', ['league_id' => $leagueId, 'group_number' => 2, 'nine' => 'back']);
        // Another league's groups are not touched.
        $this->seeInDatabase('league_groups', ['league_id' => $otherId, 'group_number' => 1, 'nine' => 'front']);
    }

    public function testSwitchOneGroupsNineFromTheList(): void
    {
        $leagueId = (new LeagueModel())->insert(['name' => 'League']);
        $db       = db_connect();
        $db->table('league_groups')->insert(['league_id' => $leagueId, 'group_number' => 1, 'nine' => 'front', 'active' => 1]);
        $one = $db->insertID();
        $db->table('league_groups')->insert(['league_id' => $leagueId, 'group_number' => 2, 'nine' => 'front', 'active' => 1]);
        $two = $db->insertID();

        // The Playing value itself is the button that switches it.
        $html = $this->asAdmin()->get("admin/groups?league_id={$leagueId}")->getBody();
        $this->assertMatchesRegularExpression("#action=\"[^\"]*admin/groups/{$one}/switch-nine\"[^>]*>.*?<button[^>]*>Front</button>#s", $html);
        $this->assertStringNotContainsString('Switch nine', $html);

        $this->asAdmin()->post("admin/groups/{$one}/switch-nine")
            ->assertSessionHas('message', 'Group 1 now plays the back nine.');
        $this->seeInDatabase('league_groups', ['id' => $one, 'nine' => 'back']);
        $this->seeInDatabase('league_groups', ['id' => $two, 'nine' => 'front']);

        $this->asAdmin()->post("admin/groups/{$one}/switch-nine")
            ->assertSessionHas('message', 'Group 1 now plays the front nine.');
        $this->seeInDatabase('league_groups', ['id' => $one, 'nine' => 'front']);
    }

    public function testAddAllGroupsToARound(): void
    {
        $leagueId = (new LeagueModel())->insert(['name' => 'League']);
        $courseId = (new CourseModel())->insert(['name' => 'Course']);
        $roundId  = (new RoundModel())->insert(['league_id' => $leagueId, 'course_id' => $courseId, 'round_date' => '2027-05-03']);
        $players  = new PlayerModel();
        $ann      = (int) $players->insert(['first_name' => 'Ann', 'last_name' => 'A']);
        $bob      = (int) $players->insert(['first_name' => 'Bob', 'last_name' => 'B']);
        $cy       = (int) $players->insert(['first_name' => 'Cy', 'last_name' => 'C']);
        $gone     = (int) $players->insert(['first_name' => 'Di', 'last_name' => 'D', 'active' => 0]);
        $db       = db_connect();
        $db->table('league_groups')->insert(['league_id' => $leagueId, 'group_number' => 1, 'nine' => 'front', 'player1_id' => $ann, 'player2_id' => $bob, 'active' => 1]);
        $group1 = $db->insertID();
        $db->table('league_groups')->insert(['league_id' => $leagueId, 'group_number' => 2, 'nine' => 'back', 'player1_id' => $cy, 'player2_id' => $gone, 'active' => 1]);
        $group2 = $db->insertID();
        // Bob is already entered.
        $db->table('scores')->insert(['league_id' => $leagueId, 'player_id' => $bob, 'round_id' => $roundId, 'playing' => 1]);

        $this->asAdmin()->get("admin/rounds/{$roundId}/edit")->assertSee('Add all groups to this round');

        $this->asAdmin()->post("admin/rounds/{$roundId}/add-groups")
            ->assertSessionHas('message', '2 players from 2 groups added to this round. 1 were already in it.');

        $this->seeInDatabase('scores', ['round_id' => $roundId, 'player_id' => $ann, 'group_id' => $group1, 'nine' => 'front', 'playing' => 1, 'paid' => 0]);
        $this->seeInDatabase('scores', ['round_id' => $roundId, 'player_id' => $cy, 'group_id' => $group2, 'nine' => 'back']);
        $this->dontSeeInDatabase('scores', ['round_id' => $roundId, 'player_id' => $gone]);
        $this->assertSame(3, $db->table('scores')->where('round_id', $roundId)->countAllResults());

        // Pressing it again adds nobody.
        $this->asAdmin()->post("admin/rounds/{$roundId}/add-groups")
            ->assertSessionHas('message', '0 players from 2 groups added to this round. 3 were already in it.');
    }

    public function testDeleteAllPlayersKeepsAdminsAndPlayersWithScores(): void
    {
        $leagueId = (new LeagueModel())->insert(['name' => 'League']);
        $otherId  = (new LeagueModel())->insert(['name' => 'Other']);
        $courseId = (new CourseModel())->insert(['name' => 'Course']);
        $roundId  = (new RoundModel())->insert(['league_id' => $leagueId, 'course_id' => $courseId, 'round_date' => '2027-05-03']);
        $players  = new PlayerModel();
        $plain    = (int) $players->insert(['first_name' => 'Ann', 'last_name' => 'A', 'league_id' => $leagueId]);
        $inGroup  = (int) $players->insert(['first_name' => 'Bob', 'last_name' => 'B', 'league_id' => $leagueId]);
        $scored   = (int) $players->insert(['first_name' => 'Cy', 'last_name' => 'C', 'league_id' => $leagueId]);
        $admin    = (int) $players->insert(['first_name' => 'Di', 'last_name' => 'D', 'league_id' => $leagueId, 'is_admin' => 1]);
        $other    = (int) $players->insert(['first_name' => 'Ed', 'last_name' => 'E', 'league_id' => $otherId]);
        $db       = db_connect();
        $db->table('league_groups')->insert(['league_id' => $leagueId, 'group_number' => 1, 'player1_id' => $inGroup, 'player2_id' => $scored, 'active' => 1]);
        $groupId = $db->insertID();
        $db->table('scores')->insert(['league_id' => $leagueId, 'player_id' => $scored, 'round_id' => $roundId]);

        $this->asAdmin()->post('admin/players/delete-all', ['league_id' => $leagueId])
            ->assertSessionHas('message', '2 players deleted. 1 admins kept. 1 kept because they have scores.');

        $this->dontSeeInDatabase('players', ['id' => $plain]);
        $this->dontSeeInDatabase('players', ['id' => $inGroup]);
        $this->seeInDatabase('players', ['id' => $scored]);
        $this->seeInDatabase('players', ['id' => $admin]);
        $this->seeInDatabase('players', ['id' => $other]);
        // Removed from the group roster; the player with scores stays on it.
        $this->seeInDatabase('league_groups', ['id' => $groupId, 'player1_id' => null, 'player2_id' => $scored]);
    }

    // TEMPORARY: remove with the "Add test players" helper.
    public function testAddTestPlayers(): void
    {
        $leagueId = (new LeagueModel())->insert(['name' => 'League']);
        $players  = new PlayerModel();
        $players->insert(['first_name' => '2', 'last_name' => 'Player2', 'login' => 'player2']);

        // Numbering continues after the highest existing PlayerN.
        $this->asAdmin()->post('admin/players/add-test', ['league_id' => $leagueId, 'count' => '3'])
            ->assertSessionHas('message', '3 test players added (Player3 to Player5). Each logs in with playerN / playerN.');

        $this->seeInDatabase('players', ['last_name' => 'Player3', 'first_name' => '3', 'login' => 'player3', 'league_id' => $leagueId, 'is_player' => 1]);
        $this->seeInDatabase('players', ['last_name' => 'Player5', 'first_name' => '5', 'login' => 'player5']);
        $this->assertNotNull($players->verifyLogin('player4', 'player4'));

        $this->asAdmin()->post('admin/players/add-test', ['league_id' => $leagueId, 'count' => '0'])->assertSessionHas('error');
        $this->asAdmin()->post('admin/players/add-test', ['league_id' => $leagueId, 'count' => '201'])->assertSessionHas('error');

        // 200 at once is allowed and quick.
        $start = microtime(true);
        $this->asAdmin()->post('admin/players/add-test', ['league_id' => $leagueId, 'count' => '200'])
            ->assertSessionHas('message', '200 test players added (Player6 to Player205). Each logs in with playerN / playerN.');
        $this->assertLessThan(15, microtime(true) - $start, 'Adding 200 test players should be well within the 30s request limit');
        $this->assertNotNull($players->verifyLogin('player205', 'player205'));
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

    public function testNewPlayerGetsDefaultLoginAndCanLogInWithPasswordSetByAdmin(): void
    {
        $this->asAdmin()->post('admin/players', [
            'first_name' => 'Mark', 'last_name' => 'Holbrook', 'password' => 'fairway-27', 'active' => '1', 'is_player' => '1',
        ])->assertRedirectTo('/admin/players');
        $player = (new PlayerModel())->where('last_name', 'Holbrook')->first();
        $this->assertSame('holbrm', $player['login']);
        $this->assertArrayNotHasKey('password_hash', $player);

        // A second Mark Holbrook gets the next free login.
        $this->asAdmin()->post('admin/players', ['first_name' => 'Mary', 'last_name' => 'Holbrooke', 'active' => '1', 'is_player' => '1']);
        $this->seeInDatabase('players', ['first_name' => 'Mary', 'login' => 'holbrm2']);

        $this->post('login', ['login' => 'holbrm', 'password' => 'wrong-password'])->assertSessionMissing('player_id');

        $result = $this->post('login', ['login' => 'holbrm', 'password' => 'fairway-27']);
        $result->assertRedirectTo('/player');
        $result->assertSessionHas('player_id', (int) $player['id']);
        $result->assertSessionMissing('admin_id');

        $this->assertNotNull((new PlayerModel())->find($player['id'])['last_login_at'], 'Player login should record the login date.');
    }

    public function testAdminCanSetLoginAndPasswordOnEdit(): void
    {
        $playerId = (int) (new PlayerModel())->insert(['first_name' => 'Jo', 'last_name' => 'Golfer']);

        $this->asAdmin()->post("admin/players/{$playerId}", [
            'first_name' => 'Jo', 'last_name' => 'Golfer', 'login' => 'JoG', 'password' => 'short', 'active' => '1',
        ])->assertSee('at least 8 characters');

        $this->asAdmin()->post("admin/players/{$playerId}", [
            'first_name' => 'Jo', 'last_name' => 'Golfer', 'login' => 'JoG', 'password' => 'putting-27', 'active' => '1',
        ])->assertRedirectTo('/admin/players');
        $this->assertNotNull((new PlayerModel())->verifyLogin('jog', 'putting-27'));

        // Clearing the login sets it again from the name.
        $this->asAdmin()->post("admin/players/{$playerId}", [
            'first_name' => 'Jo', 'last_name' => 'Golfer', 'login' => '', 'active' => '1',
        ]);
        $this->seeInDatabase('players', ['id' => $playerId, 'login' => 'golfej']);
        // Leaving the password blank kept it.
        $this->assertNotNull((new PlayerModel())->verifyLogin('golfej', 'putting-27'));

        // Another player's login is refused.
        $otherId = (int) (new PlayerModel())->insert(['first_name' => 'Al', 'last_name' => 'Other']);
        $this->asAdmin()->post("admin/players/{$otherId}", [
            'first_name' => 'Al', 'last_name' => 'Other', 'login' => 'GOLFEJ', 'active' => '1',
        ])->assertSee('already used by another player');
    }

    public function testAdminOnlyAccountIsHiddenFromPlayerListsButShownInSettings(): void
    {
        $this->asAdmin()->get('admin/players')->assertDontSee('admin1');
        $this->asAdmin()->get('admin/groups/new')->assertDontSee('Admin, Pat');
        $this->asAdmin()->get('admin/settings')->assertSee('admin1');
    }
}
