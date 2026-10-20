<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<h1>Hi, <?= esc($player['first_name']) ?></h1>
<p>Member number <?= esc($player['member_number']) ?>.</p>
<p class="muted">Your rounds, scores and sign-ups will appear here in a later update.</p>
<p><a href="<?= site_url('login/switch') ?>">Switch to a different member number</a></p>
<?= $this->endSection() ?>
