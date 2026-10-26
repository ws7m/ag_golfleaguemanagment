<?php // TEMPORARY test data helper, to be removed later (see Scores::randomScores). ?>
<?php if (! empty($filterValues['round_id'])): ?>
    <section class="panel" id="random-scores">
        <h2>Fill in random scores <span class="muted">(temporary)</span></h2>
        <p class="muted">For testing. Gives every playing player in this round a random but reasonable score on each hole of their
            nine that hasn't been entered yet: mostly par or bogey from their tees, sometimes a birdie or a double bogey.
            Holes already entered and locked scores are kept. Save anything typed above first.</p>
        <form method="post" action="<?= site_url('admin/scores/random-scores') ?>" class="form inline"
              onsubmit="return confirm('Fill in random scores for every playing player in this round?')">
            <?= csrf_field() ?>
            <input type="hidden" name="round_id" value="<?= esc((string) $filterValues['round_id'], 'attr') ?>">
            <button type="submit">Fill in random scores</button>
        </form>
    </section>
<?php endif ?>
