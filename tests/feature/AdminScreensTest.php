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

    public function testAdminLoginWithCorrectAndWrongPassword(): void
    {
        $this->post('admin/login', ['email' => 'admin@example.com', 'password' => 'wrong-pass'])
            ->assertSessionMissing('admin_id');

        $result = $this->post('admin/login', ['email' => 'admin@example.com', 'password' => 'correct-horse']);
        $result->assertRedirectTo('/admin');
        $result->assertSessionHas('admin_id', $this->adminId);
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
    }
}
