<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * A player's entry for one round, including hole-by-hole strokes and results.
 */
class ScoreModel extends Model
{
    protected $table         = 'scores';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'league_id', 'player_id', 'group_id', 'round_id', 'nine', 'playing', 'paid', 'locked',
        'hole1', 'hole2', 'hole3', 'hole4', 'hole5', 'hole6', 'hole7', 'hole8', 'hole9',
        'hole10', 'hole11', 'hole12', 'hole13', 'hole14', 'hole15', 'hole16', 'hole17', 'hole18',
        'gross', 'handicap_used', 'net', 'place', 'winnings_cents', 'image', 'team_number',
        'team_member1_id', 'team_member2_id', 'team_member3_id', 'team_member4_id', 'team_member5_id',
    ];

    // Team members a player can name for a team game.
    public const TEAM_MEMBER_SLOTS = 5;

    protected $validationRules = [
        'league_id'      => 'required|is_natural_no_zero',
        'player_id'      => 'required|is_natural_no_zero',
        'round_id'       => 'required|is_natural_no_zero',
        'group_id'       => 'permit_empty|is_natural_no_zero',
        'nine'           => 'permit_empty|in_list[front,back]',
        'playing'        => 'permit_empty|in_list[0,1]',
        'paid'           => 'permit_empty|in_list[0,1]',
        'locked'         => 'permit_empty|in_list[0,1]',
        'gross'          => 'permit_empty|is_natural_no_zero',
        'handicap_used'  => 'permit_empty|integer',
        'net'            => 'permit_empty|integer',
        'place'          => 'permit_empty|is_natural_no_zero',
        'winnings_cents' => 'permit_empty|is_natural',
        // Same round + same team number = a team; 0 = an individual score.
        'team_number'    => 'permit_empty|integer|greater_than_equal_to[0]|less_than_equal_to[99]',
    ];

    public function __construct(...$params)
    {
        parent::__construct(...$params);

        // A hole score is 1 to 20 strokes. 0 or blank means not entered yet
        // (activating a round creates score records with every hole at 0).
        for ($i = 1; $i <= self::TEAM_MEMBER_SLOTS; $i++) {
            $this->validationRules["team_member{$i}_id"] = 'permit_empty|is_natural_no_zero';
        }
        for ($i = 1; $i <= 18; $i++) {
            $this->validationRules["hole{$i}"] = 'permit_empty|integer|greater_than_equal_to[0]|less_than_equal_to[20]';
        }
    }

    /**
     * The hole numbers for a nine: 1-9 for front, 10-18 for back.
     *
     * @return list<int>
     */
    public static function holesForNine(string $nine): array
    {
        return $nine === 'back' ? range(10, 18) : range(1, 9);
    }

    /**
     * Changes to save for hole scores posted for one score: the nine's
     * holes (blank = 0, not entered), and the gross and net worked out from
     * them. Returns an error message instead if a hole isn't 0 to 20.
     *
     * @param array $score  The score as stored
     * @param array $posted holeN => value as typed
     *
     * @return array{0: array<string, mixed>, 1: ?string} [changes, error]
     */
    public static function holeChanges(array $score, array $posted): array
    {
        $nine    = $score['nine'] ?: 'front';
        $changes = ['nine' => $nine];
        foreach (self::holesForNine($nine) as $hole) {
            $value = trim((string) ($posted["hole{$hole}"] ?? ''));
            if ($value !== '' && (! ctype_digit($value) || (int) $value > 20)) {
                return [[], "Hole {$hole} must be a number from 0 to 20."];
            }
            $changes["hole{$hole}"] = (int) $value;
        }
        $changes['gross'] = self::grossForNine($changes + $score);
        $changes['net']   = $changes['gross'] !== null && $score['handicap_used'] !== null
            ? $changes['gross'] - (int) $score['handicap_used']
            : null;

        return [$changes, null];
    }

    /**
     * Total strokes for the nine played, or null if any hole of it is not
     * entered yet (blank or 0).
     */
    public static function grossForNine(array $score): ?int
    {
        if (empty($score['nine'])) {
            return null;
        }

        $total = 0;
        foreach (self::holesForNine($score['nine']) as $hole) {
            if ((int) ($score["hole{$hole}"] ?? 0) <= 0) {
                return null;
            }
            $total += (int) $score["hole{$hole}"];
        }

        return $total;
    }
}
