<?php

namespace App\Controllers\Admin;

use App\Models\LeagueModel;

class Leagues extends CrudController
{
    protected string $modelClass = LeagueModel::class;
    protected string $title      = 'Leagues';
    protected string $singular   = 'League';
    protected string $baseRoute  = 'admin/leagues';
    protected array $listColumns = ['name', 'start_date', 'end_date', 'active'];
    protected array $orderBy     = ['start_date' => 'DESC', 'id' => 'DESC'];
    protected ?string $extraFormView = 'admin/leagues/reset';

    // What has to be typed to confirm a reset.
    public const RESET_CONFIRMATION = 'RESET';

    protected function fields(): array
    {
        return [
            'name'        => ['label' => 'Name', 'required' => true],
            'description' => ['label' => 'Description', 'type' => 'textarea'],
            'start_date'  => ['label' => 'Starting date', 'type' => 'date'],
            'end_date'    => ['label' => 'Ending date', 'type' => 'date'],
            'website'     => ['label' => 'Website'],
            'email'       => ['label' => 'Email', 'type' => 'email'],
            'image'       => ['label' => 'Image', 'help' => 'Picture uploads come later; for now a file name or link.'],
            'active'      => ['label' => 'Active', 'type' => 'checkbox', 'default' => 1],
        ];
    }

    protected function rowActionsBeforeEdit(): array
    {
        return ['Notice' => 'admin/leagues/{id}/notice'];
    }

    protected function rowActions(): array
    {
        return ['Copy' => 'admin/leagues/{id}/copy'];
    }

    /**
     * Resets a league's data for testing: deletes its scores, groups,
     * rounds and registered players. The league itself, games,
     * courses and holes are kept. Players who are admins (or the logged-in
     * user), or who have scores or groups in another league, are kept too.
     */
    public function reset(int $id)
    {
        $league = $this->findOr404($id);
        $back   = redirect()->to("{$this->baseRoute}/{$id}/edit");
        if (trim((string) $this->request->getPost('confirm')) !== self::RESET_CONFIRMATION) {
            return $back->with('error', 'Nothing was deleted. Type ' . self::RESET_CONFIRMATION . ' in the box to confirm.');
        }

        $db      = db_connect();
        $counts  = [];
        $deleted = static function (string $table, array $where) use ($db): int {
            $db->table($table)->where($where)->delete();

            return $db->affectedRows();
        };

        $db->transStart();

        $counts['scores'] = $deleted('scores', ['league_id' => $id]);
        $counts['groups'] = $deleted('league_groups', ['league_id' => $id]);
        $counts['rounds'] = $deleted('rounds', ['league_id' => $id]);

        // Players registered in this league, except admins, the logged-in
        // user, and anyone still used by another league's scores or groups.
        $usedElsewhere = array_map('intval', array_column(
            $db->table('scores')->select('player_id')->distinct()->get()->getResultArray(),
            'player_id'
        ));
        foreach ($db->table('league_groups')->get()->getResultArray() as $group) {
            for ($i = 1; $i <= 6; $i++) {
                if (! empty($group["player{$i}_id"])) {
                    $usedElsewhere[] = (int) $group["player{$i}_id"];
                }
            }
        }
        $kept    = 0;
        $players = [];
        foreach ($db->table('players')->where('league_id', $id)->get()->getResultArray() as $player) {
            $playerId = (int) $player['id'];
            if ($player['is_admin'] || $playerId === (int) session('admin_id') || in_array($playerId, $usedElsewhere, true)) {
                $kept++;
            } else {
                $players[] = $playerId;
            }
        }
        if ($players !== []) {
            $db->table('players')->whereIn('id', $players)->delete();
        }
        $counts['players'] = count($players);

        $db->transComplete();

        $parts = [];
        foreach ($counts as $what => $count) {
            $parts[] = "{$count} {$what}";
        }
        $message = "{$league['name']} was reset: deleted " . implode(', ', $parts) . '.';
        if ($kept > 0) {
            $message .= " {$kept} players were kept (admins, or players used in another league).";
        }

        return $back->with('message', $message . ' Courses, holes and games were not changed.');
    }

    /**
     * The league notice page: one text box for the message shown on the
     * login screen. Kept off the main league form so it is quick to change.
     */
    public function notice(int $id): string
    {
        return view('admin/leagues/notice', [
            'title'  => 'League Notice',
            'league' => $this->findOr404($id),
            'errors' => [],
        ]);
    }

    public function saveNotice(int $id)
    {
        $league = $this->findOr404($id);
        $notice = trim((string) $this->request->getPost('notice'));
        $model  = $this->newModel();

        if (! $model->update($id, ['id' => $id, 'name' => $league['name'], 'notice' => $notice === '' ? null : $notice])) {
            return view('admin/leagues/notice', [
                'title'  => 'League Notice',
                'league' => ['notice' => $notice] + $league,
                'errors' => $model->errors(),
            ]);
        }

        return redirect()->to($this->baseRoute)
            ->with('message', $notice === '' ? "Notice cleared for {$league['name']}." : "Notice saved for {$league['name']}.");
    }

    /**
     * Starts a new league form filled in from an existing league.
     */
    public function copy(int $id): string
    {
        $record = $this->findOr404($id);
        unset($record['id'], $record['start_date'], $record['end_date']);
        $record['name']   = 'Copy of ' . $record['name'];
        $record['active'] = 1;

        return $this->form($record, null);
    }
}
