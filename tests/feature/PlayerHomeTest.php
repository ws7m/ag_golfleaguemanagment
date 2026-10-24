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
        $this->playerId = (int) (new PlayerModel())->insert(['first_name' => 'Nora', 'last_name' => 'Vote', 'league_id' => $this->leagueId]);
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
        $other = (int) (new PlayerModel())->insert(['first_name' => 'Otto', 'last_name' => 'Vote', 'league_id' => $this->leagueId]);
        $this->asPlayer($other)->post("player/rounds/{$scheduled}/vote", ['game_id' => $games['Best Ball']])->assertSessionHas('message');
        $this->seeInDatabase('game_votes', ['round_id' => $scheduled, 'game_id' => $games['Best Ball'], 'count' => 2]);
        $this->assertSame(1, $db->table('game_votes')->where('round_id', $scheduled)->countAllResults());
    }
}
