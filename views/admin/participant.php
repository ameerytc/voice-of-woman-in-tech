<div class="card" style="display:flex;gap:1.2rem;align-items:center;flex-wrap:wrap"><div class="ring" style="margin:0;width:112px;height:112px;background:conic-gradient(#FF791F,#7839D8,#EAE2FF,#FF791F)"><?= avatar_html($u, 102) ?></div><div><p class="eyebrow">Participant</p><h1 style="margin:0"><?= e($u['full_name']) ?></h1><p class="fine"><?= e($u['email']) ?> · <?= badge($u['status']) ?></p></div></div>
<p><a href="<?= e(url('/admin/participants')) ?>">‹ All participants</a></p>
<div class="grid2">
<section class="card"><h2>Details</h2>
  <p>Email: <strong><?= e($u['email']) ?></strong></p>
  <?php if ($phone): ?><p>Phone: <strong><?= e($u['phone'] ?? '—') ?></strong></p><?php endif; ?>
  <p>Age group: <?= e($u['age_label'] ?? '—') ?> · State: <?= e($u['state'] ?? '—') ?></p>
  <p>Registered: <?= e(fmt_dt($u['created_at'])) ?> · Last login: <?= e(fmt_dt($u['last_login_at'])) ?></p>
  <p>Status: <?= badge($u['status']) ?></p>
  <?php if (has_perm('participants.manage') && $u['status'] !== 'deleted'): ?>
  <form class="inline" method="post" action="<?= e(url('/admin/participants/' . $u['id'] . '/status')) ?>"><?= csrf_field() ?>
    <select name="status"><?php foreach (['active', 'inactive', 'suspended'] as $s): ?><option <?= $u['status'] === $s ? 'selected' : '' ?>><?= $s ?></option><?php endforeach; ?></select><button class="btn btn-sm" type="submit">Change status</button></form>
  <?php endif; ?></section>
<?php if (has_perm('participants.manage') && $u['status'] !== 'deleted'): ?>
<section class="card"><h2>Authorise an extra attempt</h2>
  <form method="post" action="<?= e(url('/admin/participants/' . $u['id'] . '/grant')) ?>"><?= csrf_field() ?>
    <label for="quiz_id">Quiz</label><select id="quiz_id" name="quiz_id"><?php foreach ($quizzes as $z): ?><option value="<?= (int)$z['id'] ?>"><?= e($z['title']) ?></option><?php endforeach; ?></select>
    <label for="reason">Reason (recorded)</label><input id="reason" name="reason" required minlength="5"><button class="btn btn-sm" type="submit">Grant one attempt</button></form>
  <?php foreach ($grants as $g): ?><p class="fine"><?= e($g['title']) ?> · +<?= (int)$g['extra_attempts'] ?> · <?= e($g['reason']) ?> · <?= e(fmt_dt($g['created_at'], 'd M Y')) ?></p><?php endforeach; ?>
  <h3>Delete personal data</h3>
  <form method="post" action="<?= e(url('/admin/participants/' . $u['id'] . '/anonymize')) ?>" data-confirm="This permanently removes the participant's name, email and phone. Quiz records stay without identity. Continue?"><?= csrf_field() ?>
    <input name="confirm" placeholder="Type DELETE" required><button class="btn btn-sm btn-danger" type="submit">Remove personal data</button></form></section>
<?php endif; ?></div>
<section class="card"><h2>Attempts</h2><?php if (!$attempts): ?><p class="empty">No attempts.</p><?php else: ?><div class="table-wrap"><table class="cards-sm"><thead><tr><th>Quiz</th><th>#</th><th>Score</th><th>Status</th><th>Started</th><th></th></tr></thead><tbody>
<?php foreach ($attempts as $a): ?><tr><td data-l="Quiz"><?= e($a['title']) ?></td><td data-l="#"><?= (int)$a['attempt_no'] ?></td><td data-l="Score"><?= e(num($a['final_marks'])) ?>/<?= (int)$a['max_marks'] ?></td><td data-l="Status"><?= $a['status'] === 'in_progress' ? badge('in_progress', 'In progress') : badge($a['eligibility']) ?></td><td data-l="Started"><?= e(fmt_dt($a['started_at'])) ?></td><td><a href="<?= e(url('/admin/submissions/' . $a['id'])) ?>">Open</a></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section>
