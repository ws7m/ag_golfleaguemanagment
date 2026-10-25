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

<div class="panel-pair" id="shift-tee-times">
<?php foreach (['front' => 'Front nine', 'back' => 'Back nine'] as $nine => $nineLabel): ?>
    <section class="panel">
        <h2>Shift <?= strtolower($nineLabel) ?> tee times</h2>
        <p class="muted">Moves every group's <?= strtolower($nineLabel) ?> tee time earlier or later, e.g. for daylight changes.
            Use a negative number to move earlier.</p>
        <form method="post" action="<?= site_url('admin/groups/shift-tee-times') ?>" class="form inline"
              onsubmit="return confirm('Move every group\'s <?= strtolower($nineLabel) ?> tee time by ' + this.minutes.value + ' minutes?')">
            <?= csrf_field() ?>
            <input type="hidden" name="nine" value="<?= $nine ?>">
            <label for="shift_<?= $nine ?>_league">League</label>
            <?= $leagueOptions("shift_{$nine}_league") ?>
            <label for="shift_<?= $nine ?>_minutes">Minutes</label>
            <input type="number" id="shift_<?= $nine ?>_minutes" name="minutes" step="1" required placeholder="e.g. -10 or 15" style="width: 8rem">
            <button type="submit">Shift</button>
        </form>
    </section>
<?php endforeach ?>
</div>

<section class="panel" id="create-groups">
    <h2>Create groups for registered players</h2>
    <p class="muted">Works out how many groups the league needs for its registered players at
        <?= \App\Controllers\Admin\Groups::PLAYERS_PER_GROUP ?> players to a group (8 players need 2 groups, 9 need 3), and
        creates the ones that are missing. Groups the league already has count towards that. New groups are numbered after
        the existing ones, with no tee times (set those on each group). The first half of the new groups start on the front
        nine and the second half on the back nine.
        Then it puts every player who isn't in a group yet into one. Players who listed desired group members (first and last
        names, separated by commas) go in the same group as those players where there's room; everyone else fills the places
        left. Players already in a group are not moved.</p>
    <form method="post" action="<?= site_url('admin/groups/generate') ?>" class="form inline"
          onsubmit="return confirm('Create the groups needed for this league\'s registered players and put players into them?')">
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
