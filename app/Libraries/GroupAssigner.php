<?php

namespace App\Libraries;

use App\Controllers\Admin\Groups;
use App\Models\LeagueGroupModel;

/**
 * Puts a league's players who aren't in a group yet into its groups, keeping
 * players who asked to play together in the same group where it can.
 *
 * Each player's "Desired group members" is a comma separated list of first
 * and last names, e.g. "Jim Jones, Mary Smith". A player and everyone they
 * name are kept together, and so are people named by the same players.
 * Someone named who is already in a group pulls the others into that group
 * if it has room. Players who named no one fill the places left, in last
 * name order. Groups take Groups::PLAYERS_PER_GROUP players.
 */
class GroupAssigner
{
    /**
     * @return array{placed: int, together: int, unplaced: list<string>, unknown: list<string>}
     *         players placed, how many of them are in a group with someone
     *         they named or who named them, names of players there was no room
     *         for, and names that matched no player ("Jo Doe (named by Ann Ames)")
     */
    public static function assign(int $leagueId): array
    {
        $db     = db_connect();
        $groups = $db->table('league_groups')->where(['league_id' => $leagueId, 'active' => 1])
            ->orderBy('group_number')->get()->getResultArray();
        $players = $db->table('players')->where(['league_id' => $leagueId, 'is_player' => 1, 'active' => 1])
            ->orderBy('last_name')->orderBy('first_name')->get()->getResultArray();

        // Who is in which group now, and the free places in each group.
        $groupOf = [];
        $free    = [];
        foreach ($groups as $group) {
            $id = (int) $group['id'];
            foreach (LeagueGroupModel::playerIds($group) as $playerId) {
                $groupOf[$playerId] = $id;
            }
            $free[$id] = [];
            for ($i = 1; $i <= Groups::PLAYERS_PER_GROUP; $i++) {
                if (empty($group["player{$i}_id"])) {
                    $free[$id][] = "player{$i}_id";
                }
            }
        }

        // Look players up by "first last", ignoring capitals and extra spaces.
        $byName = [];
        $name   = [];
        foreach ($players as $player) {
            $id          = (int) $player['id'];
            $name[$id]   = trim("{$player['first_name']} {$player['last_name']}");
            $byName[self::key($name[$id])][] = $id;
        }

        // Join the players not in a group into sets that want to play
        // together (union-find), noting a group to aim for when someone
        // named is already in one.
        $waiting = array_values(array_filter(array_keys($name), static fn ($id) => ! isset($groupOf[$id])));
        $parent  = array_combine($waiting, $waiting) ?: [];
        $find    = static function (int $id) use (&$parent): int {
            while ($parent[$id] !== $id) {
                $id = $parent[$id] = $parent[$parent[$id]];
            }

            return $id;
        };
        $wantsGroup = [];
        $asked      = [];
        $unknown    = [];
        foreach ($players as $player) {
            $id = (int) $player['id'];
            if (! isset($parent[$id])) {
                continue;
            }
            foreach (explode(',', (string) $player['desired_group_members']) as $wanted) {
                $wanted = trim((string) preg_replace('/\s+/', ' ', $wanted));
                if ($wanted === '') {
                    continue;
                }
                $matches = $byName[self::key($wanted)] ?? [];
                if (count($matches) !== 1) {
                    $unknown[] = "{$wanted} (named by {$name[$id]})" . ($matches === [] ? '' : ' – more than one player has that name');

                    continue;
                }
                $other = $matches[0];
                if ($other === $id) {
                    continue;
                }
                $asked[$id] = $asked[$other] = true;
                if (isset($groupOf[$other])) {
                    $wantsGroup[$id] ??= $groupOf[$other];
                } else {
                    $parent[$find($other)] = $find($id);
                }
            }
        }

        $sets = [];
        foreach ($waiting as $id) {
            $sets[$find($id)][] = $id;
        }
        // Sets that asked to play together go first, biggest first; then
        // everyone else one at a time, in last name order.
        $together = array_values(array_filter($sets, static fn ($set) => count($set) > 1 || isset($asked[$set[0]])));
        usort($together, static fn ($a, $b) => count($b) <=> count($a));
        $alone = array_values(array_filter($sets, static fn ($set) => count($set) === 1 && ! isset($asked[$set[0]])));

        $placed   = [];
        $unplaced = [];
        $put      = static function (int $playerId, int $groupId) use (&$free, &$placed, $db): void {
            $slot = array_shift($free[$groupId]);
            $db->table('league_groups')->where('id', $groupId)->update([$slot => $playerId, 'updated_at' => date('Y-m-d H:i:s')]);
            $placed[$playerId] = $groupId;
        };

        $db->transStart();
        foreach ($together as $set) {
            // The group someone in the set asked for if it has room for them
            // all, else the group with the most room.
            $target = null;
            foreach ($set as $id) {
                $groupId = $wantsGroup[$id] ?? null;
                if ($groupId !== null && count($free[$groupId]) >= count($set)) {
                    $target = $groupId;

                    break;
                }
            }
            foreach ($set as $id) {
                $groupId = $target;
                if ($groupId === null || $free[$groupId] === []) {
                    $groupId = self::roomiest($free);
                }
                if ($groupId === null) {
                    $unplaced[] = $name[$id];

                    continue;
                }
                $put($id, $groupId);
                $target ??= $groupId;
            }
        }
        foreach ($alone as [$id]) {
            $groupId = self::firstWithRoom($free);
            if ($groupId === null) {
                $unplaced[] = $name[$id];

                continue;
            }
            $put($id, $groupId);
        }
        $db->transComplete();

        // Placed in the same group as someone from their set, or in the
        // group of someone they named who was already in one.
        $togetherCount = 0;
        foreach ($together as $set) {
            foreach ($set as $id) {
                if (! isset($placed[$id])) {
                    continue;
                }
                $mates = array_filter($set, static fn ($o) => $o !== $id && ($placed[$o] ?? null) === $placed[$id]);
                if ($mates !== [] || ($wantsGroup[$id] ?? null) === $placed[$id]) {
                    $togetherCount++;
                }
            }
        }

        return [
            'placed'   => count($placed),
            'together' => $togetherCount,
            'unplaced' => $unplaced,
            'unknown'  => array_values(array_unique($unknown)),
        ];
    }

    private static function key(string $name): string
    {
        return strtolower(trim((string) preg_replace('/\s+/', ' ', $name)));
    }

    /** The group with the most free places (the first such), or null if all are full. */
    private static function roomiest(array $free): ?int
    {
        $best = null;
        foreach ($free as $groupId => $slots) {
            if ($slots !== [] && ($best === null || count($slots) > count($free[$best]))) {
                $best = $groupId;
            }
        }

        return $best;
    }

    /** The first group (by number) with a free place, or null. */
    private static function firstWithRoom(array $free): ?int
    {
        foreach ($free as $groupId => $slots) {
            if ($slots !== []) {
                return $groupId;
            }
        }

        return null;
    }
}
