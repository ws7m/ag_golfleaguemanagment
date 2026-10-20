<section class="panel">
    <h2>Shift tee times</h2>
    <p class="muted">Move every tee time in a league earlier or later, e.g. for daylight changes.</p>
    <form method="post" action="<?= site_url('admin/tee-times/shift') ?>" class="form inline"
          onsubmit="return confirm('Move all tee times in this league?')">
        <?= csrf_field() ?>
        <label for="shift_league">League</label>
        <select id="shift_league" name="league_id" required>
            <?php foreach ($fields['league_id']['options'] as $key => $label): ?>
                <option value="<?= esc((string) $key, 'attr') ?>" <?= (string) ($filterValues['league_id'] ?? '') === (string) $key ? 'selected' : '' ?>><?= esc($label) ?></option>
            <?php endforeach ?>
        </select>
        <label for="shift_minutes">Minutes</label>
        <input type="number" id="shift_minutes" name="minutes" step="1" required placeholder="e.g. -10 or 15">
        <button type="submit">Shift</button>
    </form>
</section>
