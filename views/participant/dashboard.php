<div class="dash-head reveal"><p class="eyebrow">Your galaxy</p><h1>Welcome back, <?= e(explode(' ', $u['full_name'])[0]) ?></h1></div>
<div class="dash-grid">
 <aside class="reveal">
  <section class="profile-card" aria-label="Your profile">
   <form method="post" action="<?= e(url('/account/avatar')) ?>" enctype="multipart/form-data"><?= csrf_field() ?>
    <div class="ring"><?= avatar_html(['id' => $u['id'], 'full_name' => $u['full_name'], 'has_avatar' => $card['has_avatar'], 'avatar_v' => $card['avatar_v']], 122) ?>
     <label class="cam" title="Change photo"><?= icon('camera', 18) ?><span class="sr" style="position:absolute;left:-999px">Change photo</span><input type="file" name="avatar" accept="image/jpeg,image/png,image/webp" data-preview=".ring .avatar" data-autosubmit hidden></label></div></form>
   <h2><?= e($u['full_name']) ?></h2>
   <div class="chips"><?php if ($card['age']): ?><span class="chip"><?= e($card['age']) ?></span><?php endif; ?><span class="chip"><?= e(ucfirst($u['status'])) ?></span><span class="chip">Since <?= e(fmt_dt($u['created_at'], 'M Y')) ?></span></div>
   <div class="mini"><div><b><?= (int)$stats['completed'] ?></b><span>Completed</span></div><div><b><?= (int)$stats['available'] ?></b><span>Open now</span></div><div><b><?= (int)$card['unread'] ?></b><span>New alerts</span></div></div>
   <p style="margin:1rem 0 0;position:relative"><a class="btn btn-sm btn-ghost" href="<?= e(url('/profile')) ?>">Edit profile</a></p>
  </section>
 </aside>
 <div>
  <section class="stats reveal" aria-label="Your statistics">
   <div class="stat"><span class="si"><?= icon('quiz', 20) ?></span><b><?= (int)$stats['available'] ?></b><span>Available quizzes</span></div>
   <div class="stat"><span class="si"><?= icon('check', 20) ?></span><b><?= (int)$stats['completed'] ?></b><span>Quizzes completed</span></div>
   <div class="stat"><span class="si"><?= icon('cal', 20) ?></span><b><?= (int)$stats['upcoming'] ?></b><span>Upcoming competitions</span></div>
   <div class="stat"><span class="si"><?= icon('trophy', 20) ?></span><b><?= (int)$stats['recent'] ?></b><span>Recent results</span></div>
  </section>
  <h2 id="quizzes">Available quizzes</h2>
  <?php if (!$quizzes): ?><div class="card empty">No quizzes are available right now. Check back soon or watch your notifications.</div><?php endif; ?>
  <div class="grid">
  <?php foreach ($quizzes as $q): ?>
   <article class="quiz-card reveal">
    <p class="eyebrow"><?= e($q['category']) ?></p><h3><?= e($q['title']) ?></h3>
    <?php if ($q['description'] !== ''): ?><p class="fine"><?= e($q['description']) ?></p><?php endif; ?>
    <ul class="meta"><li><?= icon('doc', 16) ?><?= (int)$q['qcount'] ?> questions</li><li><?= icon('clock', 16) ?><?= (int)$q['duration_minutes'] ?> minutes</li><li><?= icon('cal', 16) ?>Opens <?= e(fmt_dt($q['opens_at'])) ?></li><li><?= icon('cal', 16) ?>Closes <?= e(fmt_dt($q['closes_at'])) ?></li></ul>
    <div class="st"><?= badge($q['k'] === 'resume' ? 'in_progress' : ($q['k'] === 'done' ? 'submitted' : $q['k']), $q['label']) ?><?php if ((int)$q['published_results'] === 1): ?><a href="<?= e(url('/results/' . $q['id'])) ?>"><?= badge('published', 'Results published') ?></a><?php endif; ?></div>
    <?php if ($q['can']): ?><a class="btn btn-block" href="<?= e(url('/quiz/' . $q['id'])) ?>"><?= icon('play', 16) ?><?= $q['k'] === 'resume' ? 'Continue quiz' : 'Start Quiz' ?></a>
    <?php else: ?><button class="btn btn-block" disabled><?= e($q['label']) ?></button><?php endif; ?>
   </article>
  <?php endforeach; ?></div>

  <h2>My quiz history</h2>
  <?php if (!$hist): ?><div class="card empty">You have not attempted any quiz yet.</div><?php else: ?>
  <div class="table-wrap"><table class="cards-sm"><thead><tr><th>Quiz</th><th>Date</th><th>Score</th><th>Percentage</th><th>Duration</th><th>Status</th><th></th></tr></thead><tbody>
  <?php foreach ($hist as $h): ?><tr><td data-l="Quiz"><?= e($h['title']) ?></td><td data-l="Date"><?= e(fmt_dt($h['started_at'])) ?></td>
   <td data-l="Score"><?= $h['status'] === 'in_progress' ? '—' : ($h['show'] ? e(num($h['final_marks'])) . ' / ' . (int)$h['max_marks'] : 'Pending release') ?></td><td data-l="Percentage"><?= $h['show'] ? e(num($h['percentage'])) . '%' : '—' ?></td>
   <td data-l="Duration"><?= e(fmt_duration($h['duration_seconds'] === null ? null : (int)$h['duration_seconds'])) ?></td>
   <td data-l="Status"><?= $h['status'] === 'in_progress' ? badge('in_progress', 'In progress') : badge($h['eligibility'] === 'eligible' ? 'submitted' : $h['eligibility'], $h['eligibility'] === 'eligible' ? 'Submitted' : null) ?></td>
   <td><a href="<?= e(url('/attempt/' . $h['id'] . ($h['status'] === 'in_progress' ? '' : '/result'))) ?>">View</a></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>

  <h2>Notifications</h2>
  <?php if (!$notes): ?><div class="card empty">No notifications yet.</div><?php else: ?>
  <div class="card" style="padding:0"><ul class="notes"><?php foreach ($notes as $n): ?><li class="<?= $n['read_at'] ? '' : 'unread' ?>"><a href="<?= e(url('/n/' . $n['id'])) ?>"><strong><?= e($n['title']) ?></strong></a><span><?= e($n['body']) ?></span><small><?= e(fmt_dt($n['created_at'])) ?></small></li><?php endforeach; ?></ul></div>
  <p><a href="<?= e(url('/notifications')) ?>">See all notifications</a></p><?php endif; ?>
 </div>
</div>
