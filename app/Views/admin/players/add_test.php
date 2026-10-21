<?php // TEMPORARY test data helper, to be removed later (see Players::addTestPlayers). ?>
<section class="panel" id="add-test-players">
    <h2>Add test players <span class="muted">(temporary)</span></h2>
    <p class="muted">Adds players named Player1 / 1, Player2 / 2, and so on, continuing after the highest PlayerN already saved.
        Each logs in with <strong>playerN</strong> and password <strong>playerN</strong> (e.g. player3 / player3).</p>
    <form method="post" action="<?= site_url('admin/players/add-test') ?>" class="form inline"
          onsubmit="return confirm('Add ' + this.count.value + ' test players?')">
        <?= csrf_field() ?>
        <label for="test_league">League</label>
        <select id="test_league" name="league_id" required>
            <?php foreach ($fields['league_id']['options'] as $key => $label): ?>
                <option value="<?= esc((string) $key, 'attr') ?>" <?= (string) ($filterValues['league_id'] ?? '') === (string) $key ? 'selected' : '' ?>><?= esc($label) ?></option>
            <?php endforeach ?>
        </select>
        <label for="test_count">How many</label>
        <input type="number" id="test_count" name="count" min="1" max="200" value="4" required style="width: 6rem">
        <button type="submit">Add test players</button>
    </form>
</section>
