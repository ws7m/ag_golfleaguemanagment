<?php
/**
 * "Link Teams" under the round choice on the scores page, when the round's
 * game is a team game.
 *
 * @var array $filterValues
 */
$roundId = (int) ($filterValues['round_id'] ?? 0);
$game    = $roundId ? db_connect()->table('rounds r')->select('g.name, g.team_game')
    ->join('games g', 'g.id = r.game_id')->where('r.id', $roundId)->get()->getRowArray() : null;
?>
<?php if (! empty($game['team_game'])): ?>
    <form method="post" action="<?= site_url('admin/scores/link-teams') ?>" class="actions link-teams"
          onsubmit="return confirm('Set every player\'s team number in this round from the team members they chose? Players with no team members are set to 0.')">
        <?= csrf_field() ?>
        <input type="hidden" name="round_id" value="<?= $roundId ?>">
        <button type="submit">Link Teams</button>
        <span class="muted"><?= esc($game['name']) ?> is a team game. This sets each player's Team number from the team
            members players chose on their score entry page.</span>
    </form>
<?php endif ?>
