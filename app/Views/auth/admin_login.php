<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<h1>Admin login</h1>
<form method="post" action="<?= site_url('admin/login') ?>" class="form narrow">
    <?= csrf_field() ?>
    <label for="email">Email</label>
    <input type="email" id="email" name="email" value="<?= esc(old('email') ?? '', 'attr') ?>" autocomplete="username" required autofocus>
    <label for="password">Password</label>
    <input type="password" id="password" name="password" autocomplete="current-password" required>
    <button type="submit">Log in</button>
</form>
<?= $this->endSection() ?>
