<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<h1>Hi, <?= esc($player['first_name']) ?></h1>
<?= view('partials/league_notice', ['notice' => $notice]) ?>

<h2>Rounds</h2>
<?php if ($rounds === []): ?>
    <p class="muted">No rounds yet.</p>
<?php else: ?>
    <p class="muted">For a scheduled round, choose the game you'd like to play and select Vote. You get one vote per round.</p>
    <div class="table-wrap">
        <table>
            <thead>
            <tr><th>Date</th><th>Name</th><th>Course</th><th>Game</th><th>Your vote</th><th>Status</th></tr>
            </thead>
            <tbody>
            <?php foreach ($rounds as $round): ?>
                <tr>
                    <td><?= esc($round['round_date']) ?></td>
                    <td><?= esc($round['name'] ?? '') ?></td>
                    <td><?= esc($round['course'] ?? '') ?></td>
                    <td><?= esc($round['game'] ?? '') ?></td>
                    <td>
                        <?php if ($round['voted_game_id'] !== null): ?>
                            <?= esc($round['voted_game'] ?? '') ?> <span class="muted">(voted)</span>
                        <?php elseif ($round['status'] === 'scheduled' && $games !== []): ?>
                            <form method="post" action="<?= site_url("player/rounds/{$round['id']}/vote") ?>" class="row-form vote-form"
                                  onsubmit="return confirm('Vote for ' + this.game_id.options[this.game_id.selectedIndex].text + '? You can only vote once for this round.')">
                                <?= csrf_field() ?>
                                <select name="game_id" class="row-select" aria-label="Game for <?= esc($round['round_date'], 'attr') ?>" required>
                                    <option value="">Choose…</option>
                                    <?php foreach ($games as $id => $name): ?>
                                        <option value="<?= $id ?>"><?= esc($name) ?></option>
                                    <?php endforeach ?>
                                </select>
                                <button type="submit" class="link-button">Vote</button>
                            </form>
                        <?php else: ?>
                            <span class="muted">—</span>
                        <?php endif ?>
                    </td>
                    <td><?= esc($statuses[$round['status']] ?? $round['status']) ?></td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    </div>
<?php endif ?>
<?= $this->endSection() ?>
