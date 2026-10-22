<?php
/**
 * One tee time list (front or back), as it appears in the PDF and in the
 * preview: the heading, then one line per tee time with its players.
 *
 * @var string $heading
 * @var list<array{time: ?string, players: list<string>}> $rows
 */
?>
<div class="tt-doc">
    <h1 class="tt-heading"><?= esc($heading) ?></h1>
    <?php if ($rows === []): ?>
        <p class="tt-empty">No groups.</p>
    <?php else: ?>
        <table class="tt-lines">
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td class="tt-time"><?= esc($row['time'] === null ? '—' : tee_time_label($row['time'])) ?></td>
                    <td class="tt-players"><?= esc(implode(', ', $row['players'])) ?></td>
                </tr>
            <?php endforeach ?>
        </table>
    <?php endif ?>
</div>
