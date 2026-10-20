<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<h1>Admin</h1>
<p>Current league: <strong><?= esc($league['name'] ?? 'none yet') ?></strong>
    <?php if (! $league): ?> — <a href="<?= site_url('admin/leagues/new') ?>">add a league</a> to get started.<?php endif ?></p>

<?php
$cards = [
    'leagues' => ['Leagues', 'admin/leagues'], 'courses' => ['Courses', 'admin/courses'],
    'tee_times' => ['Tee times', 'admin/tee-times'], 'players' => ['Players', 'admin/players'],
    'league_groups' => ['Groups', 'admin/groups'], 'games' => ['Games', 'admin/games'],
    'rounds' => ['Rounds', 'admin/rounds'], 'scores' => ['Scores', 'admin/scores'],
];
?>
<div class="cards">
    <?php foreach ($cards as $table => [$label, $route]): ?>
        <a class="card" href="<?= site_url($route) ?>">
            <span class="card-count"><?= (int) $counts[$table] ?></span>
            <span class="card-label"><?= esc($label) ?></span>
        </a>
    <?php endforeach ?>
</div>
<p><a href="<?= site_url('admin/settings') ?>">Settings</a> — player login and admin contact.</p>
<?= $this->endSection() ?>
