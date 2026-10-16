<?php
$v = fn($k, $d = '') => old($k, $q[$k] ?? $d);
$dt = fn($k) => old($k) !== '' ? old($k) : utc_to_local_input($q[$k] ?? gmdate('Y-m-d H:i:s', $k === 'opens_at' ? time() : time() + 86400 * 7));
$sel = fn($k, $val, $d = '') => (string)$v($k, $d) === (string)$val ? 'selected' : '';
$chk = fn($k, $d) => ((string)old($k, (string)($q[$k] ?? $d)) === '1') ? 'checked' : '';
$tabs = ['basics' => '1 · Basics', 'schedule' => '2 · Schedule & attempts', 'rules' => '3 · Rules & integrity', 'results' => '4 · Results & assessment'];
?>
<div class="page-title"><div><p class="eyebrow"><?= $q ? 'Editing' : 'New quiz' ?></p><h1><?= $q ? e($q['title']) : 'Create quiz' ?></h1></div></div>
<?php if (!empty($attempts)): ?><div class="alert a-info">This quiz already has <?= (int)$attempts ?> attempt(s). Edits apply only to new attempts; earlier attempts keep the questions, answer key and rules they started with.</div><?php endif; ?>
<form class="card" method="post" action="<?= e(url($q ? '/admin/quizzes/' . $q['id'] . '/edit' : '/admin/quizzes/new')) ?>"><?= csrf_field() ?>
  <div class="tabs" role="tablist" aria-label="Quiz settings"><?php $i = 0; foreach ($tabs as $k => $l): ?><button type="button" role="tab" id="t-<?= $k ?>" aria-controls="p-<?= $k ?>" aria-selected="<?= $i === 0 ? 'true' : 'false' ?>"><?= e($l) ?></button><?php $i++; endforeach; ?></div>

  <div class="tabpanel" id="p-basics" role="tabpanel" aria-labelledby="t-basics">
    <label for="title">Title</label><input id="title" name="title" required maxlength="150" value="<?= e($v('title')) ?>">
    <label for="category">Competition category</label><input id="category" name="category" maxlength="60" value="<?= e($v('category', 'Cybersecurity Awareness')) ?>">
    <label for="description">Description (shown on quiz cards)</label><textarea id="description" name="description" rows="3" maxlength="1000"><?= e($v('description')) ?></textarea>
    <label for="instructions">Additional instructions shown with the rules</label><textarea id="instructions" name="instructions" rows="4" maxlength="3000"><?= e($v('instructions')) ?></textarea>
  </div>

  <div class="tabpanel" id="p-schedule" role="tabpanel" aria-labelledby="t-schedule" hidden>
    <div class="grid2"><div><label for="opens_at">Opens (<?= e(env('APP_TIMEZONE', 'Africa/Lagos')) ?>)</label><input id="opens_at" name="opens_at" type="datetime-local" required value="<?= e($dt('opens_at')) ?>">
    <label for="closes_at">Closes</label><input id="closes_at" name="closes_at" type="datetime-local" required value="<?= e($dt('closes_at')) ?>">
    <p class="hint">Set status to <strong>Scheduled</strong> on the Quizzes page to show it as “Upcoming” until it opens, or <strong>Published</strong> to open it at the opening time.</p></div>
    <div><label for="duration_minutes">Duration (minutes)</label><input id="duration_minutes" name="duration_minutes" type="number" min="1" max="600" required value="<?= e($v('duration_minutes', '10')) ?>">
    <label for="max_attempts">Maximum attempts per participant</label><input id="max_attempts" name="max_attempts" type="number" min="1" value="<?= e($v('max_attempts', '1')) ?>">
    <label for="passing_percent">Passing score % (optional)</label><input id="passing_percent" name="passing_percent" type="number" min="0" max="100" value="<?= e($v('passing_percent')) ?>"></div></div>
  </div>

  <div class="tabpanel" id="p-rules" role="tabpanel" aria-labelledby="t-rules" hidden>
    <div class="grid2"><div>
    <label class="check"><input type="checkbox" name="shuffle_questions" value="1" <?= $chk('shuffle_questions', 0) ?>> <span>Randomise question order</span></label>
    <label class="check"><input type="checkbox" name="shuffle_options" value="1" <?= $chk('shuffle_options', 0) ?>> <span>Randomise answer options</span></label>
    <label class="check"><input type="checkbox" name="allow_back" value="1" <?= $chk('allow_back', 1) ?>> <span>Allow Previous button</span></label>
    <label class="check"><input type="checkbox" name="allow_change" value="1" <?= $chk('allow_change', 1) ?>> <span>Allow changing saved answers</span></label>
    <label class="check"><input type="checkbox" name="timeout_counts" value="1" <?= $chk('timeout_counts', 1) ?>> <span>Timed-out attempts may be ranked (scored on saved answers)</span></label></div><div>
    <label for="resume_policy">If the browser closes or the page is reopened</label><select id="resume_policy" name="resume_policy"><option value="resume" <?= $sel('resume_policy', 'resume') ?>>Allow resume until the deadline</option><option value="no_resume" <?= $sel('resume_policy', 'no_resume') ?>>Do not allow resume (attempt ends as Incomplete)</option></select>
    <label for="visibility_policy">When a participant leaves the quiz page</label><select id="visibility_policy" name="visibility_policy"><option value="warn" <?= $sel('visibility_policy', 'warn') ?>>Warn only</option><option value="review" <?= $sel('visibility_policy', 'review', 'review') ?>>Flag for review after threshold</option><option value="disqualify" <?= $sel('visibility_policy', 'disqualify') ?>>Disqualify after threshold (announced rule)</option></select>
    <label for="visibility_threshold">Threshold (number of times)</label><input id="visibility_threshold" name="visibility_threshold" type="number" min="1" value="<?= e($v('visibility_threshold', '3')) ?>">
    <label for="tiebreak">Tie-breaker for equal scores</label><select id="tiebreak" name="tiebreak"><option value="time" <?= $sel('tiebreak', 'time', 'time') ?>>Faster completion time wins</option><option value="none" <?= $sel('tiebreak', 'none') ?>>No tie-breaker (share the rank)</option></select></div></div>
    <p class="hint">Every rule above is shown to participants and must be accepted before they start.</p>
  </div>

  <div class="tabpanel" id="p-results" role="tabpanel" aria-labelledby="t-results" hidden>
    <div class="grid2"><div>
    <label for="score_visibility">Participants see their score</label><select id="score_visibility" name="score_visibility"><option value="immediate" <?= $sel('score_visibility', 'immediate', 'immediate') ?>>Immediately</option><option value="after_close" <?= $sel('score_visibility', 'after_close') ?>>After the quiz closes</option><option value="after_publish" <?= $sel('score_visibility', 'after_publish') ?>>After results are published</option></select>
    <label for="answers_visibility">Participants see correct answers</label><select id="answers_visibility" name="answers_visibility"><option value="never" <?= $sel('answers_visibility', 'never') ?>>Never</option><option value="after_close" <?= $sel('answers_visibility', 'after_close') ?>>After the quiz closes</option><option value="after_publish" <?= $sel('answers_visibility', 'after_publish', 'after_publish') ?>>After results are published</option></select></div><div>
    <label for="assessment_type">Assessment type</label><select id="assessment_type" name="assessment_type"><option value="standalone" <?= $sel('assessment_type', 'standalone', 'standalone') ?>>Standalone quiz</option><option value="pre_test" <?= $sel('assessment_type', 'pre_test') ?>>Pre-test</option><option value="post_test" <?= $sel('assessment_type', 'post_test') ?>>Post-test</option></select>
    <label for="assessment_group">Assessment group (pairs a pre-test with its post-test)</label><input id="assessment_group" name="assessment_group" maxlength="60" value="<?= e($v('assessment_group')) ?>"></div></div>
  </div>
  <noscript><p class="fine">JavaScript is off: all four sections are shown above in one list.</p></noscript>
  <div class="actions"><a class="btn btn-outline" href="<?= e(url('/admin/quizzes')) ?>">Cancel</a><button class="btn" type="submit">Save quiz</button></div>
</form>
