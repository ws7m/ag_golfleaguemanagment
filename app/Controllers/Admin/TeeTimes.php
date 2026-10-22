<?php

namespace App\Controllers\Admin;

use App\Models\TeeTimeModel;

class TeeTimes extends CrudController
{
    protected string $modelClass     = TeeTimeModel::class;
    protected string $title          = 'Tee times';
    protected string $singular       = 'Tee time';
    protected string $baseRoute      = 'admin/tee-times';
    // No league column: the list is filtered by league at the top.
    protected array $listColumns     = ['course_id', 'nine', 'tee_time', 'group'];
    protected array $filters         = ['league_id'];
    protected array $orderBy         = ['nine' => 'DESC', 'tee_time' => 'ASC'];
    protected ?string $extraIndexView = 'admin/tee_times/tools';
    protected array $headActions      = ['Create tee times' => '#create', 'Delete all tee times' => '#delete-tee-times'];

    protected function listFields(): array
    {
        return ['group' => ['label' => 'Group']];
    }

    /**
     * Adds the group(s) assigned to each tee time, or "None".
     * Only active groups count.
     */
    protected function listRows(array $rows, array $filterValues): array
    {
        $groups = db_connect()->table('league_groups')
            ->select('group_number, front_tee_time_id, back_tee_time_id')
            ->where('active', 1)
            ->orderBy('group_number')
            ->get()->getResultArray();

        $byTeeTime = [];
        foreach ($groups as $group) {
            foreach (['front_tee_time_id', 'back_tee_time_id'] as $column) {
                if (! empty($group[$column])) {
                    $byTeeTime[(int) $group[$column]][] = 'Group ' . $group['group_number'];
                }
            }
        }

        foreach ($rows as &$row) {
            $row['group'] = implode(', ', $byTeeTime[(int) $row['id']] ?? ['None']);
        }

        return $rows;
    }

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
     * Creates a run of tee times for one nine: a starting time, then one every
     * so many minutes. Times the league already has for that nine are skipped.
     */
    public function generate()
    {
        $leagueId  = (int) $this->request->getPost('league_id');
        $courseId  = (int) $this->request->getPost('course_id');
        $nine      = (string) $this->request->getPost('nine');
        $start     = (string) $this->request->getPost('start');
        $increment = (int) $this->request->getPost('increment');
        $count     = (int) $this->request->getPost('count');
        $back      = redirect()->to($this->baseRoute . '?league_id=' . $leagueId);

        if ($leagueId <= 0 || $courseId <= 0 || ! in_array($nine, ['front', 'back'], true)) {
            return $back->with('error', 'Choose a league and a course.');
        }
        if (! preg_match('/^([01][0-9]|2[0-3]):([0-5][0-9])$/', $start, $m)) {
            return $back->with('error', 'Enter a starting tee time.');
        }
        if ($increment < 1 || $increment > 60 || $count < 1 || $count > 50) {
            return $back->with('error', 'The increment must be 1 to 60 minutes and the number of tee times 1 to 50.');
        }

        $first = (int) $m[1] * 60 + (int) $m[2];
        $last  = $first + $increment * ($count - 1);
        if ($last >= 24 * 60) {
            return $back->with('error', 'Those tee times would run past midnight. Nothing was created.');
        }

        $model    = new TeeTimeModel();
        $existing = array_column($model->where('league_id', $leagueId)->where('nine', $nine)->findAll(), 'tee_time');
        $created  = 0;
        $skipped  = 0;

        $db = db_connect();
        $db->transStart();
        for ($minutes = $first; $minutes <= $last; $minutes += $increment) {
            $time = sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
            if (in_array($time, $existing, true)) {
                $skipped++;

                continue;
            }
            $model->insert(['league_id' => $leagueId, 'course_id' => $courseId, 'nine' => $nine, 'tee_time' => $time]);
            $created++;
        }
        $db->transComplete();

        $message = "{$created} {$nine} nine tee times created.";
        if ($skipped > 0) {
            $message .= " {$skipped} skipped because they already exist.";
        }

        return $back->with('message', $message);
    }

    /**
     * Deletes every tee time in a league so the admin can start over. Groups
     * that used those tee times have their front/back tee time cleared.
     */
    public function deleteAll()
    {
        $leagueId = (int) $this->request->getPost('league_id');
        $back     = redirect()->to($this->baseRoute . '?league_id=' . $leagueId);
        if ($leagueId <= 0) {
            return $back->with('error', 'Choose a league.');
        }

        $ids = array_map('intval', array_column(
            (new TeeTimeModel())->select('id')->where('league_id', $leagueId)->findAll(),
            'id'
        ));
        if ($ids === []) {
            return $back->with('message', 'There were no tee times to delete.');
        }

        $db     = db_connect();
        $groups = $db->table('league_groups');
        $db->transStart();
        $cleared = 0;
        foreach (['front_tee_time_id', 'back_tee_time_id'] as $column) {
            $groups->whereIn($column, $ids)->update([$column => null]);
            $cleared += $db->affectedRows();
        }
        (new TeeTimeModel())->whereIn('id', $ids)->delete();
        $db->transComplete();

        $message = count($ids) . ' tee times deleted.';
        if ($cleared > 0) {
            $message .= ' Groups that used them need their tee times set again.';
        }

        return $back->with('message', $message);
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
