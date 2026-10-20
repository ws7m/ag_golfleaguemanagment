<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<h1><?= $id === null ? 'Add ' . esc(strtolower($singular)) : 'Edit ' . esc(strtolower($singular)) ?></h1>

<?php if ($errors !== []): ?>
    <div class="flash flash-error" role="alert">
        <?php foreach ($errors as $error): ?><p><?= esc($error) ?></p><?php endforeach ?>
    </div>
<?php endif ?>

<form method="post" action="<?= site_url($id === null ? $baseRoute : "{$baseRoute}/{$id}") ?>" class="form">
    <?= csrf_field() ?>
    <?php $grid = null; // name of the compact group currently open ?>
    <?php foreach ($fields as $name => $field): ?>
        <?php $compact = empty($field['compact']) ? null : (string) $field['compact']; ?>
        <?php if ($grid !== null && $compact !== $grid): $grid = null; ?></div><?php endif ?>
        <?php if ($compact !== null && $grid === null): $grid = $compact; ?><div class="grid"><?php endif ?>

        <div class="field<?= ($field['type'] ?? '') === 'checkbox' ? ' field-check' : '' ?><?= isset($errors[$name]) ? ' has-error' : '' ?>">
            <label for="f_<?= esc($name, 'attr') ?>"><?= esc($field['label']) ?><?= ! empty($field['required']) ? ' *' : '' ?></label>
            <?= field_input($name, $field, $record[$name] ?? null) ?>
            <?php if (isset($errors[$name])): ?><small class="error"><?= esc($errors[$name]) ?></small><?php endif ?>
            <?php if (! empty($field['help'])): ?><small class="help"><?= esc($field['help']) ?></small><?php endif ?>
        </div>
    <?php endforeach ?>
    <?php if ($grid !== null): ?></div><?php endif ?>

    <div class="actions">
        <button type="submit">Save</button>
        <a href="<?= site_url($baseRoute) ?>">Cancel</a>
    </div>
</form>

<?php if ($id !== null): ?>
    <form method="post" action="<?= site_url("{$baseRoute}/{$id}/delete") ?>" class="delete-form"
          onsubmit="return confirm('Delete this <?= esc(strtolower($singular), 'js') ?>? This cannot be undone.')">
        <?= csrf_field() ?>
        <button type="submit" class="button-danger">Delete <?= esc(strtolower($singular)) ?></button>
    </form>
<?php endif ?>
<?= $this->endSection() ?>
