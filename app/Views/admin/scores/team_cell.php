<?php
/**
 * The team number box for a score, part of the "hole-scores" form under the
 * list. 0 (individual) shows as blank.
 *
 * @var array $row A score
 */
$team = (int) ($row['team_number'] ?? 0);
?>
<input type="text" inputmode="numeric" pattern="[0-9]{0,2}" maxlength="2" class="hole-cell team-cell"
       form="hole-scores" name="scores[<?= (int) $row['id'] ?>][team_number]"
       value="<?= $team > 0 ? $team : '' ?>" placeholder="–"
       aria-label="Team number" title="Team number (blank = individual)">
