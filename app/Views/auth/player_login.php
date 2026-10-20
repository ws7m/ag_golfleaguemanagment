<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<h1>Player login</h1>
<p class="muted">Use the league's login ID and password. You'll enter your member number next.</p>
<form method="post" action="<?= site_url('login') ?>" class="form narrow">
    <?= csrf_field() ?>
    <label for="login_id">League login ID</label>
    <input type="text" id="login_id" name="login_id" value="<?= esc(old('login_id') ?? '', 'attr') ?>" autocomplete="username" required autofocus>
    <label for="password">League password</label>
    <input type="password" id="password" name="password" autocomplete="current-password" required>
    <button type="submit">Continue</button>
</form>
<?= $this->endSection() ?>
