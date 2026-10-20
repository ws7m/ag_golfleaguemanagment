<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<h1>Log in</h1>
<form method="post" action="<?= site_url('login') ?>" class="form narrow">
    <?= csrf_field() ?>
    <label for="login">Login</label>
    <input type="text" id="login" name="login" value="<?= esc(old('login') ?? '', 'attr') ?>"
           autocomplete="username" autocapitalize="none" spellcheck="false" required autofocus>
    <label for="password">Password</label>
    <input type="password" id="password" name="password" autocomplete="current-password" required>
    <button type="submit">Log in</button>
</form>
<p class="muted">Your login is usually the first 5 letters of your last name plus your first initial, e.g. Mark Holbrook is <strong>holbrm</strong>.
    Ask the league admin if you don't have a password yet.</p>
<?= $this->endSection() ?>
