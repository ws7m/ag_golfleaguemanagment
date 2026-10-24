<?php

namespace App\Controllers\Player;

use App\Controllers\BaseController;
use App\Libraries\Lookup;
use App\Models\LeagueModel;
use App\Models\PlayerModel;
use CodeIgniter\Database\Exceptions\DatabaseException;

/**
 * Player home page: the league notice, and the league's rounds with a vote
 * for the game each scheduled round should use.
 */
class Home extends BaseController
{
    public function index(): string
    {
        $player   = (new PlayerModel())->find(session('player_id'));
        $leagueId = $this->leagueId($player);
        $db       = db_connect();

        $rounds = $leagueId === null ? [] : $db->table('rounds r')
            ->select('r.id, r.round_date, r.name, r.status, c.name AS course, g.name AS game, v.game_id AS voted_game_id, vg.name AS voted_game')
            ->join('courses c', 'c.id = r.course_id', 'left')
            ->join('games g', 'g.id = r.game_id', 'left')
            ->join('game_voters v', 'v.round_id = r.id AND v.player_id = ' . (int) $player['id'], 'left')
            ->join('games vg', 'vg.id = v.game_id', 'left')
            ->where('r.league_id', $leagueId)
            ->orderBy('r.round_date')->orderBy('r.id')
            ->get()->getResultArray();

        return view('player/home', [
            'title'  => 'My page',
            'player' => $player,
            'notice' => $leagueId ? (new LeagueModel())->find($leagueId)['notice'] ?? null : null,
            'rounds' => $rounds,
            'games'  => $leagueId ? $this->gameChoices($leagueId) : [],
            'statuses' => ['scheduled' => 'Scheduled', 'active' => 'Active', 'closed' => 'Closed', 'scored' => 'Scored'],
        ]);
    }

    /**
     * Saves the player's vote for the game they want in a scheduled round.
     * Each player votes once per round; the vote adds 1 to that game's count.
     */
    public function vote(int $roundId)
    {
        $player = (new PlayerModel())->find(session('player_id'));
        $back   = redirect()->to('player');
        $db     = db_connect();

        $round = $db->table('rounds')->where('id', $roundId)->where('league_id', $this->leagueId($player))->get()->getRowArray();
        if ($round === null || $round['status'] !== 'scheduled') {
            return $back->with('error', 'Voting is only open for scheduled rounds.');
        }
        $gameId = (int) $this->request->getPost('game_id');
        $games  = $this->gameChoices((int) $round['league_id']);
        if (! isset($games[$gameId])) {
            return $back->with('error', 'Choose a game from the list.');
        }
        if ($db->table('game_voters')->where(['round_id' => $roundId, 'player_id' => $player['id']])->countAllResults() > 0) {
            return $back->with('error', 'You have already voted for this round.');
        }

        // The unique round + player key also stops a second vote sent at the
        // same moment (e.g. a double click).
        try {
            $db->transException(true)->transStart();
            $db->table('game_voters')->insert([
                'round_id'   => $roundId,
                'player_id'  => $player['id'],
                'game_id'    => $gameId,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            // Add 1 to the game's count for this round, starting it at 1.
            $db->query(
                'INSERT INTO ' . $db->prefixTable('game_votes') . ' (round_id, game_id, count) VALUES (?, ?, 1)
                 ON CONFLICT (round_id, game_id) DO UPDATE SET count = count + 1',
                [$roundId, $gameId]
            );
            $db->transComplete();
        } catch (DatabaseException) {
            return $back->with('error', 'Your vote could not be saved. You may have already voted for this round.');
        } finally {
            $db->transException(false);
        }

        $name = trim("{$round['round_date']} {$round['name']}");

        return $back->with('message', "Thanks! Your vote for {$games[$gameId]} in {$name} is saved.");
    }

    /**
     * The player's league, or the current league if they have none.
     */
    private function leagueId(?array $player): ?int
    {
        $leagueId = $player['league_id'] ?? null;

        return $leagueId ? (int) $leagueId : Lookup::currentLeagueId();
    }

    /**
     * The league's active games: id => name.
     *
     * @return array<int, string>
     */
    private function gameChoices(int $leagueId): array
    {
        $rows = db_connect()->table('games')->select('id, name')
            ->where(['league_id' => $leagueId, 'active' => 1])
            ->orderBy('name')->get()->getResultArray();

        return array_column($rows, 'name', 'id');
    }
}
