<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<div class="page-head">
    <h1>Tee time list</h1>
    <a class="button button-secondary" href="<?= site_url('admin/groups' . ($league ? '?league_id=' . $league['id'] : '')) ?>">Back to groups</a>
</div>

<form method="get" action="<?= site_url('admin/groups/tee-sheet') ?>" class="filters">
    <label for="sheet_league">League</label>
    <select id="sheet_league" name="league_id" onchange="this.form.submit()">
        <?php foreach ($leagues as $key => $label): ?>
            <option value="<?= esc((string) $key, 'attr') ?>" <?= $league && (int) $key === (int) $league['id'] ? 'selected' : '' ?>><?= esc($label) ?></option>
        <?php endforeach ?>
    </select>
    <label for="sheet_date">Date</label>
    <input type="date" id="sheet_date" name="date" value="<?= esc($isoDate, 'attr') ?>" required style="width: auto" onchange="this.form.submit()">
    <noscript><button type="submit">Update</button></noscript>
</form>
<p class="muted">The date is shown in each list's heading and used in the PDF file name.</p>

<div class="panel-pair">
<?php foreach (['front' => 'Front', 'back' => 'Back'] as $nine => $label): ?>
    <section class="panel">
        <div class="page-head">
            <h2><?= $label ?></h2>
            <a class="button" href="<?= site_url('admin/groups/tee-sheet/pdf?' . http_build_query(['league_id' => $league['id'] ?? '', 'nine' => $nine, 'date' => $isoDate])) ?>">
                Download PDF</a>
        </div>
        <p class="muted"><?= esc(\App\Controllers\Admin\Groups::teeSheetFilename($nine, $date)) ?></p>
        <div class="tt-preview">
            <?= view('admin/groups/tee_sheet_doc', [
                'heading' => \App\Controllers\Admin\Groups::teeSheetHeading($league['name'] ?? '', $nine, $date),
                'rows'    => $sections[$nine],
            ]) ?>
        </div>
    </section>
<?php endforeach ?>
</div>
<?= $this->endSection() ?>
