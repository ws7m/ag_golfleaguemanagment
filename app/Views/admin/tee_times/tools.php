<?php
// Create panels for the front and back nine, then the shift panel.
$courseOptions = $fields['course_id']['options'];
$onlyCourse    = count($courseOptions) === 1;
?>
<div class="panel-pair" id="create">
<?php foreach (['front' => 'Front nine', 'back' => 'Back nine'] as $nine => $label): ?>
    <section class="panel">
        <h2>Create <?= strtolower($label) ?> tee times</h2>
        <p class="muted">A starting time, then one every so many minutes. Times that already exist are skipped.</p>
        <form method="post" action="<?= site_url('admin/tee-times/generate') ?>" class="form">
            <?= csrf_field() ?>
            <input type="hidden" name="nine" value="<?= $nine ?>">
            <div class="field">
                <label for="gen_<?= $nine ?>_league">League</label>
                <select id="gen_<?= $nine ?>_league" name="league_id" required>
                    <?php foreach ($fields['league_id']['options'] as $key => $leagueName): ?>
                        <option value="<?= esc((string) $key, 'attr') ?>" <?= (string) ($filterValues['league_id'] ?? '') === (string) $key ? 'selected' : '' ?>><?= esc($leagueName) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="field">
                <label for="gen_<?= $nine ?>_course">Course</label>
                <select id="gen_<?= $nine ?>_course" name="course_id" required>
                    <option value="">—</option>
                    <?php foreach ($courseOptions as $key => $courseName): ?>
                        <option value="<?= esc((string) $key, 'attr') ?>"<?= $onlyCourse ? ' selected' : '' ?>><?= esc($courseName) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="grid">
                <div class="field">
                    <label for="gen_<?= $nine ?>_start">First tee time</label>
                    <input type="time" id="gen_<?= $nine ?>_start" name="start" required>
                </div>
                <div class="field">
                    <label for="gen_<?= $nine ?>_increment">Minutes apart</label>
                    <input type="number" id="gen_<?= $nine ?>_increment" name="increment" min="1" max="60" value="10" required>
                </div>
                <div class="field">
                    <label for="gen_<?= $nine ?>_count">How many</label>
                    <input type="number" id="gen_<?= $nine ?>_count" name="count" min="1" max="50" required>
                </div>
            </div>
            <button type="submit">Create <?= strtolower($label) ?> tee times</button>
        </form>
    </section>
<?php endforeach ?>
</div>

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

<section class="panel panel-danger" id="delete-tee-times">
    <h2>Delete all tee times</h2>
    <p class="muted">Removes every tee time in the league so you can start over. Groups that use them keep their players but lose their tee times until you set them again.</p>
    <form method="post" action="<?= site_url('admin/tee-times/delete-all') ?>" class="form inline"
          onsubmit="var s = this.league_id; return confirm('Delete all tee times in ' + s.options[s.selectedIndex].text + '? Groups will need their tee times set again. This cannot be undone.')">
        <?= csrf_field() ?>
        <label for="del_tt_league">League</label>
        <select id="del_tt_league" name="league_id" required>
            <?php foreach ($fields['league_id']['options'] as $key => $leagueName): ?>
                <option value="<?= esc((string) $key, 'attr') ?>" <?= (string) ($filterValues['league_id'] ?? '') === (string) $key ? 'selected' : '' ?>><?= esc($leagueName) ?></option>
            <?php endforeach ?>
        </select>
        <button type="submit" class="button-danger">Delete all tee times</button>
    </form>
</section>
