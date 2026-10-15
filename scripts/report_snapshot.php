<?php
// Usage: php scripts/report_snapshot.php > snapshot.json   — dumps every report's figures and every leaderboard, with no timestamps.
// Run once against SQLite and once against PostgreSQL, then diff the two files: they must be identical.
require __DIR__ . '/_cli.php';
$out = ['driver_independent' => true, 'reports' => [], 'leaderboards' => [], 'counts' => []];
foreach (['registration', 'participation', 'scores', 'competition', 'verification', 'activity', 'impact'] as $t) {
    $R = build_report($t, []);
    $out['reports'][$t] = ['kpis' => $R['kpis'], 'sections' => $R['sections']];
}
foreach (db_all('SELECT id FROM quizzes ORDER BY id') as $z) {
    $lb = compute_leaderboard((int)$z['id']);
    $out['leaderboards'][$z['id']] = ['ranked' => array_map(fn($r) => [(int)$r['user_id'], (int)$r['rank'], (float)$r['final_marks'], (int)$r['duration_seconds'], (int)$r['id']], $lb['ranked']), 'excluded' => count($lb['excluded'])];
}
foreach (['users', 'user_roles', 'quizzes', 'questions', 'question_options', 'quiz_versions', 'quiz_attempts', 'attempt_answers', 'integrity_events', 'eligibility_reviews', 'official_results', 'official_result_entries', 'notifications', 'announcements', 'audit_logs', 'attempt_grants', 'data_requests', 'age_groups', 'roles'] as $t) $out['counts'][$t] = (int)db_val("SELECT COUNT(*) FROM $t");
echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
