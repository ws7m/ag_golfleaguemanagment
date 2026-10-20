<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<h1><?= esc($league['name'] ?? APP_NAME) ?></h1>

<?php if (! empty($league['description'])): ?>
    <p><?= nl2br(esc($league['description'])) ?></p>
<?php endif ?>
<?php if (! empty($league['start_date'])): ?>
    <p class="muted">Season: <?= esc($league['start_date']) ?> to <?= esc($league['end_date'] ?? '') ?></p>
<?php endif ?>

<?php if (! empty($settings['admin_contact_email'])): ?>
    <p>Questions? Contact <?= esc($settings['admin_contact_name'] ?? 'the league admin') ?>
        at <a href="mailto:<?= esc($settings['admin_contact_email'], 'attr') ?>"><?= esc($settings['admin_contact_email']) ?></a>.</p>
<?php endif ?>

<p class="actions">
    <a class="button" href="<?= site_url('login') ?>">Player login</a>
    <a class="button button-secondary" href="<?= site_url('admin/login') ?>">Admin login</a>
</p>
<?= $this->endSection() ?>
