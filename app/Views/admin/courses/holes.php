<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<h1>Holes: <?= esc($course['name']) ?></h1>
<p class="muted">Yardage and par for each tee, then the hole's handicap (Hcp, 1–18, each number used once), Net and Adj. Leave a box blank if it doesn't apply.</p>

<?php if ($errors !== []): ?>
    <div class="flash flash-error" role="alert">
        <?php foreach ($errors as $number => $error): ?><p>Hole <?= (int) $number ?>: <?= esc($error) ?></p><?php endforeach ?>
    </div>
<?php endif ?>

<form method="post" action="<?= site_url("admin/courses/{$course['id']}/holes") ?>">
    <?= csrf_field() ?>
    <div class="table-wrap">
        <table class="holes">
            <thead>
            <tr>
                <th rowspan="2">Hole</th>
                <?php foreach (\App\Controllers\Admin\Holes::TEES as $tee): ?>
                    <th colspan="2" class="tee-<?= $tee ?>"><?= ucfirst($tee) ?></th>
                <?php endforeach ?>
                <?php foreach (\App\Controllers\Admin\Holes::HOLE_FIELDS as $label): ?>
                    <th rowspan="2"><?= $label ?></th>
                <?php endforeach ?>
            </tr>
            <tr>
                <?php foreach (\App\Controllers\Admin\Holes::TEES as $tee): ?>
                    <th>Yds</th><th>Par</th>
                <?php endforeach ?>
            </tr>
            </thead>
            <tbody>
            <?php
            $tees  = \App\Controllers\Admin\Holes::TEES;
            $stats      = \App\Controllers\Admin\Holes::STATS;
            $holeFields = \App\Controllers\Admin\Holes::HOLE_FIELDS;
            // Sum of yardage or par over a range of holes; blank when nothing is entered.
            $total = static function (int $from, int $to, string $key) use ($holes): string {
                $sum = 0;
                $any = false;
                for ($h = $from; $h <= $to; $h++) {
                    $value = $holes[$h][$key] ?? '';
                    if (is_numeric($value)) {
                        $sum += (int) $value;
                        $any = true;
                    }
                }

                return $any ? (string) $sum : '';
            };
            // A totals row: yardage and par for each tee; Hcp, Net and Adj aren't totalled.
            $totalsRow = static function (string $label, string $range, int $from, int $to) use ($tees, $stats, $holeFields, $total): string {
                $html = '<tr class="totals totals-' . $range . '"><th>' . $label . '</th>';
                foreach ($tees as $tee) {
                    foreach ($stats as $stat) {
                        $html .= '<td data-total="' . $tee . '_' . $stat . '" data-range="' . $range . '">' . $total($from, $to, "{$tee}_{$stat}") . '</td>';
                    }
                }

                return $html . str_repeat('<td></td>', count($holeFields)) . '</tr>';
            };
            ?>
            <?php for ($n = 1; $n <= 18; $n++): ?>
                <?php if ($n === 10): ?><tr class="divider"><td colspan="10">Back nine</td></tr><?php endif ?>
                <tr<?= isset($errors[$n]) ? ' class="has-error"' : '' ?>>
                    <th><?= $n ?></th>
                    <?php foreach ($tees as $tee): ?>
                        <?php foreach ($stats as $stat): ?>
                            <td><input type="number" inputmode="numeric" class="cell"
                                       name="holes[<?= $n ?>][<?= $tee ?>_<?= $stat ?>]"
                                       data-hole="<?= $n ?>" data-key="<?= $tee ?>_<?= $stat ?>"
                                       aria-label="Hole <?= $n ?> <?= $tee ?> <?= $stat ?>"
                                       value="<?= esc((string) ($holes[$n]["{$tee}_{$stat}"] ?? ''), 'attr') ?>"></td>
                        <?php endforeach ?>
                    <?php endforeach ?>
                    <?php foreach ($holeFields as $key => $label): ?>
                        <td><input type="number" inputmode="numeric" class="cell"
                                   name="holes[<?= $n ?>][<?= $key ?>]"
                                   <?= $key === 'handicap' ? 'min="1" max="18" step="1"' : 'step="1"' ?>
                                   aria-label="Hole <?= $n ?> <?= $label ?>"
                                   value="<?= esc((string) ($holes[$n][$key] ?? ''), 'attr') ?>"></td>
                    <?php endforeach ?>
                </tr>
                <?php if ($n === 9): ?><?= $totalsRow('Front 9', 'front', 1, 9) ?><?php endif ?>
                <?php if ($n === 18): ?><?= $totalsRow('Back 9', 'back', 10, 18) ?><?= $totalsRow('Total', 'all', 1, 18) ?><?php endif ?>
            <?php endfor ?>
            </tbody>
        </table>
    </div>
    <div class="actions">
        <button type="submit">Save holes</button>
        <a href="<?= site_url('admin/courses') ?>">Back to courses</a>
    </div>
</form>
<script>
// Keep the front 9, back 9 and total rows up to date while typing.
(function () {
    var table = document.querySelector('table.holes');
    var ranges = { front: [1, 9], back: [10, 18], all: [1, 18] };
    function update() {
        table.querySelectorAll('[data-total]').forEach(function (cell) {
            var range = ranges[cell.dataset.range];
            var sum = 0, any = false;
            table.querySelectorAll('input[data-key="' + cell.dataset.total + '"]').forEach(function (input) {
                var hole = Number(input.dataset.hole);
                if (hole >= range[0] && hole <= range[1] && input.value !== '' && !isNaN(input.value)) {
                    sum += Number(input.value);
                    any = true;
                }
            });
            cell.textContent = any ? sum : '';
        });
    }
    table.addEventListener('input', update);
})();
</script>
<?= $this->endSection() ?>
