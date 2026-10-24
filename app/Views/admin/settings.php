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

    <h2 id="email-server">Email server</h2>
    <p class="muted">Used to send league email, such as the tee time lists. Your email provider gives these details
        (look for "SMTP"). Replies go to the admin contact email above.</p>
    <div class="field">
        <label for="smtp_host">Server (SMTP host)</label>
        <input type="text" id="smtp_host" name="smtp_host" value="<?= esc($settings['smtp_host'] ?? '', 'attr') ?>" placeholder="e.g. smtp.fastmail.com" autocapitalize="none" spellcheck="false">
    </div>
    <div class="grid">
        <div class="field">
            <label for="smtp_port">Port</label>
            <input type="number" id="smtp_port" name="smtp_port" value="<?= esc((string) ($settings['smtp_port'] ?? ''), 'attr') ?>" placeholder="587" min="1" max="65535">
        </div>
        <div class="field">
            <label for="smtp_crypto">Security</label>
            <select id="smtp_crypto" name="smtp_crypto">
                <?php foreach (['tls' => 'TLS (587)', 'ssl' => 'SSL (465)', 'none' => 'None'] as $value => $label): ?>
                    <option value="<?= $value ?>" <?= (string) ($settings['smtp_crypto'] ?? 'tls') === $value ? 'selected' : '' ?>><?= esc($label) ?></option>
                <?php endforeach ?>
            </select>
        </div>
    </div>
    <div class="field">
        <label for="smtp_user">Username</label>
        <input type="text" id="smtp_user" name="smtp_user" value="<?= esc($settings['smtp_user'] ?? '', 'attr') ?>" autocomplete="off" autocapitalize="none" spellcheck="false">
    </div>
    <div class="field">
        <label for="smtp_pass">Password</label>
        <input type="password" id="smtp_pass" name="smtp_pass" autocomplete="new-password">
        <small class="help"><?= empty($settings['smtp_pass_encrypted']) ? 'No password saved.' : 'A password is saved. Leave blank to keep it.' ?>
            Many providers need an app password here rather than your normal one. It is stored encrypted.</small>
        <?php if (! empty($settings['smtp_pass_encrypted'])): ?>
            <div class="field-check"><input type="checkbox" id="smtp_pass_clear" name="smtp_pass_clear" value="1"><label for="smtp_pass_clear">Remove the saved password</label></div>
        <?php endif ?>
    </div>
    <div class="field">
        <label for="mail_from_email">From email</label>
        <input type="email" id="mail_from_email" name="mail_from_email" value="<?= esc($settings['mail_from_email'] ?? '', 'attr') ?>">
        <small class="help">Usually the same address as the username; many servers refuse other From addresses.</small>
    </div>
    <div class="field">
        <label for="mail_from_name">From name</label>
        <input type="text" id="mail_from_name" name="mail_from_name" value="<?= esc($settings['mail_from_name'] ?? '', 'attr') ?>" placeholder="e.g. Monday Mongrels Golf">
    </div>

    <div class="actions"><button type="submit">Save settings</button></div>
</form>

<section class="panel">
    <h2>Send a test email</h2>
    <p class="muted">Sends a short message using the saved email server settings. Save any changes first.</p>
    <form method="post" action="<?= site_url('admin/settings/test-email') ?>" class="form inline">
        <?= csrf_field() ?>
        <label for="test_to">To</label>
        <input type="email" id="test_to" name="test_to" value="<?= esc($settings['admin_contact_email'] ?? '', 'attr') ?>" required>
        <button type="submit" class="button-secondary">Send test email</button>
    </form>
</section>

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
