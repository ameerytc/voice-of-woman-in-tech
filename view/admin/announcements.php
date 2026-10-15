<h1>Announcements</h1>
<form class="card" method="post" action="<?= e(url('/admin/announcements')) ?>"><?= csrf_field() ?>
  <label for="title">Title</label><input id="title" name="title" required maxlength="150" value="<?= e(old('title')) ?>">
  <label for="body">Message</label><textarea id="body" name="body" rows="4" required maxlength="2000"><?= e(old('body')) ?></textarea>
  <button class="btn" type="submit" data-confirm="Send this announcement to all active participants?">Send to all participants</button></form>
<section class="card"><h2>Sent</h2><?php if (!$rows): ?><p class="empty">No announcements yet.</p><?php endif; ?><ul class="plain"><?php foreach ($rows as $r): ?><li><strong><?= e($r['title']) ?></strong> — <?= e($r['body']) ?><small><?= e(fmt_dt($r['created_at'])) ?> · <?= e($r['full_name'] ?? '') ?></small></li><?php endforeach; ?></ul></section>
