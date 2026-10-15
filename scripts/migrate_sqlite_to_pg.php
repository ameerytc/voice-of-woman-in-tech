<?php
/**
 * One-way, verified migration: SQLite file -> PostgreSQL.
 * Usage: DATABASE_URL=postgresql://... php scripts/migrate_sqlite_to_pg.php path/to/vw.sqlite [--dry-run] [--allow-rejects]
 *  - never modifies or deletes the SQLite file; a verified copy is saved to storage/backups first
 *  - refuses to run if the PostgreSQL database already contains users, quizzes or attempts
 *  - everything is imported in ONE transaction; it commits only if all validations pass (or --allow-rejects is given)
 */
require __DIR__ . '/_cli.php';
if (!db_is_pg()) { fwrite(STDERR, "Set DATABASE_URL (or PGHOST...) so the target is PostgreSQL.\n"); exit(1); }
$srcPath = $argv[1] ?? ''; $dry = in_array('--dry-run', $argv, true); $allowRejects = in_array('--allow-rejects', $argv, true);
if (!is_file($srcPath)) { fwrite(STDERR, "Usage: php scripts/migrate_sqlite_to_pg.php path/to/vw.sqlite [--dry-run] [--allow-rejects]\n"); exit(1); }

$report = ['started_at' => gmdate('c'), 'source' => basename($srcPath), 'dry_run' => $dry, 'tables' => [], 'validation' => [], 'status' => 'FAILED'];
// 1. back up the source and prove the copy is identical
$bk = BASE_PATH . '/storage/backups/pre-migration-' . gmdate('Ymd_His') . '.sqlite';
@mkdir(dirname($bk), 0700, true);
if (!copy($srcPath, $bk) || hash_file('sha256', $bk) !== hash_file('sha256', $srcPath)) { fwrite(STDERR, "Could not create a verified backup of the SQLite file. Aborting.\n"); exit(1); }
$report['source_backup'] = $bk; echo "Source backed up and verified: $bk\n";
$src = new PDO('sqlite:' . $srcPath, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$ic = $src->query('PRAGMA integrity_check')->fetchColumn();
if ($ic !== 'ok') { fwrite(STDERR, "Source database failed its integrity check: $ic\n"); exit(1); }

// 2. target schema + safety checks
run_migrations();
foreach (['users', 'quizzes', 'quiz_attempts'] as $t) if ((int)db_val("SELECT COUNT(*) FROM $t") > 0) { fwrite(STDERR, "Target already has rows in $t. Refusing to import into a non-empty database.\n"); exit(1); }

$order = ['age_groups', 'roles', 'users', 'user_roles', 'quizzes', 'questions', 'question_options', 'quiz_versions', 'quiz_attempts', 'attempt_answers', 'attempt_grants', 'integrity_events', 'eligibility_reviews',
    'official_results', 'official_result_entries', 'announcements', 'notifications', 'audit_logs', 'settings', 'password_reset_tokens', 'data_requests'];
$skipped = ['login_attempts' => 'temporary security counters; intentionally not migrated', 'schema_migrations' => 'managed by the target', 'sessions' => 'participants sign in again after the move'];
$srcTables = array_column($src->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(), 'name');
$tx = db(); $ok = true;
$tx->exec('BEGIN');
try {
    foreach (['settings', 'age_groups', 'roles'] as $seed) $tx->exec("DELETE FROM $seed");   // target seed rows are replaced by the real ones (same transaction)
    foreach ($order as $t) {
        $T = ['read' => 0, 'inserted' => 0, 'rejected' => 0, 'errors' => []];
        if (!in_array($t, $srcTables, true)) { $T['note'] = 'not present in source'; $report['tables'][$t] = $T; continue; }
        $cols = array_column($src->query("PRAGMA table_info($t)")->fetchAll(), 'name');
        $tcols = array_column(db_all("SELECT column_name FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = ?", [$t]), 'column_name');
        $use = array_values(array_intersect($cols, $tcols));
        $ins = $tx->prepare("INSERT INTO $t (" . implode(',', $use) . ') VALUES (' . implode(',', array_fill(0, count($use), '?')) . ')');
        foreach ($src->query('SELECT ' . implode(',', $use) . " FROM $t") as $row) {
            $T['read']++;
            $tx->exec('SAVEPOINT sp');
            try { $ins->execute(array_values($row)); $tx->exec('RELEASE SAVEPOINT sp'); $T['inserted']++; }
            catch (Throwable $e) { $tx->exec('ROLLBACK TO SAVEPOINT sp'); $T['rejected']++; if (count($T['errors']) < 10) $T['errors'][] = ['row_id' => $row['id'] ?? null, 'error' => preg_replace('/\s+/', ' ', substr($e->getMessage(), 0, 220))]; }
        }
        if (in_array('id', $tcols, true)) $tx->exec("SELECT setval(pg_get_serial_sequence('$t','id'), GREATEST(COALESCE((SELECT MAX(id) FROM $t),1),1), (SELECT MAX(id) FROM $t) IS NOT NULL)");
        $report['tables'][$t] = $T;
        printf("%-26s read=%-6d inserted=%-6d rejected=%d\n", $t, $T['read'], $T['inserted'], $T['rejected']);
    }
    db_exec("INSERT INTO settings(key,value) VALUES('public_identity','masked') ON CONFLICT DO NOTHING");
    foreach ($skipped as $t => $why) $report['tables'][$t] = ['skipped' => $why];

    // 3. validation (inside the same transaction)
    $V = [];
    foreach ($order as $t) { $r = $report['tables'][$t]; if (isset($r['note'])) continue;
        $n = (int)db_val("SELECT COUNT(*) FROM $t");
        $V[] = ['check' => "row count $t", 'expected' => $r['read'] - $r['rejected'], 'actual' => $n, 'pass' => $n === $r['read'] - $r['rejected'] && $r['rejected'] === 0 || $n === $r['read'] - $r['rejected'] && $allowRejects]; }
    $s = array_column($src->query('SELECT id, email, password_hash, full_name, status FROM users')->fetchAll(), null, 'id'); $bad = 0;
    foreach (db_all('SELECT id, email, password_hash, full_name, status FROM users') as $u) { $x = $s[$u['id']] ?? null; if (!$x || $x['email'] !== $u['email'] || $x['password_hash'] !== $u['password_hash'] || $x['full_name'] !== $u['full_name'] || $x['status'] !== $u['status']) $bad++; }
    $V[] = ['check' => 'every account: email, password hash, name, status identical', 'expected' => 0, 'actual' => $bad, 'pass' => $bad === 0];
    $bad = 0; $checked = 0;   // re-score every finished attempt from its own saved answer key and compare with the stored automatic score
    foreach (db_all("SELECT a.id, a.quiz_version_id, a.marks_earned FROM quiz_attempts a WHERE a.status<>'in_progress'") as $a) {
        $snap = json_decode((string)db_val('SELECT snapshot_json FROM quiz_versions WHERE id=?', [$a['quiz_version_id']]), true); $key = [];
        foreach ($snap['questions'] ?? [] as $q) foreach ($q['options'] as $o) if ($o['correct']) $key[$q['id']] = [$o['id'], $q['marks']];
        $sum = 0; foreach (db_all('SELECT question_id, option_id FROM attempt_answers WHERE attempt_id=?', [$a['id']]) as $ans) if (isset($key[$ans['question_id']]) && $key[$ans['question_id']][0] === (int)$ans['option_id']) $sum += $key[$ans['question_id']][1];
        $checked++; if (abs($sum - (float)$a['marks_earned']) > 0.001) $bad++;
    }
    $V[] = ['check' => "re-scored $checked finished attempts from saved answer keys", 'expected' => 0, 'actual' => $bad, 'pass' => $bad === 0];
    $orph = (int)db_val('SELECT COUNT(*) FROM official_result_entries e WHERE NOT EXISTS (SELECT 1 FROM quiz_attempts a WHERE a.id = e.attempt_id)');
    $V[] = ['check' => 'official result entries point to existing attempts', 'expected' => 0, 'actual' => $orph, 'pass' => $orph === 0];
    $report['validation'] = $V; $fails = array_filter($V, fn($v) => !$v['pass']);
    foreach ($V as $v) printf("%s  %s (expected %s, actual %s)\n", $v['pass'] ? 'PASS' : 'FAIL', $v['check'], $v['expected'], $v['actual']);
    $ok = !$fails;
    if ($dry) { $tx->exec('ROLLBACK'); $report['status'] = $ok ? 'DRY RUN OK (nothing saved)' : 'DRY RUN FAILED (nothing saved)'; }
    elseif ($ok) { $tx->exec('COMMIT'); $report['status'] = 'VERIFIED'; }
    else { $tx->exec('ROLLBACK'); $report['status'] = 'FAILED — rolled back, PostgreSQL unchanged'; }
} catch (Throwable $e) { try { $tx->exec('ROLLBACK'); } catch (Throwable $x) {} $report['status'] = 'FAILED — rolled back: ' . $e->getMessage(); }
$report['finished_at'] = gmdate('c');
@mkdir(BASE_PATH . '/storage/reports', 0700, true);
$rf = BASE_PATH . '/storage/reports/migration_report_' . gmdate('Ymd_His') . '.json';
file_put_contents($rf, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo "\nSTATUS: {$report['status']}\nReport: $rf\nThe SQLite file was NOT changed or deleted. Keep it until you have also compared reports (scripts/report_snapshot.php) and tested logins (scripts/check_login.php).\n";
exit(str_starts_with($report['status'], 'VERIFIED') || str_starts_with($report['status'], 'DRY RUN OK') ? 0 : 1);
