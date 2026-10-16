<h1>Preview: <?= e($q['title']) ?></h1>
<p><?= badge($q['status']) ?> · <?= count($qs) ?> questions · <?= (int)array_sum(array_column($qs, 'marks')) ?> marks · <a href="<?= e(url('/admin/quizzes/' . $q['id'] . '/questions')) ?>">Edit questions</a></p>
<section class="card"><h2>Rules participants will see</h2><ul class="rules"><?php foreach ($rules as $r): ?><li><?= e($r) ?></li><?php endforeach; ?></ul></section>
<?php foreach ($qs as $i => $x): ?><section class="card"><p class="eyebrow">Question <?= $i + 1 ?> · <?= e(strtoupper($x['type'])) ?> · <?= (int)$x['marks'] ?> mark(s)</p><h3><?= e($x['body']) ?></h3>
<ul class="plain"><?php foreach ($x['options'] as $o): ?><li><?= $o['is_correct'] ? '<strong class="ok">✓ ' . e($o['body']) . '</strong>' : '○ ' . e($o['body']) ?></li><?php endforeach; ?></ul></section><?php endforeach; ?>
