<?php

use App\Libraries\CourseCsv;
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

    public function testLeagueNoticeShowsOnPlayerPage(): void
    {
        $id = (new LeagueModel())->insert(['name' => 'Notice League', 'start_date' => '2099-05-01', 'active' => 1]);

        $list = $this->asAdmin()->get('admin/leagues');
        $this->assertMatchesRegularExpression('#leagues/' . $id . '/notice">Notice</a>\s*<a href="[^"]*leagues/' . $id . '/edit">Edit#', $list->response()->getBody());
        $this->asAdmin()->get("admin/leagues/{$id}/notice")->assertOK();

        $this->asAdmin()->post("admin/leagues/{$id}/notice", ['notice' => "No play on July 4.\nBring <ball> markers."])
            ->assertRedirectTo('/admin/leagues');
        $this->seeInDatabase('leagues', ['id' => $id, 'notice' => "No play on July 4.\nBring <ball> markers."]);

        // Editing the league on its main form leaves the notice alone.
        $this->asAdmin()->post("admin/leagues/{$id}", ['name' => 'Notice League', 'start_date' => '2099-05-01', 'active' => '1']);
        $this->seeInDatabase('leagues', ['id' => $id, 'notice' => "No play on July 4.\nBring <ball> markers."]);

        // Players see it on their page after logging in, not on the login screen.
        $this->withSession([])->get('login')->assertDontSee('League Notice');
        $playerId = (new PlayerModel())->insert(['first_name' => 'Nora', 'last_name' => 'Notice', 'league_id' => $id, 'must_change_password' => 0]);
        $page     = $this->withSession(['player_id' => $playerId])->get('player');
        $page->assertOK();
        $body = $page->response()->getBody();
        $this->assertStringContainsString('<h2>League Notice</h2>', $body);
        $this->assertStringContainsString('No play on July 4.<br />', $body);
        $this->assertStringContainsString('Bring &lt;ball&gt; markers.', $body);

        // A blank notice clears it.
        $this->asAdmin()->post("admin/leagues/{$id}/notice", ['notice' => '  ']);
        $this->seeInDatabase('leagues', ['id' => $id, 'notice' => null]);
        $this->withSession(['player_id' => $playerId])->get('player')->assertDontSee('League Notice');
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

    public function testImportCourseFromCsv(): void
    {
        $this->asAdmin()->get('admin/courses')->assertSee('admin/courses/import');

        // An exported course comes back as a new course with its holes.
        $courseId = (new CourseModel())->insert(['name' => 'Home Course', 'city' => 'Fort Collins', 'front_slope' => 113, 'active' => 1]);
        for ($n = 1; $n <= 18; $n++) {
            (new HoleModel())->insert(['course_id' => $courseId, 'number' => $n, 'nine' => $n <= 9 ? 'front' : 'back', 'white_par' => 4, 'white_yardage' => 300 + $n, 'handicap' => $n]);
        }
        $csv = CourseCsv::export((new CourseModel())->find($courseId), (new HoleModel())->where('course_id', $courseId)->findAll());

        // The same name is refused, and nothing is added.
        $result = CourseCsv::import($csv);
        $this->assertSame(['A course named "Home Course" already exists. To update it, use Import on that course\'s edit page.'], $result['errors']);
        $this->assertSame(1, (new CourseModel())->countAllResults());

        $result = CourseCsv::import(str_replace('name,"Home Course"', 'name,"Away Course"', $csv));
        $this->assertSame([], $result['errors']);
        $this->assertSame(18, $result['holes']);
        $this->seeInDatabase('courses', ['id' => $result['id'], 'name' => 'Away Course', 'city' => 'Fort Collins', 'front_slope' => 113, 'active' => 1]);
        $this->seeInDatabase('holes', ['course_id' => $result['id'], 'number' => 18, 'nine' => 'back', 'white_par' => 4, 'white_yardage' => 318, 'handicap' => 18]);
        $this->assertSame(18, (new HoleModel())->where('course_id', $result['id'])->countAllResults());

        // No name, or a bad hole, adds nothing.
        $this->assertSame(['The file has no course name (a "name" line in the course details).'],
            CourseCsv::import(preg_replace('/^name,.*\n/m', '', $csv))['errors']);
        $bad = str_replace('name,"Home Course"', 'name,"Third Course"', $csv);
        $this->assertSame(['Line 36: hole number must be 1 to 18.'], CourseCsv::import($bad . "19,300\n")['errors']);
        $this->dontSeeInDatabase('courses', ['name' => 'Third Course']);

        // Without a file the page says so.
        $this->asAdmin()->post('admin/courses/import')->assertSessionHas('error', 'Choose a CSV file to import.');
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

    public function testTeeTimeListsForFrontAndBackWithRoundDateOnOneLinePerTeeTime(): void
    {
        $leagueId = (new LeagueModel())->insert(['name' => 'Monday League']);
        $players  = new PlayerModel();
        $ann      = $players->insert(['first_name' => 'Ann', 'last_name' => 'Ames', 'league_id' => $leagueId]);
        $bob      = $players->insert(['first_name' => 'Bob', 'last_name' => 'Baker', 'league_id' => $leagueId]);
        $cy       = $players->insert(['first_name' => 'Cy', 'last_name' => 'Cole', 'league_id' => $leagueId]);
        $groups   = db_connect()->table('league_groups');
        // Group 1 has players in places 1 and 3, so places 2 and 4 are open.
        $groups->insert(['league_id' => $leagueId, 'group_number' => 1, 'nine' => 'front', 'front_tee_time' => '16:10', 'back_tee_time' => '16:40', 'player1_id' => $ann, 'player3_id' => $bob, 'active' => 1]);
        $groups->insert(['league_id' => $leagueId, 'group_number' => 2, 'nine' => 'front', 'front_tee_time' => '16:00', 'player1_id' => $cy, 'active' => 1]);
        $groups->insert(['league_id' => $leagueId, 'group_number' => 3, 'nine' => 'back', 'front_tee_time' => '15:00', 'back_tee_time' => '16:02', 'active' => 1]);
        $groups->insert(['league_id' => $leagueId, 'group_number' => 4, 'nine' => 'front', 'front_tee_time' => '15:30', 'active' => 0]);

        $courseId = (new CourseModel())->insert(['name' => 'Course']);
        $roundId  = (new RoundModel())->insert(['league_id' => $leagueId, 'course_id' => $courseId, 'round_date' => '2027-05-04', 'name' => 'Round 1']);
        (new RoundModel())->insert(['league_id' => $leagueId, 'course_id' => $courseId, 'round_date' => '2027-05-11', 'name' => 'Round 2']);

        $this->asAdmin()->get("admin/groups?league_id={$leagueId}")->assertSee('admin/groups/tee-sheet');

        // The round dropdown picks the date; the lists show it as 5/4/2027.
        $html = $this->asAdmin()->get("admin/groups/tee-sheet?league_id={$leagueId}&round_id={$roundId}")->response()->getBody();
        $this->assertStringContainsString("<option value=\"{$roundId}\" selected>5/4/2027 Round 1</option>", $html);
        $this->assertStringContainsString('5/11/2027 Round 2</option>', $html);
        $text = trim((string) preg_replace('/\s+/', ' ', preg_replace('/<[^>]+>/', ' ', $html)));

        // One line per tee time, earliest first: time, then four places as
        // "First Last", with "Open" for a place without a player.
        // Group 3 plays the back nine, so its front time (15:00) isn't shown;
        // inactive group 4 isn't listed.
        $this->assertStringContainsString('Monday League - Tee time list - Front - 5/4/2027 16:00 (4:00 PM) Cy Cole, Open, Open, Open 16:10 (4:10 PM) Ann Ames, Open, Bob Baker, Open', $text);
        $this->assertStringContainsString('Monday League - Tee time list - Back - 5/4/2027 16:02 (4:02 PM) Open, Open, Open, Open', $text);
        $this->assertStringContainsString('TeeTimeList-Front-5-4-2027.pdf', $text);
        $this->assertStringNotContainsString('15:00', $text);
        $this->assertStringNotContainsString('15:30', $text);

        // Players not playing in the round are left off and their place is
        // Open: Bob unticked Playing while the round was scheduled; Cy's score
        // record says not playing; Ann's says playing.
        $db = db_connect();
        $db->table('round_playing')->insert(['round_id' => $roundId, 'player_id' => $bob, 'playing' => 0]);
        $db->table('scores')->insertBatch([
            ['league_id' => $leagueId, 'round_id' => $roundId, 'player_id' => $cy, 'playing' => 0],
            ['league_id' => $leagueId, 'round_id' => $roundId, 'player_id' => $ann, 'playing' => 1],
        ]);
        $html = $this->asAdmin()->get("admin/groups/tee-sheet?league_id={$leagueId}&round_id={$roundId}")->response()->getBody();
        $text = trim((string) preg_replace('/\s+/', ' ', preg_replace('/<[^>]+>/', ' ', $html)));
        $this->assertStringContainsString('16:00 (4:00 PM) Open, Open, Open, Open 16:10 (4:10 PM) Ann Ames, Open, Open, Open', $text);
        $this->assertStringNotContainsString('Bob Baker', $text);

        // Another round still lists them.
        $html = $this->asAdmin()->get("admin/groups/tee-sheet?league_id={$leagueId}&round_id=" . ($roundId + 1))->response()->getBody();
        $this->assertStringContainsString('Bob Baker', $html);
        $this->assertStringContainsString('Cy Cole', $html);
    }

    public function testTeeTimeListDownloadsAsAPdfNamedForTheNineAndDate(): void
    {
        $leagueId = (new LeagueModel())->insert(['name' => 'League']);
        $cy       = (new PlayerModel())->insert(['first_name' => 'Cy', 'last_name' => 'Cole', 'league_id' => $leagueId]);
        db_connect()->table('league_groups')->insert(['league_id' => $leagueId, 'group_number' => 1, 'nine' => 'back', 'back_tee_time' => '16:02', 'player1_id' => $cy, 'active' => 1]);
        $courseId = (new CourseModel())->insert(['name' => 'Course']);
        $roundId  = (new RoundModel())->insert(['league_id' => $leagueId, 'course_id' => $courseId, 'round_date' => '2027-05-04']);

        $result = $this->asAdmin()->get("admin/groups/tee-sheet/pdf?league_id={$leagueId}&nine=back&round_id={$roundId}");
        $result->assertOK();
        $result->assertHeader('Content-Type', 'application/pdf');
        $result->assertHeader('Content-Disposition', 'attachment; filename="TeeTimeList-Back-5-4-2027.pdf"');
        // response()->getBody(): the test response's own getBody() returns the
        // body after an HTML parser has read it, which mangles a PDF.
        $this->assertStringStartsWith('%PDF-', $result->response()->getBody());
    }

    public function testTeeTimeLabelAddsAmPm(): void
    {
        helper('display');
        $this->assertSame('16:00 (4:00 PM)', tee_time_label('16:00'));
        $this->assertSame('09:30 (9:30 AM)', tee_time_label('09:30'));
        $this->assertSame('12:05 (12:05 PM)', tee_time_label('12:05'));
        $this->assertSame('00:15 (12:15 AM)', tee_time_label('00:15'));
        $this->assertSame('', tee_time_label(null));
    }

    public function testTeeTimeListFileNamesAreSafe(): void
    {
        $name = static fn (string $nine, string $date) => \App\Controllers\Admin\Groups::teeSheetFilename($nine, $date);

        $this->assertSame('TeeTimeList-Front-5-4-2027.pdf', $name('front', '5/4/2027'));
        $this->assertSame('TeeTimeList-Back-May-4-2027.pdf', $name('back', 'May 4, 2027'));
        $this->assertSame('TeeTimeList-Front-etc-passwd.pdf', $name('front', '../../etc/passwd'));
        $this->assertSame('TeeTimeList-Front-2027-05-04.pdf', $name('front', '2027-05-04'));
        $this->assertSame('TeeTimeList-Back-undated.pdf', $name('back', '///'));
        $this->assertSame('Monday League - Tee time list - Back - 5/4/2027', \App\Controllers\Admin\Groups::teeSheetHeading('Monday League', 'back', '5/4/2027'));

        // Without a round (or with another league's) the next round from
        // today is used, or the last round if none are left.
        $leagueId = (new LeagueModel())->insert(['name' => 'League']);
        $this->asAdmin()->get("admin/groups/tee-sheet?league_id={$leagueId}")->assertSee('This league has no rounds yet.');
        $courseId = (new CourseModel())->insert(['name' => 'Course']);
        $rounds   = new RoundModel();
        $past     = $rounds->insert(['league_id' => $leagueId, 'course_id' => $courseId, 'round_date' => '2000-05-01']);
        $html     = $this->asAdmin()->get("admin/groups/tee-sheet?league_id={$leagueId}&round_id=999")->response()->getBody();
        $this->assertStringContainsString("<option value=\"{$past}\" selected>", $html);
        $next = $rounds->insert(['league_id' => $leagueId, 'course_id' => $courseId, 'round_date' => '2099-05-01']);
        $rounds->insert(['league_id' => $leagueId, 'course_id' => $courseId, 'round_date' => '2099-05-08']);
        $html = $this->asAdmin()->get("admin/groups/tee-sheet?league_id={$leagueId}")->response()->getBody();
        $this->assertStringContainsString("<option value=\"{$next}\" selected>", $html);
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

    public function testTeamNumbersOnScores(): void
    {
        $leagueId = (new LeagueModel())->insert(['name' => 'League']);
        $courseId = (new CourseModel())->insert(['name' => 'Course']);
        $roundId  = (new RoundModel())->insert(['league_id' => $leagueId, 'course_id' => $courseId, 'round_date' => '2027-05-03', 'name' => 'Round 1']);
        $db       = db_connect();
        $db->table('league_groups')->insert(['league_id' => $leagueId, 'group_number' => 2, 'active' => 1]);
        $group2 = $db->insertID();
        $db->table('league_groups')->insert(['league_id' => $leagueId, 'group_number' => 5, 'active' => 1]);
        $group5 = $db->insertID();

        $ids = [];
        foreach (['Ames' => $group2, 'Baker' => $group2, 'Cole' => $group5, 'Dunn' => null] as $last => $groupId) {
            $playerId = (new PlayerModel())->insert(['first_name' => 'Pat', 'last_name' => $last, 'league_id' => $leagueId]);
            $db->table('scores')->insert(['league_id' => $leagueId, 'round_id' => $roundId, 'player_id' => $playerId, 'group_id' => $groupId, 'nine' => 'front']);
            $ids[$last] = $db->insertID();
        }
        $this->seeInDatabase('scores', ['id' => $ids['Ames'], 'team_number' => 0]);

        // The list has a Team box for each score.
        $this->assertStringContainsString("name=\"scores[{$ids['Ames']}][team_number]\"", $this->asAdmin()->get("admin/scores?round_id={$roundId}")->getBody());

        // Teams = groups: Ames and Baker become team 2, Cole team 5 (a team of one,
        // so a warning); Dunn has no group and is left at 0.
        $this->asAdmin()->post('admin/scores/teams-from-groups', ['round_id' => $roundId])
            ->assertSessionHas('message', '3 scores set to a team by group. Check teams: Team 5 has 1 player.');
        $this->seeInDatabase('scores', ['id' => $ids['Ames'], 'team_number' => 2]);
        $this->seeInDatabase('scores', ['id' => $ids['Baker'], 'team_number' => 2]);
        $this->seeInDatabase('scores', ['id' => $ids['Cole'], 'team_number' => 5]);
        $this->seeInDatabase('scores', ['id' => $ids['Dunn'], 'team_number' => 0]);

        // Changing teams from the list: Cole joins team 2 (now 3 players, no
        // warning), Dunn's blank box stays 0; a bad number is refused.
        $this->asAdmin()->post('admin/scores/save', ['scores' => [
            $ids['Cole'] => ['team_number' => '2'],
            $ids['Dunn'] => ['team_number' => ''],
        ], 'return_round_id' => $roundId])->assertSessionHas('message', '2 scores saved.');
        $this->seeInDatabase('scores', ['id' => $ids['Cole'], 'team_number' => 2]);
        $this->seeInDatabase('scores', ['id' => $ids['Dunn'], 'team_number' => 0]);

        $this->asAdmin()->post('admin/scores/save', ['scores' => [$ids['Dunn'] => ['team_number' => '100']]])->assertSessionHas('error');

        // Team 2 with 6 players (more than 5) gets a warning.
        foreach (['Eads', 'Ford', 'Gray'] as $last) {
            $playerId = (new PlayerModel())->insert(['first_name' => 'Pat', 'last_name' => $last, 'league_id' => $leagueId]);
            $db->table('scores')->insert(['league_id' => $leagueId, 'round_id' => $roundId, 'player_id' => $playerId, 'team_number' => 2]);
        }
        $this->asAdmin()->post('admin/scores/save', ['scores' => [$ids['Dunn'] => ['team_number' => '0']]])
            ->assertSessionHas('message', '1 scores saved. Check teams: Team 2 has 6 players.');

        // The list shows teams together, team 0 last.
        $html = $this->asAdmin()->get("admin/scores?round_id={$roundId}")->getBody();
        $this->assertLessThan(strpos($html, 'Dunn, Pat'), strpos($html, 'Cole, Pat'));
    }

    public function testScoresListHasTogglesHoleBoxesAndPlainNames(): void
    {
        $leagueId = (new LeagueModel())->insert(['name' => 'League']);
        $courseId = (new CourseModel())->insert(['name' => 'Course']);
        $roundId  = (new RoundModel())->insert(['league_id' => $leagueId, 'course_id' => $courseId, 'round_date' => '2027-05-03']);
        $playerId = (new PlayerModel())->insert(['first_name' => 'Jo', 'last_name' => 'Golfer', 'member_number' => 321]);
        $db       = db_connect();
        $db->table('league_groups')->insert(['league_id' => $leagueId, 'group_number' => 3, 'active' => 1]);
        $groupId = $db->insertID();
        $row     = ['league_id' => $leagueId, 'player_id' => $playerId, 'round_id' => $roundId, 'group_id' => $groupId, 'nine' => 'back', 'playing' => 1, 'paid' => 0, 'handicap_used' => 6];
        for ($i = 1; $i <= 18; $i++) {
            $row["hole{$i}"] = 0;
        }
        $db->table('scores')->insert($row);
        $scoreId = $db->insertID();

        $html = $this->asAdmin()->get("admin/scores?round_id={$roundId}")->getBody();
        $this->assertStringNotContainsString('Group 3', $html, 'No group column');
        $this->assertStringContainsString('<td>Golfer, Jo</td>', $html, 'Name without the member number');
        $this->assertStringNotContainsString('#321', $html);
        $this->assertStringContainsString("admin/scores/{$scoreId}/toggle/playing", $html);
        $this->assertStringContainsString("admin/scores/{$scoreId}/toggle/paid", $html);
        // Back nine: boxes for holes 10-18 only.
        $this->assertStringContainsString("name=\"scores[{$scoreId}][hole10]\"", $html);
        $this->assertStringContainsString("name=\"scores[{$scoreId}][hole18]\"", $html);
        $this->assertStringNotContainsString("name=\"scores[{$scoreId}][hole9]\"", $html);

        // Toggling Paid switches it and comes back to the same round.
        $this->asAdmin()->post("admin/scores/{$scoreId}/toggle/paid", ['return_query' => "round_id={$roundId}"])
            ->assertRedirectTo("/admin/scores?round_id={$roundId}");
        $this->seeInDatabase('scores', ['id' => $scoreId, 'paid' => 1]);
        $this->asAdmin()->post("admin/scores/{$scoreId}/toggle/playing");
        $this->seeInDatabase('scores', ['id' => $scoreId, 'playing' => 0]);

        // Saving hole scores: gross and net once all nine are in.
        $holes = [];
        foreach (range(10, 17) as $h) {
            $holes["hole{$h}"] = '5';
        }
        $holes['hole18'] = '';
        $this->asAdmin()->post('admin/scores/save', ['scores' => [$scoreId => $holes], 'return_round_id' => $roundId])
            ->assertSessionHas('message', '1 scores saved.');
        $this->seeInDatabase('scores', ['id' => $scoreId, 'hole10' => 5, 'hole18' => 0, 'gross' => null]);

        $holes['hole18'] = '4';
        $this->asAdmin()->post('admin/scores/save', ['scores' => [$scoreId => $holes], 'return_round_id' => $roundId]);
        $this->seeInDatabase('scores', ['id' => $scoreId, 'hole18' => 4, 'gross' => 44, 'net' => 38, 'hole1' => 0]);

        // A bad value is refused for that score.
        $holes['hole12'] = '55';
        $this->asAdmin()->post('admin/scores/save', ['scores' => [$scoreId => $holes]])->assertSessionHas('error');
        $this->seeInDatabase('scores', ['id' => $scoreId, 'hole12' => 5]);
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

    public function testCreateGroupsForPlayersMakesNumberedGroupsAndFillsThem(): void
    {
        $leagueId = (new LeagueModel())->insert(['name' => 'League']);
        $players  = new PlayerModel();
        for ($i = 1; $i <= 9; $i++) {
            $players->insert(['first_name' => "P{$i}", 'last_name' => 'Golfer', 'league_id' => $leagueId]);
        }
        $players->insert(['first_name' => 'Gone', 'last_name' => 'Away', 'league_id' => $leagueId, 'active' => 0]);
        db_connect()->table('league_groups')->insert(['league_id' => $leagueId, 'group_number' => 1, 'active' => 1]);

        // 9 players need 3 groups; 1 exists, so groups 2 and 3 are created,
        // and the 9 players fill them 4, 4, 1.
        $this->asAdmin()->post('admin/groups/generate', ['league_id' => $leagueId])
            ->assertSessionHas('message', "9 registered players need 3 groups of 4. Created 2 (groups 2–3), group 2 on the front nine and group 3 on the back nine. Put 9 players into groups. Set each group's tee times on the group.");
        $this->seeInDatabase('league_groups', ['league_id' => $leagueId, 'group_number' => 2, 'nine' => 'front', 'front_tee_time' => null]);
        $this->seeInDatabase('league_groups', ['league_id' => $leagueId, 'group_number' => 3, 'nine' => 'back', 'back_tee_time' => null]);
        $filled = static fn (int $number) => count(\App\Models\LeagueGroupModel::playerIds(
            db_connect()->table('league_groups')->where(['league_id' => $leagueId, 'group_number' => $number])->get()->getRowArray()
        ));
        $this->assertSame([4, 4, 1], [$filled(1), $filled(2), $filled(3)]);

        $this->asAdmin()->post('admin/groups/generate', ['league_id' => $leagueId])
            ->assertSessionHas('message', "9 registered players need 3 groups of 4. The league already has 3 groups, so none were created. Every player is already in a group. Set each group's tee times on the group.");
    }

    public function testCreatedGroupsAreSplitBetweenFrontAndBack(): void
    {
        $leagueId = (new LeagueModel())->insert(['name' => 'League']);
        for ($i = 1; $i <= 20; $i++) {
            (new PlayerModel())->insert(['first_name' => "P{$i}", 'last_name' => 'Golfer', 'league_id' => $leagueId]);
        }
        // 20 players need 5 groups: 1-3 front (the odd one out on the front), 4-5 back.
        $this->asAdmin()->post('admin/groups/generate', ['league_id' => $leagueId])->assertSessionHas('message',
            "20 registered players need 5 groups of 4. Created 5 (groups 1–5), groups 1–3 on the front nine and groups 4–5 on the back nine. Put 20 players into groups. Set each group's tee times on the group.");
        $nines = array_column(db_connect()->table('league_groups')->where('league_id', $leagueId)->orderBy('group_number')->get()->getResultArray(), 'nine', 'group_number');
        $this->assertSame([1 => 'front', 2 => 'front', 3 => 'front', 4 => 'back', 5 => 'back'], $nines);
    }

    public function testCreateGroupsPutsDesiredGroupMembersTogether(): void
    {
        $leagueId = (new LeagueModel())->insert(['name' => 'League']);
        $db       = db_connect();
        $ids      = [];
        // 12 players, 3 groups. Kim already is in group 1 with 3 places free.
        foreach ([
            ['Ann', 'Ames', ''], ['Bob', 'Baker', ''], ['Cy', 'Cole', ''], ['Di', 'Dunn', ''],
            ['Ed', 'Eads', 'gus  GRAY , Hal Hill'],        // Ed, Gus and Hal together (any capitals and spaces)
            ['Fay', 'Ford', 'Ivy Ives'], ['Gus', 'Gray', ''], ['Hal', 'Hill', ''],
            ['Ivy', 'Ives', 'Joe Jones, Nobody Here'],     // Fay, Ivy and Joe together; one name unknown
            ['Joe', 'Jones', ''], ['Kim', 'King', ''],
            ['Lee', 'Lowe', 'Kim King'],                   // Lee joins Kim's group
        ] as [$first, $last, $desired]) {
            $ids[$first] = (int) (new PlayerModel())->insert(['first_name' => $first, 'last_name' => $last, 'league_id' => $leagueId, 'desired_group_members' => $desired]);
        }
        $db->table('league_groups')->insert(['league_id' => $leagueId, 'group_number' => 1, 'player1_id' => $ids['Kim'], 'active' => 1]);

        $this->asAdmin()->post('admin/groups/generate', ['league_id' => $leagueId])
            ->assertSessionHas('message', '12 registered players need 3 groups of 4. Created 2 (groups 2–3), group 2 on the front nine and group 3 on the back nine. Put 11 players into groups, 7 of them with the group members they asked for.'
                . " Desired group members not found: Nobody Here (named by Ivy Ives). Set each group's tee times on the group.");

        $groupOf = [];
        foreach ($db->table('league_groups')->where('league_id', $leagueId)->get()->getResultArray() as $group) {
            foreach (\App\Models\LeagueGroupModel::playerIds($group) as $playerId) {
                $groupOf[$playerId] = (int) $group['group_number'];
            }
        }
        $this->assertCount(12, $groupOf);
        $this->assertSame($groupOf[$ids['Kim']], $groupOf[$ids['Lee']]);
        $this->assertSame($groupOf[$ids['Ed']], $groupOf[$ids['Gus']]);
        $this->assertSame($groupOf[$ids['Ed']], $groupOf[$ids['Hal']]);
        $this->assertSame($groupOf[$ids['Fay']], $groupOf[$ids['Ivy']]);
        $this->assertSame($groupOf[$ids['Fay']], $groupOf[$ids['Joe']]);
        $this->assertNotSame($groupOf[$ids['Ed']], $groupOf[$ids['Fay']]);
        foreach (array_count_values($groupOf) as $count) {
            $this->assertLessThanOrEqual(4, $count);
        }
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

    public function testActivatingARoundAddsAScoreRecordForEachPlayer(): void
    {
        $leagueId = (new LeagueModel())->insert(['name' => 'League']);
        $otherId  = (new LeagueModel())->insert(['name' => 'Other']);
        $courseId = (new CourseModel())->insert(['name' => 'Course']);
        $roundId  = (new RoundModel())->insert(['league_id' => $leagueId, 'course_id' => $courseId, 'round_date' => '2027-05-03', 'name' => 'Round 1']);
        $players  = new PlayerModel();
        $ann      = (int) $players->insert(['first_name' => 'Ann', 'last_name' => 'Ames', 'league_id' => $leagueId, 'handicap' => 8]);
        $bob      = (int) $players->insert(['first_name' => 'Bob', 'last_name' => 'Baker', 'league_id' => $leagueId, 'handicap' => 12]);
        $cy       = (int) $players->insert(['first_name' => 'Cy', 'last_name' => 'Cole', 'league_id' => $leagueId, 'handicap' => 3]);
        // Not added: inactive, another league's player, an admin-only account.
        $players->insert(['first_name' => 'Gone', 'last_name' => 'Away', 'league_id' => $leagueId, 'active' => 0]);
        $players->insert(['first_name' => 'Ed', 'last_name' => 'Else', 'league_id' => $otherId]);
        $players->insert(['first_name' => 'Ad', 'last_name' => 'Min', 'league_id' => $leagueId, 'is_player' => 0]);
        $db = db_connect();
        $db->table('league_groups')->insert(['league_id' => $leagueId, 'group_number' => 1, 'nine' => 'back', 'player1_id' => $ann, 'active' => 1]);
        $groupId = $db->insertID();
        // Cy already has a score for this round, which must be left alone.
        $db->table('scores')->insert(['league_id' => $leagueId, 'round_id' => $roundId, 'player_id' => $cy, 'hole1' => 5, 'handicap_used' => 99]);

        $this->asAdmin()->post("admin/rounds/{$roundId}/status", ['value' => 'active'])
            ->assertSessionHas('message', '2027-05-03 Round 1 is now Active (live scoring open). 2 score records added for players. 1 players already had one and were left alone.');

        $this->assertSame(3, $db->table('scores')->where('round_id', $roundId)->countAllResults());
        $annScore = $db->table('scores')->where(['round_id' => $roundId, 'player_id' => $ann])->get()->getRowArray();
        $this->assertSame(8, (int) $annScore['handicap_used']);
        $this->assertSame($groupId, (int) $annScore['group_id']);
        $this->assertSame('back', $annScore['nine']);
        for ($i = 1; $i <= 18; $i++) {
            $this->assertSame(0, (int) $annScore["hole{$i}"], "hole {$i}");
        }
        $this->seeInDatabase('scores', ['round_id' => $roundId, 'player_id' => $bob, 'handicap_used' => 12, 'group_id' => null, 'hole18' => 0, 'playing' => 1, 'paid' => 0]);
        $this->seeInDatabase('scores', ['round_id' => $roundId, 'player_id' => $cy, 'hole1' => 5, 'handicap_used' => 99]);

        // Activating again adds nothing.
        $this->asAdmin()->post("admin/rounds/{$roundId}/status", ['value' => 'active'])
            ->assertSessionHas('message', '2027-05-03 Round 1 is now Active (live scoring open). 0 score records added for players. 3 players already had one and were left alone.');
        $this->assertSame(3, $db->table('scores')->where('round_id', $roundId)->countAllResults());

        // A score created this way can be edited and saved: 0 means not entered yet.
        $scoreId = (int) $annScore['id'];
        $post    = ['round_id' => $roundId, 'player_id' => $ann, 'nine' => 'back', 'playing' => '1', 'handicap_used' => '8'];
        for ($i = 1; $i <= 18; $i++) {
            $post["hole{$i}"] = '0';
        }
        $post['hole10'] = '5';
        $this->asAdmin()->post("admin/scores/{$scoreId}", $post)->assertRedirectTo('/admin/scores');
        $this->seeInDatabase('scores', ['id' => $scoreId, 'hole10' => 5, 'gross' => null]);
    }

    public function testActivatingARoundFromItsEditPageAlsoAddsScores(): void
    {
        $leagueId = (new LeagueModel())->insert(['name' => 'League']);
        $courseId = (new CourseModel())->insert(['name' => 'Course']);
        $roundId  = (new RoundModel())->insert(['league_id' => $leagueId, 'course_id' => $courseId, 'round_date' => '2027-05-03']);
        (new PlayerModel())->insert(['first_name' => 'Ann', 'last_name' => 'Ames', 'league_id' => $leagueId, 'handicap' => 8]);

        $this->asAdmin()->post("admin/rounds/{$roundId}", [
            'league_id' => $leagueId, 'course_id' => $courseId, 'round_date' => '2027-05-03', 'status' => 'active', 'entry_fee_cents' => '10.00',
        ])->assertSessionHas('message', 'Round saved. 1 score records added for players.');
        $this->assertSame(1, db_connect()->table('scores')->where('round_id', $roundId)->countAllResults());
    }

    public function testRoundStatusCanBeChangedFromTheList(): void
    {
        $leagueId = (new LeagueModel())->insert(['name' => 'League']);
        $courseId = (new CourseModel())->insert(['name' => 'Course']);
        $roundId  = (new RoundModel())->insert(['league_id' => $leagueId, 'course_id' => $courseId, 'round_date' => '2027-05-03', 'name' => 'Round 1']);

        $html = $this->asAdmin()->get("admin/rounds?league_id={$leagueId}")->getBody();
        $this->assertMatchesRegularExpression("#action=\"[^\"]*admin/rounds/{$roundId}/status\"[^>]*>.*?<select name=\"value\"[^>]*onchange=\"this.form.submit\(\)\"#s", $html);
        $this->assertStringContainsString('<option value="scheduled" selected>Scheduled</option>', $html);

        $this->asAdmin()->post("admin/rounds/{$roundId}/status", ['value' => 'active'])
            ->assertSessionHas('message', '2027-05-03 Round 1 is now Active (live scoring open). 0 score records added for players.');
        $this->seeInDatabase('rounds', ['id' => $roundId, 'status' => 'active']);

        $this->asAdmin()->post("admin/rounds/{$roundId}/status", ['value' => 'finished'])->assertSessionHas('error');
        $this->seeInDatabase('rounds', ['id' => $roundId, 'status' => 'active']);

        // The round edit page no longer offers to add all groups.
        $this->asAdmin()->get("admin/rounds/{$roundId}/edit")->assertDontSee('Add all groups');
    }

    public function testRoundGameDropdownAndMostVotedGame(): void
    {
        $leagueId = (new LeagueModel())->insert(['name' => 'League']);
        $courseId = (new CourseModel())->insert(['name' => 'Course']);
        $roundId  = (new RoundModel())->insert(['league_id' => $leagueId, 'course_id' => $courseId, 'round_date' => '2027-05-03', 'name' => 'Round 1']);
        $db       = db_connect();
        $games    = [];
        foreach (['Best Ball', 'Skins', 'Scramble'] as $name) {
            $db->table('games')->insert(['league_id' => $leagueId, 'name' => $name]);
            $games[$name] = (int) $db->insertID();
        }

        $html = $this->asAdmin()->get("admin/rounds?league_id={$leagueId}")->response()->getBody();
        $this->assertMatchesRegularExpression("#action=\"[^\"]*admin/rounds/{$roundId}/game\"[^>]*>.*?<select name=\"value\"[^>]*onchange=\"this.form.submit\(\)\"#s", $html);
        $this->assertStringContainsString('<option value="" selected>—</option>', $html);
        $this->assertStringContainsString('<th>Game votes</th>', $html);

        $this->asAdmin()->post("admin/rounds/{$roundId}/game", ['value' => (string) $games['Skins']])
            ->assertSessionHas('message', '2027-05-03 Round 1 game is now Skins.');
        $this->seeInDatabase('rounds', ['id' => $roundId, 'game_id' => $games['Skins']]);
        $this->asAdmin()->post("admin/rounds/{$roundId}/game", ['value' => '999'])->assertSessionHas('error');
        $this->asAdmin()->post("admin/rounds/{$roundId}/game", ['value' => ''])
            ->assertSessionHas('message', '2027-05-03 Round 1 has no game set.');
        $this->seeInDatabase('rounds', ['id' => $roundId, 'game_id' => null]);

        // The most voted game shows with its count; a tie shows both.
        $db->table('game_votes')->insertBatch([
            ['round_id' => $roundId, 'game_id' => $games['Skins'], 'count' => 3],
            ['round_id' => $roundId, 'game_id' => $games['Best Ball'], 'count' => 1],
        ]);
        $this->assertStringContainsString('<td>Skins (3)</td>', $this->asAdmin()->get("admin/rounds?league_id={$leagueId}")->response()->getBody());
        $db->table('game_votes')->insert(['round_id' => $roundId, 'game_id' => $games['Scramble'], 'count' => 3]);
        $this->assertStringContainsString('<td>Scramble / Skins (3)</td>', $this->asAdmin()->get("admin/rounds?league_id={$leagueId}")->response()->getBody());
    }

    public function testTeamGameFlagAndScoresRoundChoice(): void
    {
        $leagueId = (new LeagueModel())->insert(['name' => 'League', 'active' => 1]);
        $courseId = (new CourseModel())->insert(['name' => 'Course']);

        // A game can be marked as a team game; the games list shows it after Name.
        $this->asAdmin()->post('admin/games', ['league_id' => $leagueId, 'name' => 'Best Ball', 'team_game' => '1', 'active' => '1'])
            ->assertRedirectTo('/admin/games');
        $this->seeInDatabase('games', ['name' => 'Best Ball', 'team_game' => 1]);
        $this->asAdmin()->post('admin/games', ['league_id' => $leagueId, 'name' => 'Skins', 'active' => '1']);
        $this->seeInDatabase('games', ['name' => 'Skins', 'team_game' => 0]);
        $html = $this->asAdmin()->get("admin/games?league_id={$leagueId}")->response()->getBody();
        $this->assertMatchesRegularExpression('#<th>Name</th>\s*<th>Team</th>#', $html);
        $bestBall = (int) db_connect()->table('games')->where('name', 'Best Ball')->get()->getRow()->id;

        $rounds = new RoundModel();
        $past   = $rounds->insert(['league_id' => $leagueId, 'course_id' => $courseId, 'round_date' => '2000-05-01', 'name' => 'Round 1', 'game_id' => $bestBall, 'status' => 'scored']);
        $next   = $rounds->insert(['league_id' => $leagueId, 'course_id' => $courseId, 'round_date' => '2099-05-08', 'name' => 'Round 2']);
        $later  = $rounds->insert(['league_id' => $leagueId, 'course_id' => $courseId, 'round_date' => '2099-05-15', 'name' => 'Round 3']);
        $ann    = (new PlayerModel())->insert(['first_name' => 'Ann', 'last_name' => 'Ames', 'league_id' => $leagueId]);
        $bob    = (new PlayerModel())->insert(['first_name' => 'Bob', 'last_name' => 'Baker', 'league_id' => $leagueId]);
        db_connect()->table('scores')->insertBatch([
            ['league_id' => $leagueId, 'round_id' => $past, 'player_id' => $ann],
            ['league_id' => $leagueId, 'round_id' => $next, 'player_id' => $bob],
        ]);

        // Rounds are listed as date - name - game - team, with no "All", and
        // without a choice the next round from today is shown.
        $html = $this->asAdmin()->get('admin/scores')->response()->getBody();
        $this->assertStringContainsString('2000-05-01 - Round 1 - Best Ball - Team: Yes</option>', $html);
        $this->assertStringContainsString("<option value=\"{$next}\" selected>2099-05-08 - Round 2 - No game set - Team: —</option>", $html);
        $this->assertStringNotContainsString('<option value="">All</option>', $html);
        $this->assertStringContainsString('Baker, Bob', $html);
        $this->assertStringNotContainsString('Ames, Ann', $html);
        $this->assertStringNotContainsString('<th>Round</th>', $html);

        // An active round is shown first when there is one.
        $rounds->update($past, ['status' => 'active']);
        $html = $this->asAdmin()->get('admin/scores')->response()->getBody();
        $this->assertStringContainsString('Ames, Ann', $html);
        $this->assertStringNotContainsString('Baker, Bob', $html);

        // A chosen round is shown.
        $html = $this->asAdmin()->get("admin/scores?round_id={$later}")->response()->getBody();
        $this->assertStringNotContainsString('Ames, Ann', $html);
        $this->assertStringNotContainsString('Baker, Bob', $html);
    }

    public function testLinkTeamsFromChosenTeamMembers(): void
    {
        $leagueId = (new LeagueModel())->insert(['name' => 'League', 'active' => 1]);
        $courseId = (new CourseModel())->insert(['name' => 'Course']);
        $db       = db_connect();
        $db->table('games')->insert(['league_id' => $leagueId, 'name' => 'Skins', 'team_game' => 0]);
        $gameId  = (int) $db->insertID();
        $roundId = (new RoundModel())->insert(['league_id' => $leagueId, 'course_id' => $courseId, 'round_date' => '2027-06-07', 'game_id' => $gameId, 'status' => 'active']);

        // Not a team game: no button.
        $html = $this->asAdmin()->get("admin/scores?round_id={$roundId}")->response()->getBody();
        $this->assertStringNotContainsString('>Link Teams</button>', $html);
        $this->assertStringNotContainsString('>Teams = groups</button>', $html);
        $this->assertStringNotContainsString('>Map Team Scores</button>', $html);
        $db->table('games')->where('id', $gameId)->update(['team_game' => 1]);
        $ids = [];
        foreach (['Ames', 'Baker', 'Cole', 'Dunn', 'Eads', 'Ford', 'Gray', 'Kim', 'Lee', 'Mae'] as $last) {
            $ids[$last] = (int) (new PlayerModel())->insert(['first_name' => 'Pat', 'last_name' => $last, 'league_id' => $leagueId]);
        }
        $outside = (int) (new PlayerModel())->insert(['first_name' => 'Hal', 'last_name' => 'Hill', 'league_id' => $leagueId]);
        $score = static fn (string $last, array $members = [], int $team = 0) => [
            'league_id' => $leagueId, 'round_id' => $roundId, 'player_id' => $ids[$last], 'team_number' => $team,
            'team_member1_id' => $members[0] ?? null, 'team_member2_id' => $members[1] ?? null,
            'team_member3_id' => $members[2] ?? null, 'team_member4_id' => $members[3] ?? null,
            'team_member5_id' => $members[4] ?? null,
        ];
        $db->table('scores')->insertBatch([
            // Ames named Baker; Cole named Baker too, so all three are one team.
            $score('Ames', [$ids['Ames'], $ids['Baker']]),
            $score('Baker'),
            $score('Cole', [$ids['Baker']]),
            // Dunn and Eads named each other (Eads in the fifth box).
            $score('Dunn', [null, null, null, null, $ids['Eads']]),
            $score('Eads', [$ids['Dunn'], $ids['Eads']]),
            // Ford named only Hill, who isn't in this round; Gray named nobody.
            $score('Ford', [$outside]),
            $score('Gray'),
            // Kim was already put on team 1 by hand, and Lee on team 3. Gray
            // isn't linked to Kim even though Lee named Kim.
            $score('Kim', [], 1),
            $score('Lee', [$ids['Kim']], 3),
            // Mae named Kim, who is already on a team.
            $score('Mae', [$ids['Kim']]),
        ]);
        $html = $this->asAdmin()->get("admin/scores?round_id={$roundId}")->response()->getBody();
        $this->assertStringContainsString('Link Teams', $html);
        $this->assertLessThan(strpos($html, '<table'), strpos($html, 'Link Teams'));

        $this->asAdmin()->post('admin/scores/link-teams', ['round_id' => $roundId])
            ->assertSessionHas('message', 'Linked 2 new teams (5 players) from the team members players chose. 2 players were already on a team and kept it.'
                . ' Not linked, because they are already on a team: Mae, Pat named Kim, Pat.'
                . ' Not linked, because they have no score in this round: Ford, Pat named Hill, Hal.'
                . ' Check teams: Team 1 has 1 player; Team 3 has 1 player.');
        $team = static fn (string $last) => (int) $db->table('scores')->where('player_id', $ids[$last])->get()->getRow()->team_number;
        // New teams skip the numbers 1 and 3 already in use.
        $this->assertSame([2, 2, 2, 4, 4, 0, 0, 1, 3, 0], array_map($team, ['Ames', 'Baker', 'Cole', 'Dunn', 'Eads', 'Ford', 'Gray', 'Kim', 'Lee', 'Mae']));

        // Everyone on a new team has the team's players as their team members
        // (by last name); players on preset teams keep what they chose.
        $this->seeInDatabase('scores', ['player_id' => $ids['Cole'], 'team_member1_id' => $ids['Ames'], 'team_member2_id' => $ids['Baker'], 'team_member3_id' => $ids['Cole'], 'team_member4_id' => null]);
        $this->seeInDatabase('scores', ['player_id' => $ids['Baker'], 'team_member1_id' => $ids['Ames'], 'team_member3_id' => $ids['Cole']]);
        $this->seeInDatabase('scores', ['player_id' => $ids['Eads'], 'team_member1_id' => $ids['Dunn'], 'team_member2_id' => $ids['Eads'], 'team_member5_id' => null]);
        $this->seeInDatabase('scores', ['player_id' => $ids['Lee'], 'team_member1_id' => $ids['Kim'], 'team_member2_id' => null]);
        $this->seeInDatabase('scores', ['player_id' => $ids['Gray'], 'team_member1_id' => null]);

        // Running it again changes nothing: everyone linked is now on a team.
        $this->asAdmin()->post('admin/scores/link-teams', ['round_id' => $roundId]);
        $this->assertSame([2, 2, 2, 4, 4, 0, 0, 1, 3, 0], array_map($team, ['Ames', 'Baker', 'Cole', 'Dunn', 'Eads', 'Ford', 'Gray', 'Kim', 'Lee', 'Mae']));

        // Teams = groups sits right after Link Teams.
        $html = $this->asAdmin()->get("admin/scores?round_id={$roundId}")->response()->getBody();
        $this->assertLessThan(strpos($html, '>Teams = groups</button>'), strpos($html, '>Link Teams</button>'));
        $this->assertLessThan(strpos($html, '>Map Team Scores</button>'), strpos($html, '>Teams = groups</button>'));

        $this->asAdmin()->post('admin/scores/link-teams', ['round_id' => 999])->assertSessionHas('error', 'Choose a round first.');
    }

    public function testMapTeamScoresGivesEachMemberTheTeamsBestHole(): void
    {
        $leagueId = (new LeagueModel())->insert(['name' => 'League', 'active' => 1]);
        $courseId = (new CourseModel())->insert(['name' => 'Course']);
        $db       = db_connect();
        $db->table('games')->insert(['league_id' => $leagueId, 'name' => 'Best Ball', 'team_game' => 1]);
        $roundId = (new RoundModel())->insert(['league_id' => $leagueId, 'course_id' => $courseId, 'round_date' => '2027-06-07', 'game_id' => $db->insertID(), 'status' => 'active']);

        $ids   = [];
        $score = static function (string $last, int $team, array $holes, int $handicap = 0) use (&$ids, $db, $leagueId, $roundId): void {
            $ids[$last] = (int) (new PlayerModel())->insert(['first_name' => 'Pat', 'last_name' => $last, 'league_id' => $leagueId]);
            $row = ['league_id' => $leagueId, 'round_id' => $roundId, 'player_id' => $ids[$last], 'nine' => 'front', 'team_number' => $team, 'handicap_used' => $handicap];
            for ($h = 1; $h <= 18; $h++) {
                $row["hole{$h}"] = $holes[$h - 1] ?? 0;
            }
            $db->table('scores')->insert($row);
        };
        // Team 1: hole 1 best is 3, hole 2 best is 4 (0 = not entered is ignored), hole 3 nobody yet.
        $score('Ames', 1, [5, 0, 0, 4, 4, 4, 4, 4, 4], 2);
        $score('Baker', 1, [3, 6, 0, 5, 5, 5, 5, 5, 5]);
        $score('Cole', 1, [4, 4, 0, 3, 3, 3, 3, 3, 3]);
        // Team 2 hasn't entered anything; Dunn (team 0) plays alone.
        $score('Eads', 2, []);
        $score('Ford', 2, []);
        $score('Dunn', 0, [9, 9, 9, 9, 9, 9, 9, 9, 9]);

        $html = $this->asAdmin()->get("admin/scores?round_id={$roundId}")->response()->getBody();
        $this->assertLessThan(strpos($html, 'Map Team Scores'), strpos($html, 'Link Teams'));

        $this->asAdmin()->post('admin/scores/map-team-scores', ['round_id' => $roundId])
            ->assertSessionHas('message', "Mapped team scores for 1 team: each member now has the team's lowest score on every hole entered (16 hole scores changed).");
        $holes = static fn (string $last) => array_map('intval', array_values(array_intersect_key(
            $db->table('scores')->where('player_id', $ids[$last])->get()->getRowArray(),
            array_flip(array_map(static fn ($h) => "hole{$h}", range(1, 9)))
        )));
        foreach (['Ames', 'Baker', 'Cole'] as $last) {
            $this->assertSame([3, 4, 0, 3, 3, 3, 3, 3, 3], $holes($last), $last);
        }
        $this->assertSame([0, 0, 0, 0, 0, 0, 0, 0, 0], $holes('Eads'));
        $this->assertSame([9, 9, 9, 9, 9, 9, 9, 9, 9], $holes('Dunn'));
        // Hole 3 is still blank, so there's no total yet.
        $this->seeInDatabase('scores', ['player_id' => $ids['Ames'], 'gross' => null]);

        // Once hole 3 is in, mapping again totals gross and net.
        $db->table('scores')->where('player_id', $ids['Cole'])->update(['hole3' => 5]);
        $this->asAdmin()->post('admin/scores/map-team-scores', ['round_id' => $roundId])->assertSessionHas('message');
        $this->seeInDatabase('scores', ['player_id' => $ids['Ames'], 'hole3' => 5, 'gross' => 30, 'net' => 28]);
        $this->seeInDatabase('scores', ['player_id' => $ids['Baker'], 'gross' => 30, 'net' => 30]);

        // No team scores at all.
        $db->table('scores')->where('team_number >', 0)->update(array_fill_keys(array_map(static fn ($h) => "hole{$h}", range(1, 18)), 0));
        $this->asAdmin()->post('admin/scores/map-team-scores', ['round_id' => $roundId])
            ->assertSessionHas('message', 'No team scores to map: no team in this round has entered a hole score yet.');
    }

    public function testTeamButtonsSaveTypedScoresFirst(): void
    {
        $leagueId = (new LeagueModel())->insert(['name' => 'League', 'active' => 1]);
        $courseId = (new CourseModel())->insert(['name' => 'Course']);
        $db       = db_connect();
        $db->table('games')->insert(['league_id' => $leagueId, 'name' => 'Scramble', 'team_game' => 1]);
        $roundId = (new RoundModel())->insert(['league_id' => $leagueId, 'course_id' => $courseId, 'round_date' => '2027-06-14', 'game_id' => $db->insertID(), 'status' => 'active']);
        $ids     = [];
        foreach (['Ames', 'Baker', 'Cole'] as $last) {
            $playerId = (new PlayerModel())->insert(['first_name' => 'Pat', 'last_name' => $last, 'league_id' => $leagueId]);
            $db->table('scores')->insert(['league_id' => $leagueId, 'round_id' => $roundId, 'player_id' => $playerId, 'nine' => 'front', 'team_number' => 2]);
            $ids[$last] = (int) $db->insertID();
        }

        // The buttons send the list's typed boxes with them.
        $html = $this->asAdmin()->get("admin/scores?round_id={$roundId}")->response()->getBody();
        $this->assertStringContainsString('form="hole-scores" name="then" value="map_team_scores"', $html);

        // Scores typed for one member, not yet saved, then Map Team Scores:
        // they're saved and copied to the whole team.
        $typed = array_combine(array_map(static fn ($h) => "hole{$h}", range(1, 9)), ['4', '5', '3', '4', '4', '5', '3', '4', '4']);
        $this->asAdmin()->post('admin/scores/save', [
            'scores'          => [$ids['Ames'] => ['team_number' => '2'] + $typed, $ids['Baker'] => ['team_number' => '2'], $ids['Cole'] => ['team_number' => '2']],
            'return_round_id' => $roundId,
            'then'            => 'map_team_scores',
        ])->assertSessionHas('message', "3 scores saved. Mapped team scores for 1 team: each member now has the team's lowest score on every hole entered (18 hole scores changed).");
        foreach ($ids as $id) {
            $this->seeInDatabase('scores', ['id' => $id, 'hole1' => 4, 'hole9' => 4, 'gross' => 36]);
        }

        // A typing mistake stops the button's job.
        $this->asAdmin()->post('admin/scores/save', [
            'scores' => [$ids['Ames'] => ['hole1' => '40']], 'return_round_id' => $roundId, 'then' => 'link_teams',
        ])->assertSessionHas('error', 'Hole 1 must be a number from 0 to 20 (score ' . $ids['Ames'] . '). 0 other scores were saved. Link Teams was not run; fix the scores above and try again.');
    }

    public function testTeamsFromGroupsKeepsLinkedTeamsAndSkipsTheirNumbers(): void
    {
        $leagueId = (new LeagueModel())->insert(['name' => 'League']);
        $courseId = (new CourseModel())->insert(['name' => 'Course']);
        $roundId  = (new RoundModel())->insert(['league_id' => $leagueId, 'course_id' => $courseId, 'round_date' => '2027-05-03']);
        $db       = db_connect();
        $groups   = [];
        foreach ([1, 2, 3] as $number) {
            $db->table('league_groups')->insert(['league_id' => $leagueId, 'group_number' => $number, 'active' => 1]);
            $groups[$number] = (int) $db->insertID();
        }
        $ids = [];
        // Ames and Baker were already linked as teams 1 and 2. The others aren't on a team.
        foreach ([['Ames', 1, 1], ['Baker', 1, 2], ['Cole', 1, 0], ['Dunn', 2, 0], ['Eads', 2, 0], ['Ford', 3, 0], ['Gray', null, 0]] as [$last, $group, $team]) {
            $playerId = (new PlayerModel())->insert(['first_name' => 'Pat', 'last_name' => $last, 'league_id' => $leagueId]);
            $db->table('scores')->insert(['league_id' => $leagueId, 'round_id' => $roundId, 'player_id' => $playerId,
                'group_id' => $group === null ? null : $groups[$group], 'team_number' => $team]);
            $ids[$last] = (int) $db->insertID();
        }

        $this->asAdmin()->post('admin/scores/teams-from-groups', ['round_id' => $roundId])
            ->assertSessionHas('message', '4 scores set to a team by group. Numbers already in use were skipped, so group 1 is team 3, group 2 is team 4, group 3 is team 5.'
                . ' 2 players were already on a team and kept it. Check teams: Team 1 has 1 player; Team 2 has 1 player; Team 3 has 1 player; Team 5 has 1 player.');
        $team = static fn (string $last) => (int) $db->table('scores')->where('id', $ids[$last])->get()->getRow()->team_number;
        $this->assertSame([1, 2, 3, 4, 4, 5, 0], array_map($team, ['Ames', 'Baker', 'Cole', 'Dunn', 'Eads', 'Ford', 'Gray']));

        // The new teams' members are filled in; Ames's preset team isn't touched.
        $player = static fn (string $last) => (int) $db->table('scores')->where('id', $ids[$last])->get()->getRow()->player_id;
        $this->seeInDatabase('scores', ['id' => $ids['Dunn'], 'team_member1_id' => $player('Dunn'), 'team_member2_id' => $player('Eads'), 'team_member3_id' => null]);
        $this->seeInDatabase('scores', ['id' => $ids['Cole'], 'team_member1_id' => $player('Cole'), 'team_member2_id' => null]);
        $this->seeInDatabase('scores', ['id' => $ids['Ames'], 'team_member1_id' => null]);
    }

    public function testAdminScoreEditPageHasTeamMembers(): void
    {
        $leagueId = (new LeagueModel())->insert(['name' => 'League', 'active' => 1]);
        $courseId = (new CourseModel())->insert(['name' => 'Course']);
        $roundId  = (new RoundModel())->insert(['league_id' => $leagueId, 'course_id' => $courseId, 'round_date' => '2027-06-07']);
        $ann      = (int) (new PlayerModel())->insert(['first_name' => 'Ann', 'last_name' => 'Ames', 'league_id' => $leagueId]);
        $bob      = (int) (new PlayerModel())->insert(['first_name' => 'Bob', 'last_name' => 'Baker', 'league_id' => $leagueId]);
        db_connect()->table('scores')->insert(['league_id' => $leagueId, 'round_id' => $roundId, 'player_id' => $ann, 'team_member2_id' => $bob]);
        $scoreId = (int) db_connect()->insertID();

        $html = $this->asAdmin()->get("admin/scores/{$scoreId}/edit")->response()->getBody();
        $this->assertSame(5, substr_count($html, 'name="team_member'));
        $this->assertMatchesRegularExpression('#name="team_member2_id".*?<option value="' . $bob . '" selected>Bob Baker</option>#s', $html);

        $form = ['round_id' => $roundId, 'player_id' => $ann, 'playing' => '1', 'team_member1_id' => (string) $ann, 'team_member2_id' => (string) $bob, 'team_member3_id' => ''];
        $this->asAdmin()->post("admin/scores/{$scoreId}", $form)->assertRedirectTo('/admin/scores');
        $this->seeInDatabase('scores', ['id' => $scoreId, 'team_member1_id' => $ann, 'team_member2_id' => $bob, 'team_member3_id' => null]);

        $this->asAdmin()->post("admin/scores/{$scoreId}", ['team_member3_id' => (string) $bob] + $form)
            ->assertSee('A player is chosen more than once as a team member.');
        $this->seeInDatabase('scores', ['id' => $scoreId, 'team_member3_id' => null]);
    }

    public function testAddTestPlayersHaveRandomNamesSexAndTees(): void
    {
        $leagueId = (new LeagueModel())->insert(['name' => 'League', 'active' => 1]);
        (new PlayerModel())->insert(['first_name' => 'Old', 'last_name' => 'Tester', 'login' => 'player7', 'league_id' => $leagueId]);

        // Someone already has the login a test player named Jim Jones would get.
        (new PlayerModel())->insert(['first_name' => 'Jill', 'last_name' => 'Jonas', 'login' => 'jjones', 'league_id' => $leagueId]);

        $this->asAdmin()->post('admin/players/add-test', ['league_id' => $leagueId, 'count' => 40])->assertSessionHas('message');

        $added = (new PlayerModel())->where('league_id', $leagueId)->whereNotIn('login', ['player7', 'jjones'])->findAll();
        $this->assertCount(40, $added);
        $names = [];
        foreach ($added as $player) {
            $this->assertContains($player['gender'], ['M', 'F']);
            $this->assertSame($player['gender'] === 'F' ? 'red' : 'white', $player['tee'], $player['first_name']);
            $this->assertSame(8, (int) $player['handicap']);
            $this->assertMatchesRegularExpression('/^[A-Z][a-z]+$/', $player['first_name']);
            $this->assertMatchesRegularExpression('/^[A-Z][a-z]+$/', $player['last_name']);
            $names[] = "{$player['first_name']} {$player['last_name']}";

            // Login: first initial and last name (a number added if taken);
            // password: the login plus 12345, with no change needed.
            $base = strtolower($player['first_name'][0] . $player['last_name']);
            $this->assertMatchesRegularExpression('/^' . $base . '\\d*$/', $player['login']);
            $this->assertNotSame('jjones', $player['login']);
            $this->assertSame(0, (int) $player['must_change_password']);
        }
        $this->assertSame($names, array_unique($names), 'Each test player has a different name.');
        // With 40 players, both men and women turn up (1 in 2^39 chance of not).
        $this->assertCount(2, array_unique(array_column($added, 'gender')));
        $login = $added[0]['login'];
        $this->assertNotNull((new PlayerModel())->verifyLogin($login, $login . '12345'));
        $this->post('login', ['login' => $login, 'password' => $login . '12345'])->assertRedirectTo('/player');
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

        $result = $this->asAdmin()->post('admin/players/add-test', ['league_id' => $leagueId, 'count' => '3']);
        $result->assertSessionHas('message');
        $this->assertMatchesRegularExpression('/^3 test players added \([a-z]+, [a-z]+, [a-z]+\)\. Each logs in with their login/', session('message'));
        $this->assertSame(3, $players->where('league_id', $leagueId)->where('is_player', 1)->where('flight', 2)->where('handicap', 8)->countAllResults());

        $this->asAdmin()->post('admin/players/add-test', ['league_id' => $leagueId, 'count' => '0'])->assertSessionHas('error');
        $this->asAdmin()->post('admin/players/add-test', ['league_id' => $leagueId, 'count' => '201'])->assertSessionHas('error');

        // 200 at once is allowed and quick.
        $start = microtime(true);
        $this->asAdmin()->post('admin/players/add-test', ['league_id' => $leagueId, 'count' => '200'])->assertSessionHas('message');
        $this->assertLessThan(15, microtime(true) - $start, 'Adding 200 test players should be well within the 30s request limit');
        $this->assertSame(203, $players->where('league_id', $leagueId)->countAllResults());
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

        // A password set by an admin is temporary, so the player chooses
        // their own first.
        $result = $this->post('login', ['login' => 'holbrm', 'password' => 'fairway-27']);
        $result->assertRedirectTo('/password');
        $result->assertSessionHas('player_id', (int) $player['id']);
        $result->assertSessionMissing('admin_id');

        $this->assertNotNull((new PlayerModel())->find($player['id'])['last_login_at'], 'Player login should record the login date.');
    }

    public function testFirstLoginMakesThePlayerChooseTheirOwnPassword(): void
    {
        $players  = new PlayerModel();
        $playerId = (int) $players->insert(['first_name' => 'Mark', 'last_name' => 'Holbrook', 'is_player' => 1]);
        $this->seeInDatabase('players', ['id' => $playerId, 'must_change_password' => 1]);

        // Logging in with the starting password (last name) goes to the
        // password page, and other player pages send them back there.
        $this->post('login', ['login' => 'holbrm', 'password' => 'holbrook'])->assertRedirectTo('/password');
        $asPlayer = fn () => $this->withSession(['player_id' => $playerId, 'player_name' => 'Mark Holbrook']);
        $asPlayer()->get('player')->assertRedirectTo('/password');
        $page = $asPlayer()->get('password');
        $page->assertOK();
        $page->assertSee('Choose your password');
        $page->assertDontSee('Current password');

        // Too short, not matching, or the last name are refused.
        $asPlayer()->post('password', ['password' => 'short', 'password_confirm' => 'short'])->assertSee('at least 8 characters');
        $asPlayer()->post('password', ['password' => 'fairway-27', 'password_confirm' => 'fairway-28'])->assertSee('passwords don');
        $asPlayer()->post('password', ['password' => 'HOLBROOK', 'password_confirm' => 'HOLBROOK'])->assertSee('your last name or login');
        $this->assertTrue($players->mustChangePassword($playerId));

        $asPlayer()->post('password', ['password' => 'fairway-27', 'password_confirm' => 'fairway-27'])
            ->assertRedirectTo('/player');
        $this->assertFalse($players->mustChangePassword($playerId));
        $this->assertNotNull($players->verifyLogin('holbrm', 'fairway-27'));
        $asPlayer()->get('player')->assertOK();
        $this->post('login', ['login' => 'holbrm', 'password' => 'fairway-27'])->assertRedirectTo('/player');

        // Changing it later asks for the current password.
        $page = $asPlayer()->get('password');
        $page->assertSee('Current password');
        $asPlayer()->post('password', ['current_password' => 'wrong', 'password' => 'bunker-99x', 'password_confirm' => 'bunker-99x'])
            ->assertSee('current password is not correct');
        $asPlayer()->post('password', ['current_password' => 'fairway-27', 'password' => 'bunker-99x', 'password_confirm' => 'bunker-99x'])
            ->assertRedirectTo('/player');
        $this->assertNotNull($players->verifyLogin('holbrm', 'bunker-99x'));

        // Not logged in: the page sends you to log in.
        $this->withSession([])->get('password')->assertRedirectTo('/login');
    }

    public function testPasswordBoxesGetGenerateButtons(): void
    {
        // public/js/passwords.js adds "Show password" to every password box
        // and a Generate button to those marked data-generate.
        $html = $this->asAdmin()->get('admin/players/new')->response()->getBody();
        $this->assertStringContainsString('js/passwords.js', $html);
        $this->assertStringContainsString('name="password" autocomplete="new-password" data-generate>', $html);

        $playerId = (int) (new PlayerModel())->insert(['first_name' => 'Jo', 'last_name' => 'Golfer', 'is_player' => 1]);
        $html     = $this->withSession(['player_id' => $playerId])->get('password')->response()->getBody();
        $this->assertStringContainsString('data-generate data-confirm="password_confirm"', $html);

        $this->assertFileExists(FCPATH . 'js/passwords.js');
    }

    public function testNewAdminMustChooseTheirPasswordBeforeUsingAdminPages(): void
    {
        $players = new PlayerModel();
        $adminId = (int) $players->insert(['first_name' => 'Al', 'last_name' => 'Boss', 'is_admin' => 1, 'is_player' => 0]);
        $this->withSession(['admin_id' => $adminId])->get('admin/leagues')->assertRedirectTo('/password');

        // An admin setting their own password on the edit page isn't asked to change it.
        $this->asAdmin()->post("admin/players/{$this->adminId}", [
            'first_name' => 'Pat', 'last_name' => 'Admin', 'login' => 'admin1', 'password' => 'new-admin-pass', 'active' => '1', 'is_admin' => '1',
        ]);
        $this->assertFalse($players->mustChangePassword($this->adminId));
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
