<h1 style="color:#fff">Notifications</h1>
<?php if (!$notes): ?><div class="card empty">No notifications yet.</div><?php else: ?>
<form method="post" action="<?= e(url('/notifications/read')) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-outline" type="submit">Mark all as read</button></form>
<ul class="notes card">
<?php foreach ($notes as $n): ?>
  <li class="<?= $n['read_at'] ? '' : 'unread' ?>"><a href="<?= e(url('/n/' . $n['id'])) ?>"><strong><?= e($n['title']) ?></strong></a><span><?= e($n['body']) ?></span><small><?= e(fmt_dt($n['created_at'])) ?></small></li>
<?php endforeach; ?></ul>
<?php endif; ?>
