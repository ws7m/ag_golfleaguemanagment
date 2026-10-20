<?php

namespace App\Controllers\Admin;

use App\Models\TeeTimeModel;

class TeeTimes extends CrudController
{
    protected string $modelClass     = TeeTimeModel::class;
    protected string $title          = 'Tee times';
    protected string $singular       = 'Tee time';
    protected string $baseRoute      = 'admin/tee-times';
    protected array $listColumns     = ['league_id', 'course_id', 'nine', 'tee_time'];
    protected array $filters         = ['league_id'];
    protected array $orderBy         = ['nine' => 'DESC', 'tee_time' => 'ASC'];
    protected ?string $extraIndexView = 'admin/tee_times/shift';

    protected function fields(): array
    {
        return [
            'league_id' => ['label' => 'League', 'type' => 'select', 'options' => 'leagues', 'default' => 'currentLeague', 'required' => true],
            'course_id' => ['label' => 'Course', 'type' => 'select', 'options' => 'courses', 'required' => true],
            'nine'      => ['label' => 'Front or back', 'type' => 'select', 'options' => ['front' => 'Front', 'back' => 'Back'], 'required' => true],
            'tee_time'  => ['label' => 'Time', 'type' => 'time', 'required' => true],
        ];
    }

    /**
     * Moves every tee time in a league earlier or later by a number of minutes.
     */
    public function shift()
    {
        $leagueId = (int) $this->request->getPost('league_id');
        $minutes  = (int) $this->request->getPost('minutes');
        $back     = redirect()->to($this->baseRoute . '?league_id=' . $leagueId);

        if ($leagueId <= 0 || $minutes === 0) {
            return $back->with('error', 'Choose a league and a number of minutes other than 0.');
        }

        $model = new TeeTimeModel();
        $rows  = $model->where('league_id', $leagueId)->findAll();
        $moved = [];
        foreach ($rows as $row) {
            [$h, $m] = array_map('intval', explode(':', $row['tee_time']));
            $total   = $h * 60 + $m + $minutes;
            if ($total < 0 || $total >= 24 * 60) {
                return $back->with('error', "Shifting by {$minutes} minutes would move {$row['tee_time']} past midnight. Nothing was changed.");
            }
            $moved[$row['id']] = sprintf('%02d:%02d', intdiv($total, 60), $total % 60);
        }

        $db = db_connect();
        $db->transStart();
        foreach ($moved as $id => $time) {
            $model->update($id, ['tee_time' => $time]);
        }
        $db->transComplete();

        $direction = $minutes > 0 ? 'later' : 'earlier';

        return $back->with('message', count($moved) . ' tee times moved ' . abs($minutes) . " minutes {$direction}.");
    }
}
