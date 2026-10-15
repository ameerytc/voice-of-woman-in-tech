<?php
declare(strict_types=1);

/* ================= submissions (everything participants submit lands here) ================= */
function submission_filter(): array {
    $w = ["a.status<>'in_progress'"]; $p = [];
    if (($z = (int)get('quiz')) > 0) { $w[] = 'a.quiz_id=:z'; $p['z'] = $z; }
    if (array_key_exists(get('elig'), ELIG_LABELS)) { $w[] = 'a.eligibility=:e'; $p['e'] = get('elig'); }
    if (in_array(get('status'), ['submitted', 'timed_out', 'abandoned'], true)) { $w[] = 'a.status=:s'; $p['s'] = get('status'); }
    if (($q = get('q')) !== '') { $L = sql_like(); $w[] = "(u.full_name $L :q ESCAPE '\\' OR u.email $L :q ESCAPE '\\')"; $p['q'] = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%'; }
    if (get('from') !== '') { $w[] = 'a.started_at>=:f'; $p['f'] = get('from') . ' 00:00:00'; }
    if (get('to') !== '') { $w[] = 'a.started_at<=:t'; $p['t'] = get('to') . ' 23:59:59'; }
    return [implode(' AND ', $w), $p];
}
const SUB_SELECT = "SELECT a.*, u.full_name, u.email, z.title, CASE WHEN EXISTS (SELECT 1 FROM user_avatars av WHERE av.user_id=u.id) THEN 1 ELSE 0 END has_avatar,
    (SELECT COUNT(*) FROM integrity_events e WHERE e.attempt_id=a.id AND e.event_type='left_page') lefts
    FROM quiz_attempts a JOIN users u ON u.id=a.user_id JOIN quizzes z ON z.id=a.quiz_id";

route('GET', '/admin/submissions', function () {
    $me = require_admin('submissions.view');
    finalize_expired();
    [$w, $p] = submission_filter();
    $pg = paginate((int)db_val("SELECT COUNT(*) FROM quiz_attempts a JOIN users u ON u.id=a.user_id WHERE $w", $p), 25);
    $rows = db_all(SUB_SELECT . " WHERE $w ORDER BY a.id DESC LIMIT {$pg['per']} OFFSET {$pg['offset']}", $p);
    $newCount = (int)db_val("SELECT COUNT(*) FROM quiz_attempts WHERE id > ? AND status<>'in_progress'", [$me['admin_seen_attempt_id']]);
    $maxId = (int)db_val("SELECT COALESCE(MAX(id),0) FROM quiz_attempts WHERE status<>'in_progress'");
    $seenBefore = (int)$me['admin_seen_attempt_id'];
    db_exec('UPDATE users SET admin_seen_attempt_id=? WHERE id=?', [$maxId, $me['id']]);
    admin_view('admin/submissions', ['rows' => $rows, 'pg' => $pg, 'quizzes' => db_all('SELECT id,title FROM quizzes ORDER BY id DESC'), 'newCount' => $newCount, 'seenBefore' => $seenBefore, 'canExport' => has_perm('submissions.view')]);
});
route('GET', '/admin/submissions/export', function () {
    require_admin('submissions.view');
    [$w, $p] = submission_filter();
    $rows = [];
    foreach (db_all(SUB_SELECT . " WHERE $w ORDER BY a.id", $p) as $r)
        $rows[] = [$r['id'], $r['title'], $r['full_name'], $r['email'], $r['attempt_no'], $r['status'], ELIG_LABELS[$r['eligibility']] ?? $r['eligibility'], $r['final_marks'], $r['max_marks'], $r['percentage'], $r['duration_seconds'], $r['lefts'], $r['started_at'], $r['submitted_at'], $r['eligibility_reason']];
    audit('export_submissions', 'attempts', null, count($rows) . ' rows');
    csv_out('submissions_' . gmdate('Ymd_His') . '.csv', ['Attempt ID', 'Quiz', 'Participant', 'Email', 'Attempt #', 'Status', 'Eligibility', 'Marks', 'Max marks', 'Percentage', 'Duration (s)', 'Times left page', 'Started (UTC)', 'Submitted (UTC)', 'Decision reason'], $rows);
});
route('GET', '/admin/submissions/{id}', function ($p) {
    require_admin('submissions.view');
    $a = db_one(SUB_SELECT . ' WHERE a.id=?', [(int)$p['id']]);
    if (!$a) abort(404);
    $snap = attempt_snapshot($a);
    $ans = []; foreach (db_all('SELECT * FROM attempt_answers WHERE attempt_id=?', [$a['id']]) as $r) $ans[$r['question_id']] = $r;
    $detail = [];
    foreach ($snap['questions'] as $q) {
        $row = ['body' => $q['body'], 'marks' => $q['marks'], 'mine' => null, 'correct' => null, 'ok' => null, 'awarded' => null];
        foreach ($q['options'] as $o) { if (isset($ans[$q['id']]) && $o['id'] === (int)$ans[$q['id']]['option_id']) $row['mine'] = $o['body']; if ($o['correct']) $row['correct'] = $o['body']; }
        if (isset($ans[$q['id']])) { $row['ok'] = $ans[$q['id']]['is_correct']; $row['awarded'] = $ans[$q['id']]['marks_awarded']; }
        $detail[] = $row;
    }
    admin_view('admin/submission', [
        'a' => $a, 'detail' => $detail, 'ver' => db_one('SELECT version_no FROM quiz_versions WHERE id=?', [$a['quiz_version_id']]),
        'events' => db_all('SELECT * FROM integrity_events WHERE attempt_id=? ORDER BY id', [$a['id']]),
        'reviews' => db_all('SELECT r.*, u.full_name reviewer FROM eligibility_reviews r LEFT JOIN users u ON u.id=r.reviewer_id WHERE r.attempt_id=? ORDER BY r.id', [$a['id']]),
        'canReview' => has_perm('submissions.review'),
    ]);
});
route('POST', '/admin/submissions/{id}/review', function ($p) {
    $me = require_admin('submissions.review');
    $a = db_one('SELECT * FROM quiz_attempts WHERE id=?', [(int)$p['id']]);
    $dec = post('decision'); $reason = post('reason');
    if (!$a || $a['status'] === 'in_progress') abort(404);
    if (!in_array($dec, ['eligible', 'disqualified', 'pending_review'], true)) abort(422, 'Invalid decision');
    if (mb_strlen($reason) < 5) { flash('error', 'A reason (at least 5 characters) is required for every decision.'); redirect('/admin/submissions/' . $a['id']); }
    tx(function () use ($a, $dec, $reason, $me) {
        db_exec('UPDATE quiz_attempts SET eligibility=?, eligibility_reason=?, updated_at=? WHERE id=?', [$dec, mb_substr($reason, 0, 500), now(), $a['id']]);
        db_exec('INSERT INTO eligibility_reviews(attempt_id,decision,reason,reviewer_id,is_system) VALUES(?,?,?,?,0)', [$a['id'], $dec, mb_substr($reason, 0, 500), $me['id']]);
        audit('eligibility_decision', 'attempt', $a['id'], $a['eligibility'] . ' -> ' . $dec . ': ' . $reason);
    });
    flash('success', 'Decision recorded: ' . (ELIG_LABELS[$dec]) . '.');
    redirect('/admin/submissions/' . $a['id']);
});
route('POST', '/admin/submissions/{id}/adjust', function ($p) {
    $me = require_admin('submissions.review');
    $a = db_one('SELECT * FROM quiz_attempts WHERE id=?', [(int)$p['id']]);
    $adj = (float)str_replace(',', '.', post('adjustment')); $reason = post('reason');
    if (!$a || $a['status'] === 'in_progress') abort(404);
    if ($adj == 0.0 || mb_strlen($reason) < 5) { flash('error', 'Enter a non-zero adjustment and a reason.'); redirect('/admin/submissions/' . $a['id']); }
    $new = (float)$a['marks_earned'] + (float)$a['marks_adjustment'] + $adj;
    if ($new < 0 || $new > (float)$a['max_marks']) { flash('error', 'The adjusted score must stay between 0 and the maximum marks (' . $a['max_marks'] . ').'); redirect('/admin/submissions/' . $a['id']); }
    $max = (int)$a['max_marks'];
    tx(function () use ($a, $adj, $new, $max, $reason) {
        db_exec('UPDATE quiz_attempts SET marks_adjustment=marks_adjustment+?, final_marks=?, percentage=?, updated_at=? WHERE id=?', [$adj, $new, $max > 0 ? round($new / $max * 100, 2) : 0, now(), $a['id']]);
        audit('score_adjusted', 'attempt', $a['id'], 'from ' . num($a['final_marks']) . ' to ' . num($new) . ' (' . ($adj > 0 ? '+' : '') . num($adj) . '): ' . $reason);
    });
    flash('success', 'Score correction recorded in the audit log.');
    redirect('/admin/submissions/' . $a['id']);
});

/* ================= verification queue ================= */
route('GET', '/admin/verification', function () {
    require_admin('submissions.view');
    finalize_expired();
    $rows = db_all(SUB_SELECT . " WHERE a.eligibility IN ('pending_review') AND a.status<>'in_progress' ORDER BY a.id");
    $recent = db_all("SELECT r.*, u.full_name reviewer, z.title, p.full_name participant, a.id aid FROM eligibility_reviews r
        JOIN quiz_attempts a ON a.id=r.attempt_id JOIN quizzes z ON z.id=a.quiz_id JOIN users p ON p.id=a.user_id LEFT JOIN users u ON u.id=r.reviewer_id
        WHERE r.is_system=0 ORDER BY r.id DESC LIMIT 15");
    admin_view('admin/verification', ['rows' => $rows, 'recent' => $recent]);
});

/* ================= results and winners ================= */
route('GET', '/admin/results', function () {
    require_admin('results.view');
    finalize_expired();
    $quizzes = db_all('SELECT z.id,z.title,z.status,(SELECT COUNT(*) FROM quiz_attempts a WHERE a.quiz_id=z.id AND a.status<>\'in_progress\') n FROM quizzes z ORDER BY z.id DESC');
    $qid = (int)get('quiz');
    $lb = $qid ? compute_leaderboard($qid) : null;
    $history = $qid ? db_all('SELECT * FROM official_results WHERE quiz_id=? ORDER BY result_version DESC', [$qid]) : [];
    $pending = $qid ? (int)db_val("SELECT COUNT(*) FROM quiz_attempts WHERE quiz_id=? AND eligibility='pending_review'", [$qid]) : 0;
    $live = $qid ? (int)db_val("SELECT COUNT(*) FROM quiz_attempts WHERE quiz_id=? AND status='in_progress'", [$qid]) : 0;
    admin_view('admin/results', ['quizzes' => $quizzes, 'qid' => $qid, 'lb' => $lb, 'history' => $history, 'pending' => $pending, 'live' => $live, 'canFinalize' => has_perm('results.manage')]);
});
route('POST', '/admin/results/finalize', function () {
    $me = require_admin('results.manage');
    $qid = (int)post('quiz_id');
    $pending = (int)db_val("SELECT COUNT(*) FROM quiz_attempts WHERE quiz_id=? AND eligibility='pending_review'", [$qid]);
    if ($pending > 0 && post('ack_pending') !== '1') { flash('error', "$pending attempt(s) are still Pending Review and would be excluded. Review them first, or tick the confirmation box."); redirect('/admin/results?quiz=' . $qid); }
    try { $rid = finalize_results($qid, max(0, min(10, (int)post('prizes', '3'))), post('publish') === '1', post('reason'), $me); }
    catch (UserError $e) { flash('error', $e->getMessage()); redirect('/admin/results?quiz=' . $qid); }
    audit('results_finalised', 'official_result', $rid, 'quiz ' . $qid . (post('reason') !== '' ? ' correction: ' . post('reason') : ''));
    flash('success', 'Official results saved as an immutable snapshot.');
    redirect('/admin/results/view/' . $rid);
});
route('GET', '/admin/results/view/{id}', function ($p) {
    require_admin('results.view');
    $r = db_one('SELECT r.*, z.title FROM official_results r JOIN quizzes z ON z.id=r.quiz_id WHERE r.id=?', [(int)$p['id']]);
    if (!$r) abort(404);
    admin_view('admin/result_view', ['r' => $r, 'entries' => db_all('SELECT * FROM official_result_entries WHERE result_id=? ORDER BY final_rank IS NULL, final_rank, id', [$r['id']]), 'rules' => json_decode($r['rules_json'], true)]);
});
route('GET', '/admin/results/view/{id}/export', function ($p) {
    require_admin('results.view');
    $r = db_one('SELECT * FROM official_results WHERE id=?', [(int)$p['id']]);
    if (!$r) abort(404);
    $rows = [];
    foreach (db_all('SELECT * FROM official_result_entries WHERE result_id=? ORDER BY final_rank IS NULL, final_rank, id', [$r['id']]) as $e)
        $rows[] = [$e['final_rank'] ?? 'excluded', $e['participant_name'], $e['score'], $e['max_marks'], $e['percentage'], $e['duration_seconds'], ELIG_LABELS[$e['eligibility']] ?? $e['eligibility'], $e['badge'], $e['note']];
    csv_out('official_results_v' . $r['result_version'] . '_quiz' . $r['quiz_id'] . '.csv', ['Rank', 'Participant', 'Score', 'Max', 'Percentage', 'Duration (s)', 'Eligibility', 'Badge', 'Note'], $rows);
});

/* ================= reports ================= */
route('GET', '/admin/reports', function () {
    require_admin('reports.view');
    $types = ['registration' => 'Participant registration', 'participation' => 'Quiz participation', 'scores' => 'Assessment scores', 'competition' => 'Competition results', 'verification' => 'Verification outcomes', 'activity' => 'Program activity over time', 'impact' => 'Program impact'];
    $type = array_key_exists(get('type'), $types) ? get('type') : 'participation';
    $from = get('from'); $to = get('to'); $qid = (int)get('quiz'); $age = (int)get('age'); $ps = get('pstatus');
    $fromUtc = $from !== '' ? local_to_utc($from . 'T00:00') : null;
    $toUtc = $to !== '' ? local_to_utc($to . 'T23:59') : null;
    finalize_expired();
    $R = build_report($type, ['from' => $fromUtc, 'to' => $toUtc, 'quiz' => $qid, 'age' => $age, 'pstatus' => $ps]);
    $ageLabel = $age ? (string)db_val('SELECT label FROM age_groups WHERE id=?', [$age]) : 'All age groups';
    $meta = ['period' => ($from !== '' ? $from : 'start of records') . ' to ' . ($to !== '' ? $to : 'today'), 'generated' => fmt_dt(now(), 'd M Y, H:i') . ' (' . env('APP_TIMEZONE', 'Africa/Lagos') . ')',
        'quiz' => $qid ? (string)db_val('SELECT title FROM quizzes WHERE id=?', [$qid]) : 'All quizzes', 'age' => $ageLabel, 'pstatus' => $ps !== '' ? $ps : 'all finished', 'by' => current_user()['full_name']];
    if (get('format') === 'csv') {
        $rows = [['Organisation', setting('org_name')], ['Report', $R['title']], ['Period', $meta['period']], ['Quiz filter', $meta['quiz']], ['Age group filter', $meta['age']], ['Participation status', $meta['pstatus']], ['Generated', $meta['generated']], ['Definitions', $R['defs']], ['Data source', $R['scope']], ['']];
        foreach ($R['kpis'] as $k => $v) $rows[] = [$k, $v];
        foreach ($R['sections'] as [$h, $heads, $data]) { $rows[] = ['']; $rows[] = [$h]; $rows[] = $heads; foreach ($data as $d) $rows[] = $d; }
        audit('export_report', 'report', null, $type);
        csv_out('report_' . $type . '_' . gmdate('Ymd_His') . '.csv', [setting('org_name'), 'Report export'], $rows);
    }
    $vars = ['type' => $type, 'types' => $types, 'R' => $R, 'meta' => $meta, 'quizzes' => db_all('SELECT id,title FROM quizzes ORDER BY id DESC'), 'ages' => db_all('SELECT id,label FROM age_groups ORDER BY sort_order'), 'from' => $from, 'to' => $to, 'qid' => $qid, 'age' => $age, 'ps' => $ps];
    if (get('print') === '1') view('admin/report_print', $vars, 'print'); else admin_view('admin/reports', $vars);
});
