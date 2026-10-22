<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<div class="page-head no-print">
    <h1>Tee time list</h1>
    <span class="head-actions">
        <a class="button button-secondary" href="<?= site_url('admin/groups' . ($league ? '?league_id=' . $league['id'] : '')) ?>">Back to groups</a>
        <button type="button" onclick="window.print()">Print</button>
    </span>
</div>

<form method="get" action="<?= site_url('admin/groups/tee-sheet') ?>" class="filters no-print">
    <label for="sheet_league">League</label>
    <select id="sheet_league" name="league_id" onchange="this.form.submit()">
        <?php foreach ($leagues as $key => $label): ?>
            <option value="<?= esc((string) $key, 'attr') ?>" <?= $league && (int) $key === (int) $league['id'] ? 'selected' : '' ?>><?= esc($label) ?></option>
        <?php endforeach ?>
    </select>
    <noscript><button type="submit">Show</button></noscript>
</form>

<?php if ($league): ?><p class="print-only"><strong><?= esc($league['name']) ?></strong></p><?php endif ?>

<?php foreach (['front' => 'Front', 'back' => 'Back'] as $nine => $heading): ?>
    <section class="tee-sheet">
        <h2><?= $heading ?></h2>
        <?php if ($sections[$nine] === []): ?>
            <p class="muted">No groups are playing the <?= $nine ?> nine.</p>
        <?php else: ?>
            <table>
                <thead>
                <tr><th>Tee time</th><th>Players</th></tr>
                </thead>
                <tbody>
                <?php foreach ($sections[$nine] as $row): ?>
                    <tr>
                        <td class="tee-sheet-time"><?= esc($row['time'] ?? '—') ?></td>
                        <td>
                            <?php foreach ($row['players'] as $name): ?>
                                <div><?= esc($name) ?></div>
                            <?php endforeach ?>
                            <?php if ($row['players'] === []): ?><span class="muted">No players</span><?php endif ?>
                        </td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        <?php endif ?>
    </section>
<?php endforeach ?>
<?= $this->endSection() ?>
