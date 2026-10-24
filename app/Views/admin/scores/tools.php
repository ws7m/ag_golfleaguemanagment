<form method="post" action="<?= site_url('admin/scores/save') ?>" id="hole-scores" class="actions">
    <?= csrf_field() ?>
    <input type="hidden" name="return_round_id" value="<?= esc((string) ($filterValues['round_id'] ?? ''), 'attr') ?>">
    <button type="submit">Save scores</button>
    <span class="muted">Type team numbers and each player's strokes in the boxes above (the grey number is the hole), then save.
        Players in a round with the same team number are a team; a blank team means an individual score. A blank hole means not entered yet.</span>
</form>

<?php if (! empty($filterValues['round_id'])): ?>
    <section class="panel" id="teams-from-groups">
        <h2>Teams = groups</h2>
        <p class="muted">Sets every player's team number in this round to their group number, so teams start out as the groups
            they tee off with. Players who aren't in a group are not changed. You can then change any player's team above.</p>
        <form method="post" action="<?= site_url('admin/scores/teams-from-groups') ?>" class="form inline"
              onsubmit="return confirm('Set every player\'s team in this round to their group number?')">
            <?= csrf_field() ?>
            <input type="hidden" name="round_id" value="<?= esc((string) $filterValues['round_id'], 'attr') ?>">
            <button type="submit">Teams = groups</button>
        </form>
    </section>
<?php endif ?>

<?php
$leagues = \App\Libraries\Lookup::leagues();
$current = \App\Libraries\Lookup::currentLeagueId();
?>
<section class="panel panel-danger" id="delete-scores">
    <h2>Delete all scores</h2>
    <p class="muted">Deletes every score for one round, or for every round in a league, so you can start over.
        This removes the players' entries for those rounds (playing, paid, hole scores and results). It cannot be undone.</p>
    <form method="post" action="<?= site_url('admin/scores/delete-all') ?>" class="form inline"
          onsubmit="var r = this.round_id, l = this.league_id; return confirm('Delete all scores for ' + (r.value ? 'the round ' + r.options[r.selectedIndex].text : 'every round in ' + l.options[l.selectedIndex].text) + '? This cannot be undone.')">
        <?= csrf_field() ?>
        <label for="del_scores_league">League</label>
        <select id="del_scores_league" name="league_id" required>
            <?php foreach ($leagues as $key => $label): ?>
                <option value="<?= esc((string) $key, 'attr') ?>" <?= (int) $key === (int) $current ? 'selected' : '' ?>><?= esc($label) ?></option>
            <?php endforeach ?>
        </select>
        <label for="del_scores_round">Round</label>
        <select id="del_scores_round" name="round_id">
            <option value="">All rounds in the league</option>
            <?php foreach ($fields['round_id']['options'] as $key => $label): ?>
                <option value="<?= esc((string) $key, 'attr') ?>" <?= (string) ($filterValues['round_id'] ?? '') === (string) $key ? 'selected' : '' ?>><?= esc($label) ?></option>
            <?php endforeach ?>
        </select>
        <button type="submit" class="button-danger">Delete scores</button>
    </form>
</section>
