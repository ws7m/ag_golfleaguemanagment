<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<?php $value = static fn (string $field) => esc((string) ($player[$field] ?? ''), 'attr'); ?>
<h1>Update your information</h1>

<?php if ($errors !== []): ?>
    <div class="flash flash-error" role="alert">
        <?php foreach ($errors as $error): ?><p><?= esc($error) ?></p><?php endforeach ?>
    </div>
<?php endif ?>

<form method="post" action="<?= site_url('info') ?>" class="form">
    <?= csrf_field() ?>
    <div class="grid two">
        <div class="field">
            <label for="first_name">First name *</label>
            <input type="text" id="first_name" name="first_name" value="<?= $value('first_name') ?>" required maxlength="50" autocomplete="given-name">
        </div>
        <div class="field">
            <label for="last_name">Last name *</label>
            <input type="text" id="last_name" name="last_name" value="<?= $value('last_name') ?>" required maxlength="50" autocomplete="family-name">
        </div>
    </div>
    <div class="field">
        <label for="gender">Male or female</label>
        <select id="gender" name="gender">
            <option value="">—</option>
            <?php foreach (['M' => 'Male', 'F' => 'Female'] as $key => $label): ?>
                <option value="<?= $key ?>" <?= ($player['gender'] ?? '') === $key ? 'selected' : '' ?>><?= $label ?></option>
            <?php endforeach ?>
        </select>
    </div>
    <div class="field">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" value="<?= $value('email') ?>" autocomplete="email">
    </div>
    <div class="field">
        <label for="phone">Phone</label>
        <input type="tel" id="phone" name="phone" value="<?= $value('phone') ?>" autocomplete="tel">
    </div>
    <div class="field field-check">
        <input type="checkbox" id="text_msg_ok" name="text_msg_ok" value="1" <?= ! empty($player['text_msg_ok']) ? 'checked' : '' ?>>
        <label for="text_msg_ok">Text messages OK</label>
    </div>
    <div class="field">
        <label for="desired_tee_time">Desired tee time</label>
        <input type="time" id="desired_tee_time" name="desired_tee_time" value="<?= $value('desired_tee_time') ?>" style="width: auto">
    </div>
    <div class="field">
        <label for="desired_group_members">Desired group members</label>
        <textarea id="desired_group_members" name="desired_group_members" rows="3"><?= esc((string) ($player['desired_group_members'] ?? '')) ?></textarea>
        <small class="help">First and last names of players you'd like to be grouped with, separated by commas, e.g. Jim Jones, Mary Smith.</small>
    </div>

    <h2>Password</h2>
    <p class="muted">Leave these blank to keep your password. To change it, enter your current password and the new one twice
        (at least <?= \App\Controllers\Auth::MIN_PASSWORD_LENGTH ?> characters, not your last name or login).</p>
    <div class="field">
        <label for="current_password">Current password</label>
        <input type="password" id="current_password" name="current_password" autocomplete="current-password">
    </div>
    <div class="field">
        <label for="password">New password</label>
        <input type="password" id="password" name="password" autocomplete="new-password" data-generate data-confirm="password_confirm"
               minlength="<?= \App\Controllers\Auth::MIN_PASSWORD_LENGTH ?>">
    </div>
    <div class="field">
        <label for="password_confirm">New password again</label>
        <input type="password" id="password_confirm" name="password_confirm" autocomplete="new-password">
    </div>

    <div class="actions">
        <button type="submit">Save</button>
        <a href="<?= site_url(session('admin_id') ? 'admin' : 'player') ?>">Cancel</a>
    </div>
</form>
<?= $this->endSection() ?>
