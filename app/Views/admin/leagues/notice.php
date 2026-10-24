<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<div class="page-head">
    <h1>League Notice: <?= esc($league['name']) ?></h1>
</div>
<form method="post" action="<?= site_url("admin/leagues/{$league['id']}/notice") ?>" class="form">
    <?= csrf_field() ?>
    <div class="field<?= isset($errors['notice']) ? ' has-error' : '' ?>">
        <label for="notice">Notice</label>
        <textarea id="notice" name="notice" rows="8"><?= esc($league['notice'] ?? '') ?></textarea>
        <?php if (isset($errors['notice'])): ?><small class="error"><?= esc($errors['notice']) ?></small><?php endif ?>
        <small class="help">Shown to players on their page after they log in, under the heading "League Notice". Leave blank to show no notice.</small>
    </div>
    <div class="actions">
        <button type="submit">Save notice</button>
        <a href="<?= site_url('admin/leagues') ?>">Cancel</a>
    </div>
</form>
<?= $this->endSection() ?>
