<?= view('admin/players/add_test', ['fields' => $fields, 'filterValues' => $filterValues]) ?>

<section class="panel panel-danger" id="delete-players">
    <h2>Delete all players</h2>
    <p class="muted">Deletes every player registered in the league so you can start over, and removes them from group rosters.
        Admins (including you) and players who already have scores are kept. This cannot be undone.</p>
    <form method="post" action="<?= site_url('admin/players/delete-all') ?>" class="form inline"
          onsubmit="var s = this.league_id; return confirm('Delete all players in ' + s.options[s.selectedIndex].text + '? Admins and players with scores are kept. This cannot be undone.')">
        <?= csrf_field() ?>
        <label for="del_players_league">League</label>
        <select id="del_players_league" name="league_id" required>
            <?php foreach ($fields['league_id']['options'] as $key => $label): ?>
                <option value="<?= esc((string) $key, 'attr') ?>" <?= (string) ($filterValues['league_id'] ?? '') === (string) $key ? 'selected' : '' ?>><?= esc($label) ?></option>
            <?php endforeach ?>
        </select>
        <button type="submit" class="button-danger">Delete all players</button>
    </form>
</section>
