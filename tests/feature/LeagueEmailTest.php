<?php

use App\Libraries\Mailer;
use App\Models\CourseModel;
use App\Models\LeagueModel;
use App\Models\PlayerModel;
use App\Models\RoundModel;
use App\Models\SettingModel;
use CodeIgniter\Config\Factories;
use CodeIgniter\Config\Services;
use CodeIgniter\Events\Events;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Test\Mock\MockEmail;

/**
 * Email server settings and emailing the tee time lists. Email goes to
 * CodeIgniter's MockEmail, so nothing is really sent.
 *
 * @internal
 */
final class LeagueEmailTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;
    protected $namespace   = 'App';

    private int $adminId;
    private MockEmail $mail;

    /** @var list<array> Messages "sent" through the mock */
    private array $sent = [];

    protected function setUp(): void
    {
        parent::setUp();

        $filters = config(\Config\Filters::class);
        $filters->globals['before'] = array_values(array_diff($filters->globals['before'], ['csrf']));

        $this->adminId = (int) (new PlayerModel())->insert([
            'login' => 'admin1', 'first_name' => 'Pat', 'last_name' => 'Admin', 'is_admin' => 1, 'is_player' => 0, 'must_change_password' => 0,
        ]);

        $this->mail = new MockEmail(config(\Config\Email::class));
        Services::injectMock('email', $this->mail);
        $this->sent = [];
        Events::on('email', function (array $archive): void {
            $this->sent[] = $archive;
        });
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Factories::reset('config');
        Services::reset();
        Events::removeAllListeners('email');
    }

    private function asAdmin(): self
    {
        return $this->withSession(['admin_id' => $this->adminId, 'admin_name' => 'Pat Admin']);
    }

    private function saveServer(array $extra = []): void
    {
        $this->asAdmin()->post('admin/settings', $extra + [
            'admin_contact_name' => 'Pat Admin', 'admin_contact_email' => 'pat@example.com',
            'smtp_host' => 'smtp.example.com', 'smtp_port' => '587', 'smtp_crypto' => 'tls',
            'smtp_user' => 'league@example.com', 'smtp_pass' => 'app-password-1',
            'mail_from_email' => 'league@example.com', 'mail_from_name' => 'Monday Golf',
        ])->assertRedirectTo('/admin/settings');
    }

    public function testEmailServerSettingsKeepThePasswordEncrypted(): void
    {
        $this->saveServer();
        $settings = (new SettingModel())->first();
        $this->assertSame('smtp.example.com', $settings['smtp_host']);
        $this->assertSame(587, (int) $settings['smtp_port']);
        $this->assertStringNotContainsString('app-password-1', (string) $settings['smtp_pass_encrypted']);
        $this->assertSame('app-password-1', Mailer::decryptPassword($settings['smtp_pass_encrypted']));

        $html = $this->asAdmin()->get('admin/settings')->response()->getBody();
        $this->assertStringContainsString('A password is saved.', $html);
        $this->assertStringNotContainsString('app-password-1', $html);

        // A blank password keeps it; "remove" clears it.
        $this->saveServer(['smtp_pass' => '', 'smtp_crypto' => 'none']);
        $settings = (new SettingModel())->first();
        $this->assertSame('app-password-1', Mailer::decryptPassword($settings['smtp_pass_encrypted']));
        $this->assertSame('none', $settings['smtp_crypto']);
        $this->saveServer(['smtp_pass' => '', 'smtp_pass_clear' => '1']);
        $this->assertNull((new SettingModel())->first()['smtp_pass_encrypted']);

        // Bad values are refused.
        $this->asAdmin()->post('admin/settings', ['smtp_port' => 'abc', 'mail_from_email' => 'nope'])
            ->assertSee('The email server port must be a number');
    }

    public function testSendTestEmail(): void
    {
        $this->asAdmin()->post('admin/settings/test-email', ['test_to' => 'pat@example.com'])
            ->assertSessionHas('error', 'Save the email server and From address first.');

        $this->saveServer();
        $this->asAdmin()->post('admin/settings/test-email', ['test_to' => 'pat@example.com'])
            ->assertSessionHas('message', 'Test email sent to pat@example.com.');
        $this->assertCount(1, $this->sent);
        $this->assertSame(['pat@example.com'], $this->sent[0]['recipients']);

        $this->mail->returnValue = false;
        $this->asAdmin()->post('admin/settings/test-email', ['test_to' => 'pat@example.com'])->assertSessionHas('error');
    }

    public function testEmailTeeSheetsToLeaguePlayers(): void
    {
        $leagueId = (new LeagueModel())->insert(['name' => 'Monday League', 'active' => 1]);
        $courseId = (new CourseModel())->insert(['name' => 'Course']);
        $roundId  = (new RoundModel())->insert(['league_id' => $leagueId, 'course_id' => $courseId, 'round_date' => '2099-05-04', 'name' => 'Round 1']);
        $players  = new PlayerModel();
        $players->insert(['first_name' => 'Ann', 'last_name' => 'Ames', 'email' => 'ann@example.com', 'league_id' => $leagueId]);
        $players->insert(['first_name' => 'Bob', 'last_name' => 'Baker', 'email' => 'bob@example.com', 'league_id' => $leagueId]);
        $players->insert(['first_name' => 'Cy', 'last_name' => 'Cole', 'league_id' => $leagueId]);
        $players->insert(['first_name' => 'Di', 'last_name' => 'Dunn', 'email' => 'di@example.com', 'league_id' => $leagueId, 'active' => 0]);
        $other = (new LeagueModel())->insert(['name' => 'Other League']);
        $players->insert(['first_name' => 'Ed', 'last_name' => 'Eads', 'email' => 'ed@example.com', 'league_id' => $other]);

        $page = "admin/groups/tee-sheet?league_id={$leagueId}&round_id={$roundId}";
        $html = $this->asAdmin()->get($page)->response()->getBody();
        $this->assertStringContainsString('Email isn\'t set up yet.', $html);
        $this->assertStringContainsString('name="message" form="tee-email"', $html);
        // The notice and email boxes come before the Front/Back lists.
        $this->assertLessThan(strpos($html, 'Download PDF'), strpos($html, 'Email the tee time lists'));

        $form = ['league_id' => $leagueId, 'round_id' => $roundId, 'message' => "Cart path only tonight.\nSee you there."];
        $this->asAdmin()->post('admin/groups/tee-sheet/email', $form)
            ->assertSessionHas('error', 'Nothing was sent. Set up the email server on the Settings page first.');
        $this->assertSame([], $this->sent);

        $this->saveServer();
        $html = $this->asAdmin()->get($page)->response()->getBody();
        $this->assertStringContainsString('<strong>2</strong> players registered in this league', $html);
        $this->assertStringContainsString('No email address, so not sent: Cy Cole.', $html);

        $this->asAdmin()->post('admin/groups/tee-sheet/email', $form)
            ->assertSessionHas('message', 'Tee sheets emailed to 2 players. Not sent (no email address): Cy Cole.');
        $this->assertCount(2, $this->sent);
        $this->assertSame([['ann@example.com'], ['bob@example.com']], array_column($this->sent, 'recipients'));
        $first = $this->sent[0];
        $this->assertSame('Monday League - Tee times - 5/4/2099', $first['subject']);
        $this->assertStringContainsString("Cart path only tonight.\nSee you there.", $first['body']);
        $this->assertSame(['TeeTimeList-Front-5-4-2099.pdf', 'TeeTimeList-Back-5-4-2099.pdf'], array_map(static fn ($a) => $a['name'][1], $first['attachments']));
        $this->assertStringStartsWith('%PDF-', $first['attachments'][0]['name'][0]);

        // A failed send is reported and the message is kept for another try.
        $this->mail->returnValue = false;
        $this->asAdmin()->post('admin/groups/tee-sheet/email', $form)->assertSessionHas('error');
    }
}
