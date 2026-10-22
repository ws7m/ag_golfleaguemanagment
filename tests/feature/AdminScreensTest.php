<?php

use App\Models\CourseModel;
use App\Models\HoleModel;
use App\Models\LeagueModel;
use App\Models\PlayerModel;
use App\Models\RoundModel;
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
            'admin/players', 'admin/players/new',
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

    public function testGroupListShowsTheTeeTimeForTheNineBeingPlayed(): void
    {
        $leagueId = (new LeagueModel())->insert(['name' => 'Monday League']);
        $player   = (new PlayerModel())->insert(['first_name' => 'Jordan', 'last_name' => 'Williams', 'league_id' => $leagueId]);
        $db       = db_connect();
        $db->table('league_groups')->insert([
            'league_id' => $leagueId, 'group_number' => 2, 'nine' => 'front', 'front_tee_time' => '15:10', 'back_tee_time' => '15:12', 'player1_id' => $player, 'active' => 1,
        ]);
        $groupId = $db->insertID();

        // Playing the front nine: only the front tee time is listed.
        $html = $this->asAdmin()->get('admin/groups?league_id=' . $leagueId)->getBody();
        $this->assertMatchesRegularExpression('#>Front</button>\s*</form>\s*</td>\s*<td>15:10</td>\s*<td>Williams, J</td>#', $html);
        $this->assertStringNotContainsString('15:12', $html);

        // After switching to the back nine, the back tee time is listed instead.
        $this->asAdmin()->post("admin/groups/{$groupId}/switch-nine");
        $html = $this->asAdmin()->get('admin/groups?league_id=' . $leagueId)->getBody();
        $this->assertStringContainsString('<td>15:12</td>', $html);
        $this->assertStringNotContainsString('15:10', $html);

        // The edit form has both tee times.
        $form = $this->asAdmin()->get("admin/groups/{$groupId}/edit");
        $form->assertSee('name="front_tee_time" value="15:10"');
        $form->assertSee('name="back_tee_time" value="15:12"');
        $form->assertSee('Williams, Jordan');
    }

    public function testGroupsAreListedFrontThenBackInTeeTimeOrder(): void
    {
        $leagueId = (new LeagueModel())->insert(['name' => 'League']);
        $groups   = db_connect()->table('league_groups');
        foreach ([
            [1, 'back', '16:00', '16:30'],
            [2, 'front', '16:20', '16:50'],
            [3, 'front', '16:10', '16:40'],
            [4, 'back', '16:00', '16:20'],
            [5, 'front', null, null],
            [6, 'front', '09:05', '09:35'],
        ] as [$number, $nine, $front, $back]) {
            $groups->insert(['league_id' => $leagueId, 'group_number' => $number, 'nine' => $nine, 'front_tee_time' => $front, 'back_tee_time' => $back, 'active' => 1]);
        }

        $html = $this->asAdmin()->get('admin/groups?league_id=' . $leagueId)->getBody();
        preg_match_all('#<td><a [^>]*>(\d+)</a></td>|<tr>\s*<td>(\d+)</td>#', $html, $m);
        $order = array_values(array_filter(array_map(static fn ($a, $b) => $a !== '' ? $a : $b, $m[1], $m[2])));

        // Front: 09:05 (6), 16:10 (3), 16:20 (2), no time (5); then back: 16:20 (4), 16:30 (1).
        $this->assertSame(['6', '3', '2', '5', '4', '1'], $order);
    }

    public function testTeeTimeListShowsFrontThenBackInTimeOrderWithPlayers(): void
    {
        $leagueId = (new LeagueModel())->insert(['name' => 'Monday League']);
        $players  = new PlayerModel();
        $ann      = $players->insert(['first_name' => 'Ann', 'last_name' => 'Ames', 'league_id' => $leagueId]);
        $bob      = $players->insert(['first_name' => 'Bob', 'last_name' => 'Baker', 'league_id' => $leagueId]);
        $cy       = $players->insert(['first_name' => 'Cy', 'last_name' => 'Cole', 'league_id' => $leagueId]);
        $groups   = db_connect()->table('league_groups');
        $groups->insert(['league_id' => $leagueId, 'group_number' => 1, 'nine' => 'front', 'front_tee_time' => '16:10', 'back_tee_time' => '16:40', 'player1_id' => $ann, 'player2_id' => $bob, 'active' => 1]);
        $groups->insert(['league_id' => $leagueId, 'group_number' => 2, 'nine' => 'front', 'front_tee_time' => '16:00', 'player1_id' => $cy, 'active' => 1]);
        $groups->insert(['league_id' => $leagueId, 'group_number' => 3, 'nine' => 'back', 'front_tee_time' => '15:00', 'back_tee_time' => '16:02', 'active' => 1]);
        $groups->insert(['league_id' => $leagueId, 'group_number' => 4, 'nine' => 'front', 'front_tee_time' => '15:30', 'active' => 0]);

        $this->asAdmin()->get("admin/groups?league_id={$leagueId}")->assertSee('admin/groups/tee-sheet');

        $html = $this->asAdmin()->get("admin/groups/tee-sheet?league_id={$leagueId}")->getBody();
        $text = trim((string) preg_replace(['/<[^>]+>/', '/\s+/'], ' ', substr($html, strpos($html, '<h2>Front</h2>'))));
        $text = (string) preg_replace('/\s+/', ' ', $text);

        // Front: 16:00 then 16:10, with players as "Last, First"; then Back.
        // Group 3's front time (15:00) isn't shown, as it plays the back
        // nine; inactive group 4 isn't listed.
        $this->assertStringStartsWith('Front Tee time Players 16:00 Cole, Cy 16:10 Ames, Ann Baker, Bob Back Tee time Players 16:02 No players', $text);
        $this->assertStringNotContainsString('15:00', $html);
        $this->assertStringNotContainsString('15:30', $html);
    }

    public function testGroupTeeTimesAreSavedAndChecked(): void
    {
        $leagueId = (new LeagueModel())->insert(['name' => 'League']);
        $base     = ['league_id' => $leagueId, 'group_number' => '1', 'nine' => 'front', 'active' => '1'];

        $this->asAdmin()->post('admin/groups', $base + ['front_tee_time' => '3:10pm'])->assertSee('flash-error');
        $this->dontSeeInDatabase('league_groups', ['league_id' => $leagueId]);

        $this->asAdmin()->post('admin/groups', $base + ['front_tee_time' => '15:10', 'back_tee_time' => '15:12'])
            ->assertRedirectTo('/admin/groups');
        $this->seeInDatabase('league_groups', ['league_id' => $leagueId, 'front_tee_time' => '15:10', 'back_tee_time' => '15:12']);

        // Two groups may have the same tee time.
        $this->asAdmin()->post('admin/groups', ['group_number' => '2', 'front_tee_time' => '15:10'] + $base)
            ->assertRedirectTo('/admin/groups');
        $this->assertSame(2, db_connect()->table('league_groups')->where('front_tee_time', '15:10')->countAllResults());
    }

    public function testShiftFrontAndBackTeeTimesForAllGroups(): void
    {
        $leagueId = (new LeagueModel())->insert(['name' => 'League']);
        $otherId  = (new LeagueModel())->insert(['name' => 'Other']);
        $db       = db_connect();
        $db->table('league_groups')->insert(['league_id' => $leagueId, 'group_number' => 1, 'front_tee_time' => '15:10', 'back_tee_time' => '15:12', 'active' => 1]);
        $one = $db->insertID();
        $db->table('league_groups')->insert(['league_id' => $leagueId, 'group_number' => 2, 'front_tee_time' => '15:20', 'back_tee_time' => null, 'active' => 1]);
        $two = $db->insertID();
        $db->table('league_groups')->insert(['league_id' => $otherId, 'group_number' => 1, 'front_tee_time' => '15:10', 'active' => 1]);
        $other = $db->insertID();

        $this->asAdmin()->post('admin/groups/shift-tee-times', ['league_id' => $leagueId, 'nine' => 'front', 'minutes' => '-20'])
            ->assertSessionHas('message', "2 groups' front nine tee times moved 20 minutes earlier.");
        $this->seeInDatabase('league_groups', ['id' => $one, 'front_tee_time' => '14:50', 'back_tee_time' => '15:12']);
        $this->seeInDatabase('league_groups', ['id' => $two, 'front_tee_time' => '15:00']);
        $this->seeInDatabase('league_groups', ['id' => $other, 'front_tee_time' => '15:10']);

        $this->asAdmin()->post('admin/groups/shift-tee-times', ['league_id' => $leagueId, 'nine' => 'back', 'minutes' => '15'])
            ->assertSessionHas('message', "1 groups' back nine tee times moved 15 minutes later.");
        $this->seeInDatabase('league_groups', ['id' => $one, 'front_tee_time' => '14:50', 'back_tee_time' => '15:27']);
        $this->seeInDatabase('league_groups', ['id' => $two, 'back_tee_time' => null]);

        // Past midnight changes nothing.
        $this->asAdmin()->post('admin/groups/shift-tee-times', ['league_id' => $leagueId, 'nine' => 'front', 'minutes' => '600'])
            ->assertSessionHas('error');
        $this->seeInDatabase('league_groups', ['id' => $one, 'front_tee_time' => '14:50']);
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

    public function testCreateGroupsForPlayersMakesEmptyNumberedGroups(): void
    {
        $leagueId = (new LeagueModel())->insert(['name' => 'League']);
        $players  = new PlayerModel();
        for ($i = 1; $i <= 9; $i++) {
            $players->insert(['first_name' => "P{$i}", 'last_name' => 'Golfer', 'league_id' => $leagueId]);
        }
        $players->insert(['first_name' => 'Gone', 'last_name' => 'Away', 'league_id' => $leagueId, 'active' => 0]);
        db_connect()->table('league_groups')->insert(['league_id' => $leagueId, 'group_number' => 1, 'active' => 1]);

        // 9 players need 3 groups; 1 exists, so groups 2 and 3 are created, empty.
        $this->asAdmin()->post('admin/groups/generate', ['league_id' => $leagueId])
            ->assertSessionHas('message', '9 registered players need 3 groups of 4. Created 2 (groups 2–3). Set their tee times and players on each group.');
        $this->seeInDatabase('league_groups', ['league_id' => $leagueId, 'group_number' => 3, 'nine' => 'front', 'front_tee_time' => null, 'player1_id' => null]);

        $this->asAdmin()->post('admin/groups/generate', ['league_id' => $leagueId])
            ->assertSessionHas('message', '9 registered players need 3 groups of 4. The league already has 3 groups, so none were created.');
    }

    public function testGroupFormOffersOnlyUnassignedPlayers(): void
    {
        $leagueId = (new LeagueModel())->insert(['name' => 'League']);
        $otherId  = (new LeagueModel())->insert(['name' => 'Other']);
        $players  = new PlayerModel();
        $ann = $players->insert(['first_name' => 'Ann', 'last_name' => 'Ames', 'league_id' => $leagueId]);
        $bob = $players->insert(['first_name' => 'Bob', 'last_name' => 'Baker', 'league_id' => $leagueId]);
        $players->insert(['first_name' => 'Cy', 'last_name' => 'Elsewhere', 'league_id' => $otherId]);
        $db = db_connect();
        $db->table('league_groups')->insert(['league_id' => $leagueId, 'group_number' => 1, 'player1_id' => $ann, 'active' => 1]);
        $group1 = $db->insertID();
        $db->table('league_groups')->insert(['league_id' => $leagueId, 'group_number' => 2, 'active' => 1]);
        $group2 = $db->insertID();

        // The options in one dropdown of the form, as value => label.
        $options = static function (string $html, string $field): array {
            preg_match('#<select id="f_' . $field . '"[^>]*>(.*?)</select>#s', $html, $select);
            preg_match_all('#<option value="(\d+)"[^>]*>([^<]*)</option>#', $select[1] ?? '', $m);

            return array_combine($m[1], $m[2]);
        };

        // Ann is in group 1, so group 2 isn't offered her; nor anyone from the other league.
        $html = $this->asAdmin()->get("admin/groups/{$group2}/edit")->getBody();
        $this->assertSame([(string) $bob => 'Baker, Bob'], $options($html, 'player1_id'));

        // Group 1's own edit page still offers its own player.
        $html = $this->asAdmin()->get("admin/groups/{$group1}/edit")->getBody();
        $this->assertSame([(string) $ann => 'Ames, Ann', (string) $bob => 'Baker, Bob'], $options($html, 'player1_id'));
    }

    public function testSavingAGroupRefusesPlayersAnotherGroupHas(): void
    {
        $leagueId = (new LeagueModel())->insert(['name' => 'League']);
        $ann      = (new PlayerModel())->insert(['first_name' => 'Ann', 'last_name' => 'Ames', 'league_id' => $leagueId]);
        $db       = db_connect();
        $db->table('league_groups')->insert(['league_id' => $leagueId, 'group_number' => 1, 'front_tee_time' => '16:00', 'player1_id' => $ann, 'active' => 1]);
        $group1 = $db->insertID();
        $db->table('league_groups')->insert(['league_id' => $leagueId, 'group_number' => 2, 'active' => 1]);
        $group2 = $db->insertID();

        $base = ['league_id' => $leagueId, 'group_number' => '2', 'nine' => 'front', 'active' => '1'];
        $this->asAdmin()->post("admin/groups/{$group2}", $base + ['player1_id' => $ann])
            ->assertSee('Ann Ames is already in group 1.');
        $this->seeInDatabase('league_groups', ['id' => $group2, 'player1_id' => null]);

        // Group 1 can still be saved with its own player.
        $this->asAdmin()->post("admin/groups/{$group1}", ['league_id' => $leagueId, 'group_number' => '1', 'nine' => 'back', 'front_tee_time' => '16:00', 'player1_id' => $ann, 'active' => '1'])
            ->assertRedirectTo('/admin/groups');
        $this->seeInDatabase('league_groups', ['id' => $group1, 'nine' => 'back']);
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

        $this->seeInDatabase('players', ['last_name' => 'Player3', 'first_name' => '3', 'login' => 'player3', 'league_id' => $leagueId, 'is_player' => 1,
            'tee' => 'white', 'flight' => 2, 'handicap' => 10]);
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

    public function testDeleteAllScoresForOneRoundOrAWholeLeague(): void
    {
        $leagueId = (new LeagueModel())->insert(['name' => 'League']);
        $otherId  = (new LeagueModel())->insert(['name' => 'Other']);
        $courseId = (new CourseModel())->insert(['name' => 'Course']);
        $rounds   = new RoundModel();
        $round1   = $rounds->insert(['league_id' => $leagueId, 'course_id' => $courseId, 'round_date' => '2027-05-03', 'name' => 'Round 1']);
        $round2   = $rounds->insert(['league_id' => $leagueId, 'course_id' => $courseId, 'round_date' => '2027-05-10', 'name' => 'Round 2']);
        $other    = $rounds->insert(['league_id' => $otherId, 'course_id' => $courseId, 'round_date' => '2027-05-03']);
        $db       = db_connect();
        foreach ([1, 2, 3] as $n) {
            $playerId = (new PlayerModel())->insert(['first_name' => "P{$n}", 'last_name' => 'Golfer']);
            $db->table('scores')->insert(['league_id' => $leagueId, 'player_id' => $playerId, 'round_id' => $round1]);
            $db->table('scores')->insert(['league_id' => $leagueId, 'player_id' => $playerId, 'round_id' => $round2]);
            $db->table('scores')->insert(['league_id' => $otherId, 'player_id' => $playerId, 'round_id' => $other]);
        }

        $this->asAdmin()->get('admin/scores')->assertSee('Delete all scores');

        // One round.
        $this->asAdmin()->post('admin/scores/delete-all', ['league_id' => $leagueId, 'round_id' => $round1])
            ->assertSessionHas('message', '3 scores deleted from the 2027-05-03 Round 1 round.');
        $this->assertSame(0, $db->table('scores')->where('round_id', $round1)->countAllResults());
        $this->assertSame(3, $db->table('scores')->where('round_id', $round2)->countAllResults());

        // Every round in the league; the other league is untouched.
        $this->asAdmin()->post('admin/scores/delete-all', ['league_id' => $leagueId, 'round_id' => ''])
            ->assertSessionHas('message', '3 scores deleted from every round in the league.');
        $this->assertSame(0, $db->table('scores')->where('league_id', $leagueId)->countAllResults());
        $this->assertSame(3, $db->table('scores')->where('league_id', $otherId)->countAllResults());
    }

    public function testResetLeagueDeletesItsDataButKeepsCoursesHolesAndGames(): void
    {
        $db       = db_connect();
        $leagueId = (new LeagueModel())->insert(['name' => 'League']);
        $otherId  = (new LeagueModel())->insert(['name' => 'Other']);
        $courseId = (new CourseModel())->insert(['name' => 'Course']);
        (new HoleModel())->insert(['course_id' => $courseId, 'number' => 1, 'nine' => 'front', 'white_par' => 4]);
        $gameId   = (new \App\Models\GameModel())->insert(['league_id' => $leagueId, 'name' => 'Stroke play']);
        $roundId  = (new RoundModel())->insert(['league_id' => $leagueId, 'course_id' => $courseId, 'game_id' => $gameId, 'round_date' => '2027-05-03']);
        $players  = new PlayerModel();
        $plain    = (int) $players->insert(['first_name' => 'Ann', 'last_name' => 'A', 'league_id' => $leagueId]);
        $admin    = (int) $players->insert(['first_name' => 'Bo', 'last_name' => 'B', 'league_id' => $leagueId, 'is_admin' => 1]);
        $shared   = (int) $players->insert(['first_name' => 'Cy', 'last_name' => 'C', 'league_id' => $leagueId]);
        $db->table('league_groups')->insert(['league_id' => $leagueId, 'group_number' => 1, 'front_tee_time' => '16:00', 'player1_id' => $plain, 'active' => 1]);
        $db->table('scores')->insert(['league_id' => $leagueId, 'player_id' => $plain, 'round_id' => $roundId]);
        // Cy also plays in the other league, so is kept.
        $otherRound = (new RoundModel())->insert(['league_id' => $otherId, 'course_id' => $courseId, 'round_date' => '2027-05-03']);
        $db->table('scores')->insert(['league_id' => $otherId, 'player_id' => $shared, 'round_id' => $otherRound]);

        $this->asAdmin()->get("admin/leagues/{$leagueId}/edit")->assertSee('Reset league data');

        // Without typing RESET nothing happens.
        $this->asAdmin()->post("admin/leagues/{$leagueId}/reset", ['confirm' => 'yes'])->assertSessionHas('error');
        $this->seeInDatabase('rounds', ['id' => $roundId]);

        $this->asAdmin()->post("admin/leagues/{$leagueId}/reset", ['confirm' => 'RESET'])
            ->assertSessionHas('message', 'League was reset: deleted 1 scores, 1 groups, 1 rounds, 1 players. 2 players were kept (admins, or players used in another league). Courses, holes and games were not changed.');

        foreach (['scores', 'league_groups', 'rounds'] as $table) {
            $this->assertSame(0, $db->table($table)->where('league_id', $leagueId)->countAllResults(), "{$table} left behind");
        }
        $this->dontSeeInDatabase('players', ['id' => $plain]);
        $this->seeInDatabase('players', ['id' => $admin]);
        $this->seeInDatabase('players', ['id' => $shared]);
        $this->seeInDatabase('leagues', ['id' => $leagueId]);
        $this->seeInDatabase('courses', ['id' => $courseId]);
        $this->seeInDatabase('holes', ['course_id' => $courseId]);
        $this->seeInDatabase('games', ['id' => $gameId]);
        $this->seeInDatabase('rounds', ['id' => $otherRound]);
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
