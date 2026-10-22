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
<section class="panel" id="switch-nine">
    <h2>Switch front/back for all groups</h2>
    <p class="muted">Every group in the league swaps to the other nine: groups playing the front nine move to the back nine,
        and back nine groups move to the front. Use it between rounds.</p>
    <form method="post" action="<?= site_url('admin/groups/switch-nine') ?>" class="form inline"
          onsubmit="var s = this.league_id; return confirm('Switch every group in ' + s.options[s.selectedIndex].text + ' to the other nine?')">
        <?= csrf_field() ?>
        <label for="switch_league">League</label>
        <?= $leagueOptions('switch_league') ?>
        <button type="submit">Switch front/back</button>
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
