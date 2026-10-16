<div class="card center result-card">
  <h1>Your result</h1>
  <p class="fine"><?= e($quiz['title']) ?> · attempt <?= (int)$a['attempt_no'] ?></p>
  <?php if ($showScore): ?>
    <p class="score"><?= e(num($a['final_marks'])) ?> / <?= (int)$a['max_marks'] ?></p>
    <p class="percent"><?= e(num($a['percentage'])) ?>%
      <?php if ($passing !== null): ?> · <?= (float)$a['percentage'] >= (float)$passing ? '<span class="ok">Passed</span>' : '<span class="bad">Below passing score (' . (int)$passing . '%)</span>' ?><?php endif; ?></p>
  <?php else: ?>
    <p class="score">Submitted</p><p class="lead">Your score will be released <?= $quiz['score_visibility'] === 'after_close' ? 'after the quiz closes.' : 'when the organisers publish the results.' ?></p>
  <?php endif; ?>
  <p>Status: <?= $a['status'] === 'timed_out' ? badge('timed_out', 'Timed out (answers saved before the deadline were scored)') : badge($a['status'] === 'abandoned' ? 'incomplete' : 'submitted', $a['status'] === 'abandoned' ? 'Incomplete' : 'Submitted') ?></p>
  <p>Time used: <strong><?= e(fmt_duration($a['duration_seconds'] === null ? null : (int)$a['duration_seconds'])) ?></strong> · You left the quiz page <strong><?= (int)$left ?></strong> time(s).</p>
  <?php if ($a['eligibility'] === 'pending_review'): ?><div class="alert a-warn">Your attempt is under review by the organisers before it can be included in the rankings.</div><?php endif; ?>
  <?php if ($a['eligibility'] === 'disqualified'): ?><div class="alert a-error">Your attempt was disqualified. Reason: <?= e($a['eligibility_reason']) ?></div><?php endif; ?>
  <?php if ($a['eligibility'] === 'incomplete'): ?><div class="alert a-error"><?= e($a['eligibility_reason']) ?></div><?php endif; ?>
  <?php if (results_published((int)$quiz['id'])): ?><p><a class="btn" href="<?= e(url('/results/' . $quiz['id'])) ?>">See official results</a></p><?php endif; ?>
</div>
<?php if ($showAnswers && $review): ?>
<h2>Review your answers</h2>
<?php foreach ($review as $i => $r): ?>
  <div class="card rv <?= $r['ok'] ? 'ok' : 'bad' ?>"><p class="eyebrow"><?= $r['ok'] ? '✓ Correct' : '✗ Incorrect' ?> · Question <?= $i + 1 ?></p><h3><?= e($r['body']) ?></h3>
    <p>Your answer: <strong><?= e($r['mine'] ?? 'No answer') ?></strong></p><p>Correct answer: <strong><?= e($r['correct']) ?></strong></p>
    <?php if ($r['explanation'] !== ''): ?><p class="fine"><?= e($r['explanation']) ?></p><?php endif; ?></div>
<?php endforeach; elseif (!$showAnswers): ?><p class="fine center">Correct answers are not shown yet.</p><?php endif; ?>
<p class="center"><a class="btn btn-outline" href="<?= e(url('/dashboard')) ?>">Back to dashboard</a></p>
