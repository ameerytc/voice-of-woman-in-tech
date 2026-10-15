<div class="page-title"><h1 style="display:flex;gap:.7rem;align-items:center"><?= avatar_html(['id' => $a['user_id'], 'full_name' => $a['full_name'], 'has_avatar' => $a['has_avatar']], 52) ?> Attempt #<?= (int)$a['id'] ?> · <?= e($a['full_name']) ?></h1></div>
<p><a href="<?= e(url('/admin/submissions')) ?>">‹ Submissions</a> · <a href="<?= e(url('/admin/participants/' . $a['user_id'])) ?>">Participant profile</a></p>
<div class="grid2">
<section class="card"><h2>Summary</h2>
  <p>Quiz: <strong><?= e($a['title']) ?></strong> (version <?= (int)($ver['version_no'] ?? 0) ?>) · attempt <?= (int)$a['attempt_no'] ?></p>
  <p>Score: <strong><?= e(num($a['final_marks'])) ?> / <?= (int)$a['max_marks'] ?></strong> (<?= e(num($a['percentage'])) ?>%)<?php if ((float)$a['marks_adjustment'] != 0.0): ?> · automatic <?= e(num($a['marks_earned'])) ?>, adjustment <?= e(num($a['marks_adjustment'])) ?><?php endif; ?></p>
  <p>Status: <?= badge($a['status']) ?> · Eligibility: <?= badge($a['eligibility']) ?></p>
  <p>Reason: <?= e($a['eligibility_reason'] ?? '—') ?></p>
  <p>Rules acknowledged: <?= e(fmt_dt($a['rules_ack_at'], 'd M Y, H:i:s')) ?></p>
  <p>Started <?= e(fmt_dt($a['started_at'], 'd M Y, H:i:s')) ?> · Deadline <?= e(fmt_dt($a['deadline_at'], 'H:i:s')) ?> · Submitted <?= e(fmt_dt($a['submitted_at'], 'H:i:s')) ?> · Duration <?= e(fmt_duration($a['duration_seconds'] === null ? null : (int)$a['duration_seconds'])) ?></p></section>
<section class="card"><h2>Integrity events</h2><p class="fine">Browser signals only. They indicate the page lost focus; they do not prove cheating.</p>
  <?php if (!$events): ?><p class="empty">None recorded.</p><?php endif; ?><ul class="plain"><?php foreach ($events as $ev): ?><li><strong><?= e($ev['event_type']) ?></strong> <?= e($ev['detail']) ?><small><?= e(fmt_dt($ev['created_at'], 'H:i:s')) ?></small></li><?php endforeach; ?></ul></section>
</div>
<?php if ($canReview && $a['status'] !== 'in_progress'): ?>
<div class="grid2">
<form class="card" method="post" action="<?= e(url('/admin/submissions/' . $a['id'] . '/review')) ?>"><h2>Eligibility decision</h2><?= csrf_field() ?>
  <label for="decision">Decision</label><select id="decision" name="decision"><option value="eligible">Approve (Eligible)</option><option value="disqualified">Disqualify</option><option value="pending_review">Return to Pending Review</option></select>
  <label for="reason">Reason (required, recorded)</label><textarea id="reason" name="reason" rows="2" required minlength="5"></textarea><button class="btn" type="submit">Record decision</button></form>
<form class="card" method="post" action="<?= e(url('/admin/submissions/' . $a['id'] . '/adjust')) ?>"><h2>Score correction</h2><?= csrf_field() ?>
  <label for="adjustment">Adjustment in marks (+ or −)</label><input id="adjustment" name="adjustment" type="number" step="0.5" required>
  <label for="areason">Reason (required, audited)</label><textarea id="areason" name="reason" rows="2" required minlength="5"></textarea><button class="btn btn-outline" type="submit">Apply correction</button></form>
</div><?php endif; ?>
<section class="card"><h2>Answers (version <?= (int)($ver['version_no'] ?? 0) ?> answer key)</h2><div class="table-wrap"><table class="cards-sm"><thead><tr><th>#</th><th>Question</th><th>Participant's answer</th><th>Correct answer</th><th>Marks</th></tr></thead><tbody>
<?php foreach ($detail as $i => $d): ?><tr><td data-l="#"><?= $i + 1 ?></td><td data-l="Question"><?= e($d['body']) ?></td><td data-l="Answer"><?= $d['mine'] === null ? '<em>No answer</em>' : e($d['mine']) ?></td><td data-l="Correct"><?= e($d['correct']) ?></td><td data-l="Marks"><?= $d['ok'] === null ? '0' : e((string)$d['awarded']) ?> / <?= (int)$d['marks'] ?></td></tr><?php endforeach; ?></tbody></table></div></section>
<section class="card"><h2>Decision history</h2><ul class="plain"><?php foreach ($reviews as $r): ?><li><?= badge($r['decision']) ?> <?= e($r['reason']) ?><small><?= $r['is_system'] ? 'System' : e($r['reviewer'] ?? 'Reviewer') ?> · <?= e(fmt_dt($r['created_at'], 'd M Y, H:i:s')) ?></small></li><?php endforeach; ?></ul></section>
