<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<h1><?= $forced ? 'Choose your password' : 'Change password' ?></h1>
<?php if ($forced): ?>
    <p>Welcome! Before you start, please choose your own password in place of the one you were given.</p>
<?php endif ?>

<?php if ($errors !== []): ?>
    <div class="flash flash-error" role="alert">
        <?php foreach ($errors as $error): ?><p><?= esc($error) ?></p><?php endforeach ?>
    </div>
<?php endif ?>

<form method="post" action="<?= site_url('password') ?>" class="form narrow">
    <?= csrf_field() ?>
    <?php if (! $forced): ?>
        <label for="current_password">Current password</label>
        <input type="password" id="current_password" name="current_password" autocomplete="current-password" required autofocus>
    <?php endif ?>
    <label for="password">New password</label>
    <input type="password" id="password" name="password" autocomplete="new-password" data-generate data-confirm="password_confirm"
           minlength="<?= \App\Controllers\Auth::MIN_PASSWORD_LENGTH ?>" required <?= $forced ? 'autofocus' : '' ?>>
    <label for="password_confirm">New password again</label>
    <input type="password" id="password_confirm" name="password_confirm" autocomplete="new-password" required>
    <p class="muted">At least <?= \App\Controllers\Auth::MIN_PASSWORD_LENGTH ?> characters, and not your last name or login.</p>
    <button type="submit">Save password</button>
</form>
<?= $this->endSection() ?>
