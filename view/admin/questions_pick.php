<h1>Questions</h1><p>Choose a quiz to manage its questions.</p>
<?php if (!$rows): ?><div class="card empty">No quizzes yet. <a href="<?= e(url('/admin/quizzes/new')) ?>">Create one</a>.</div><?php endif; ?>
<ul class="plain card"><?php foreach ($rows as $r): ?><li><a href="<?= e(url('/admin/quizzes/' . $r['id'] . '/questions')) ?>"><?= e($r['title']) ?></a> <?= badge($r['status']) ?> <small><?= (int)$r['qn'] ?> questions</small></li><?php endforeach; ?></ul>
