<?php
/**
 * A score's net, with an id so the Handicap box on the row can update it.
 *
 * @var array $row A score
 */
?>
<span id="net-<?= (int) $row['id'] ?>"><?= esc((string) ($row['net'] ?? '')) ?></span>
