<section class="panel" id="generate">
    <h2>Generate rounds</h2>
    <p class="muted">Creates one round a week, starting on the league's starting date, named Round 1, Round 2, and so on.
        Leave the number of rounds blank to run to the league's ending date. Dates that already have a round are skipped.</p>
    <form method="post" action="<?= site_url('admin/rounds/generate') ?>" class="form inline"
          onsubmit="return confirm('Create weekly rounds for this league?')">
        <?= csrf_field() ?>
        <label for="gen_league">League</label>
        <select id="gen_league" name="league_id" required>
            <?php foreach ($fields['league_id']['options'] as $key => $label): ?>
                <option value="<?= esc((string) $key, 'attr') ?>" <?= (string) ($filterValues['league_id'] ?? '') === (string) $key ? 'selected' : '' ?>><?= esc($label) ?></option>
            <?php endforeach ?>
        </select>
        <label for="gen_course">Course</label>
        <select id="gen_course" name="course_id" required>
            <option value="">—</option>
            <?php foreach ($fields['course_id']['options'] as $key => $label): ?>
                <option value="<?= esc((string) $key, 'attr') ?>"<?= count($fields['course_id']['options']) === 1 ? ' selected' : '' ?>><?= esc($label) ?></option>
            <?php endforeach ?>
        </select>
        <label for="gen_game">Game</label>
        <select id="gen_game" name="game_id">
            <option value="">— set later —</option>
            <?php foreach ($fields['game_id']['options'] as $key => $label): ?>
                <option value="<?= esc((string) $key, 'attr') ?>"><?= esc($label) ?></option>
            <?php endforeach ?>
        </select>
        <label for="gen_count">Number of rounds</label>
        <input type="number" id="gen_count" name="count" min="1" max="52" placeholder="to end date" style="width: 8.5rem">
        <button type="submit">Generate rounds</button>
    </form>
</section>
