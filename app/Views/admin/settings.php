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

<section class="panel">
    <h2>Admin-only accounts</h2>
    <p class="muted">Admins who don't play in the league. They don't appear on the Players list.
        To add one, add a player, tick Admin, and untick "Plays in the league".</p>
    <?php if ($admins === []): ?>
        <p class="muted">None.</p>
    <?php else: ?>
        <ul>
            <?php foreach ($admins as $admin): ?>
                <li><strong><?= esc($admin['login']) ?></strong> — <?= esc($admin['first_name'] . ' ' . $admin['last_name']) ?>
                    <?= $admin['active'] ? '' : '(inactive)' ?>
                    · <a href="<?= site_url("admin/players/{$admin['id']}/edit") ?>">Edit</a></li>
            <?php endforeach ?>
        </ul>
    <?php endif ?>
</section>
<?= $this->endSection() ?>
