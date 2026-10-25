<?php

use App\Models\PlayerModel;
use CodeIgniter\Config\Factories;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * @internal
 */
final class PlayerHomeTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;
    protected $namespace   = 'App';

    private int $leagueId;
    private int $playerId;

    protected function setUp(): void
    {
        parent::setUp();

        // Posts in these tests carry no CSRF token.
        $filters = config(\Config\Filters::class);
        $filters->globals['before'] = array_values(array_diff($filters->globals['before'], ['csrf']));

        $db             = db_connect();
        $db->table('leagues')->insert(['name' => 'Mongrels', 'active' => 1, 'start_date' => '2099-05-01']);
        $this->leagueId = (int) $db->insertID();
        $this->playerId = (int) (new PlayerModel())->insert(['first_name' => 'Nora', 'last_name' => 'Vote', 'league_id' => $this->leagueId, 'must_change_password' => 0]);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Factories::reset('config');
    }

    private function asPlayer(?int $playerId = null): self
    {
        return $this->withSession(['player_id' => $playerId ?? $this->playerId]);
    }

    public function testRoundsListAndOneVotePerRound(): void
    {
        $db = db_connect();
        $db->table('courses')->insert(['name' => 'Home Course']);
        $courseId = (int) $db->insertID();
        $games    = [];
        foreach (['Best Ball', 'Skins', 'Old Game'] as $name) {
            $db->table('games')->insert(['league_id' => $this->leagueId, 'name' => $name, 'active' => $name === 'Old Game' ? 0 : 1]);
            $games[$name] = (int) $db->insertID();
        }
        $db->table('rounds')->insert(['league_id' => $this->leagueId, 'course_id' => $courseId, 'game_id' => $games['Skins'], 'round_date' => '2099-06-07', 'name' => 'Round 1', 'status' => 'scheduled']);
        $scheduled = (int) $db->insertID();
        $db->table('rounds')->insert(['league_id' => $this->leagueId, 'course_id' => $courseId, 'round_date' => '2099-05-31', 'name' => 'Round 0', 'status' => 'scored']);
        $scored = (int) $db->insertID();

        $page = $this->asPlayer()->get('player');
        $page->assertOK();
        $body = $page->response()->getBody();
        $this->assertStringNotContainsString('Login:', $body);
        $this->assertStringNotContainsString('League Notice', $body);
        $this->assertMatchesRegularExpression('#2099-05-31.*Round 0.*Home Course.*Scored#s', $body);
        $this->assertStringContainsString("player/rounds/{$scheduled}/vote", $body);
        $this->assertStringNotContainsString("player/rounds/{$scored}/vote", $body);
        // Status is shown as text only, and inactive games are not offered.
        $this->assertStringNotContainsString('name="value"', $body);
        $this->assertStringNotContainsString('Old Game', $body);

        // Voting only for a scheduled round, and only for an offered game.
        $this->asPlayer()->post("player/rounds/{$scored}/vote", ['game_id' => $games['Skins']])->assertSessionHas('error');
        $this->asPlayer()->post("player/rounds/{$scheduled}/vote", ['game_id' => $games['Old Game']])->assertSessionHas('error');
        $this->dontSeeInDatabase('game_votes', ['round_id' => $scheduled]);

        $this->asPlayer()->post("player/rounds/{$scheduled}/vote", ['game_id' => $games['Best Ball']])
            ->assertSessionHas('message', 'Thanks! Your vote for Best Ball in 2099-06-07 Round 1 is saved.');
        $this->seeInDatabase('game_votes', ['round_id' => $scheduled, 'game_id' => $games['Best Ball'], 'count' => 1]);
        $this->seeInDatabase('game_voters', ['round_id' => $scheduled, 'player_id' => $this->playerId, 'game_id' => $games['Best Ball']]);

        // A second vote by the same player in that round is refused.
        $this->asPlayer()->post("player/rounds/{$scheduled}/vote", ['game_id' => $games['Skins']])
            ->assertSessionHas('error', 'You have already voted for this round.');
        $this->dontSeeInDatabase('game_votes', ['round_id' => $scheduled, 'game_id' => $games['Skins']]);
        $body = $this->asPlayer()->get('player')->response()->getBody();
        $this->assertStringNotContainsString("player/rounds/{$scheduled}/vote", $body);
        $this->assertStringContainsString('Best Ball <span class="muted">(voted)</span>', $body);

        // Another player's vote for the same game adds to its count.
        $other = (int) (new PlayerModel())->insert(['first_name' => 'Otto', 'last_name' => 'Vote', 'league_id' => $this->leagueId, 'must_change_password' => 0]);
        $this->asPlayer($other)->post("player/rounds/{$scheduled}/vote", ['game_id' => $games['Best Ball']])->assertSessionHas('message');
        $this->seeInDatabase('game_votes', ['round_id' => $scheduled, 'game_id' => $games['Best Ball'], 'count' => 2]);
        $this->assertSame(1, $db->table('game_votes')->where('round_id', $scheduled)->countAllResults());
    }

    public function testPlayingBoxForScheduledAndActiveRounds(): void
    {
        $db = db_connect();
        $db->table('players')->where('id', $this->playerId)->update(['handicap' => 7]);
        $db->table('courses')->insert(['name' => 'Home Course']);
        $courseId = (int) $db->insertID();
        $leagueId = $this->leagueId;
        $round    = static function (string $date, string $status) use ($db, $courseId, $leagueId): int {
            $db->table('rounds')->insert(['league_id' => $leagueId, 'course_id' => $courseId, 'round_date' => $date, 'status' => $status]);

            return (int) $db->insertID();
        };
        $scheduled = $round('2099-06-14', 'scheduled');
        $active    = $round('2099-06-07', 'active');
        $scored    = $round('2099-05-31', 'scored');

        // Playing is ticked by default, and offered for scheduled and active rounds only.
        $body = $this->asPlayer()->get('player')->response()->getBody();
        $this->assertSame(2, substr_count($body, 'type="checkbox" name="playing" value="1" checked'));
        $this->assertStringContainsString("player/rounds/{$scheduled}/playing", $body);
        $this->assertStringContainsString("player/rounds/{$active}/playing", $body);
        $this->assertStringNotContainsString("player/rounds/{$scored}/playing", $body);
        $this->asPlayer()->post("player/rounds/{$scored}/playing", ['playing' => '0'])->assertSessionHas('error');

        // Scheduled: the choice is kept for when the round is made active.
        $this->asPlayer()->post("player/rounds/{$scheduled}/playing", ['playing' => '0'])
            ->assertSessionHas('message', 'You are not playing in 2099-06-14.');
        $this->seeInDatabase('round_playing', ['round_id' => $scheduled, 'player_id' => $this->playerId, 'playing' => 0]);
        $this->dontSeeInDatabase('scores', ['round_id' => $scheduled]);
        $body = $this->asPlayer()->get('player')->response()->getBody();
        $this->assertSame(1, substr_count($body, 'value="1" checked'));

        // Active: unticking adds a score record marked not playing; ticking sets it playing.
        $this->asPlayer()->post("player/rounds/{$active}/playing", ['playing' => '0']);
        $this->seeInDatabase('scores', ['round_id' => $active, 'player_id' => $this->playerId, 'playing' => 0, 'handicap_used' => 7, 'hole1' => 0]);
        $this->asPlayer()->post("player/rounds/{$active}/playing", ['playing' => '1'])
            ->assertSessionHas('message', 'You are playing in 2099-06-07.');
        $this->seeInDatabase('scores', ['round_id' => $active, 'player_id' => $this->playerId, 'playing' => 1]);
        $this->assertSame(1, $db->table('scores')->where('round_id', $active)->countAllResults());

        // Making the scheduled round active uses the player's choice.
        $other = (int) (new PlayerModel())->insert(['first_name' => 'Otto', 'last_name' => 'Play', 'league_id' => $this->leagueId]);
        \App\Libraries\RoundEntries::addPlayers($db->table('rounds')->where('id', $scheduled)->get()->getRowArray());
        $this->seeInDatabase('scores', ['round_id' => $scheduled, 'player_id' => $this->playerId, 'playing' => 0]);
        $this->seeInDatabase('scores', ['round_id' => $scheduled, 'player_id' => $other, 'playing' => 1]);
    }

    public function testScoreEntryForTheActiveRound(): void
    {
        $db = db_connect();
        $db->table('players')->where('id', $this->playerId)->update(['handicap' => 6, 'tee' => 'blue']);
        $db->table('courses')->insert(['name' => 'Home Course']);
        $courseId = (int) $db->insertID();
        for ($hole = 1; $hole <= 18; $hole++) {
            $db->table('holes')->insert(['course_id' => $courseId, 'number' => $hole, 'nine' => $hole <= 9 ? 'front' : 'back', 'blue_par' => 4, 'white_par' => 3]);
        }

        // No active round: no link, and the page sends the player back.
        $this->assertStringNotContainsString('player/score', $this->asPlayer()->get('player')->response()->getBody());
        $this->asPlayer()->get('player/score/1')->assertRedirectTo('/player');

        $db->table('rounds')->insert(['league_id' => $this->leagueId, 'course_id' => $courseId, 'round_date' => '2099-06-07', 'name' => 'Round 1', 'status' => 'active']);
        $roundId = (int) $db->insertID();

        $home = $this->asPlayer()->get('player')->response()->getBody();
        $this->assertStringContainsString('Enter scores for 6/7/2099 Round 1', $home);
        $this->assertLessThan(strpos($home, '<h2>Rounds</h2>'), strpos($home, 'player/score'));

        // The page shows the nine holes on one line with the player's tee pars.
        $page = $this->asPlayer()->get("player/score/{$roundId}");
        $page->assertOK();
        $body = $page->response()->getBody();
        $this->assertSame(9, substr_count($body, 'name="holes[hole'));
        $this->assertStringContainsString('name="holes[hole9]"', $body);
        $this->assertStringContainsString('Par 36', $body);
        $this->assertStringContainsString("The game for this round hasn't been set yet.", $body);

        // The game line says which game, and whether it's a team game.
        $db->table('games')->insert(['league_id' => $this->leagueId, 'name' => 'Best Ball', 'team_game' => 1]);
        $db->table('rounds')->where('id', $roundId)->update(['game_id' => $db->insertID()]);
        $body = $this->asPlayer()->get("player/score/{$roundId}")->response()->getBody();
        $this->assertMatchesRegularExpression('#Game: <strong>Best Ball</strong> ·\s*<strong>Team game</strong>#', $body);
        $this->assertLessThan(strpos($body, 'name="holes[hole1]"'), strpos($body, 'Best Ball'));

        // Partly entered: saved without a total. No score record yet, so one is added.
        $holes = ['hole1' => '5', 'hole2' => '4', 'hole3' => ''];
        $this->asPlayer()->post("player/score/{$roundId}", ['holes' => $holes])
            ->assertSessionHas('message', 'Scores saved. Your total shows once all nine holes are entered.');
        $this->seeInDatabase('scores', ['round_id' => $roundId, 'player_id' => $this->playerId, 'hole1' => 5, 'hole2' => 4, 'hole3' => 0, 'gross' => null, 'playing' => 1]);

        // All nine: gross and net (gross less handicap).
        $holes = array_combine(array_map(static fn ($h) => "hole{$h}", range(1, 9)), array_fill(0, 9, '5'));
        $this->asPlayer()->post("player/score/{$roundId}", ['holes' => $holes])
            ->assertSessionHas('message', 'Scores saved. Gross 45, net 39.');
        $this->assertSame(1, $db->table('scores')->where('round_id', $roundId)->countAllResults());

        // A bad number saves nothing.
        $this->asPlayer()->post("player/score/{$roundId}", ['holes' => ['hole1' => '25'] + $holes])
            ->assertSessionHas('error', 'Hole 1 must be a number from 0 to 20. Nothing was saved.');
        $this->seeInDatabase('scores', ['round_id' => $roundId, 'hole1' => 5]);

        // Locked scores can't be changed, and only the active round takes scores.
        $db->table('scores')->where('round_id', $roundId)->update(['locked' => 1]);
        $this->asPlayer()->post("player/score/{$roundId}", ['holes' => ['hole1' => '3'] + $holes])->assertSessionHas('error');
        $this->seeInDatabase('scores', ['round_id' => $roundId, 'hole1' => 5]);
        $this->asPlayer()->post('player/score/' . ($roundId + 1), ['holes' => $holes])->assertRedirectTo('/player');
    }

    public function testEnterScoresButtonForEachActiveRoundThePlayerIsIn(): void
    {
        $db = db_connect();
        $db->table('courses')->insert(['name' => 'Home Course']);
        $courseId = (int) $db->insertID();
        $round    = function (string $date, string $name, string $status) use ($db, $courseId): int {
            $db->table('rounds')->insert(['league_id' => $this->leagueId, 'course_id' => $courseId, 'round_date' => $date, 'name' => $name, 'status' => $status]);

            return (int) $db->insertID();
        };
        $first     = $round('2099-06-07', 'Round 1', 'active');
        $second    = $round('2099-06-14', 'Round 2', 'active');
        $third     = $round('2099-06-21', 'Round 3', 'active');
        $scheduled = $round('2099-06-28', 'Round 4', 'scheduled');

        // Not playing round 2 (score record) or round 3 (said so before it was active).
        $db->table('scores')->insert(['league_id' => $this->leagueId, 'round_id' => $second, 'player_id' => $this->playerId, 'playing' => 0]);
        $db->table('round_playing')->insert(['round_id' => $third, 'player_id' => $this->playerId, 'playing' => 0]);

        $home = $this->asPlayer()->get('player')->response()->getBody();
        $this->assertStringContainsString("player/score/{$first}\">Enter scores for 6/7/2099 Round 1</a>", $home);
        $this->assertStringNotContainsString("player/score/{$second}", $home);
        $this->assertStringNotContainsString("player/score/{$third}", $home);
        $this->assertStringNotContainsString("player/score/{$scheduled}", $home);

        // Playing again in round 2: both buttons show.
        $db->table('scores')->where('round_id', $second)->update(['playing' => 1]);
        $home = $this->asPlayer()->get('player')->response()->getBody();
        $this->assertStringContainsString("player/score/{$first}", $home);
        $this->assertStringContainsString("player/score/{$second}\">Enter scores for 6/14/2099 Round 2</a>", $home);

        // Each round's page saves to that round; a scheduled round takes no scores.
        $this->asPlayer()->get("player/score/{$second}")->assertSee('Round 2');
        $this->asPlayer()->post("player/score/{$second}", ['holes' => ['hole1' => '4']])->assertRedirectTo("/player/score/{$second}");
        $this->seeInDatabase('scores', ['round_id' => $second, 'player_id' => $this->playerId, 'hole1' => 4]);
        $this->dontSeeInDatabase('scores', ['round_id' => $first, 'player_id' => $this->playerId]);
        $this->asPlayer()->get("player/score/{$scheduled}")->assertRedirectTo('/player');
    }

    public function testTeamMembersOnScoreEntryForTeamGames(): void
    {
        $db = db_connect();
        $db->table('courses')->insert(['name' => 'Home Course']);
        $courseId = (int) $db->insertID();
        $db->table('games')->insert(['league_id' => $this->leagueId, 'name' => 'Skins', 'team_game' => 0]);
        $skins = (int) $db->insertID();
        $db->table('rounds')->insert(['league_id' => $this->leagueId, 'course_id' => $courseId, 'game_id' => $skins, 'round_date' => '2099-06-07', 'name' => 'Round 1', 'status' => 'active']);
        $roundId = (int) $db->insertID();
        $players = new PlayerModel();
        $otto    = (int) $players->insert(['first_name' => 'Otto', 'last_name' => 'Ames', 'league_id' => $this->leagueId]);
        $other   = (int) (new \App\Models\LeagueModel())->insert(['name' => 'Other']);
        $outsider = (int) $players->insert(['first_name' => 'Ed', 'last_name' => 'Eads', 'league_id' => $other]);

        // Not a team game: no team boxes or note.
        $body = $this->asPlayer()->get("player/score/{$roundId}")->response()->getBody();
        $this->assertStringNotContainsString('team_members[1]', $body);
        $this->assertStringNotContainsString('All team members should enter', $body);

        $db->table('games')->where('id', $skins)->update(['team_game' => 1]);
        $body = $this->asPlayer()->get("player/score/{$roundId}")->response()->getBody();
        $this->assertStringContainsString('All team members should enter the same score on every hole for team games.', $body);
        $this->assertLessThan(strpos($body, 'All team members'), strpos($body, 'Game: <strong>Skins'));
        $this->assertSame(5, substr_count($body, 'name="team_members['));
        $this->assertLessThan(strpos($body, 'name="team_members[1]'), strpos($body, 'Include yourself in the team selections'));
        // Players are listed First Last; your own name is filled in first.
        $this->assertStringContainsString('<option value="' . $otto . '" >Otto Ames</option>', $body);
        $this->assertStringContainsString('<option value="' . $this->playerId . '" selected>Nora Vote</option>', $body);
        $this->assertStringNotContainsString('Ed Eads', $body);

        $holes = ['hole1' => '4'];
        $this->asPlayer()->post("player/score/{$roundId}", ['holes' => $holes, 'team_members' => [1 => $this->playerId, 2 => $otto, 3 => '', 4 => '']])
            ->assertSessionHas('message');
        $this->seeInDatabase('scores', ['round_id' => $roundId, 'player_id' => $this->playerId, 'team_member1_id' => $this->playerId, 'team_member2_id' => $otto, 'team_member3_id' => null, 'hole1' => 4]);

        // Someone twice, or from another league, is refused.
        $this->asPlayer()->post("player/score/{$roundId}", ['holes' => $holes, 'team_members' => [1 => $otto, 2 => $otto]])
            ->assertSessionHas('error', 'Otto Ames is chosen more than once as a team member. Nothing was saved.');
        $this->asPlayer()->post("player/score/{$roundId}", ['holes' => $holes, 'team_members' => [1 => $outsider]])
            ->assertSessionHas('error', "Team member 1 isn't a player in this league. Nothing was saved.");
        $this->seeInDatabase('scores', ['round_id' => $roundId, 'player_id' => $this->playerId, 'team_member2_id' => $otto]);
    }

    public function testPlayerCanUpdateTheirInfo(): void
    {
        $players = new PlayerModel();
        db_connect()->table('players')->where('id', $this->playerId)->update(['login' => 'voten', 'tee' => 'white', 'handicap' => 9]);
        $players->setPassword($this->playerId, 'fairway-27');

        // The link sits before Password at the top right.
        $home = $this->asPlayer()->get('player')->response()->getBody();
        $this->assertLessThan(strpos($home, '>Password</a>'), strpos($home, '>Update Info</a>'));

        $page = $this->asPlayer()->get('info');
        $page->assertOK();
        $body = $page->response()->getBody();
        foreach (['first_name', 'last_name', 'gender', 'email', 'phone', 'text_msg_ok', 'desired_tee_time', 'desired_group_members', 'password'] as $field) {
            $this->assertStringContainsString("name=\"{$field}\"", $body);
        }
        // Not things only the admin sets.
        foreach (['tee', 'handicap', 'flight', 'login', 'league_id'] as $field) {
            $this->assertStringNotContainsString("name=\"{$field}\"", $body);
        }

        $form = [
            'first_name' => 'Nora', 'last_name' => 'Voter', 'gender' => 'F', 'email' => 'nora@example.com', 'phone' => '970-555-1234',
            'text_msg_ok' => '1', 'desired_tee_time' => '16:10', 'desired_group_members' => 'Jim Jones, Mary Smith',
        ];
        $this->asPlayer()->post('info', $form)->assertSessionHas('message', 'Your information is saved.');
        $this->seeInDatabase('players', ['id' => $this->playerId, 'last_name' => 'Voter', 'gender' => 'F', 'email' => 'nora@example.com', 'text_msg_ok' => 1,
            'desired_tee_time' => '16:10', 'desired_group_members' => 'Jim Jones, Mary Smith', 'login' => 'voten', 'tee' => 'white', 'handicap' => 9]);
        $this->assertSame('Nora Voter', session('player_name'));
        $this->assertNotNull($players->verifyLogin('voten', 'fairway-27'), 'The password is kept when left blank.');

        // Unticking text messages, bad values, and password rules.
        $this->asPlayer()->post('info', ['text_msg_ok' => ''] + $form);
        $this->seeInDatabase('players', ['id' => $this->playerId, 'text_msg_ok' => 0]);
        $this->asPlayer()->post('info', ['first_name' => '', 'email' => 'not-an-email'] + $form)->assertSee('flash-error');
        $this->seeInDatabase('players', ['id' => $this->playerId, 'first_name' => 'Nora', 'email' => 'nora@example.com']);
        $this->asPlayer()->post('info', ['password' => 'bunker-99x', 'password_confirm' => 'bunker-99x', 'current_password' => 'wrong'] + $form)
            ->assertSee('current password is not correct');

        $this->asPlayer()->post('info', ['password' => 'bunker-99x', 'password_confirm' => 'bunker-99x', 'current_password' => 'fairway-27'] + $form)
            ->assertSessionHas('message', 'Your information and new password are saved.');
        $this->assertNotNull($players->verifyLogin('voten', 'bunker-99x'));

        $this->withSession([])->get('info')->assertRedirectTo('/login');
    }
}
