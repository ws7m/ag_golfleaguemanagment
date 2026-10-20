<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<h1>Holes: <?= esc($course['name']) ?></h1>
<p class="muted">Yardage, par and hole handicap (1–18) for each tee. Leave a box blank if it doesn't apply.</p>

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
                    <th colspan="3" class="tee-<?= $tee ?>"><?= ucfirst($tee) ?></th>
                <?php endforeach ?>
            </tr>
            <tr>
                <?php foreach (\App\Controllers\Admin\Holes::TEES as $tee): ?>
                    <th>Yds</th><th>Par</th><th>Hcp</th>
                <?php endforeach ?>
            </tr>
            </thead>
            <tbody>
            <?php for ($n = 1; $n <= 18; $n++): ?>
                <?php if ($n === 10): ?><tr class="divider"><td colspan="10">Back nine</td></tr><?php endif ?>
                <tr<?= isset($errors[$n]) ? ' class="has-error"' : '' ?>>
                    <th><?= $n ?></th>
                    <?php foreach (\App\Controllers\Admin\Holes::TEES as $tee): ?>
                        <?php foreach (\App\Controllers\Admin\Holes::STATS as $stat): ?>
                            <td><input type="number" inputmode="numeric" class="cell"
                                       name="holes[<?= $n ?>][<?= $tee ?>_<?= $stat ?>]"
                                       aria-label="Hole <?= $n ?> <?= $tee ?> <?= $stat ?>"
                                       value="<?= esc((string) ($holes[$n]["{$tee}_{$stat}"] ?? ''), 'attr') ?>"></td>
                        <?php endforeach ?>
                    <?php endforeach ?>
                </tr>
            <?php endfor ?>
            </tbody>
        </table>
    </div>
    <div class="actions">
        <button type="submit">Save holes</button>
        <a href="<?= site_url('admin/courses') ?>">Back to courses</a>
    </div>
</form>
<?= $this->endSection() ?>
