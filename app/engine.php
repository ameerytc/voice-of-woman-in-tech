<?php
declare(strict_types=1);

function quiz_rules_array(array $q): array {
    return [
        'title' => $q['title'], 'duration_minutes' => (int)$q['duration_minutes'], 'opens_at' => $q['opens_at'], 'closes_at' => $q['closes_at'],
        'max_attempts' => (int)$q['max_attempts'], 'allow_back' => (int)$q['allow_back'], 'allow_change' => (int)$q['allow_change'],
        'resume_policy' => $q['resume_policy'], 'visibility_policy' => $q['visibility_policy'], 'visibility_threshold' => (int)$q['visibility_threshold'],
        'timeout_counts' => (int)$q['timeout_counts'], 'passing_percent' => $q['passing_percent'] === null ? null : (int)$q['passing_percent'],
        'shuffle_questions' => (int)$q['shuffle_questions'], 'shuffle_options' => (int)$q['shuffle_options'], 'tiebreak' => $q['tiebreak'],
    ];
}
function quiz_questions(int $quizId): array {
    $qs = db_all('SELECT * FROM questions WHERE quiz_id=? ORDER BY sort_order, id', [$quizId]);
    foreach ($qs as &$x) $x['options'] = db_all('SELECT * FROM question_options WHERE question_id=? ORDER BY sort_order, id', [$x['id']]);
    return $qs;
}
/** Returns the quiz_versions row matching the quiz's CURRENT content+rules, creating a new immutable version if it changed. */
function ensure_version(array $quiz): array {
    $snap = ['rules' => quiz_rules_array($quiz), 'questions' => []];
    $total = 0;
    foreach (quiz_questions((int)$quiz['id']) as $q) {
        $total += (int)$q['marks'];
        $opts = [];
        foreach ($q['options'] as $o) $opts[] = ['id' => (int)$o['id'], 'body' => $o['body'], 'correct' => (int)$o['is_correct']];
        $snap['questions'][] = ['id' => (int)$q['id'], 'type' => $q['type'], 'body' => $q['body'], 'marks' => (int)$q['marks'], 'explanation' => $q['explanation'], 'options' => $opts];
    }
    $json = json_encode($snap, JSON_UNESCAPED_UNICODE);
    $hash = hash('sha256', $json);
    $row = db_one('SELECT * FROM quiz_versions WHERE quiz_id=? AND content_hash=? ORDER BY version_no DESC LIMIT 1', [$quiz['id'], $hash]);
    if ($row) return $row;
    $n = (int)db_val('SELECT COALESCE(MAX(version_no),0)+1 FROM quiz_versions WHERE quiz_id=?', [$quiz['id']]);
    $id = db_insert('INSERT INTO quiz_versions(quiz_id,version_no,content_hash,snapshot_json,total_marks) VALUES(?,?,?,?,?)', [$quiz['id'], $n, $hash, $json, $total]);
    return db_one('SELECT * FROM quiz_versions WHERE id=?', [$id]);
}
function build_layout(array $snap): array {
    $ids = array_column($snap['questions'], 'id');
    if ($snap['rules']['shuffle_questions']) shuffle($ids);
    $o = [];
    foreach ($snap['questions'] as $q) {
        $oi = array_column($q['options'], 'id');
        if ($snap['rules']['shuffle_options'] && $q['type'] === 'mcq') shuffle($oi);
        $o[(string)$q['id']] = $oi;
    }
    return ['q' => $ids, 'o' => $o];
}
function attempts_allowed(array $quiz, int $uid): int {
    return (int)$quiz['max_attempts'] + (int)db_val('SELECT COALESCE(SUM(extra_attempts),0) FROM attempt_grants WHERE user_id=? AND quiz_id=?', [$uid, $quiz['id']]);
}
function quiz_window_state(array $q): string {
    if (!in_array($q['status'], ['scheduled', 'published'], true)) return 'closed';
    $n = time();
    if ($n < ts($q['opens_at'])) return 'upcoming';
    if ($n >= ts($q['closes_at'])) return 'closed';
    return 'open';
}
function results_published(int $quizId): bool {
    return (bool)db_val("SELECT 1 FROM official_results WHERE quiz_id=? AND status='official' AND published_at IS NOT NULL", [$quizId]);
}
function score_visible(array $quiz): bool {
    return match ($quiz['score_visibility']) {
        'immediate' => true,
        'after_close' => $quiz['status'] === 'closed' || $quiz['status'] === 'archived' || time() >= ts($quiz['closes_at']),
        default => results_published((int)$quiz['id']),
    };
}
function answers_visible(array $quiz): bool {
    return match ($quiz['answers_visibility']) {
        'never' => false,
        'after_close' => $quiz['status'] === 'closed' || $quiz['status'] === 'archived' || time() >= ts($quiz['closes_at']),
        default => results_published((int)$quiz['id']),
    };
}
/** Human-readable rules, generated only from quiz settings. */
function quiz_rules_list(array $q, int $questionCount): array {
    $r = [];
    $r[] = "The quiz has $questionCount question(s) and lasts {$q['duration_minutes']} minute(s). The timer is controlled by the server, not by your device.";
    $r[] = 'You must start before ' . fmt_dt($q['closes_at']) . '. Once you start, you receive the full duration.';
    $r[] = 'Permitted attempts per participant: ' . (int)$q['max_attempts'] . '. Extra attempts are only given when an administrator authorises them.';
    if ($q['passing_percent'] !== null) $r[] = 'Passing score: ' . (int)$q['passing_percent'] . '%.';
    $r[] = $q['allow_back'] ? 'You may move back to previous questions.' : 'You cannot move back to previous questions.';
    $r[] = $q['allow_change'] ? 'You may change an answer until you submit or time runs out.' : 'Once you save an answer it cannot be changed.';
    $r[] = $q['resume_policy'] === 'resume'
        ? 'If your browser closes or refreshes, you can reopen the quiz and continue; the original deadline still applies.'
        : 'This quiz cannot be resumed. If you leave and reopen the quiz page, it ends immediately with the answers already saved and is marked Incomplete.';
    $th = (int)$q['visibility_threshold'];
    $r[] = 'Your browser reports when you switch tabs, minimise the window or the page loses focus. These events are recorded with a time stamp. No camera, microphone or other browsing information is accessed.';
    $r[] = match ($q['visibility_policy']) {
        'warn' => 'Leaving the quiz page is recorded and you will see a warning. It does not by itself change your eligibility.',
        'review' => "If you leave the quiz page $th time(s) or more, your attempt is flagged for review by an administrator before it can be ranked.",
        default => "If you leave the quiz page $th time(s) or more, your attempt will be automatically disqualified.",
    };
    $r[] = 'Scoring: each question has its own marks. Percentage = marks earned ÷ maximum marks × 100.';
    $r[] = 'Winners: eligible attempts are ranked by highest score; ties are ranked by the faster completion time' . ($q['tiebreak'] === 'time' ? '' : ' (time tie-break is OFF for this quiz, so equal scores share the same rank)') . '. '
        . ($q['timeout_counts'] ? 'Attempts that time out are scored on the answers saved before the deadline.' : 'Attempts that time out are not eligible for ranking.')
        . ' Attempts flagged for review are excluded until an administrator decides.';
    $r[] = 'Privacy: your name, answers, scores and timings are stored and visible to authorised administrators. Public leaderboards show only your first name and last initial. Email and phone number are never displayed publicly.';
    return $r;
}

function start_attempt(int $uid, int $quizId): int {
    return tx(function () use ($uid, $quizId) {
        db_one('SELECT id FROM users WHERE id=?' . sql_lock(), [$uid]);   // serialise attempts per participant (PostgreSQL)
        $quiz = db_one('SELECT * FROM quizzes WHERE id=?', [$quizId]);
        if (!$quiz || quiz_window_state($quiz) !== 'open') throw new UserError('This quiz is not open for participation.');
        $cur = db_one("SELECT id FROM quiz_attempts WHERE quiz_id=? AND user_id=? AND status='in_progress'", [$quizId, $uid]);
        if ($cur) return (int)$cur['id'];
        $count = (int)db_val('SELECT COUNT(*) FROM quiz_attempts WHERE quiz_id=? AND user_id=?', [$quizId, $uid]);
        if ($count >= attempts_allowed($quiz, $uid)) throw new UserError('You have used all permitted attempts for this quiz.');
        if ((int)db_val('SELECT COUNT(*) FROM questions WHERE quiz_id=?', [$quizId]) < 1) throw new UserError('This quiz has no questions yet.');
        $ver = ensure_version($quiz);
        $snap = json_decode($ver['snapshot_json'], true);
        $start = time();
        $id = db_insert('INSERT INTO quiz_attempts(quiz_id,quiz_version_id,user_id,attempt_no,status,started_at,deadline_at,rules_ack_at,max_marks,layout_json)
                         VALUES(?,?,?,?,?,?,?,?,?,?)',
            [$quizId, $ver['id'], $uid, $count + 1, 'in_progress', gmdate('Y-m-d H:i:s', $start), gmdate('Y-m-d H:i:s', $start + (int)$quiz['duration_minutes'] * 60), now(), $ver['total_marks'], json_encode(build_layout($snap))]);
        db_exec('INSERT INTO integrity_events(attempt_id,event_type,detail) VALUES(?,?,?)', [$id, 'rules_acknowledged', 'Participant confirmed the displayed rules']);
        return $id;
    });
}

function attempt_snapshot(array $a): array {
    static $c = [];
    $k = (int)$a['quiz_version_id'];
    if (!isset($c[$k])) $c[$k] = json_decode((string)db_val('SELECT snapshot_json FROM quiz_versions WHERE id=?', [$k]), true);
    return $c[$k];
}

function record_event(int $attemptId, string $type, ?string $detail): void {
    $last = db_one('SELECT created_at FROM integrity_events WHERE attempt_id=? AND event_type=? ORDER BY id DESC LIMIT 1', [$attemptId, $type]);
    if ($last && time() - ts($last['created_at']) < 2) return;
    db_exec('INSERT INTO integrity_events(attempt_id,event_type,detail) VALUES(?,?,?)', [$attemptId, $type, $detail === null ? null : mb_substr($detail, 0, 200)]);
}

/** Scores on the server from the version's answer key. Safe to call repeatedly: only an in_progress attempt is finalised. */
function finalize_attempt(int $id, bool $auto = false, ?string $forceElig = null, ?string $forceStatus = null, ?string $reason = null): ?array {
    return tx(function () use ($id, $auto, $forceElig, $forceStatus, $reason) {
        $a = db_one('SELECT * FROM quiz_attempts WHERE id=?' . sql_lock(), [$id]);
        if (!$a || $a['status'] !== 'in_progress') return $a;
        $snap = attempt_snapshot($a);
        $rules = $snap['rules'];
        $deadline = ts($a['deadline_at']);
        $n = time();
        $timedOut = $n >= $deadline || ($auto && $deadline - $n <= 5);
        $endTs = $timedOut ? $deadline : $n;
        $key = [];
        foreach ($snap['questions'] as $q) {
            $correct = null;
            foreach ($q['options'] as $o) if ($o['correct']) $correct = (int)$o['id'];
            $key[(int)$q['id']] = ['opt' => $correct, 'marks' => (int)$q['marks']];
        }
        $earned = 0;
        foreach (db_all('SELECT * FROM attempt_answers WHERE attempt_id=?', [$id]) as $ans) {
            $k = $key[(int)$ans['question_id']] ?? null;
            $ok = $k && $k['opt'] !== null && (int)$ans['option_id'] === $k['opt'];
            $m = $ok ? $k['marks'] : 0;
            $earned += $m;
            db_exec('UPDATE attempt_answers SET is_correct=?, marks_awarded=? WHERE attempt_id=? AND question_id=?', [$ok ? 1 : 0, $m, $id, $ans['question_id']]);
        }
        $max = (int)$a['max_marks'];
        $pct = $max > 0 ? round($earned / $max * 100, 2) : 0.0;
        $status = $forceStatus ?? ($timedOut ? 'timed_out' : 'submitted');
        $elig = $timedOut ? 'timed_out' : 'eligible';
        $why = $timedOut ? 'Time expired; answers saved before the deadline were scored.' : 'Valid completed attempt.';
        $left = (int)db_val("SELECT COUNT(*) FROM integrity_events WHERE attempt_id=? AND event_type='left_page'", [$id]);
        $th = max(1, (int)$rules['visibility_threshold']);
        if ($rules['visibility_policy'] === 'review' && $left >= $th) { $elig = 'pending_review'; $why = "Left the quiz page $left time(s); flagged for review under the announced rule."; }
        if ($rules['visibility_policy'] === 'disqualify' && $left >= $th) { $elig = 'disqualified'; $why = "Left the quiz page $left time(s); disqualified under the announced rule."; }
        if ($forceElig) { $elig = $forceElig; $why = $reason ?? $why; }
        db_exec('UPDATE quiz_attempts SET status=?, submitted_at=?, duration_seconds=?, marks_earned=?, final_marks=?, percentage=?, eligibility=?, eligibility_reason=?, updated_at=? WHERE id=?',
            [$status, gmdate('Y-m-d H:i:s', $endTs), max(0, $endTs - ts($a['started_at'])), $earned, $earned, $pct, $elig, $why, now(), $id]);
        db_exec('INSERT INTO eligibility_reviews(attempt_id,decision,reason,reviewer_id,is_system) VALUES(?,?,?,NULL,1)', [$id, $elig, $why]);
        return db_one('SELECT * FROM quiz_attempts WHERE id=?', [$id]);
    });
}
function finalize_expired(): void {
    foreach (db_all("SELECT id FROM quiz_attempts WHERE status='in_progress' AND deadline_at <= ?", [gmdate('Y-m-d H:i:s', time() - 3)]) as $r) finalize_attempt((int)$r['id']);
}
