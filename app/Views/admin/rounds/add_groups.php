<section class="panel">
    <h2>Add all groups to this round</h2>
    <p class="muted">Enters every player in the league's active groups into this round, with their group and the nine
        the group is playing (front or back, set on each group). They are marked as playing and not yet paid.
        Players already in the round and inactive players are skipped, so it is safe to press again after changing groups.
        See who is entered on the <a href="<?= site_url('admin/scores?round_id=' . $id) ?>">Scores</a> page.</p>
    <form method="post" action="<?= site_url("admin/rounds/{$id}/add-groups") ?>" class="form inline"
          onsubmit="return confirm('Add every player in the league\'s groups to this round?')">
        <?= csrf_field() ?>
        <button type="submit">Add all groups to this round</button>
    </form>
</section>
