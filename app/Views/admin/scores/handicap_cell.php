<?php
/**
 * The handicap box for a score, part of the "hole-scores" form under the
 * list. Shows the handicap saved with the score, or the player's handicap if
 * none is saved yet (saved with the next Save scores). Typing a new one
 * updates the row's Net straight away; it is saved with Save scores.
 *
 * @var array $row A score
 */
$id       = (int) $row['id'];
$handicap = $row['handicap_used'] ?? $row['player_handicap'] ?? null;
?>
<input type="text" inputmode="numeric" pattern="-?[0-9]{1,2}" maxlength="3" class="hole-cell handicap-cell"
       form="hole-scores" name="scores[<?= $id ?>][handicap_used]"
       value="<?= $handicap === null ? '' : (int) $handicap ?>" placeholder="–"
       aria-label="Handicap" title="Handicap used for net"
       data-gross="<?= esc((string) ($row['gross'] ?? ''), 'attr') ?>" data-net="net-<?= $id ?>"
       oninput="var g = parseInt(this.dataset.gross, 10), h = parseInt(this.value, 10), n = document.getElementById(this.dataset.net); if (n) { n.textContent = isNaN(g) ? '' : String(isNaN(h) ? '' : g - h); }">
