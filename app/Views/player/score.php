<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<?php
$locked  = ! empty($score['locked']);
$roundName = trim(date('n/j/Y', strtotime($round['round_date'])) . ' ' . ($round['name'] ?? ''));
?>
<div class="page-head">
    <h1>Enter scores</h1>
    <a class="button button-secondary" href="<?= site_url('player') ?>">Back to my page</a>
</div>
<p><strong><?= esc($roundName) ?></strong><?= $course !== '' ? ' · ' . esc($course) : '' ?> ·
    <?= $nine === 'back' ? 'Back nine' : 'Front nine' ?>
    <?php if ($score['handicap_used'] !== null): ?> · Handicap <?= (int) $score['handicap_used'] ?><?php endif ?></p>
<p class="game-line">
    <?php if ($game === null): ?>
        <span class="muted">The game for this round hasn't been set yet.</span>
    <?php else: ?>
        Game: <strong><?= esc($game['name']) ?></strong> ·
        <?= $game['team_game'] ? '<strong>Team game</strong>' . ((int) ($score['team_number'] ?? 0) > 0 ? ' (you are on team ' . (int) $score['team_number'] . ')' : '') : 'Individual game' ?>
    <?php endif ?>
</p>
<?php if (! empty($game['team_game'])): ?>
    <p class="team-note">All team members should enter the same score on every hole for team games.</p>
<?php endif ?>

<?php if ($locked): ?>
    <p class="flash flash-error">Your score for this round is locked. Ask the league admin if something needs changing.</p>
<?php elseif (isset($score['playing']) && ! $score['playing']): ?>
    <p class="muted">You're marked as not playing this round. Saving scores marks you as playing.</p>
<?php endif ?>

<form method="post" action="<?= site_url("player/score/{$round['id']}") ?>">
    <?= csrf_field() ?>
    <div class="table-wrap">
        <table class="score-entry">
            <thead>
            <tr>
                <th>Hole</th>
                <?php foreach ($holes as $hole): ?><th><?= $hole ?></th><?php endforeach ?>
            </tr>
            </thead>
            <tbody>
            <tr class="muted">
                <th>Par</th>
                <?php foreach ($holes as $hole): ?><td><?= esc((string) ($pars[$hole] ?? '')) ?></td><?php endforeach ?>
            </tr>
            <tr>
                <th>Score</th>
                <?php foreach ($holes as $hole): ?>
                    <?php $value = old("holes.hole{$hole}") ?? ((int) ($score["hole{$hole}"] ?? 0) ?: ''); ?>
                    <td><input type="text" inputmode="numeric" pattern="[0-9]{0,2}" maxlength="2" class="hole-cell"
                               name="holes[hole<?= $hole ?>]" value="<?= esc((string) $value, 'attr') ?>"
                               aria-label="Hole <?= $hole ?>" <?= $locked ? 'readonly' : '' ?>></td>
                <?php endforeach ?>
            </tr>
            </tbody>
        </table>
    </div>
    <?php if ($teamPlayers !== []): ?>
        <?php
        // Your own name is filled in first until the team is saved.
        $members = [];
        for ($i = 1; $i <= \App\Models\ScoreModel::TEAM_MEMBER_SLOTS; $i++) {
            $members[$i] = old("team_members.{$i}") ?? ($score["team_member{$i}_id"] ?? null);
        }
        if (array_filter($members) === [] && old('team_members') === null) {
            $members[1] = $score['player_id'];
        }
        ?>
        <fieldset class="team-members" <?= $locked ? 'disabled' : '' ?>>
            <legend>Your team</legend>
            <p class="team-note">Include yourself in the team selections</p>
            <div class="grid">
                <?php foreach ($members as $i => $memberId): ?>
                    <div class="field">
                        <label for="team_member_<?= $i ?>">Team member <?= $i ?></label>
                        <select id="team_member_<?= $i ?>" name="team_members[<?= $i ?>]">
                            <option value="">—</option>
                            <?php foreach ($teamPlayers as $id => $name): ?>
                                <option value="<?= $id ?>" <?= (int) $memberId === $id ? 'selected' : '' ?>><?= esc($name) ?></option>
                            <?php endforeach ?>
                        </select>
                    </div>
                <?php endforeach ?>
            </div>
        </fieldset>
    <?php endif ?>
    <?php $par = array_sum(array_map(static fn ($h) => (int) ($pars[$h] ?? 0), $holes)); ?>
    <p class="score-totals">
        <?php if ($par > 0): ?>Par <?= $par ?> · <?php endif ?>
        Gross <strong><?= esc((string) ($score['gross'] ?? '—')) ?></strong> ·
        Net <strong><?= esc((string) ($score['net'] ?? '—')) ?></strong>
    </p>
    <?php if (! $locked): ?>
        <div class="actions">
            <button type="submit">Save scores</button>
            <span class="muted">Leave a hole blank until you've played it. Gross and net show once all nine are entered.</span>
        </div>
    <?php endif ?>
</form>
<?= $this->endSection() ?>
