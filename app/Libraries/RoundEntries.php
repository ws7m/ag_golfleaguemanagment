<?php

namespace App\Libraries;

use App\Models\LeagueGroupModel;

/**
 * Score records for the players in a round.
 */
class RoundEntries
{
    /**
     * Gives every active player registered in the round's league a score
     * record for the round, unless they already have one. New records have
     * all 18 hole scores set to 0 (not entered yet), the handicap used set to
     * the player's handicap, the player's group and the nine that group is
     * playing (if they are in one), and playing as the player said on their
     * page while the round was scheduled (playing if they didn't say).
     * Existing records are left alone.
     *
     * @return array{int, int} [records added, players who already had one]
     */
    public static function addPlayers(array $round): array
    {
        $db      = db_connect();
        $players = $db->table('players')
            ->where('league_id', $round['league_id'])
            ->where('is_player', 1)
            ->where('active', 1)
            ->get()->getResultArray();
        $already = array_map('intval', array_column(
            $db->table('scores')->select('player_id')->where('round_id', $round['id'])->get()->getResultArray(),
            'player_id'
        ));
        $groupOf = self::groupsByPlayer((int) $round['league_id']);
        $playing = self::playingChoices((int) $round['id']);

        $added = 0;
        $db->transStart();
        foreach ($players as $player) {
            if (in_array((int) $player['id'], $already, true)) {
                continue;
            }
            $db->table('scores')->insert(self::scoreRow($round, $player, $groupOf, $playing[(int) $player['id']] ?? 1));
            $added++;
        }
        $db->transComplete();

        $kept = count(array_intersect($already, array_map(static fn ($p) => (int) $p['id'], $players)));

        return [$added, $kept];
    }

    /**
     * Sets whether a player is playing in a round. For a scheduled round the
     * choice is kept until the round is made active. For an active round the
     * player's score record is updated, or added (set up as addPlayers()
     * would) if they don't have one yet.
     */
    public static function setPlaying(array $round, array $player, bool $playing): void
    {
        $db  = db_connect();
        $now = date('Y-m-d H:i:s');
        $key = ['round_id' => (int) $round['id'], 'player_id' => (int) $player['id']];

        if ($round['status'] === 'scheduled') {
            $db->query(
                'INSERT INTO ' . $db->prefixTable('round_playing') . ' (round_id, player_id, playing, updated_at) VALUES (?, ?, ?, ?)
                 ON CONFLICT (round_id, player_id) DO UPDATE SET playing = excluded.playing, updated_at = excluded.updated_at',
                [$key['round_id'], $key['player_id'], (int) $playing, $now]
            );

            return;
        }

        if ($db->table('scores')->where($key)->countAllResults() > 0) {
            $db->table('scores')->where($key)->update(['playing' => (int) $playing, 'updated_at' => $now]);
        } else {
            $db->table('scores')->insert(self::scoreRow($round, $player, self::groupsByPlayer((int) $round['league_id']), (int) $playing));
        }
    }

    /**
     * A player's score record for a round. Without one, a new (unsaved)
     * record set up as addPlayers() would, with playing ticked.
     */
    public static function scoreFor(array $round, array $player): array
    {
        $score = db_connect()->table('scores')
            ->where(['round_id' => (int) $round['id'], 'player_id' => (int) $player['id']])
            ->get()->getRowArray();

        return $score ?? self::scoreRow($round, $player, self::groupsByPlayer((int) $round['league_id']), 1);
    }

    /**
     * Player id => playing (0/1) for the choices made while the round was
     * scheduled.
     *
     * @return array<int, int>
     */
    public static function playingChoices(int $roundId): array
    {
        $rows = db_connect()->table('round_playing')->where('round_id', $roundId)->get()->getResultArray();

        return array_map('intval', array_column($rows, 'playing', 'player_id'));
    }

    /**
     * Each player's group in a league (active groups only).
     *
     * @return array<int, array> Player id => group row
     */
    private static function groupsByPlayer(int $leagueId): array
    {
        $groupOf = [];
        $groups  = db_connect()->table('league_groups')->where('league_id', $leagueId)->where('active', 1)->get()->getResultArray();
        foreach ($groups as $group) {
            foreach (LeagueGroupModel::playerIds($group) as $playerId) {
                $groupOf[$playerId] ??= $group;
            }
        }

        return $groupOf;
    }

    private static function scoreRow(array $round, array $player, array $groupOf, int $playing): array
    {
        $now   = date('Y-m-d H:i:s');
        $group = $groupOf[(int) $player['id']] ?? null;
        $row   = [
            'league_id'     => $round['league_id'],
            'round_id'      => (int) $round['id'],
            'player_id'     => (int) $player['id'],
            'group_id'      => $group['id'] ?? null,
            'nine'          => $group === null ? null : ($group['nine'] === 'back' ? 'back' : 'front'),
            'playing'       => $playing,
            'paid'          => 0,
            'locked'        => 0,
            'handicap_used' => $player['handicap'],
            'created_at'    => $now,
            'updated_at'    => $now,
        ];
        for ($i = 1; $i <= 18; $i++) {
            $row["hole{$i}"] = 0;
        }

        return $row;
    }
}
