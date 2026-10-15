<div class="page-title"><div><p class="eyebrow">Reporting centre</p><h1>Reports</h1></div></div>
<form class="card filters" method="get" action="<?= e(url('/admin/reports')) ?>">
  <select name="type" aria-label="Report"><?php foreach ($types as $k => $l): ?><option value="<?= $k ?>" <?= $type === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select>
  <select name="quiz" aria-label="Quiz or competition"><option value="">All quizzes</option><?php foreach ($quizzes as $z): ?><option value="<?= (int)$z['id'] ?>" <?= $qid === (int)$z['id'] ? 'selected' : '' ?>><?= e($z['title']) ?></option><?php endforeach; ?></select>
  <select name="age" aria-label="Age group"><option value="">All age groups</option><?php foreach ($ages as $g): ?><option value="<?= (int)$g['id'] ?>" <?= $age === (int)$g['id'] ? 'selected' : '' ?>><?= e($g['label']) ?></option><?php endforeach; ?></select>
  <select name="pstatus" aria-label="Participation status"><option value="">All finished attempts</option><?php foreach (['submitted' => 'Completed', 'timed_out' => 'Timed out', 'abandoned' => 'Abandoned'] as $k => $l): ?><option value="<?= $k ?>" <?= $ps === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select>
  <label>From <input type="date" name="from" value="<?= e($from) ?>"></label><label>To <input type="date" name="to" value="<?= e($to) ?>"></label>
  <button class="btn btn-sm" type="submit">Generate</button>
  <a class="btn btn-sm btn-outline" href="<?= e(url('/admin/reports') . qs(['format' => 'csv'])) ?>">Download CSV</a>
  <a class="btn btn-sm btn-outline" target="_blank" rel="noopener" href="<?= e(url('/admin/reports') . qs(['print' => '1'])) ?>">Printable / PDF view</a></form>
<?php include __DIR__ . '/_report_body.php'; ?>
