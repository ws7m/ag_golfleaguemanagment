<?php // TEMPORARY test data helper, to be removed later (see Players::addTestPlayers). ?>
<section class="panel" id="add-test-players">
    <h2>Add test players <span class="muted">(temporary)</span></h2>
    <p class="muted">Adds players with random common names, men and women. Men play from the white tees and women from the red
        tees, all in flight 2 with a handicap of 8. The login is the first initial and last name (Jim Jones is <strong>jjones</strong>,
        or jjones2 if that's taken) and the password is the login plus 12345 (<strong>jjones12345</strong>). Test players don't have
        to change their password when they log in.</p>
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
