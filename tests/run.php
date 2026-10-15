<?php
// Usage: php tests/run.php                       — SQLite, throwaway temp file
//        TEST_DATABASE_URL=postgresql://... php tests/run.php — PostgreSQL, in a scratch schema "vw_test" (use a scratch/dev database, never production)
declare(strict_types=1);
$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'vw_test_' . bin2hex(random_bytes(4)) . '.sqlite';
$pgUrl = getenv('TEST_DATABASE_URL') ?: '';
$_ENV['APP_ENV'] = 'development';
if ($pgUrl !== '') { $_ENV['DATABASE_URL'] = $pgUrl; $_ENV['DB_DRIVER'] = 'pgsql'; } else { $_ENV['DB_DRIVER'] = 'sqlite'; $_ENV['DB_PATH'] = $tmp; }
require dirname(__DIR__) . '/app/bootstrap.php';
$_SESSION = [];
if (db_is_pg()) { db()->exec('DROP SCHEMA IF EXISTS vw_test CASCADE'); db()->exec('CREATE SCHEMA vw_test'); db()->exec('SET search_path TO vw_test'); }
$pass = 0; $fail = 0;
function t(string $name, bool $cond, string $info = ''): void { global $pass, $fail; if ($cond) { $pass++; echo "  PASS  $name\n"; } else { $fail++; echo "  FAIL  $name $info\n"; } }
function throws(callable $f): bool { try { $f(); return false; } catch (Throwable $e) { return true; } }
function mkuser(string $n, string $role = ''): int {
    $id = db_insert('INSERT INTO users(full_name,email,password_hash,age_group_id) VALUES(?,?,?,1)', [$n, strtolower(str_replace(' ', '.', $n)) . '@example.com', password_hash('Passw0rdTest', PASSWORD_DEFAULT)]);
    if ($role) db_exec('INSERT INTO user_roles(user_id,role_id) SELECT CAST(? AS INTEGER),id FROM roles WHERE name=?', [$id, $role]);
    return $id;
}
function mkquiz(array $o = []): int {
    $q = $o + ['title' => 'Test quiz', 'status' => 'published', 'max_attempts' => 1, 'visibility_policy' => 'review', 'visibility_threshold' => 2, 'timeout_counts' => 1, 'tiebreak' => 'time'];
    $id = db_insert('INSERT INTO quizzes(title,duration_minutes,opens_at,closes_at,status,max_attempts,visibility_policy,visibility_threshold,timeout_counts,tiebreak) VALUES(?,?,?,?,?,?,?,?,?,?)',
        [$q['title'], 10, gmdate('Y-m-d H:i:s', time() - 3600), gmdate('Y-m-d H:i:s', time() + 86400), $q['status'], $q['max_attempts'], $q['visibility_policy'], $q['visibility_threshold'], $q['timeout_counts'], $q['tiebreak']]);
    foreach ([[1, 'Q one'], [2, 'Q two'], [3, 'Q three']] as [$n, $b]) {
        $qid = db_insert('INSERT INTO questions(quiz_id,type,body,marks,sort_order) VALUES(?,?,?,?,?)', [$id, 'mcq', $b, $n, $n]);   // marks 1,2,3 = 6 total
        foreach (['right', 'wrong'] as $k => $o) db_exec('INSERT INTO question_options(question_id,body,is_correct,sort_order) VALUES(?,?,?,?)', [$qid, $o, $k === 0 ? 1 : 0, $k + 1]);
    }
    return $id;
}
/** Play an attempt: $correct = list of question numbers (1-3) answered correctly; others answered wrong. $secs back-dates the start so duration = $secs. */
function play(int $uid, int $quiz, array $correct, int $secs = 60, int $lefts = 0): int {
    $aid = start_attempt($uid, $quiz);
    $a = db_one('SELECT * FROM quiz_attempts WHERE id=?', [$aid]);
    foreach (db_all('SELECT * FROM questions WHERE quiz_id=? ORDER BY sort_order', [$quiz]) as $i => $q) {
        $opt = db_all('SELECT * FROM question_options WHERE question_id=? ORDER BY sort_order', [$q['id']]);
        $pick = in_array($i + 1, $correct, true) ? $opt[0] : $opt[1];
        db_exec('INSERT INTO attempt_answers(attempt_id,question_id,option_id) VALUES(?,?,?)', [$aid, $q['id'], $pick['id']]);
    }
    for ($i = 0; $i < $lefts; $i++) db_exec("INSERT INTO integrity_events(attempt_id,event_type) VALUES(?, 'left_page')", [$aid]);
    db_exec('UPDATE quiz_attempts SET started_at=?, deadline_at=? WHERE id=?', [gmdate('Y-m-d H:i:s', time() - $secs), gmdate('Y-m-d H:i:s', time() - $secs + 600), $aid]);
    finalize_attempt($aid);
    return $aid;
}

echo "Voice of Women in Tech — acceptance tests (driver: " . db_driver() . (db_is_pg() ? ", scratch schema vw_test" : ", temporary DB: $tmp") . ")\n";
run_migrations();
db_val('SELECT 1');

echo "\nEmpty database\n";
t('T22 reports on an empty database work and show zero', build_report('participation', [])['kpis']['Total quiz attempts (finished)'] === 0 && build_report('impact', [])['kpis']['Unique participants reached'] === 0);
t('T22 registration report empty', build_report('registration', [])['kpis']['Total registrations'] === 0);

echo "\nValidation helpers (registration rules)\n";
t('T1 phone 0803… normalised', normalize_phone('0803 123 4567') === '+2348031234567');
t('T1 invalid phone rejected', normalize_phone('12345') === null);
t('T1 email validation', valid_email('a@b.com') && !valid_email('nope'));
t('T1 weak password rejected', valid_password('short') !== null && valid_password('GoodPass123') === null);
$u1 = mkuser('Ada One');
t('T2 duplicate email rejected by database', throws(fn() => db_insert('INSERT INTO users(full_name,email,password_hash) VALUES(?,?,?)', ['Dup', 'ADA.ONE@example.com', 'x'])));
t('T3 password hash verifies', password_verify('Passw0rdTest', db_val('SELECT password_hash FROM users WHERE id=?', [$u1])));

echo "\nPermissions\n";
$part = mkuser('Plain Participant'); $rev = mkuser('Rita Reviewer', 'results_reviewer'); $rep = mkuser('Ray Reporter', 'report_viewer'); $sup = mkuser('Sam Super', 'super_admin');
$as = function (int $id) { $_SESSION['uid'] = $id; };
$as($part); t('T4 participant is not an admin', !is_admin() && !has_perm('results.manage'));
$as($rep); t('T20 report viewer cannot finalise or review', !has_perm('results.manage') && !has_perm('submissions.review') && has_perm('reports.view'));
$as($rev); t('T20 reviewer can review and finalise', has_perm('results.manage') && has_perm('submissions.review') && !has_perm('admins.manage'));
$as($sup); t('Super admin has every permission', has_perm('admins.manage') && has_perm('audit.view') && has_perm('anything.at.all'));
$_SESSION = [];

echo "\nQuiz engine\n";
$z = mkquiz();
$a = play($u1, $z, [1, 3], 120);
$r = db_one('SELECT * FROM quiz_attempts WHERE id=?', [$a]);
t('T9 server scored 1+3 = 4 of 6', (float)$r['final_marks'] === 4.0 && (int)$r['max_marks'] === 6 && abs((float)$r['percentage'] - 66.67) < 0.01, json_encode($r));
t('T9 duration and status recorded', (int)$r['duration_seconds'] === 120 && $r['status'] === 'submitted' && $r['eligibility'] === 'eligible');
t('T7 rules acknowledgement stored', $r['rules_ack_at'] !== null && (int)db_val("SELECT COUNT(*) FROM integrity_events WHERE attempt_id=? AND event_type='rules_acknowledged'", [$a]) === 1);
t('T10 second attempt denied by the server', throws(fn() => start_attempt($u1, $z)));
db_exec('INSERT INTO attempt_grants(user_id,quiz_id,extra_attempts,reason) VALUES(?,?,1,?)', [$u1, $z, 'test']);
t('T10 extra attempt works only after authorisation', is_int(start_attempt($u1, $z)));
db_exec("UPDATE quiz_attempts SET status='submitted', eligibility='incomplete' WHERE user_id=? AND status='in_progress'", [$u1]);
t('T10 DB unique index blocks duplicate attempt numbers', throws(fn() => db_exec('INSERT INTO quiz_attempts(quiz_id,quiz_version_id,user_id,attempt_no,started_at,deadline_at,rules_ack_at,layout_json) SELECT quiz_id,quiz_version_id,user_id,attempt_no,started_at,deadline_at,rules_ack_at,layout_json FROM quiz_attempts WHERE id=?', [$a])));
$z2 = mkquiz(['title' => 'Deadline quiz']);
$u2 = mkuser('Bola Two');
$aid = start_attempt($u2, $z2);
db_exec('UPDATE quiz_attempts SET deadline_at=? WHERE id=?', [gmdate('Y-m-d H:i:s', time() - 5), $aid]);
$res = finalize_attempt($aid);
t('T8 expired attempt becomes timed_out', $res['status'] === 'timed_out' && $res['eligibility'] === 'timed_out');
t('T8 finalising twice does not change the result', finalize_attempt($aid)['id'] === $res['id'] && (int)db_val('SELECT COUNT(*) FROM eligibility_reviews WHERE attempt_id=?', [$aid]) === 1);
t('T6 closed/unpublished quiz cannot be started', throws(fn() => start_attempt(mkuser('Cy Three'), mkquiz(['status' => 'draft']))));

echo "\nRanking and review\n";
$R = mkquiz(['title' => 'Ranking quiz']);
$p = []; foreach (['P1', 'P2', 'P3', 'P4', 'P5', 'P6'] as $n) $p[$n] = mkuser("Rank $n");
play($p['P1'], $R, [1, 2, 3], 300);          // 6 marks, slow
play($p['P2'], $R, [1, 2, 3], 100);          // 6 marks, fast  -> should beat P1
play($p['P3'], $R, [3], 50);                 // 3 marks
play($p['P4'], $R, [1, 2, 3], 10, 2);        // 6 marks but left page 2x -> pending review
play($p['P5'], $R, [3], 50);                 // 3 marks, same time as P3 -> tie preserved
$lb = compute_leaderboard($R); $rk = [];
foreach ($lb['ranked'] as $e) $rk[$e['full_name']] = $e['rank'];
t('T11 highest score ranked first, then faster time', ($rk['Rank P2'] ?? 0) === 1 && ($rk['Rank P1'] ?? 0) === 2, json_encode($rk));
t('T12 equal scores ordered by time (P2 before P1)', $lb['ranked'][0]['full_name'] === 'Rank P2');
t('T12 identical score and time keep a shared rank', ($rk['Rank P3'] ?? 0) === 3 && ($rk['Rank P5'] ?? 0) === 3);
t('T13 REVIEW attempt excluded until verified', !isset($rk['Rank P4']));
$p4 = (int)db_val('SELECT id FROM quiz_attempts WHERE user_id=? AND quiz_id=?', [$p['P4'], $R]);
t('T13 attempt is Pending Review', db_val('SELECT eligibility FROM quiz_attempts WHERE id=?', [$p4]) === 'pending_review');
db_exec("UPDATE quiz_attempts SET eligibility='eligible', eligibility_reason='Approved after review' WHERE id=?", [$p4]);
$rk2 = []; foreach (compute_leaderboard($R)['ranked'] as $e) $rk2[$e['full_name']] = $e['rank'];
t('T13 after approval the attempt is ranked (fastest of the 6-mark group)', ($rk2['Rank P4'] ?? 0) === 1, json_encode($rk2));
db_exec("UPDATE quiz_attempts SET eligibility='disqualified', eligibility_reason='Confirmed tab switching' WHERE id=?", [$p4]);
db_exec('INSERT INTO eligibility_reviews(attempt_id,decision,reason,reviewer_id) VALUES(?,?,?,?)', [$p4, 'disqualified', 'Confirmed tab switching', $rev]);
t('T14 disqualification reason stored and visible', db_val('SELECT reason FROM eligibility_reviews WHERE attempt_id=? ORDER BY id DESC LIMIT 1', [$p4]) === 'Confirmed tab switching');
t('T14 disqualified attempt excluded from ranking', !isset(array_column(compute_leaderboard($R)['ranked'], 'rank', 'full_name')['Rank P4']));
$Rn = mkquiz(['title' => 'No timeouts', 'timeout_counts' => 0]); $tu = mkuser('Timo Out');
$ta = start_attempt($tu, $Rn); db_exec('UPDATE quiz_attempts SET deadline_at=? WHERE id=?', [gmdate('Y-m-d H:i:s', time() - 9), $ta]); finalize_attempt($ta);
t('Timed-out attempts excluded when the announced rules require it', count(compute_leaderboard($Rn)['ranked']) === 0);
$Rd = mkquiz(['title' => 'Disq policy', 'visibility_policy' => 'disqualify']);
$da = play(mkuser('Dee Q'), $Rd, [1, 2, 3], 30, 2);
t('T14 auto-disqualification under announced rule stores reason', db_val('SELECT eligibility FROM quiz_attempts WHERE id=?', [$da]) === 'disqualified' && str_contains((string)db_val('SELECT eligibility_reason FROM quiz_attempts WHERE id=?', [$da]), 'disqualified'));

echo "\nOfficial results snapshot and versioning\n";
$rid = finalize_results($R, 3, true, '', ['id' => $rev, 'full_name' => 'Rita Reviewer']);
$snap1 = db_all('SELECT final_rank, participant_name, score, duration_seconds, badge FROM official_result_entries WHERE result_id=? AND final_rank IS NOT NULL ORDER BY final_rank, id', [$rid]);
t('T15 snapshot saved with ranks, scores and badges', count($snap1) === 4 && $snap1[0]['badge'] === 'gold' && $snap1[1]['badge'] === 'silver', json_encode($snap1));
t('T15 second finalisation without reason is refused', throws(fn() => finalize_results($R, 3, true, '', ['id' => $rev, 'full_name' => 'Rita'])));
// edit the quiz AFTER attempts exist
$q1 = (int)db_val('SELECT id FROM questions WHERE quiz_id=? ORDER BY sort_order LIMIT 1', [$R]);
db_exec('UPDATE question_options SET is_correct = 1 - is_correct WHERE question_id=?', [$q1]);
db_exec("UPDATE questions SET body='CHANGED', marks=50 WHERE id=?", [$q1]);
db_exec('UPDATE quizzes SET duration_minutes=99 WHERE id=?', [$R]);
$after = db_one('SELECT final_marks, max_marks FROM quiz_attempts WHERE user_id=? AND quiz_id=?', [$p['P2'], $R]);
t('T23 editing a quiz does not change historical scores', (float)$after['final_marks'] === 6.0 && (int)$after['max_marks'] === 6);
$snap2 = db_all('SELECT final_rank, participant_name, score, duration_seconds, badge FROM official_result_entries WHERE result_id=? AND final_rank IS NOT NULL ORDER BY final_rank, id', [$rid]);
t('T15/T23 official snapshot unchanged after quiz edit', $snap1 === $snap2);
$newAttempt = (int)db_val('SELECT COUNT(DISTINCT quiz_version_id) FROM quiz_attempts WHERE quiz_id=?', [$R]);
$late = mkuser('Late Comer'); $la = start_attempt($late, $R);
t('T23 new attempts use a NEW quiz version while old ones keep theirs', (int)db_val('SELECT COUNT(DISTINCT quiz_version_id) FROM quiz_attempts WHERE quiz_id=?', [$R]) === $newAttempt + 1);
$rid2 = finalize_results($R, 3, false, 'Reviewer approved P4 appeal', ['id' => $rev, 'full_name' => 'Rita Reviewer']);
t('T15 correction creates version 2 and supersedes (not rewrites) version 1', (int)db_val('SELECT result_version FROM official_results WHERE id=?', [$rid2]) === 2 && db_val('SELECT status FROM official_results WHERE id=?', [$rid]) === 'superseded' && $snap1 === db_all('SELECT final_rank, participant_name, score, duration_seconds, badge FROM official_result_entries WHERE result_id=? AND final_rank IS NOT NULL ORDER BY final_rank, id', [$rid]));

echo "\nReports and statistics\n";
$rp = build_report('participation', []);
t('T16 attempt count comes from real records', $rp['kpis']['Total quiz attempts (finished)'] === (int)db_val("SELECT COUNT(*) FROM quiz_attempts WHERE status IN ('submitted','timed_out','abandoned')"));
t('T18 impact report shows "Not yet available" without pre/post data', build_report('impact', [])['sections'][2][2][0][0] === NA);
db_exec("UPDATE quizzes SET assessment_type='pre_test', assessment_group='G1' WHERE id=?", [$z]); db_exec("UPDATE quizzes SET assessment_type='post_test', assessment_group='G1' WHERE id=?", [$z2]);
play($u2, $z, [1], 30); play($u1, $z2, [1, 2, 3], 30);
t('Pre/post comparison appears only for matched participants', (build_report('impact', [])['sections'][2][2][0][0] ?? '') === 'G1');
t('Competition report lists disqualification reasons', count(build_report('competition', [])['sections'][2][2]) >= 1);

echo "\nProfile pictures, identity and portability\n";
$av = mkuser('Avi Tar');
save_avatar($av, 'image/jpeg', "\xFF\xD8\xFF\xE0binary-\x00-data");
$ld = load_avatar($av);
t('Avatar bytes survive a round trip through the database (BLOB/BYTEA)', $ld !== null && $ld['data'] === "\xFF\xD8\xFF\xE0binary-\x00-data" && $ld['mime'] === 'image/jpeg');
save_avatar($av, 'image/png', 'second'); t('Avatar can be replaced (upsert)', load_avatar($av)['data'] === 'second' && (int)db_val('SELECT COUNT(*) FROM user_avatars WHERE user_id=?', [$av]) === 1);
$e = ['user_id' => 42, 'participant_name' => 'Amina Bello Yusuf'];
set_setting('public_identity', 'masked'); t('Public identity: first name + initial', public_identity($e, 1) === 'Amina Y.');
set_setting('public_identity', 'id'); t('Public identity: participant ID', public_identity($e, 1) === 'VW-00042');
set_setting('public_identity', 'anonymous'); $an = public_identity($e, 1); t('Public identity: stable anonymous code without the name', $an === public_identity($e, 1) && !str_contains($an, 'Amina'));
t('Database ping works', db_ping());
$cnt = db_val("SELECT COUNT(*) FROM notifications WHERE user_id=?", [$av]); notify_user($av, 'x', 'y', null, 'k1'); notify_user($av, 'x', 'y', null, 'k1');
t('Duplicate notification keys are ignored (ON CONFLICT DO NOTHING)', (int)db_val('SELECT COUNT(*) FROM notifications WHERE user_id=?', [$av]) === (int)$cnt + 1);
notify_all_participants('Hello all', 'body', '/dashboard', 'ann:test'); notify_all_participants('Hello all', 'body', '/dashboard', 'ann:test');
t('Broadcast notifications are created once per participant', (int)db_val("SELECT COUNT(*) FROM notifications WHERE dedupe_key='ann:test'") === (int)db_val("SELECT COUNT(*) FROM users u WHERE u.status='active' AND " . PART_COND));
$pp = db_prepare_sql("SELECT :a, :b, :a WHERE x = 'it:s :not' AND y::text = :b", ['a' => 1, 'b' => 2]);
t('Named parameters may repeat and quoted text is left alone', $pp[0] === "SELECT ?, ?, ? WHERE x = 'it:s :not' AND y::text = ?" && $pp[1] === [1, 2, 1, 2]);

echo "\nResult: $pass passed, $fail failed\n";
if (db_is_pg()) db()->exec('DROP SCHEMA IF EXISTS vw_test CASCADE'); else { @unlink($tmp); @unlink($tmp . '-wal'); @unlink($tmp . '-shm'); }
exit($fail ? 1 : 0);
