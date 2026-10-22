<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= isset($title) && $title !== APP_NAME ? esc($title) . ' · ' : '' ?><?= esc(APP_NAME) ?></title>
    <link rel="stylesheet" href="<?= base_url('css/app.css') ?>">
</head>
<body>
<header class="site-header">
    <a class="brand" href="<?= site_url(session('admin_id') ? 'admin' : '/') ?>"><?= esc(APP_NAME) ?></a>
    <?php if (session('admin_id')): ?>
        <nav class="nav">
            <a href="<?= site_url('admin/leagues') ?>">Leagues</a>
            <a href="<?= site_url('admin/courses') ?>">Courses</a>
            <a href="<?= site_url('admin/players') ?>">Players</a>
            <a href="<?= site_url('admin/groups') ?>">Groups</a>
            <a href="<?= site_url('admin/games') ?>">Games</a>
            <a href="<?= site_url('admin/rounds') ?>">Rounds</a>
            <a href="<?= site_url('admin/scores') ?>">Scores</a>
            <a href="<?= site_url('admin/settings') ?>">Settings</a>
        </nav>
        <span class="who"><?= esc(session('admin_name')) ?> · <a href="<?= site_url('logout') ?>">Log out</a></span>
    <?php elseif (session('player_id')): ?>
        <span class="who"><?= esc(session('player_name')) ?> · <a href="<?= site_url('logout') ?>">Log out</a></span>
    <?php endif ?>
</header>

<main class="page">
    <?php if (session('message')): ?>
        <p class="flash flash-ok" role="status"><?= esc(session('message')) ?></p>
    <?php endif ?>
    <?php if (session('error')): ?>
        <p class="flash flash-error" role="alert"><?= esc(session('error')) ?></p>
    <?php endif ?>

    <?= $this->renderSection('content') ?>
</main>
</body>
</html>
