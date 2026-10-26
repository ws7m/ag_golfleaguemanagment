<form method="post" action="<?= site_url('admin/scores/save') ?>" id="hole-scores" class="actions" onsubmit="packScores(this)">
    <?= csrf_field() ?>
    <input type="hidden" name="return_round_id" value="<?= esc((string) ($filterValues['round_id'] ?? ''), 'attr') ?>">
    <input type="hidden" name="scores_json" value="">
    <button type="submit">Save scores</button>
    <span class="muted">Type team numbers, each player's strokes (the grey number is the hole) and handicaps in the boxes above, then save.
        Players in a round with the same team number are a team; a blank team means an individual score. A blank hole means not entered yet.
        The Hcp box starts with the player's handicap; Net is gross minus Hcp.</span>
</form>
<script>
    // A big round has more boxes (about 11 per player) than PHP accepts as
    // separate form values (max_input_vars, 1000 by default), so the boxes
    // are sent together as one value. Without JavaScript they're sent one
    // by one, which works for smaller rounds.
    function packScores(form) {
        var scores = {};
        var boxes = document.querySelectorAll('[form="hole-scores"][name^="scores["]');
        boxes.forEach(function (box) {
            var m = box.name.match(/^scores\[(\d+)\]\[(\w+)\]$/);
            if (! m) {
                return;
            }
            (scores[m[1]] = scores[m[1]] || {})[m[2]] = box.value;
            box.disabled = true;
        });
        form.elements.scores_json.value = JSON.stringify(scores);
    }
    // Coming back with the browser's Back button: make the boxes usable again.
    window.addEventListener('pageshow', function () {
        document.querySelectorAll('[form="hole-scores"][name^="scores["]').forEach(function (box) { box.disabled = false; });
    });
</script>

<?= view('admin/scores/random_scores', ['filterValues' => $filterValues]) ?>

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
