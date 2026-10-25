<?php

namespace App\Libraries;

use App\Models\CourseModel;
use App\Models\LeagueModel;
use App\Models\PlayerModel;

/**
 * Pick-list options (id => label) used by the admin screens.
 */
class Lookup
{
    /**
     * The league members currently play in: the newest active league.
     */
    public static function currentLeagueId(): ?int
    {
        $row = (new LeagueModel())
            ->where('active', 1)
            ->orderBy('start_date', 'DESC')
            ->orderBy('id', 'DESC')
            ->first();

        return $row ? (int) $row['id'] : null;
    }

    public static function leagues(): array
    {
        $rows = (new LeagueModel())->orderBy('start_date', 'DESC')->orderBy('id', 'DESC')->findAll();

        return self::labels($rows, static fn ($r) => $r['name']);
    }

    public static function courses(): array
    {
        $rows = (new CourseModel())->orderBy('name')->findAll();

        return self::labels($rows, static fn ($r) => $r['name']);
    }

    public static function players(): array
    {
        $rows = (new PlayerModel())->where('is_player', 1)->orderBy('last_name')->orderBy('first_name')->findAll();

        return self::labels($rows, static fn ($r) => "{$r['last_name']}, {$r['first_name']} (#{$r['member_number']})");
    }

    /**
     * Player labels without the member number: "Last, First".
     */
    public static function playerNames(): array
    {
        $rows = (new PlayerModel())->findAll();

        return self::labels($rows, static fn ($r) => "{$r['last_name']}, {$r['first_name']}");
    }

    /**
     * Short player labels for list pages: last name and first initial.
     */
    public static function playersShort(): array
    {
        $rows = (new PlayerModel())->where('is_player', 1)->findAll();

        return self::labels($rows, static fn ($r) => $r['last_name'] . ', ' . mb_substr($r['first_name'], 0, 1));
    }

    public static function games(): array
    {
        $rows = db_connect()->table('games g')
            ->select('g.id, g.name, g.active, l.name AS league')
            ->join('leagues l', 'l.id = g.league_id')
            ->orderBy('l.start_date', 'DESC')->orderBy('g.name')
            ->get()->getResultArray();

        return self::labels($rows, static fn ($r) => "{$r['name']} ({$r['league']})");
    }

    /**
     * Games for the dropdown on the rounds list: '' (no game) first, then
     * games by name. The league is added only when there is more than one.
     */
    public static function gamesShort(): array
    {
        $rows = db_connect()->table('games g')
            ->select('g.id, g.name, g.active, l.name AS league')
            ->join('leagues l', 'l.id = g.league_id')
            ->orderBy('l.start_date', 'DESC')->orderBy('g.name')
            ->get()->getResultArray();
        $oneLeague = count(array_unique(array_column($rows, 'league'))) <= 1;

        return ['' => '—'] + self::labels($rows, static fn ($r) => $oneLeague ? $r['name'] : "{$r['name']} ({$r['league']})");
    }

    public static function groups(): array
    {
        $rows = db_connect()->table('league_groups g')
            ->select('g.id, g.group_number, g.active, l.name AS league')
            ->join('leagues l', 'l.id = g.league_id')
            ->orderBy('l.start_date', 'DESC')->orderBy('g.group_number')
            ->get()->getResultArray();

        return self::labels($rows, static fn ($r) => "Group {$r['group_number']} ({$r['league']})");
    }

    /**
     * Short group labels for list pages, without the league.
     */
    public static function groupsShort(): array
    {
        $rows = db_connect()->table('league_groups')->select('id, group_number, active')->get()->getResultArray();

        return self::labels($rows, static fn ($r) => "Group {$r['group_number']}");
    }

    /**
     * Rounds for the scores page's round choice, newest first:
     * "2027-06-07 - Round 1 - Skins - Team: Yes".
     */
    public static function roundsWithGame(): array
    {
        $rows = db_connect()->table('rounds r')
            ->select('r.id, r.round_date, r.name, g.name AS game, g.team_game')
            ->join('games g', 'g.id = r.game_id', 'left')
            ->orderBy('r.round_date', 'DESC')->orderBy('r.id', 'DESC')
            ->get()->getResultArray();

        return self::labels($rows, static fn ($r) => implode(' - ', [
            $r['round_date'],
            ($r['name'] ?? '') !== '' ? $r['name'] : 'No name',
            $r['game'] ?? 'No game set',
            'Team: ' . ($r['game'] === null ? '—' : ($r['team_game'] ? 'Yes' : 'No')),
        ]));
    }

    public static function rounds(): array
    {
        $rows = db_connect()->table('rounds r')
            ->select('r.id, r.round_date, r.name')
            ->orderBy('r.round_date', 'DESC')
            ->get()->getResultArray();

        return self::labels($rows, static fn ($r) => trim("{$r['round_date']} {$r['name']}"));
    }

    /**
     * Builds id => label, marking inactive records so they stand out.
     */
    private static function labels(array $rows, callable $label): array
    {
        $options = [];
        foreach ($rows as $row) {
            $text = $label($row);
            if (isset($row['active']) && (int) $row['active'] === 0) {
                $text .= ' (inactive)';
            }
            $options[(int) $row['id']] = $text;
        }

        return $options;
    }
}
