<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<h1>Hi, <?= esc($player['first_name']) ?></h1>
<p>Login: <?= esc($player['login']) ?></p>
<p class="muted">Your rounds, scores and sign-ups will appear here in a later update.</p>
<?= $this->endSection() ?>
