<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Standing groups for a league (table league_groups).
 */
class LeagueGroupModel extends Model
{
    protected $table         = 'league_groups';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'league_id', 'group_number', 'nine', 'front_tee_time_id', 'back_tee_time_id',
        'player1_id', 'player2_id', 'player3_id', 'player4_id', 'player5_id', 'player6_id',
        'active',
    ];

    protected $validationRules = [
        'league_id'         => 'required|is_natural_no_zero',
        'group_number'      => 'required|is_natural_no_zero',
        'nine'              => 'permit_empty|in_list[front,back]',
        'front_tee_time_id' => 'permit_empty|is_natural_no_zero',
        'back_tee_time_id'  => 'permit_empty|is_natural_no_zero',
        'player1_id'        => 'permit_empty|is_natural_no_zero',
        'player2_id'        => 'permit_empty|is_natural_no_zero',
        'player3_id'        => 'permit_empty|is_natural_no_zero',
        'player4_id'        => 'permit_empty|is_natural_no_zero',
        'player5_id'        => 'permit_empty|is_natural_no_zero',
        'player6_id'        => 'permit_empty|is_natural_no_zero',
        'active'            => 'permit_empty|in_list[0,1]',
    ];

    /**
     * The player IDs in a group's default roster, skipping empty slots.
     *
     * @return list<int>
     */
    public static function playerIds(array $group): array
    {
        $ids = [];
        for ($i = 1; $i <= 6; $i++) {
            if (! empty($group["player{$i}_id"])) {
                $ids[] = (int) $group["player{$i}_id"];
            }
        }

        return $ids;
    }
}
