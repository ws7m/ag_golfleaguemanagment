<?php
/**
 * "Link Teams", "Teams = groups" and "Map Team Scores" under the round
 * choice on the scores page, when the round's game is a team game.
 *
 * @var array $filterValues
 */
$roundId = (int) ($filterValues['round_id'] ?? 0);
$game    = $roundId ? db_connect()->table('rounds r')->select('g.name, g.team_game')
    ->join('games g', 'g.id = r.game_id')->where('r.id', $roundId)->get()->getRowArray() : null;
?>
<?php if (! empty($game['team_game'])): ?>
    <div class="actions link-teams">
        <form method="post" action="<?= site_url('admin/scores/link-teams') ?>" class="row-form"
              onsubmit="return confirm('Put players who aren\'t on a team yet on teams from the team members they chose?')">
            <?= csrf_field() ?>
            <input type="hidden" name="round_id" value="<?= $roundId ?>">
            <button type="submit">Link Teams</button>
        </form>
        <form method="post" action="<?= site_url('admin/scores/teams-from-groups') ?>" class="row-form"
              onsubmit="return confirm('Put players who aren\'t on a team yet on a team with their group?')">
            <?= csrf_field() ?>
            <input type="hidden" name="round_id" value="<?= $roundId ?>">
            <button type="submit">Teams = groups</button>
        </form>
        <form method="post" action="<?= site_url('admin/scores/map-team-scores') ?>" class="row-form"
              onsubmit="return confirm('Give every team member their team\'s lowest score on each hole?')">
            <?= csrf_field() ?>
            <input type="hidden" name="round_id" value="<?= $roundId ?>">
            <button type="submit">Map Team Scores</button>
        </form>
    </div>
<?php endif ?>
