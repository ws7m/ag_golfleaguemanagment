<?php $word = \App\Controllers\Admin\Leagues::RESET_CONFIRMATION; ?>
<section class="panel panel-danger" id="reset-league">
    <h2>Reset this league's data</h2>
    <p class="muted">For testing. Deletes all of this league's <strong>scores, groups (with their tee times), rounds and registered players</strong>.
        The league itself, golf courses, holes and games are not changed. Admins (including you), and players who are used in another
        league's scores or groups, are kept. This cannot be undone.</p>
    <form method="post" action="<?= site_url("admin/leagues/{$id}/reset") ?>" class="form inline"
          onsubmit="return confirm('Delete all scores, groups, rounds and players in this league? This cannot be undone.')">
        <?= csrf_field() ?>
        <label for="reset_confirm">Type <?= $word ?> to confirm</label>
        <input type="text" id="reset_confirm" name="confirm" autocomplete="off" required pattern="<?= $word ?>" style="width: 8rem">
        <button type="submit" class="button-danger">Reset league data</button>
    </form>
</section>
