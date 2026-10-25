<?php

namespace App\Controllers\Player;

use App\Controllers\BaseController;
use App\Libraries\Lookup;
use App\Libraries\RoundEntries;
use App\Models\LeagueModel;
use App\Models\PlayerModel;
use App\Models\ScoreModel;
use CodeIgniter\Database\Exceptions\DatabaseException;

/**
 * Player home page: the league notice, and the league's rounds with a vote
 * for the game each scheduled round should use and whether the player is
 * playing.
 */
class Home extends BaseController
{
    public function index(): string
    {
        $player   = (new PlayerModel())->find(session('player_id'));
        $leagueId = $this->leagueId($player);
        $db       = db_connect();

        $rounds = $leagueId === null ? [] : $db->table('rounds r')
            ->select('r.id, r.round_date, r.name, r.status, c.name AS course, g.name AS game, v.game_id AS voted_game_id, vg.name AS voted_game,
                s.playing AS score_playing, rp.playing AS chosen_playing')
            ->join('courses c', 'c.id = r.course_id', 'left')
            ->join('games g', 'g.id = r.game_id', 'left')
            ->join('game_voters v', 'v.round_id = r.id AND v.player_id = ' . (int) $player['id'], 'left')
            ->join('games vg', 'vg.id = v.game_id', 'left')
            ->join('scores s', 's.round_id = r.id AND s.player_id = ' . (int) $player['id'], 'left')
            ->join('round_playing rp', 'rp.round_id = r.id AND rp.player_id = ' . (int) $player['id'], 'left')
            ->where('r.league_id', $leagueId)
            ->orderBy('r.round_date')->orderBy('r.id')
            ->get()->getResultArray();

        $active = $leagueId === null ? null : $this->activeRound($leagueId);

        return view('player/home', [
            'title'  => 'My page',
            'activeRound' => $active,
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
     * Saves the Playing box for a scheduled or active round (see
     * RoundEntries::setPlaying()).
     */
    public function playing(int $roundId)
    {
        $player = (new PlayerModel())->find(session('player_id'));
        $back   = redirect()->to('player');
        $round  = db_connect()->table('rounds')->where('id', $roundId)->where('league_id', $this->leagueId($player))->get()->getRowArray();
        if ($round === null || ! in_array($round['status'], ['scheduled', 'active'], true)) {
            return $back->with('error', 'Playing can only be changed for scheduled or active rounds.');
        }

        $playing = (bool) $this->request->getPost('playing');
        RoundEntries::setPlaying($round, $player, $playing);
        $name = trim("{$round['round_date']} {$round['name']}");

        return $back->with('message', $playing ? "You are playing in {$name}." : "You are not playing in {$name}.");
    }

    /**
     * Score entry for the league's active round: the player's nine holes on
     * one line, saved together.
     */
    public function score()
    {
        $player = (new PlayerModel())->find(session('player_id'));
        $round  = $this->activeRound((int) $this->leagueId($player));
        if ($round === null) {
            return redirect()->to('player')->with('error', 'There is no active round to enter scores for.');
        }
        $score = RoundEntries::scoreFor($round, $player);
        $nine  = $score['nine'] ?: 'front';

        // Par for each hole from the player's tees (white if not set).
        $tee   = in_array($player['tee'] ?? '', ['blue', 'white', 'red'], true) ? $player['tee'] : 'white';
        $holes = db_connect()->table('holes')->select("number, {$tee}_par AS par")
            ->where('course_id', $round['course_id'])->get()->getResultArray();

        return view('player/score', [
            'title'  => 'Enter scores',
            'round'  => $round,
            'score'  => $score,
            'nine'   => $nine,
            'holes'  => ScoreModel::holesForNine($nine),
            'pars'   => array_column($holes, 'par', 'number'),
            'course' => db_connect()->table('courses')->where('id', $round['course_id'])->get()->getRowArray()['name'] ?? '',
            'game'   => $round['game_id'] ? db_connect()->table('games')->where('id', $round['game_id'])->get()->getRowArray() : null,
        ]);
    }

    public function saveScore()
    {
        $player = (new PlayerModel())->find(session('player_id'));
        $back   = redirect()->to('player/score');
        $round  = $this->activeRound((int) $this->leagueId($player));
        if ($round === null || (int) $this->request->getPost('round_id') !== (int) $round['id']) {
            return redirect()->to('player')->with('error', 'Scores can only be entered while the round is active.');
        }

        $score = RoundEntries::scoreFor($round, $player);
        if (! empty($score['locked'])) {
            return $back->with('error', 'Your score for this round is locked. Ask the league admin to change it.');
        }
        [$changes, $error] = ScoreModel::holeChanges($score, (array) ($this->request->getPost('holes') ?? []));
        if ($error !== null) {
            return $back->withInput()->with('error', $error . ' Nothing was saved.');
        }

        // Entering scores means the player is playing.
        $changes['playing']    = 1;
        $changes['updated_at'] = date('Y-m-d H:i:s');
        $db = db_connect();
        if (isset($score['id'])) {
            $db->table('scores')->where('id', (int) $score['id'])->update($changes);
        } else {
            $db->table('scores')->insert($changes + $score);
        }

        $message = $changes['gross'] === null
            ? 'Scores saved. Your total shows once all nine holes are entered.'
            : "Scores saved. Gross {$changes['gross']}" . ($changes['net'] !== null ? ", net {$changes['net']}." : '.');

        return $back->with('message', $message);
    }

    /**
     * The league's active round: the latest by date if more than one.
     */
    private function activeRound(int $leagueId): ?array
    {
        return db_connect()->table('rounds')
            ->where(['league_id' => $leagueId, 'status' => 'active'])
            ->orderBy('round_date', 'DESC')->orderBy('id', 'DESC')
            ->get()->getRowArray();
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
