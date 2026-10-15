<?php
declare(strict_types=1);

/** Applies the quiz's ranking rules. Never trusts the browser; used for preview AND for the official snapshot. */
function compute_leaderboard(int $quizId): array {
    $quiz = db_one('SELECT * FROM quizzes WHERE id=?', [$quizId]);
    if (!$quiz) return ['quiz' => null, 'ranked' => [], 'excluded' => []];
    $grants = [];
    foreach (db_all('SELECT user_id, SUM(extra_attempts) s FROM attempt_grants WHERE quiz_id=? GROUP BY user_id', [$quizId]) as $g) $grants[$g['user_id']] = (int)$g['s'];
    $rows = db_all("SELECT a.*, u.full_name, u.status AS user_status, CASE WHEN EXISTS (SELECT 1 FROM user_avatars av WHERE av.user_id=u.id) THEN 1 ELSE 0 END has_avatar FROM quiz_attempts a JOIN users u ON u.id=a.user_id
                    WHERE a.quiz_id=? AND a.status IN ('submitted','timed_out','abandoned') ORDER BY a.user_id, a.id", [$quizId]);
    $excluded = []; $best = [];
    foreach ($rows as $r) {
        $limit = (int)$quiz['max_attempts'] + ($grants[$r['user_id']] ?? 0);
        $why = null;
        if ((int)$r['attempt_no'] > $limit) $why = 'Beyond the permitted attempt limit';
        elseif ($r['eligibility'] === 'pending_review') $why = 'Pending review';
        elseif ($r['eligibility'] === 'disqualified') $why = 'Disqualified: ' . ($r['eligibility_reason'] ?? '');
        elseif ($r['eligibility'] === 'incomplete') $why = 'Incomplete attempt';
        elseif ($r['eligibility'] === 'timed_out' && !(int)$quiz['timeout_counts']) $why = 'Timed out (not ranked under the announced rules)';
        if ($why) { $r['note'] = $why; $excluded[] = $r; continue; }
        $u = $r['user_id'];
        if (!isset($best[$u]) || (float)$r['final_marks'] > (float)$best[$u]['final_marks']
            || ((float)$r['final_marks'] === (float)$best[$u]['final_marks'] && (int)$r['duration_seconds'] < (int)$best[$u]['duration_seconds'])) {
            if (isset($best[$u])) { $best[$u]['note'] = 'Lower-scoring attempt of the same participant'; $excluded[] = $best[$u]; }
            $best[$u] = $r;
        } else { $r['note'] = 'Lower-scoring attempt of the same participant'; $excluded[] = $r; }
    }
    $ranked = array_values($best);
    $useTime = $quiz['tiebreak'] === 'time';
    usort($ranked, function ($a, $b) use ($useTime) {
        $c = (float)$b['final_marks'] <=> (float)$a['final_marks'];
        if ($c !== 0) return $c;
        return $useTime ? ((int)$a['duration_seconds'] <=> (int)$b['duration_seconds']) : 0;
    });
    $same = fn($a, $b) => (float)$a['final_marks'] === (float)$b['final_marks'] && (!$useTime || (int)$a['duration_seconds'] === (int)$b['duration_seconds']);
    foreach ($ranked as $i => &$r) {
        $r['rank'] = ($i > 0 && $same($ranked[$i - 1], $r)) ? $ranked[$i - 1]['rank'] : $i + 1;
    }
    unset($r);
    return ['quiz' => $quiz, 'ranked' => $ranked, 'excluded' => $excluded];
}
function badge_for_rank(?int $rank, int $prizes): ?string {
    if ($prizes < 1 || $rank === null) return null;
    return $prizes >= 3 ? [1 => 'gold', 2 => 'silver', 3 => 'bronze'][$rank] ?? null : ($rank <= $prizes ? 'gold' : null);
}
function medal(?string $b): string { return ['gold' => '🥇', 'silver' => '🥈', 'bronze' => '🥉'][$b ?? ''] ?? ''; }

/** Saves an immutable, versioned snapshot. Earlier official results are marked superseded, never rewritten. */
function finalize_results(int $quizId, int $prizes, bool $publish, string $reason, array $admin): int {
    return tx(function () use ($quizId, $prizes, $publish, $reason, $admin) {
        db_one('SELECT id FROM quizzes WHERE id=?' . sql_lock(), [$quizId]);
        $lb = compute_leaderboard($quizId);
        $quiz = $lb['quiz'];
        if (!$quiz) throw new UserError('Quiz not found.');
        $prev = db_one("SELECT * FROM official_results WHERE quiz_id=? AND status='official' ORDER BY result_version DESC LIMIT 1", [$quizId]);
        if ($prev && $reason === '') throw new UserError('Results were already finalised. Enter a correction reason to publish a new version.');
        $ver = (int)db_val('SELECT COALESCE(MAX(result_version),0)+1 FROM official_results WHERE quiz_id=?', [$quizId]);
        $maxVer = db_val('SELECT MAX(quiz_version_id) FROM quiz_attempts WHERE quiz_id=?', [$quizId]);
        $rules = ['tiebreak' => $quiz['tiebreak'], 'timeout_counts' => (int)$quiz['timeout_counts'], 'max_attempts' => (int)$quiz['max_attempts'],
            'visibility_policy' => $quiz['visibility_policy'], 'visibility_threshold' => (int)$quiz['visibility_threshold'], 'prizes' => $prizes,
            'selection' => 'Best valid attempt per participant; rank by highest marks then (if enabled) shortest duration; unresolved ties share a rank.'];
        $rid = db_insert('INSERT INTO official_results(quiz_id,result_version,quiz_version_id,rules_json,prizes,correction_reason,published_at,finalized_by,finalized_by_name) VALUES(?,?,?,?,?,?,?,?,?)',
            [$quizId, $ver, $maxVer, json_encode($rules), $prizes, $reason ?: null, $publish ? now() : null, $admin['id'], $admin['full_name']]);
        if ($prev) db_exec("UPDATE official_results SET status='superseded' WHERE id=?", [$prev['id']]);
        $ins = 'INSERT INTO official_result_entries(result_id,final_rank,user_id,participant_name,attempt_id,quiz_version_id,score,max_marks,percentage,duration_seconds,eligibility,note,badge) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)';
        foreach ($lb['ranked'] as $r) db_exec($ins, [$rid, $r['rank'], $r['user_id'], $r['full_name'], $r['id'], $r['quiz_version_id'], $r['final_marks'], $r['max_marks'], $r['percentage'], $r['duration_seconds'], $r['eligibility'], null, badge_for_rank((int)$r['rank'], $prizes)]);
        foreach ($lb['excluded'] as $r) db_exec($ins, [$rid, null, $r['user_id'], $r['full_name'], $r['id'], $r['quiz_version_id'], $r['final_marks'], $r['max_marks'], $r['percentage'], $r['duration_seconds'], $r['eligibility'], $r['note'], null]);
        if ($publish) {
            foreach ($lb['ranked'] as $r) notify_user((int)$r['user_id'], 'Results published: ' . $quiz['title'], 'The official results are now available.', '/results/' . $quizId, "results:$rid");
            foreach ($lb['excluded'] as $r) notify_user((int)$r['user_id'], 'Results published: ' . $quiz['title'], 'The official results are now available.', '/results/' . $quizId, "results:$rid");
        }
        return $rid;
    });
}
