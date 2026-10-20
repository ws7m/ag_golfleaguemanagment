<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<div class="page-head">
    <h1><?= esc($title) ?></h1>
    <span class="head-actions">
    <?php foreach ($headActions as $label => $link): ?>
        <a class="button button-secondary" href="<?= str_starts_with($link, '#') ? esc($link, 'attr') : site_url($link) ?>"><?= esc($label) ?></a>
    <?php endforeach ?>
    <a class="button" href="<?= site_url($baseRoute . '/new' . (isset($filterValues['round_id']) ? '?round_id=' . $filterValues['round_id'] : '')) ?>">Add <?= esc(strtolower($singular)) ?></a>
    </span>
</div>

<?php if ($filters !== [] || $sortOptions !== []): ?>
    <form method="get" action="<?= site_url($baseRoute) ?>" class="filters">
        <?php foreach ($filters as $name): ?>
            <label for="filter_<?= esc($name, 'attr') ?>"><?= esc($fields[$name]['label']) ?></label>
            <select id="filter_<?= esc($name, 'attr') ?>" name="<?= esc($name, 'attr') ?>" onchange="this.form.submit()">
                <option value="">All</option>
                <?php foreach ($fields[$name]['options'] as $key => $label): ?>
                    <option value="<?= esc((string) $key, 'attr') ?>" <?= (string) ($filterValues[$name] ?? '') === (string) $key ? 'selected' : '' ?>><?= esc($label) ?></option>
                <?php endforeach ?>
            </select>
        <?php endforeach ?>
        <?php if ($sortOptions !== []): ?>
            <label for="sort">Sort by</label>
            <select id="sort" name="sort" onchange="this.form.submit()">
                <?php foreach ($sortOptions as $key => $label): ?>
                    <option value="<?= esc($key, 'attr') ?>" <?= $sortValue === $key ? 'selected' : '' ?>><?= esc($label) ?></option>
                <?php endforeach ?>
            </select>
        <?php endif ?>
        <noscript><button type="submit">Filter</button></noscript>
    </form>
<?php endif ?>

<?php if ($rows === []): ?>
    <p class="muted">No <?= esc(strtolower($title)) ?> yet.</p>
<?php else: ?>
    <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <?php foreach ($columns as $name): ?>
                    <th><?= esc($fields[$name]['label']) ?></th>
                <?php endforeach ?>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <?php foreach ($columns as $name): ?>
                        <td><?= esc(field_display($fields[$name], $row[$name] ?? null)) ?></td>
                    <?php endforeach ?>
                    <td class="row-actions">
                        <a href="<?= site_url("{$baseRoute}/{$row['id']}/edit") ?>">Edit</a>
                        <?php foreach ($actions as $label => $route): ?>
                            <a href="<?= site_url(str_replace('{id}', (string) $row['id'], $route)) ?>"><?= esc($label) ?></a>
                        <?php endforeach ?>
                    </td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    </div>
<?php endif ?>

<?php if ($extraIndexView): ?>
    <?= view($extraIndexView, ['fields' => $fields, 'filterValues' => $filterValues]) ?>
<?php endif ?>
<?= $this->endSection() ?>
