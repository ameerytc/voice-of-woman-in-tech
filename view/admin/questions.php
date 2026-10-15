<?php $ed = $edit; $type = old('type', $ed['type'] ?? 'mcq'); $okIdx = -1; if ($ed) foreach ($ed['options'] as $i => $o) if ($o['is_correct']) $okIdx = $i; ?>
<h1>Questions · <?= e($q['title']) ?></h1>
<p><a href="<?= e(url('/admin/quizzes')) ?>">‹ Quizzes</a> · <a href="<?= e(url('/admin/quizzes/' . $q['id'] . '/preview')) ?>">Preview quiz</a> · <?= badge($q['status']) ?></p>
<?php if ($attempts): ?><div class="alert a-info">Attempts exist for this quiz. Question edits only affect new attempts; earlier results keep their original version.</div><?php endif; ?>
<form class="card" method="post" action="<?= e(url('/admin/quizzes/' . $q['id'] . '/questions')) ?>" id="qeditor"><?= csrf_field() ?>
  <h2><?= $ed ? 'Edit question' : 'Add question' ?></h2><input type="hidden" name="question_id" value="<?= (int)($ed['id'] ?? 0) ?>">
  <div class="grid2"><div><label for="type">Type</label><select id="type" name="type"><option value="mcq" <?= $type === 'mcq' ? 'selected' : '' ?>>Multiple choice</option><option value="tf" <?= $type === 'tf' ? 'selected' : '' ?>>True / False</option></select></div>
  <div><label for="marks">Marks</label><input id="marks" name="marks" type="number" min="1" max="100" value="<?= e(old('marks', $ed['marks'] ?? 1)) ?>"></div></div>
  <label for="body">Question text</label><textarea id="body" name="body" rows="2" required maxlength="1000"><?= e(old('body', $ed['body'] ?? '')) ?></textarea>
  <div id="mcq-box"><p class="fine">Fill 2–6 options and select the correct one.</p>
  <?php for ($i = 0; $i < 6; $i++): ?><div class="optrow"><input type="radio" name="correct" value="<?= $i ?>" aria-label="Correct option <?= $i + 1 ?>" <?= (int)old('correct', $okIdx) === $i ? 'checked' : '' ?>><input name="opt[<?= $i ?>]" placeholder="Option <?= $i + 1 ?>" maxlength="300" value="<?= e($_SESSION['old']['opt'][$i] ?? ($ed['options'][$i]['body'] ?? '')) ?>"></div><?php endfor; ?></div>
  <div id="tf-box"><label>Correct answer</label><select name="correct_tf"><option value="true" <?= ($ed && $type === 'tf' && $okIdx === 0) ? 'selected' : '' ?>>True</option><option value="false" <?= ($ed && $type === 'tf' && $okIdx === 1) ? 'selected' : '' ?>>False</option></select></div>
  <label for="explanation">Explanation (optional)</label><input id="explanation" name="explanation" maxlength="1000" value="<?= e(old('explanation', $ed['explanation'] ?? '')) ?>">
  <label for="sort_order">Order (blank/0 = add at end)</label><input id="sort_order" name="sort_order" type="number" value="<?= e(old('sort_order', $ed['sort_order'] ?? '')) ?>">
  <div class="actions"><?php if ($ed): ?><a class="btn btn-outline" href="<?= e(url('/admin/quizzes/' . $q['id'] . '/questions')) ?>">Cancel edit</a><?php endif; ?><button class="btn" type="submit">Save question</button></div>
</form>
<form class="card" method="post" enctype="multipart/form-data" action="<?= e(url('/admin/quizzes/' . $q['id'] . '/import')) ?>"><?= csrf_field() ?>
  <h2>Import from CSV</h2><p class="fine">Columns: question, type (mcq/tf), option_a … option_f, correct (letter, or true/false), marks, explanation. The whole file is validated first; nothing is saved if any row has an error. <a href="<?= e(url('/admin/questions/template.csv')) ?>">Download template</a></p>
  <input type="file" name="csv" accept=".csv,text/csv" required><button class="btn btn-sm" type="submit">Validate and import</button></form>
<h2><?= count($qs) ?> question(s) · <?= (int)array_sum(array_column($qs, 'marks')) ?> total marks</h2>
<?php if (!$qs): ?><div class="card empty">No questions yet.</div><?php endif; ?>
<?php foreach ($qs as $i => $x): ?><section class="card"><p class="eyebrow">#<?= (int)$x['sort_order'] ?> · <?= e(strtoupper($x['type'])) ?> · <?= (int)$x['marks'] ?> mark(s)</p><h3><?= e($x['body']) ?></h3>
<ul class="plain"><?php foreach ($x['options'] as $o): ?><li><?= $o['is_correct'] ? '<strong class="ok">✓ ' . e($o['body']) . '</strong>' : '○ ' . e($o['body']) ?></li><?php endforeach; ?></ul>
<a class="btn btn-sm btn-outline" href="<?= e(url('/admin/quizzes/' . $q['id'] . '/questions?edit=' . $x['id'])) ?>">Edit</a>
<form class="inline" method="post" action="<?= e(url('/admin/quizzes/' . $q['id'] . '/questions/' . $x['id'] . '/delete')) ?>" data-confirm="Delete this question? Past attempts keep their original copy."><?= csrf_field() ?><button class="btn btn-sm btn-danger" type="submit">Delete</button></form></section><?php endforeach; ?>
