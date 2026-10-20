<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<h1>Enter your member number</h1>
<form method="post" action="<?= site_url('login/member') ?>" class="form narrow">
    <?= csrf_field() ?>
    <label for="member_number">Member number</label>
    <input type="text" id="member_number" name="member_number" inputmode="numeric" pattern="[0-9]{5}" maxlength="5" autocomplete="off" required autofocus>
    <button type="submit">Log in</button>
</form>
<p><a href="<?= site_url('logout') ?>">Cancel</a></p>
<?= $this->endSection() ?>
