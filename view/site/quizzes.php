<section class="section"><div class="wrap"><div class="section-head"><p class="eyebrow">Cybersecurity Quiz</p><h1>Available quizzes</h1><p>Create a free account or log in to take part. Rules are shown before every quiz.</p></div>
<?php if (!$ok): ?><div class="glass empty-glass">Quizzes could not be loaded right now. Please refresh.</div>
<?php elseif (!$rows): ?><div class="glass empty-glass"><?= icon('cal', 28) ?><h3>No quizzes are open right now</h3><p>Check back soon, or create an account to be notified when a new quiz is published.</p><a class="btn" href="<?= e(url('/register')) ?>">Create Your Account</a></div>
<?php else: ?><div class="grid"><?php foreach ($rows as $q): $up = ts($q['opens_at']) > time(); ?>
 <article class="quiz-card"><p class="eyebrow"><?= e($q['category']) ?></p><h3><?= e($q['title']) ?></h3><?php if ($q['description'] !== ''): ?><p><?= e($q['description']) ?></p><?php endif; ?>
  <ul class="meta"><li><?= icon('doc', 16) ?><?= (int)$q['qcount'] ?> questions</li><li><?= icon('clock', 16) ?><?= (int)$q['duration_minutes'] ?> minutes</li><li><?= icon('cal', 16) ?>Opens <?= e(fmt_dt($q['opens_at'])) ?></li><li><?= icon('cal', 16) ?>Closes <?= e(fmt_dt($q['closes_at'])) ?></li></ul>
  <div class="st"><?= badge($up ? 'upcoming' : 'open', $up ? 'Upcoming' : 'Open') ?></div>
  <a class="btn btn-block" href="<?= e(url('/quiz/' . $q['id'])) ?>"><?= $up ? 'View details' : 'Log in to start' ?></a></article><?php endforeach; ?></div><?php endif; ?></div></section>
