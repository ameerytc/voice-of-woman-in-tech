<?php
declare(strict_types=1);

const NA = 'Not yet available';
function pct_of($a, $b): string { return $b > 0 ? num(round($a / $b * 100, 1)) . '%' : NA; }
function fmt_avg($v): string { return $v === null ? NA : num(round((float)$v, 1)) . '%'; }

const REPORT_DEFS = [
    'registration' => ['Total registrations = participant accounts created in the period. Active = account status "active". Administrator accounts are excluded.'],
    'participation' => ['Attempt = a quiz attempt that has finished (submitted, timed out or abandoned). Completion rate = submitted ÷ finished attempts. Timeout rate = timed out ÷ finished attempts. Average score = mean percentage of submitted and timed-out attempts. Average duration = mean time of submitted attempts.'],
    'scores' => ['Percentage = marks earned ÷ maximum marks × 100 (after any recorded score correction). Pass rate only where the quiz has a passing score. Distribution buckets are percentage bands of finished attempts.'],
    'competition' => ['Participants = unique people with a finished attempt. Winners come from the latest finalised official result of each quiz. Disqualified attempts list the reason recorded by the system or a reviewer.'],
    'verification' => ['Eligibility is the current decision on each finished attempt. Manual decisions = reviewer decisions recorded with a reason; system flags are automatic (for example leaving the quiz page under an announced rule).'],
    'activity' => ['Monthly counts of new participant registrations, finished quiz attempts and official result versions finalised.'],
    'impact' => ['Unique participants reached = distinct people with a finished attempt. Quizzes delivered = quizzes with at least one finished attempt. Learning change is shown only for people who completed both a pre-test and a post-test of the same assessment group.'],
];

/** Builds a report from real records only. $f = ['from','to','quiz','age','pstatus'] (from/to UTC or null). */
function build_report(string $type, array $f): array {
    $a = ['from' => $f['from'] ?? '0000-01-01 00:00:00', 'to' => $f['to'] ?? '9999-12-31 23:59:59'];
    $quizId = (int)($f['quiz'] ?? 0); $age = (int)($f['age'] ?? 0); $ps = $f['pstatus'] ?? '';
    $w = ' AND a.started_at BETWEEN :from AND :to';
    if ($quizId) $w .= ' AND a.quiz_id=' . $quizId;
    if ($age) $w .= ' AND u.age_group_id=' . $age;
    $stat = in_array($ps, ['submitted', 'timed_out', 'abandoned'], true) ? "a.status='$ps'" : "a.status IN ('submitted','timed_out','abandoned')";
    $fin = $stat . $w;
    $J = 'FROM quiz_attempts a JOIN users u ON u.id=a.user_id';
    $regW = ' AND u.created_at BETWEEN :from AND :to' . ($age ? ' AND u.age_group_id=' . $age : '');
    $q = fn(string $sql) => db_all($sql, $a);
    $v = fn(string $sql) => db_val($sql, $a);
    $R = ['kpis' => [], 'sections' => [], 'defs' => REPORT_DEFS[$type][0] ?? '', 'scope' => 'Participant accounts and quiz attempts stored in the VW database at the time this report was generated. In-progress attempts and administrator accounts are excluded.'];

    if ($type === 'registration') {
        $R['title'] = 'Participant Registration Report';
        $tot = (int)$v('SELECT COUNT(*) FROM users u WHERE ' . PART_COND . $regW);
        $act = (int)$v("SELECT COUNT(*) FROM users u WHERE " . PART_COND . " AND u.status='active'" . $regW);
        $R['kpis'] = ['Total registrations' => $tot, 'Active accounts' => $act, 'Inactive / suspended / deleted accounts' => $tot - $act];
        $R['sections'][] = ['Registrations by month', ['Month', 'Registrations'], array_map(fn($r) => [$r['m'], $r['c']], $q("SELECT substr(u.created_at,1,7) m, COUNT(*) c FROM users u WHERE " . PART_COND . $regW . " GROUP BY substr(u.created_at,1,7) ORDER BY m"))];
        $R['sections'][] = ['Registrations by age group', ['Age group', 'Registrations'], array_map(fn($r) => [$r['l'], $r['c']], $q("SELECT COALESCE(g.label,'Not provided') l, COUNT(*) c FROM users u LEFT JOIN age_groups g ON g.id=u.age_group_id WHERE " . PART_COND . $regW . " GROUP BY COALESCE(g.label,'Not provided') ORDER BY MIN(g.sort_order)"))];
        $R['sections'][] = ['Account status', ['Status', 'Accounts'], array_map(fn($r) => [ucfirst($r['status']), $r['c']], $q("SELECT u.status, COUNT(*) c FROM users u WHERE " . PART_COND . $regW . " GROUP BY u.status"))];
    } elseif ($type === 'participation') {
        $R['title'] = 'Quiz Participation Report';
        $att = (int)$v("SELECT COUNT(*) $J WHERE $fin");
        $sub = (int)$v("SELECT COUNT(*) $J WHERE a.status='submitted'$w");
        $tmo = (int)$v("SELECT COUNT(*) $J WHERE a.status='timed_out'$w");
        $allFin = (int)$v("SELECT COUNT(*) $J WHERE a.status IN ('submitted','timed_out','abandoned')$w");
        $uniq = (int)$v("SELECT COUNT(DISTINCT a.user_id) $J WHERE $fin");
        $avgS = $v("SELECT AVG(a.percentage) $J WHERE a.status IN ('submitted','timed_out')$w");
        $avgD = $v("SELECT AVG(a.duration_seconds) $J WHERE a.status='submitted'$w");
        $R['kpis'] = ['Total published quizzes' => (int)db_val("SELECT COUNT(*) FROM quizzes WHERE status IN ('published','closed','archived','scheduled') AND (:q=0 OR id=:q)", ['q' => $quizId]),
            'Total quiz attempts (finished)' => $att, 'Unique participants' => $uniq, 'Completion rate' => pct_of($sub, $allFin), 'Average score' => fmt_avg($avgS),
            'Average completion duration' => $avgD === null ? NA : fmt_duration((int)round((float)$avgD)), 'Timeout rate' => pct_of($tmo, $allFin)];
        $R['sections'][] = ['Participation by quiz', ['Quiz', 'Attempts', 'Completed', 'Timed out', 'Unique participants', 'Average score'],
            array_map(fn($r) => [$r['title'], $r['n'], $r['s'], $r['t'], $r['u'], fmt_avg($r['avg'])],
                $q("SELECT z.title, COUNT(*) n, SUM(CASE WHEN a.status='submitted' THEN 1 ELSE 0 END) s, SUM(CASE WHEN a.status='timed_out' THEN 1 ELSE 0 END) t, COUNT(DISTINCT a.user_id) u, AVG(CASE WHEN a.status IN ('submitted','timed_out') THEN a.percentage END) avg
                    FROM quiz_attempts a JOIN users u ON u.id=a.user_id JOIN quizzes z ON z.id=a.quiz_id WHERE $fin GROUP BY z.id ORDER BY n DESC"))];
        $R['sections'][] = ['Participation by age group', ['Age group', 'Attempts', 'Unique participants'],
            array_map(fn($r) => [$r['l'], $r['n'], $r['u']], $q("SELECT COALESCE(g.label,'Not provided') l, COUNT(*) n, COUNT(DISTINCT a.user_id) u FROM quiz_attempts a JOIN users u ON u.id=a.user_id LEFT JOIN age_groups g ON g.id=u.age_group_id WHERE $fin GROUP BY COALESCE(g.label,'Not provided') ORDER BY n DESC"))];
    } elseif ($type === 'scores') {
        $R['title'] = 'Assessment Scores Report';
        $sc = "a.status IN ('submitted','timed_out')$w";
        $b = fn($lo, $hi) => (int)$v("SELECT COUNT(*) $J WHERE $sc AND a.percentage>=$lo AND a.percentage<$hi");
        $n = (int)$v("SELECT COUNT(*) $J WHERE $sc");
        $R['kpis'] = ['Scored attempts' => $n, 'Average score' => fmt_avg($v("SELECT AVG(a.percentage) $J WHERE $sc")), 'Highest score' => $n ? num((float)$v("SELECT MAX(a.percentage) $J WHERE $sc")) . '%' : NA, 'Lowest score' => $n ? num((float)$v("SELECT MIN(a.percentage) $J WHERE $sc")) . '%' : NA];
        $R['sections'][] = ['Score distribution', ['Band', 'Attempts'], $n ? [['0–19%', $b(0, 20)], ['20–39%', $b(20, 40)], ['40–59%', $b(40, 60)], ['60–79%', $b(60, 80)], ['80–100%', $b(80, 100.01)]] : []];
        $R['sections'][] = ['Scores by quiz', ['Quiz', 'Scored attempts', 'Average', 'Lowest', 'Highest', 'Pass rate (if passing score set)'],
            array_map(fn($r) => [$r['title'], $r['n'], fmt_avg($r['av']), num((float)$r['mn']) . '%', num((float)$r['mx']) . '%', (int)$r['graded'] > 0 ? pct_of($r['passed'], $r['graded']) : NA],
                $q("SELECT z.title, COUNT(*) n, AVG(a.percentage) av, MIN(a.percentage) mn, MAX(a.percentage) mx, SUM(CASE WHEN z.passing_percent IS NOT NULL AND a.percentage>=z.passing_percent THEN 1 ELSE 0 END) passed, SUM(CASE WHEN z.passing_percent IS NOT NULL THEN 1 ELSE 0 END) graded
                    FROM quiz_attempts a JOIN users u ON u.id=a.user_id JOIN quizzes z ON z.id=a.quiz_id WHERE $sc GROUP BY z.id ORDER BY n DESC"))];
    } elseif ($type === 'competition') {
        $R['title'] = 'Competition Results Report';
        $rows = []; $dq = []; $win = [];
        foreach (db_all('SELECT * FROM quizzes WHERE (:q=0 OR id=:q) ORDER BY opens_at DESC', ['q' => $quizId]) as $z) {
            $p = ['id' => $z['id'], 'from' => $a['from'], 'to' => $a['to']];
            $base = "FROM quiz_attempts a JOIN users u ON u.id=a.user_id WHERE a.quiz_id=:id AND a.started_at BETWEEN :from AND :to" . ($age ? ' AND u.age_group_id=' . $age : '');
            $n = (int)db_val("SELECT COUNT(DISTINCT a.user_id) $base AND a.status IN ('submitted','timed_out','abandoned')", $p);
            if ($n === 0) continue;
            $bk = fn($lo, $hi) => (int)db_val("SELECT COUNT(*) $base AND a.status IN ('submitted','timed_out') AND a.percentage>=$lo AND a.percentage<$hi", $p);
            $pend = (int)db_val("SELECT COUNT(*) $base AND a.eligibility='pending_review'", $p);
            $off = db_one("SELECT * FROM official_results WHERE quiz_id=? AND status='official' ORDER BY result_version DESC LIMIT 1", [$z['id']]);
            $rows[] = [$z['title'], fmt_dt($z['opens_at'], 'd M Y'), $n, $bk(0, 40), $bk(40, 60), $bk(60, 80), $bk(80, 100.01), $pend, $off ? 'v' . $off['result_version'] . ' finalised ' . fmt_dt($off['finalized_at']) : 'Not finalised'];
            if ($off) foreach (db_all('SELECT * FROM official_result_entries WHERE result_id=? AND final_rank IS NOT NULL AND final_rank<=? ORDER BY final_rank, id', [$off['id'], max(1, (int)$off['prizes'])]) as $e)
                $win[] = [$z['title'], $e['final_rank'], public_identity($e, (int)$off['id']), num($e['score']) . '/' . $e['max_marks'], fmt_duration($e['duration_seconds'] === null ? null : (int)$e['duration_seconds'])];
            foreach (db_all("SELECT u.full_name, a.attempt_no, a.eligibility_reason $base AND a.eligibility='disqualified'", $p) as $d) $dq[] = [$z['title'], $d['full_name'], $d['attempt_no'], $d['eligibility_reason']];
        }
        $R['kpis'] = ['Competitions with participation' => count($rows), 'Disqualified attempts' => count($dq), 'Pending verification cases' => array_sum(array_column($rows, 7))];
        $R['sections'][] = ['Competitions', ['Quiz', 'Date', 'Participants', 'Score 0–39%', '40–59%', '60–79%', '80–100%', 'Pending verification', 'Official result'], $rows];
        $R['sections'][] = ['Finalised winners (identity format follows the public-results setting)', ['Quiz', 'Rank', 'Participant', 'Score', 'Time (mm:ss)'], $win];
        $R['sections'][] = ['Disqualified attempts and documented reasons', ['Quiz', 'Participant', 'Attempt', 'Reason'], $dq];
    } elseif ($type === 'verification') {
        $R['title'] = 'Verification Outcomes Report';
        $by = $q("SELECT a.eligibility e, COUNT(*) c $J WHERE a.status IN ('submitted','timed_out','abandoned')$w GROUP BY a.eligibility");
        $man = $q("SELECT r.decision d, COUNT(*) c FROM eligibility_reviews r JOIN quiz_attempts a ON a.id=r.attempt_id JOIN users u ON u.id=a.user_id WHERE r.is_system=0 AND r.created_at BETWEEN :from AND :to" . ($quizId ? ' AND a.quiz_id=' . $quizId : '') . ($age ? ' AND u.age_group_id=' . $age : '') . " GROUP BY r.decision");
        $sys = (int)$v("SELECT COUNT(*) FROM eligibility_reviews r JOIN quiz_attempts a ON a.id=r.attempt_id JOIN users u ON u.id=a.user_id WHERE r.is_system=1 AND r.decision IN ('pending_review','disqualified') AND r.created_at BETWEEN :from AND :to" . ($quizId ? ' AND a.quiz_id=' . $quizId : '') . ($age ? ' AND u.age_group_id=' . $age : ''));
        $m = array_column($by, 'c', 'e');
        $R['kpis'] = ['Pending review now' => (int)($m['pending_review'] ?? 0), 'Disqualified' => (int)($m['disqualified'] ?? 0), 'Eligible' => (int)($m['eligible'] ?? 0), 'Automatic flags raised' => $sys, 'Manual reviewer decisions' => array_sum(array_column($man, 'c'))];
        $R['sections'][] = ['Current eligibility of finished attempts', ['Eligibility', 'Attempts'], array_map(fn($r) => [ELIG_LABELS[$r['e']] ?? $r['e'], $r['c']], $by)];
        $R['sections'][] = ['Manual reviewer decisions', ['Decision', 'Count'], array_map(fn($r) => [ELIG_LABELS[$r['d']] ?? $r['d'], $r['c']], $man)];
        $R['sections'][] = ['Disqualification reasons', ['Quiz', 'Participant', 'Reason'], array_map(fn($r) => [$r['title'], $r['full_name'], $r['eligibility_reason']], $q("SELECT z.title, u.full_name, a.eligibility_reason FROM quiz_attempts a JOIN users u ON u.id=a.user_id JOIN quizzes z ON z.id=a.quiz_id WHERE a.eligibility='disqualified'$w ORDER BY a.id DESC"))];
    } elseif ($type === 'activity') {
        $R['title'] = 'Program Activity Over Time';
        $reg = array_column($q("SELECT substr(u.created_at,1,7) m, COUNT(*) c FROM users u WHERE " . PART_COND . $regW . " GROUP BY substr(u.created_at,1,7)"), 'c', 'm');
        $att = array_column($q("SELECT substr(a.started_at,1,7) m, COUNT(*) c $J WHERE $fin GROUP BY substr(a.started_at,1,7)"), 'c', 'm');
        $res = array_column(db_all("SELECT substr(finalized_at,1,7) m, COUNT(*) c FROM official_results WHERE finalized_at BETWEEN :from AND :to" . ($quizId ? ' AND quiz_id=' . $quizId : '') . " GROUP BY substr(finalized_at,1,7)", ['from' => $a['from'], 'to' => $a['to']]), 'c', 'm');
        $months = array_unique(array_merge(array_keys($reg), array_keys($att), array_keys($res))); sort($months);
        $R['kpis'] = ['Registrations' => array_sum($reg), 'Finished attempts' => array_sum($att), 'Result versions finalised' => array_sum($res)];
        $R['sections'][] = ['Month by month', ['Month', 'New registrations', 'Finished attempts', 'Results finalised'], array_map(fn($mo) => [$mo, $reg[$mo] ?? 0, $att[$mo] ?? 0, $res[$mo] ?? 0], $months)];
    } else {
        $R['title'] = 'Program Impact Report';
        $uniq = (int)$v("SELECT COUNT(DISTINCT a.user_id) $J WHERE $fin");
        $delivered = (int)$v("SELECT COUNT(DISTINCT a.quiz_id) $J WHERE $fin");
        $done = (int)$v("SELECT COUNT(*) $J WHERE a.status='submitted'$w");
        $R['kpis'] = ['Unique participants reached' => $uniq, 'Quizzes delivered (with participation)' => $delivered, 'Completed learning assessments' => $done];
        $R['sections'][] = ['Participation trend (attempts per month)', ['Month', 'Finished attempts', 'Unique participants'], array_map(fn($r) => [$r['m'], $r['n'], $r['u']], $q("SELECT substr(a.started_at,1,7) m, COUNT(*) n, COUNT(DISTINCT a.user_id) u $J WHERE $fin GROUP BY substr(a.started_at,1,7) ORDER BY m"))];
        $st = $q("SELECT COALESCE(NULLIF(TRIM(u.state),''),'Not provided') s, COUNT(DISTINCT a.user_id) n $J WHERE $fin GROUP BY COALESCE(NULLIF(TRIM(u.state),''),'Not provided') ORDER BY n DESC");
        $has = count(array_filter($st, fn($r) => $r['s'] !== 'Not provided')) > 0;
        $R['sections'][] = ['Self-reported state of residence (optional field)', ['State', 'Participants'], $has ? array_map(fn($r) => [$r['s'], $r['n']], $st) : [[NA, '']]];
        $cmp = [];
        foreach (db_all("SELECT DISTINCT assessment_group g FROM quizzes WHERE assessment_type IN ('pre_test','post_test') AND assessment_group IS NOT NULL AND assessment_group<>''") as $g) {
            $best = fn(string $t) => db_all("SELECT a.user_id, MAX(a.percentage) p FROM quiz_attempts a JOIN quizzes z ON z.id=a.quiz_id
                WHERE z.assessment_group=? AND z.assessment_type=? AND a.status IN ('submitted','timed_out') AND a.eligibility IN ('eligible','timed_out') GROUP BY a.user_id", [$g['g'], $t]);
            $pre = array_column($best('pre_test'), 'p', 'user_id'); $post = array_column($best('post_test'), 'p', 'user_id');
            $both = array_intersect_key($pre, $post);
            if (!$both) continue;
            $d = []; foreach ($both as $uid => $pv) $d[] = (float)$post[$uid] - (float)$pv;
            $cmp[] = [$g['g'], count($both), num(round(array_sum(array_map('floatval', $both)) / count($both), 1)) . '%', num(round(array_sum(array_map('floatval', array_intersect_key($post, $both))) / count($both), 1)) . '%', num(round(array_sum($d) / count($d), 1)) . ' pts'];
        }
        $R['sections'][] = ['Pre-test / post-test comparison (matched participants only)', ['Assessment group', 'Participants with both', 'Avg pre-test', 'Avg post-test', 'Avg change'], $cmp ?: [[NA, '', '', '', '']]];
        $R['note'] = 'Learning-gain figures appear only where the same participant completed both a pre-test and a post-test in the same assessment group. No improvement is claimed otherwise.';
    }
    return $R;
}
