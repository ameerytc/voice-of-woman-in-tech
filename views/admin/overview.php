<div class="page-title"><div><p class="eyebrow">Control centre</p><h1>Overview</h1></div><?php if (has_perm('quizzes.manage')): ?><a class="btn btn-sm" href="<?= e(url('/admin/quizzes/new')) ?>">+ New quiz</a><?php endif; ?></div>
<?php if ($okS && $s['new'] > 0): ?><div class="alert a-info"><strong><?= (int)$s['new'] ?></strong> new submission(s) since your last visit. <a href="<?= e(url('/admin/submissions')) ?>">Open submissions</a></div><?php endif; ?>
<?php if (!$okS): ?><?= state_error() ?><?php elseif ($s['participants'] === 0 && $s['published'] === 0 && $s['attempts'] === 0): ?><div class="alert a-info">Your database is empty. Create your first quiz, publish it, and statistics will appear here as participants register and take part.</div><?php endif; ?>
<?php if ($okS): ?><section class="stats" style="grid-template-columns:repeat(auto-fit,minmax(170px,1fr))" aria-label="Key figures">
<?php foreach ([['Registered participants', $s['participants'], 'users', $s['reg7'] . ' in the last 7 days'], ['Active participants', $s['active'], 'check', ''], ['Published quizzes', $s['published'], 'quiz', ''], ['Total submissions', $s['attempts'], 'inbox', $s['att7'] . ' in the last 7 days'],
  ['Completed attempts', $s['completed'], 'doc', ''], ['Pending reviews', $s['pending'], 'shield', ''], ['Disqualified attempts', $s['disqualified'], 'alert', ''], ['Eligible participants', $s['eligible'], 'trophy', ''], ['Taking a quiz now', $s['live'], 'clock', '']] as [$l, $v, $ic, $cap]): ?>
  <div class="stat"><span class="si"><?= icon($ic, 20) ?></span><b><?= (int)$v ?></b><span><?= e($l) ?></span><?php if ($cap): ?><small><?= e($cap) ?></small><?php endif; ?></div>
<?php endforeach; ?></section><?php endif; ?>

<div class="grid2">
 <section class="card"><h2 style="margin-top:0">Registrations over time</h2><p class="fine">New participant accounts per month (last 12 months).</p><?= $okR ? svg_line($reg[0], $reg[1]) : state_error() ?></section>
 <section class="card"><h2 style="margin-top:0">Completion rate</h2><p class="fine">Completed attempts as a share of finished attempts.</p><?= $okQ ? svg_donut($fin > 0 ? round($done / $fin * 100, 1) : null, 'completed') : state_error() ?>
   <?php if ($okQ && $byQuiz): ?><?php $lab = []; $val = []; foreach ($byQuiz as $b) if ((int)$b['fin'] > 0) { $lab[] = $b['title']; $val[] = round($b['done'] / $b['fin'] * 100, 1); } echo bars($lab, $val, '%', 100.0); ?><?php endif; ?></section>
 <section class="card"><h2 style="margin-top:0">Quiz participation</h2><?= $okQ ? bars(array_column($byQuiz, 'title'), array_map('intval', array_column($byQuiz, 'n')), ' attempts') : state_error() ?></section>
 <section class="card"><h2 style="margin-top:0">Score distribution</h2><?= $okD ? bars(['0–19%', '20–39%', '40–59%', '60–79%', '80–100%'], $dist) : state_error() ?></section>
 <section class="card"><h2 style="margin-top:0">Participation by age group</h2><p class="fine">Unique participants who finished a quiz.</p><?= $okA ? bars(array_column($ages, 'l'), array_map('intval', array_column($ages, 'c'))) : state_error() ?></section>
 <section class="card"><h2 style="margin-top:0">Competition performance</h2><p class="fine">Average score per quiz.</p><?= $okF ? bars(array_column($perf, 'title'), array_map(fn($r) => round((float)$r['avg'], 1), $perf), '%', 100.0) : state_error() ?></section>
</div>
<div class="grid2">
 <section class="card"><h2 style="margin-top:0">Recent activity</h2><?php if (!$okE): ?><?= state_error() ?><?php elseif (!$feed): ?><p class="empty">No activity recorded yet.</p><?php else: ?><ul class="activity"><?php foreach ($feed as $f): ?>
   <li><span class="dot"><?= icon($f['ic'], 18) ?></span><div><b><?= $f['link'] ? '<a href="' . e(url($f['link'])) . '">' . e($f['title']) . '</a>' : e($f['title']) ?></b><span class="fine"><?= e($f['text']) ?></span><br><small><?= e(fmt_dt($f['t'])) ?></small></div></li><?php endforeach; ?></ul><?php endif; ?></section>
 <section class="card"><h2 style="margin-top:0">Upcoming competitions</h2><?php if (!$upcoming): ?><p class="empty">None scheduled.</p><?php endif; ?><ul class="plain"><?php foreach ($upcoming as $q): ?><li><a href="<?= e(url('/admin/quizzes/' . $q['id'] . '/edit')) ?>"><?= e($q['title']) ?></a><small><?= e(fmt_dt($q['opens_at'])) ?> → <?= e(fmt_dt($q['closes_at'])) ?></small></li><?php endforeach; ?></ul></section>
</div>
