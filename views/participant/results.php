<h1 style="color:#fff"><?= e($quiz['title']) ?> · Official results</h1>
<p class="fine">Version <?= (int)$res['result_version'] ?> · finalised <?= e(fmt_dt($res['finalized_at'])) ?>. Only first names and last initials are shown.</p>
<div class="table-wrap"><table class="cards-sm">
<thead><tr><th>Rank</th><th>Participant</th><th>Score</th><th>Percentage</th><th>Time</th></tr></thead><tbody>
<?php foreach ($entries as $e): $mine = (int)$e['user_id'] === (int)$u['id']; ?>
  <tr class="<?= $mine ? 'mine' : '' ?>"><td data-l="Rank"><?= e(medal($e['badge'])) ?> <?= (int)$e['final_rank'] ?></td><td data-l="Participant"><?= e(public_identity($e, (int)$res['id'])) ?><?= $mine ? ' (you)' : '' ?></td>
  <td data-l="Score"><?= e(num($e['score'])) ?> / <?= (int)$e['max_marks'] ?></td><td data-l="Percentage"><?= e(num($e['percentage'])) ?>%</td><td data-l="Time"><?= e(fmt_duration($e['duration_seconds'] === null ? null : (int)$e['duration_seconds'])) ?></td></tr>
<?php endforeach; if (!$entries): ?><tr><td colspan="5" class="empty">No ranked participants.</td></tr><?php endif; ?>
</tbody></table></div>
<p><a href="<?= e(url('/dashboard')) ?>">Back to dashboard</a></p>
