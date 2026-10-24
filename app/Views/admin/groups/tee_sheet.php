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
    <label for="sheet_round">Round</label>
    <?php if ($rounds === []): ?>
        <span class="muted">This league has no rounds yet.</span>
    <?php else: ?>
        <select id="sheet_round" name="round_id" onchange="this.form.submit()">
            <?php foreach ($rounds as $key => $label): ?>
                <option value="<?= $key ?>" <?= $round && (int) $key === (int) $round['id'] ? 'selected' : '' ?>><?= esc($label) ?></option>
            <?php endforeach ?>
        </select>
    <?php endif ?>
    <noscript><button type="submit">Update</button></noscript>
</form>
<p class="muted">The round's date is shown in each list's heading and used in the PDF file name.
    Players who said they are not playing in the round are left off, and their place shows as Open.</p>

<form method="post" action="<?= site_url('admin/groups/tee-sheet/email') ?>" id="tee-email">
    <?= csrf_field() ?>
    <input type="hidden" name="league_id" value="<?= esc((string) ($league['id'] ?? ''), 'attr') ?>">
    <input type="hidden" name="round_id" value="<?= esc((string) ($round['id'] ?? ''), 'attr') ?>">
</form>

<section class="panel">
    <h2><label for="tee_message">Notice</label></h2>
    <p class="muted">A message sent with the tee time lists, above the attached PDFs. Optional.</p>
    <textarea id="tee_message" name="message" form="tee-email" rows="5"><?= esc(old('message') ?? '') ?></textarea>
</section>

<section class="panel">
    <h2>Email the tee time lists</h2>
    <?php $count = count($recipients['send']); ?>
    <?php if (! $mailReady): ?>
        <p>Email isn't set up yet. Enter the email server details on the <a href="<?= site_url('admin/settings#email-server') ?>">Settings</a> page.</p>
    <?php elseif ($round === null): ?>
        <p class="muted">Add a round to this league first.</p>
    <?php else: ?>
        <p>Sends the notice and the Front and Back lists for <strong><?= esc($date) ?></strong> to
            <strong><?= $count ?></strong> <?= $count === 1 ? 'player' : 'players' ?> registered in this league, one email each.</p>
        <?php if ($recipients['noEmail'] !== []): ?>
            <p class="muted">No email address, so not sent: <?= esc(implode(', ', $recipients['noEmail'])) ?>.</p>
        <?php endif ?>
        <button type="submit" form="tee-email" <?= $count === 0 ? 'disabled' : '' ?>
                onclick="return confirm('Email the tee time lists to <?= $count ?> players?')">Email tee sheets</button>
    <?php endif ?>
</section>

<div class="panel-pair">
<?php foreach (['front' => 'Front', 'back' => 'Back'] as $nine => $label): ?>
    <section class="panel">
        <div class="page-head">
            <h2><?= $label ?></h2>
            <a class="button" href="<?= site_url('admin/groups/tee-sheet/pdf?' . http_build_query(['league_id' => $league['id'] ?? '', 'nine' => $nine, 'round_id' => $round['id'] ?? ''])) ?>">
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
