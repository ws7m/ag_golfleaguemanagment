<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<h1>Hi, <?= esc($player['first_name']) ?></h1>
<?= view('partials/league_notice', ['notice' => $notice]) ?>

<?php if ($activeRound !== null): ?>
    <p class="actions"><a class="button" href="<?= site_url('player/score') ?>">Enter scores for <?= esc(trim(date('n/j/Y', strtotime($activeRound['round_date'])) . ' ' . ($activeRound['name'] ?? ''))) ?></a></p>
<?php endif ?>

<h2>Rounds</h2>
<?php if ($rounds === []): ?>
    <p class="muted">No rounds yet.</p>
<?php else: ?>
    <p class="muted">For a scheduled round, choose the game you'd like to play and select Vote. You get one vote per round.
        Untick Playing for a scheduled or active round you can't make.</p>
    <div class="table-wrap">
        <table>
            <thead>
            <tr><th>Date</th><th>Name</th><th>Course</th><th>Game</th><th>Your vote</th><th>Status</th><th>Playing</th></tr>
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
                    <?php
                    // A score record says it once the round is active; before that, the
                    // player's own choice. Playing unless they said otherwise.
                    $playing = $round['score_playing'] ?? ($round['status'] === 'scheduled' ? $round['chosen_playing'] : null) ?? 1;
                    ?>
                    <td>
                        <?php if (in_array($round['status'], ['scheduled', 'active'], true)): ?>
                            <form method="post" action="<?= site_url("player/rounds/{$round['id']}/playing") ?>" class="row-form">
                                <?= csrf_field() ?>
                                <input type="hidden" name="playing" value="0">
                                <input type="checkbox" name="playing" value="1" <?= $playing ? 'checked' : '' ?>
                                       aria-label="Playing on <?= esc($round['round_date'], 'attr') ?>" onchange="this.form.submit()">
                                <noscript><button type="submit" class="link-button">Save</button></noscript>
                            </form>
                        <?php elseif ($round['score_playing'] !== null): ?>
                            <?= $round['score_playing'] ? 'Yes' : 'No' ?>
                        <?php else: ?>
                            <span class="muted">—</span>
                        <?php endif ?>
                    </td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    </div>
<?php endif ?>
<?= $this->endSection() ?>
