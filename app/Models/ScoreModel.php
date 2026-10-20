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
        'gross', 'handicap_used', 'net', 'place', 'winnings_cents', 'image',
    ];

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
    ];

    public function __construct(...$params)
    {
        parent::__construct(...$params);

        // A hole score, when entered, is 1 to 20 strokes. Blank means not entered yet.
        for ($i = 1; $i <= 18; $i++) {
            $this->validationRules["hole{$i}"] = 'permit_empty|integer|greater_than_equal_to[1]|less_than_equal_to[20]';
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
     * Total strokes for the nine played, or null if any hole is still blank.
     */
    public static function grossForNine(array $score): ?int
    {
        if (empty($score['nine'])) {
            return null;
        }

        $total = 0;
        foreach (self::holesForNine($score['nine']) as $hole) {
            if ($score["hole{$hole}"] === null || $score["hole{$hole}"] === '') {
                return null;
            }
            $total += (int) $score["hole{$hole}"];
        }

        return $total;
    }
}
