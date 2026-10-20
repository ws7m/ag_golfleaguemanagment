<?php

use App\Database\Seeds\AdminSeeder;
use App\Models\CourseModel;
use App\Models\HoleModel;
use App\Models\LeagueModel;
use App\Models\PlayerModel;
use App\Models\ScoreModel;
use App\Models\TeeTimeModel;
use CodeIgniter\Database\Exceptions\DatabaseException;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * @internal
 */
final class Phase1SchemaTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;
    protected $namespace   = 'App';

    public function testAllPhase1TablesExist(): void
    {
        foreach (['leagues', 'courses', 'holes', 'players', 'tee_times', 'league_groups',
            'games', 'rounds', 'scores', 'settings'] as $table) {
            $this->assertTrue($this->db->tableExists($table), "Missing table {$table}");
        }
    }

    public function testNewPlayerGetsUniqueThreeDigitMemberNumber(): void
    {
        $players = new PlayerModel();
        $a       = $players->find($players->insert(['first_name' => 'Ann', 'last_name' => 'A']));
        $b       = $players->find($players->insert(['first_name' => 'Bob', 'last_name' => 'B']));

        $this->assertGreaterThanOrEqual(100, (int) $a['member_number']);
        $this->assertLessThanOrEqual(999, (int) $a['member_number']);
        $this->assertNotSame($a['member_number'], $b['member_number']);
    }

    public function testAdminSeederCreatesAdminWithHashedPassword(): void
    {
        putenv('ADMIN_LOGIN=admin1');
        putenv('ADMIN_EMAIL=admin@example.com');
        putenv('ADMIN_PASSWORD=correct-horse');

        ob_start();
        $this->seed(AdminSeeder::class);
        ob_end_clean();

        $players = new PlayerModel();
        $this->assertNotNull($players->verifyLogin('admin1', 'correct-horse'));
        $this->assertNull($players->verifyLogin('admin1', 'wrong-password'));

        $admin = $players->where('login', 'admin1')->first();
        $this->assertArrayNotHasKey('password_hash', $admin);
        $this->assertSame(1, (int) $admin['is_admin']);
        $this->assertSame(0, (int) $admin['is_player']);
        $this->seeInDatabase('settings', ['admin_contact_email' => 'admin@example.com']);

        foreach (['ADMIN_LOGIN', 'ADMIN_EMAIL', 'ADMIN_PASSWORD'] as $name) {
            putenv($name);
        }
    }

    public function testDefaultLoginIsFiveLettersOfLastNamePlusFirstInitial(): void
    {
        $this->assertSame('holbrm', PlayerModel::loginBase('Mark', 'Holbrook'));
        $this->assertSame('catr', PlayerModel::loginBase('Ricky', 'Cat'));
        $this->assertSame('obries', PlayerModel::loginBase('Sean', "O'Brien"));
    }

    public function testForeignKeysStillPointAtPlayersAfterMigrations(): void
    {
        foreach (['scores', 'league_groups'] as $table) {
            $sql = $this->db->query("SELECT sql FROM sqlite_master WHERE name = '{$this->db->DBPrefix}{$table}'")->getRow()->sql;
            $this->assertStringNotContainsString('temp_', $sql, "{$table} foreign keys point at a leftover temporary table");
        }
    }

    public function testCourseInUseCannotBeDeleted(): void
    {
        $courses  = new CourseModel();
        $courseId = $courses->insert(['name' => 'Home Course']);
        (new HoleModel())->insert(['course_id' => $courseId, 'number' => 1, 'nine' => 'front', 'white_par' => 4]);

        $this->expectException(DatabaseException::class);
        $courses->delete($courseId);
    }

    public function testInvalidTeeTimeIsRejected(): void
    {
        $leagueId = (new LeagueModel())->insert(['name' => '2027 League']);
        $courseId = (new CourseModel())->insert(['name' => 'Home Course']);
        $teeTimes = new TeeTimeModel();

        $this->assertNotFalse($teeTimes->insert(['league_id' => $leagueId, 'course_id' => $courseId, 'nine' => 'front', 'tee_time' => '15:10']));
        $this->assertFalse($teeTimes->insert(['league_id' => $leagueId, 'course_id' => $courseId, 'nine' => 'front', 'tee_time' => '3:10pm']));
    }

    public function testGrossIsOnlyTotalledWhenEveryHoleOfTheNineIsEntered(): void
    {
        $score = ['nine' => 'back'];
        for ($i = 1; $i <= 18; $i++) {
            $score["hole{$i}"] = null;
        }
        for ($i = 10; $i <= 17; $i++) {
            $score["hole{$i}"] = 5;
        }
        $this->assertNull(ScoreModel::grossForNine($score));

        $score['hole18'] = 4;
        $this->assertSame(44, ScoreModel::grossForNine($score));
    }
}
