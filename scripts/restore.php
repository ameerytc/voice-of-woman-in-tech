<?php
// Usage: php scripts/restore.php path/to/backup.sqlite   — STOP the web server first. The current database is saved aside, not deleted.
require __DIR__ . '/_cli.php';
if (db_is_pg()) { fwrite(STDERR, "php scripts/restore.php is for the SQLite file. For PostgreSQL use: pg_restore (see README, section Backups).\n"); exit(1); }
$src = $argv[1] ?? '';
if (!is_file($src)) { fwrite(STDERR, "Usage: php scripts/restore.php path/to/backup.sqlite\n"); exit(1); }
$chk = new PDO('sqlite:' . $src); $ok = $chk->query('PRAGMA integrity_check')->fetchColumn(); $chk = null;
if ($ok !== 'ok') { fwrite(STDERR, "Backup failed integrity check: $ok\n"); exit(1); }
$dst = db_path();
if (is_file($dst)) { $aside = $dst . '.before-restore-' . gmdate('Ymd_His'); copy($dst, $aside); echo "Current database saved to $aside\n"; }
foreach (['-wal', '-shm'] as $x) if (is_file($dst . $x)) unlink($dst . $x);
copy($src, $dst);
echo "Restored. Start the web server again and check the dashboard.\n";
