<?php
/**
 * "Link Teams" and "Map Team Scores" under the round choice on the scores
 * page, when the round's game is a team game.
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
              onsubmit="return confirm('Set every player\'s team number in this round from the team members they chose? Players with no team members are set to 0.')">
            <?= csrf_field() ?>
            <input type="hidden" name="round_id" value="<?= $roundId ?>">
            <button type="submit">Link Teams</button>
        </form>
        <form method="post" action="<?= site_url('admin/scores/map-team-scores') ?>" class="row-form"
              onsubmit="return confirm('Give every team member their team\'s lowest score on each hole?')">
            <?= csrf_field() ?>
            <input type="hidden" name="round_id" value="<?= $roundId ?>">
            <button type="submit">Map Team Scores</button>
        </form>
        <span class="muted"><?= esc($game['name']) ?> is a team game. Link Teams sets each player's Team number from the team
            members players chose. Map Team Scores then gives everyone on a team the team's lowest score on each hole.</span>
    </div>
<?php endif ?>
