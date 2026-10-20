<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<h1>Settings</h1>

<?php if ($errors !== []): ?>
    <div class="flash flash-error" role="alert">
        <?php foreach ($errors as $error): ?><p><?= esc($error) ?></p><?php endforeach ?>
    </div>
<?php endif ?>

<form method="post" action="<?= site_url('admin/settings') ?>" class="form">
    <?= csrf_field() ?>
    <h2>Player login</h2>
    <p class="muted">All players log in with this one login ID and password, then enter their member number.</p>
    <div class="field">
        <label for="shared_login_id">Shared login ID</label>
        <input type="text" id="shared_login_id" name="shared_login_id" value="<?= esc($settings['shared_login_id'] ?? '', 'attr') ?>" autocomplete="off">
    </div>
    <div class="field">
        <label for="shared_password">Shared password</label>
        <input type="password" id="shared_password" name="shared_password" autocomplete="new-password">
        <small class="help"><?= empty($settings['shared_password_hash']) ? 'Not set yet — players cannot log in until it is.' : 'Set. Leave blank to keep the current password.' ?> At least 8 characters.</small>
    </div>

    <h2>Admin contact</h2>
    <p class="muted">Shown to guests on the league page.</p>
    <div class="field">
        <label for="admin_contact_name">Name</label>
        <input type="text" id="admin_contact_name" name="admin_contact_name" value="<?= esc($settings['admin_contact_name'] ?? '', 'attr') ?>">
    </div>
    <div class="field">
        <label for="admin_contact_email">Email</label>
        <input type="email" id="admin_contact_email" name="admin_contact_email" value="<?= esc($settings['admin_contact_email'] ?? '', 'attr') ?>">
    </div>

    <div class="actions"><button type="submit">Save settings</button></div>
</form>
<?= $this->endSection() ?>
