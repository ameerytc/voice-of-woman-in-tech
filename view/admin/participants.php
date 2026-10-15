<div class="page-title"><h1>Participants</h1></div>
<?php if ($requests): ?><section class="card"><h2>Open privacy requests</h2><ul class="plain"><?php foreach ($requests as $r): ?>
  <li><strong><?= e($r['full_name']) ?></strong> (<?= e($r['type']) ?>) — <?= e($r['message']) ?>
    <form class="inline" method="post" action="<?= e(url('/admin/requests/' . $r['id'] . '/resolve')) ?>"><?= csrf_field() ?><input type="hidden" name="status" value="resolved"><button class="btn btn-sm" type="submit">Mark resolved</button></form>
    <form class="inline" method="post" action="<?= e(url('/admin/requests/' . $r['id'] . '/resolve')) ?>"><?= csrf_field() ?><input type="hidden" name="status" value="rejected"><button class="btn btn-sm btn-outline" type="submit">Reject</button></form>
    <a href="<?= e(url('/admin/participants/' . $r['user_id'])) ?>">Open participant</a></li><?php endforeach; ?></ul></section><?php endif; ?>
<form class="card filters" method="get" action="<?= e(url('/admin/participants')) ?>">
  <input name="q" type="search" placeholder="Search name, email or phone" value="<?= e(get('q')) ?>">
  <select name="age"><option value="">All age groups</option><?php foreach ($ages as $g): ?><option value="<?= (int)$g['id'] ?>" <?= (int)get('age') === (int)$g['id'] ? 'selected' : '' ?>><?= e($g['label']) ?></option><?php endforeach; ?></select>
  <select name="status"><option value="">Any status</option><?php foreach (['active', 'inactive', 'suspended', 'deleted'] as $s): ?><option <?= get('status') === $s ? 'selected' : '' ?>><?= $s ?></option><?php endforeach; ?></select>
  <select name="quiz"><option value="">Any quiz</option><?php foreach ($quizzes as $z): ?><option value="<?= (int)$z['id'] ?>" <?= (int)get('quiz') === (int)$z['id'] ? 'selected' : '' ?>><?= e($z['title']) ?></option><?php endforeach; ?></select>
  <label>From <input type="date" name="from" value="<?= e(get('from')) ?>"></label><label>To <input type="date" name="to" value="<?= e(get('to')) ?>"></label>
  <button class="btn btn-sm" type="submit">Filter</button>
  <?php if (has_perm('participants.export')): ?><a class="btn btn-sm btn-outline" href="<?= e(url('/admin/participants/export') . qs(['page' => null])) ?>">Export CSV</a><?php endif; ?>
</form>
<div class="table-wrap"><table class="cards-sm"><thead><tr><th>Name</th><th>Email</th><?php if ($phone): ?><th>Phone</th><?php endif; ?><th>Age group</th><th>Registered</th><th>Status</th><th>Attempts</th><th>Completed</th></tr></thead><tbody>
<?php foreach ($rows as $r): ?><tr><td data-l="Name"><span class="person"><?= avatar_html($r, 36) ?><a href="<?= e(url('/admin/participants/' . $r['id'])) ?>"><?= e($r['full_name']) ?></a></span></td><td data-l="Email"><?= e($r['email']) ?></td><?php if ($phone): ?><td data-l="Phone"><?= e($r['phone']) ?></td><?php endif; ?>
<td data-l="Age"><?= e($r['age_label'] ?? '—') ?></td><td data-l="Registered"><?= e(fmt_dt($r['created_at'], 'd M Y')) ?></td><td data-l="Status"><?= badge($r['status']) ?></td><td data-l="Attempts"><?= (int)$r['attempts'] ?></td><td data-l="Completed"><?= (int)$r['completed'] ?></td></tr>
<?php endforeach; if (!$rows): ?><tr><td colspan="8" class="empty">No participants match.</td></tr><?php endif; ?></tbody></table></div>
<?= pager($pg, '/admin/participants') ?>
