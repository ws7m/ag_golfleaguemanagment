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
                <?php if (! in_array($name, $requiredFilters, true)): ?><option value="">All</option><?php endif ?>
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

<?php if ($belowFiltersView): ?>
    <?= view($belowFiltersView, ['filterValues' => $filterValues]) ?>
<?php endif ?>

<?php if ($rows === []): ?>
    <p class="muted">No <?= esc(strtolower($title)) ?> yet.</p>
<?php else: ?>
    <?php $editColumn = $editLinkColumn === null || $actions !== [] || $actionsBeforeEdit !== []; ?>
    <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <?php foreach ($columns as $name): ?>
                    <th<?= ($fields[$name]['type'] ?? '') === 'money' ? ' class="num"' : '' ?>><?= esc($fields[$name]['listLabel'] ?? $fields[$name]['label']) ?></th>
                <?php endforeach ?>
                <?php if ($editColumn): ?><th></th><?php endif ?>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <?php foreach ($columns as $name): ?>
                        <?php if ($name === $editLinkColumn): ?>
                            <td><a href="<?= site_url("{$baseRoute}/{$row['id']}/edit") ?>" title="Edit"><?= esc(field_display($fields[$name], $row[$name] ?? null)) ?: 'Edit' ?></a></td>
                        <?php elseif (isset($cellViews[$name])): ?>
                            <?php // Rendered once per row, so without the debug toolbar's per-view comments and saved data. ?>
                            <td><?= view($cellViews[$name], ['row' => $row], ['debug' => false, 'saveData' => false]) ?></td>
                        <?php elseif (isset($selectColumns[$name])): ?>
                            <td>
                                <form method="post" action="<?= site_url(str_replace('{id}', (string) $row['id'], $selectColumns[$name])) ?>" class="row-form">
                                    <?= csrf_field() ?>
                                    <select name="value" class="row-select" aria-label="<?= esc($fields[$name]['label'], 'attr') ?>" onchange="this.form.submit()">
                                        <?php foreach ($fields[$name]['options'] as $key => $label): ?>
                                            <option value="<?= esc((string) $key, 'attr') ?>" <?= (string) $key === (string) ($row[$name] ?? '') ? 'selected' : '' ?>><?= esc($label) ?></option>
                                        <?php endforeach ?>
                                    </select>
                                    <noscript><button type="submit">Save</button></noscript>
                                </form>
                            </td>
                        <?php elseif (isset($toggleColumns[$name])): ?>
                            <td>
                                <form method="post" action="<?= site_url(str_replace('{id}', (string) $row['id'], $toggleColumns[$name])) ?>" class="row-form">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="return_query" value="<?= esc(service('request')->getUri()->getQuery(), 'attr') ?>">
                                    <button type="submit" class="link-button" title="Click to change"><?= esc(field_display($fields[$name], $row[$name] ?? null)) ?: '—' ?></button>
                                </form>
                            </td>
                        <?php else: ?>
                            <td<?= ($fields[$name]['type'] ?? '') === 'money' ? ' class="num"' : '' ?>><?= field_cell($fields[$name], $row[$name] ?? null) ?></td>
                        <?php endif ?>
                    <?php endforeach ?>
                    <?php if ($editColumn): ?>
                        <td class="row-actions">
                            <?php foreach ($actionsBeforeEdit as $label => $route): ?>
                                <a href="<?= site_url(str_replace('{id}', (string) $row['id'], $route)) ?>"><?= esc($label) ?></a>
                            <?php endforeach ?>
                            <a href="<?= site_url("{$baseRoute}/{$row['id']}/edit") ?>">Edit</a>
                            <?php foreach ($actions as $label => $route): ?>
                                <a href="<?= site_url(str_replace('{id}', (string) $row['id'], $route)) ?>"><?= esc($label) ?></a>
                            <?php endforeach ?>
                        </td>
                    <?php endif ?>
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
