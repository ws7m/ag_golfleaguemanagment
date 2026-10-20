<?php

namespace App\Controllers\Admin;

use App\Libraries\Lookup;
use App\Models\RoundModel;
use App\Models\ScoreModel;

class Scores extends CrudController
{
    protected string $modelClass = ScoreModel::class;
    protected string $title      = 'Scores';
    protected string $singular   = 'Score';
    protected string $baseRoute  = 'admin/scores';
    protected array $listColumns = ['round_id', 'player_id', 'group_id', 'nine', 'playing', 'paid', 'gross', 'net', 'place', 'winnings_cents'];
    protected array $filters     = ['round_id'];
    protected array $orderBy     = ['round_id' => 'DESC', 'player_id' => 'ASC'];

    protected function fields(): array
    {
        $fields = [
            'round_id'  => ['label' => 'Round', 'type' => 'select', 'options' => 'rounds', 'required' => true],
            'player_id' => ['label' => 'Player', 'type' => 'select', 'options' => 'players', 'required' => true],
            'group_id'  => ['label' => 'Group played in', 'type' => 'select', 'options' => 'groups'],
            'nine'      => ['label' => 'Nine played', 'type' => 'select', 'options' => ['front' => 'Front', 'back' => 'Back']],
            'playing'   => ['label' => 'Playing', 'type' => 'checkbox', 'default' => 1],
            'paid'      => ['label' => 'Paid', 'type' => 'checkbox'],
            'locked'    => ['label' => 'Locked', 'type' => 'checkbox'],
        ];
        for ($i = 1; $i <= 18; $i++) {
            $fields["hole{$i}"] = ['label' => "Hole {$i}", 'type' => 'number', 'compact' => 'holes'];
        }

        return $fields + [
            'gross'          => ['label' => 'Gross', 'type' => 'number', 'compact' => 'results', 'help' => 'Leave blank to total the nine played automatically.'],
            'handicap_used'  => ['label' => 'Handicap used', 'type' => 'number', 'compact' => 'results'],
            'net'            => ['label' => 'Net', 'type' => 'number', 'compact' => 'results'],
            'place'          => ['label' => 'Place', 'type' => 'number', 'compact' => 'results'],
            'winnings_cents' => ['label' => 'Winnings', 'type' => 'money', 'compact' => 'results'],
            'image'          => ['label' => 'Scorecard picture', 'help' => 'Picture uploads come later; for now a file name or link.'],
        ];
    }

    public function new(): string
    {
        $record             = ['playing' => 1];
        $record['round_id'] = $this->request->getGet('round_id');

        return $this->form($record, null);
    }

    /**
     * Fills in the league from the round, and gross/net when they are blank.
     */
    protected function collect(): array
    {
        $data  = parent::collect();
        $round = $data['round_id'] ? (new RoundModel())->find($data['round_id']) : null;
        $data['league_id'] = $round['league_id'] ?? Lookup::currentLeagueId();

        if ($data['gross'] === null) {
            $data['gross'] = ScoreModel::grossForNine($data);
        }
        if ($data['net'] === null && $data['gross'] !== null && $data['handicap_used'] !== null) {
            $data['net'] = (int) $data['gross'] - (int) $data['handicap_used'];
        }

        return $data;
    }

    protected function checkBeforeSave(array $data, ?int $id): ?string
    {
        $existing = (new ScoreModel())
            ->where('round_id', $data['round_id'])
            ->where('player_id', $data['player_id']);
        if ($id !== null) {
            $existing->where('id !=', $id);
        }

        return $existing->countAllResults() > 0
            ? 'This player already has a score for this round.'
            : null;
    }
}
