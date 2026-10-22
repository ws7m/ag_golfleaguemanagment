<?php
/**
 * Small boxes for the nine holes a score is playing (front if not set yet),
 * part of the "hole-scores" form under the list. 0 shows as blank.
 *
 * @var array $row A score
 */
$nine = $row['nine'] ?: 'front';
?>
<span class="hole-cells">
    <?php foreach (\App\Models\ScoreModel::holesForNine($nine) as $hole): ?>
        <?php $value = (int) ($row["hole{$hole}"] ?? 0); ?>
        <input type="text" inputmode="numeric" pattern="[0-9]{0,2}" maxlength="2" class="hole-cell"
               form="hole-scores" name="scores[<?= (int) $row['id'] ?>][hole<?= $hole ?>]"
               value="<?= $value > 0 ? $value : '' ?>" placeholder="<?= $hole ?>"
               aria-label="Hole <?= $hole ?>" title="Hole <?= $hole ?>">
    <?php endforeach ?>
</span>
