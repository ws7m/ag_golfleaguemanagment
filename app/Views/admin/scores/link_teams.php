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
    <?php // Each button sends the scores typed in the list too (the "hole-scores" form), so they're saved first. ?>
    <div class="actions link-teams">
        <button type="submit" form="hole-scores" name="then" value="link_teams"
                onclick="return confirm('Save the scores typed below, then put players who aren\'t on a team yet on teams from the team members they chose?')">Link Teams</button>
        <button type="submit" form="hole-scores" name="then" value="teams_from_groups"
                onclick="return confirm('Save the scores typed below, then put players who aren\'t on a team yet on a team with their group?')">Teams = groups</button>
        <button type="submit" form="hole-scores" name="then" value="map_team_scores"
                onclick="return confirm('Save the scores typed below, then give every team member their team\'s lowest score on each hole?')">Map Team Scores</button>
    </div>
<?php endif ?>
