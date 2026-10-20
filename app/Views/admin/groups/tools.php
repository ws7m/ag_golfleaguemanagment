<?php
// League pick list for both panels, starting on the league shown in the list.
$leagueOptions = static function (string $id) use ($fields, $filterValues): string {
    $html = '<select id="' . $id . '" name="league_id" required>';
    foreach ($fields['league_id']['options'] as $key => $label) {
        $selected = (string) ($filterValues['league_id'] ?? '') === (string) $key ? ' selected' : '';
        $html .= '<option value="' . esc((string) $key, 'attr') . '"' . $selected . '>' . esc($label) . '</option>';
    }

    return $html . '</select>';
};
?>
<section class="panel" id="create-groups">
    <h2>Create groups from tee times</h2>
    <p class="muted">Creates one group for each front nine tee time, in time order, and gives each the closest back nine tee time
        (one not already used by another group, where possible). Front nine tee times that already have a group are skipped.
        Add players to the groups afterwards.</p>
    <form method="post" action="<?= site_url('admin/groups/generate') ?>" class="form inline"
          onsubmit="return confirm('Create a group for each front nine tee time?')">
        <?= csrf_field() ?>
        <label for="gen_group_league">League</label>
        <?= $leagueOptions('gen_group_league') ?>
        <button type="submit">Create groups</button>
    </form>
</section>

<section class="panel panel-danger" id="delete-groups">
    <h2>Delete all groups</h2>
    <p class="muted">Removes every group in the league so you can start over. Scores that recorded one of these groups as the
        group played in are cleared of it first, so the groups can be deleted. Players and tee times are not deleted.</p>
    <form method="post" action="<?= site_url('admin/groups/delete-all') ?>" class="form inline"
          onsubmit="var s = this.league_id; return confirm('Delete all groups in ' + s.options[s.selectedIndex].text + '? This cannot be undone.')">
        <?= csrf_field() ?>
        <label for="del_group_league">League</label>
        <?= $leagueOptions('del_group_league') ?>
        <button type="submit" class="button-danger">Delete all groups</button>
    </form>
</section>
