<div class="narrow-card">
<h1 style="color:#fff"><?= e($quiz['title']) ?></h1>
<p class="eyebrow"><?= e($quiz['category']) ?></p>
<?php if ($quiz['description'] !== ''): ?><p><?= e($quiz['description']) ?></p><?php endif; ?>
<div class="card">
  <h2>Quiz rules</h2>
  <ul class="rules"><?php foreach ($rules as $r): ?><li><?= e($r) ?></li><?php endforeach; ?></ul>
  <?php if ($quiz['instructions'] !== ''): ?><h3>Additional instructions</h3><p class="pre"><?= e($quiz['instructions']) ?></p><?php endif; ?>
</div>
<?php if ($open): ?>
  <div class="alert a-info">You have a quiz in progress. Continue where you stopped; the original deadline still applies.</div>
<?php elseif (!$can): ?>
  <div class="alert a-error"><?= $state === 'upcoming' ? 'This quiz opens ' . e(fmt_dt($quiz['opens_at'])) . '.' : ($state === 'closed' ? 'This quiz is closed.' : 'You have already used your permitted attempt(s) for this quiz.') ?></div>
<?php endif; ?>
<?php if ($can): ?>
<form class="card" method="post" action="<?= e(url('/quiz/' . $quiz['id'] . '/start')) ?>">
  <?= csrf_field() ?>
  <label class="check"><input type="checkbox" name="ack" value="1" required> <span>I have read and understood the quiz rules and agree to participate.</span></label>
  <div class="actions"><a class="btn btn-outline" href="<?= e(url('/dashboard')) ?>">Cancel</a><button class="btn" type="submit"><?= $open ? 'I Agree and Resume Quiz' : 'I Agree and Start Quiz' ?></button></div>
  <p class="fine">Your confirmation and the time you gave it are recorded.</p>
</form>
<?php else: ?><p><a class="btn btn-outline" href="<?= e(url('/dashboard')) ?>">Back to dashboard</a></p><?php endif; ?>
</div>
