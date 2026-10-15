<?php
declare(strict_types=1);

function own_attempt(int $id, array $u): array {
    $a = db_one('SELECT * FROM quiz_attempts WHERE id=? AND user_id=?', [$id, $u['id']]);
    if (!$a) abort(404);
    return $a;
}

route('GET', '/quiz/{id}', function ($p) {
    $u = require_login();
    $quiz = db_one("SELECT * FROM quizzes WHERE id=? AND status IN ('scheduled','published')", [(int)$p['id']]);
    if (!$quiz) abort(404);
    $qn = (int)db_val('SELECT COUNT(*) FROM questions WHERE quiz_id=?', [$quiz['id']]);
    $open = db_one("SELECT id FROM quiz_attempts WHERE quiz_id=? AND user_id=? AND status='in_progress'", [$quiz['id'], $u['id']]);
    $used = (int)db_val('SELECT COUNT(*) FROM quiz_attempts WHERE quiz_id=? AND user_id=?', [$quiz['id'], $u['id']]);
    $state = quiz_window_state($quiz);
    $can = $open || ($state === 'open' && $used < attempts_allowed($quiz, (int)$u['id']) && $qn > 0);
    view('participant/rules', ['u' => $u, 'quiz' => $quiz, 'rules' => quiz_rules_list($quiz, $qn), 'open' => $open, 'can' => $can, 'state' => $state, 'used' => $used]);
});

route('POST', '/quiz/{id}/start', function ($p) {
    $u = require_login();
    if (post('ack') !== '1') { flash('error', 'You must confirm that you have read and understood the rules.'); redirect('/quiz/' . (int)$p['id']); }
    try { $aid = start_attempt((int)$u['id'], (int)$p['id']); }
    catch (UserError $e) { flash('error', $e->getMessage()); redirect('/dashboard'); }
    catch (Throwable $e) { app_log('start_attempt: ' . $e->getMessage()); flash('error', 'Could not start the quiz. Please try again.'); redirect('/dashboard'); }
    redirect('/attempt/' . $aid);
});

route('GET', '/attempt/{id}', function ($p) {
    $u = require_login();
    $a = own_attempt((int)$p['id'], $u);
    if ($a['status'] !== 'in_progress') redirect('/attempt/' . $a['id'] . '/result');
    if (time() >= ts($a['deadline_at'])) { finalize_attempt((int)$a['id']); redirect('/attempt/' . $a['id'] . '/result'); }
    $snap = attempt_snapshot($a);
    if ($snap['rules']['resume_policy'] === 'no_resume' && (int)$a['loads'] >= 1) {
        finalize_attempt((int)$a['id'], false, 'incomplete', 'abandoned', 'The quiz page was reopened after leaving; this quiz cannot be resumed (rule announced before the quiz).');
        record_event((int)$a['id'], 'resume_denied', 'Reopened a no-resume quiz');
        flash('error', 'This quiz cannot be resumed. Your saved answers were submitted and the attempt is marked Incomplete.');
        redirect('/attempt/' . $a['id'] . '/result');
    }
    if ((int)$a['loads'] >= 1) record_event((int)$a['id'], 'page_reloaded', 'Quiz page reopened');
    db_exec('UPDATE quiz_attempts SET loads=loads+1 WHERE id=?', [$a['id']]);
    $layout = json_decode($a['layout_json'], true);
    $byId = []; foreach ($snap['questions'] as $q) $byId[$q['id']] = $q;
    $questions = [];
    foreach ($layout['q'] as $qid) {
        $q = $byId[$qid]; $opts = []; $oById = [];
        foreach ($q['options'] as $o) $oById[$o['id']] = $o;
        foreach ($layout['o'][(string)$qid] as $oid) $opts[] = ['id' => $oid, 'body' => $oById[$oid]['body']];   // correct flag is never sent
        $questions[] = ['id' => $qid, 'type' => $q['type'], 'body' => $q['body'], 'marks' => $q['marks'], 'options' => $opts];
    }
    $answers = [];
    foreach (db_all('SELECT question_id, option_id FROM attempt_answers WHERE attempt_id=?', [$a['id']]) as $r) $answers[$r['question_id']] = (int)$r['option_id'];
    $left = (int)db_val("SELECT COUNT(*) FROM integrity_events WHERE attempt_id=? AND event_type='left_page'", [$a['id']]);
    $data = ['attemptId' => (int)$a['id'], 'deadline' => ts($a['deadline_at']), 'now' => time(), 'questions' => $questions, 'answers' => (object)$answers,
        'allowBack' => (bool)$snap['rules']['allow_back'], 'allowChange' => (bool)$snap['rules']['allow_change'], 'policy' => $snap['rules']['visibility_policy'],
        'threshold' => (int)$snap['rules']['visibility_threshold'], 'left' => $left, 'csrf' => csrf_token(), 'base' => rtrim((string)env('APP_BASE_PATH', ''), '/'), 'title' => $snap['rules']['title']];
    view('participant/attempt', ['u' => $u, 'data' => $data, 'title' => $snap['rules']['title']], 'quiz');
});

/* ----- JSON endpoints (CSRF via X-CSRF-Token header; all checks are server-side) ----- */
function live_attempt(array $p): array {
    $u = current_user();
    if (!$u) json_out(['ok' => false, 'error' => 'Please log in again.'], 401);
    $a = db_one('SELECT * FROM quiz_attempts WHERE id=? AND user_id=?', [(int)$p['id'], $u['id']]);
    if (!$a) json_out(['ok' => false, 'error' => 'Not found'], 404);
    if ($a['status'] !== 'in_progress') json_out(['ok' => false, 'ended' => true, 'redirect' => url('/attempt/' . $a['id'] . '/result')]);
    return $a;
}
route('POST', '/attempt/{id}/answer', function ($p) {
    $a = live_attempt($p);
    if (time() > ts($a['deadline_at']) + 3) {   // 3 s network grace; later answers are rejected
        finalize_attempt((int)$a['id']);
        json_out(['ok' => false, 'ended' => true, 'redirect' => url('/attempt/' . $a['id'] . '/result')]);
    }
    $in = json_input(); $qid = (int)($in['question_id'] ?? 0); $oid = (int)($in['option_id'] ?? 0);
    $snap = attempt_snapshot($a);
    $valid = false;
    foreach ($snap['questions'] as $q) if ($q['id'] === $qid) foreach ($q['options'] as $o) if ($o['id'] === $oid) $valid = true;
    if (!$valid) json_out(['ok' => false, 'error' => 'Invalid answer.'], 422);
    $exists = db_val('SELECT option_id FROM attempt_answers WHERE attempt_id=? AND question_id=?', [$a['id'], $qid]);
    if ($exists !== null && !$snap['rules']['allow_change']) json_out(['ok' => false, 'error' => 'This quiz does not allow changing a saved answer.'], 409);
    db_exec('INSERT INTO attempt_answers(attempt_id,question_id,option_id) VALUES(?,?,?) ON CONFLICT(attempt_id,question_id) DO UPDATE SET option_id=excluded.option_id, answered_at=?', [$a['id'], $qid, $oid, now()]);
    json_out(['ok' => true, 'remaining' => max(0, ts($a['deadline_at']) - time())]);
});
route('POST', '/attempt/{id}/event', function ($p) {
    $a = live_attempt($p);
    $in = json_input(); $type = (string)($in['type'] ?? '');
    if (!in_array($type, ['left_page', 'returned'], true)) json_out(['ok' => false], 422);
    record_event((int)$a['id'], $type, (string)($in['detail'] ?? ''));
    $snap = attempt_snapshot($a); $r = $snap['rules'];
    $left = (int)db_val("SELECT COUNT(*) FROM integrity_events WHERE attempt_id=? AND event_type='left_page'", [$a['id']]);
    if ($type === 'left_page' && $r['visibility_policy'] === 'disqualify' && $left >= (int)$r['visibility_threshold']) {
        finalize_attempt((int)$a['id']);
        json_out(['ok' => true, 'ended' => true, 'redirect' => url('/attempt/' . $a['id'] . '/result')]);
    }
    json_out(['ok' => true, 'left' => $left]);
});
route('POST', '/attempt/{id}/submit', function ($p) {
    $a = live_attempt($p);
    $in = json_input();
    finalize_attempt((int)$a['id'], !empty($in['auto']));
    json_out(['ok' => true, 'redirect' => url('/attempt/' . $a['id'] . '/result')]);
});

route('GET', '/attempt/{id}/result', function ($p) {
    $u = require_login();
    $a = own_attempt((int)$p['id'], $u);
    if ($a['status'] === 'in_progress') {
        if (time() >= ts($a['deadline_at'])) { $a = finalize_attempt((int)$a['id']); } else redirect('/attempt/' . $a['id']);
    }
    $quiz = db_one('SELECT * FROM quizzes WHERE id=?', [$a['quiz_id']]);
    $showScore = score_visible($quiz); $showAnswers = answers_visible($quiz);
    $review = [];
    if ($showAnswers) {
        $snap = attempt_snapshot($a);
        $ans = []; foreach (db_all('SELECT * FROM attempt_answers WHERE attempt_id=?', [$a['id']]) as $r) $ans[$r['question_id']] = $r;
        foreach ($snap['questions'] as $q) {
            $mine = $ans[$q['id']]['option_id'] ?? null; $row = ['body' => $q['body'], 'explanation' => $q['explanation'], 'mine' => null, 'correct' => null, 'ok' => false, 'marks' => $q['marks']];
            foreach ($q['options'] as $o) { if ($o['id'] === (int)$mine) $row['mine'] = $o['body']; if ($o['correct']) $row['correct'] = $o['body']; }
            $row['ok'] = isset($ans[$q['id']]) && (int)$ans[$q['id']]['is_correct'] === 1;
            $review[] = $row;
        }
    }
    $left = (int)db_val("SELECT COUNT(*) FROM integrity_events WHERE attempt_id=? AND event_type='left_page'", [$a['id']]);
    $passing = $quiz['passing_percent'];
    view('participant/result', compact('u', 'a', 'quiz', 'showScore', 'showAnswers', 'review', 'left', 'passing'));
});
