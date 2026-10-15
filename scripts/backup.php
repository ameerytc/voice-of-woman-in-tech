<?php
// Usage: php scripts/backup.php [target-folder]   — consistent online backup (safe while the site is running).
require __DIR__ . '/_cli.php';
if (db_is_pg()) { fwrite(STDERR, "php scripts/backup.php is for the SQLite file. For PostgreSQL use: sh scripts/pg_backup.sh\n"); exit(1); }
$dir = $argv[1] ?? BASE_PATH . '/storage/backups';
if (!is_dir($dir) && !mkdir($dir, 0700, true)) { fwrite(STDERR, "Cannot create $dir\n"); exit(1); }
$file = rtrim($dir, '/\\') . '/vw_' . gmdate('Ymd_His') . '.sqlite';
db()->exec("VACUUM INTO '" . str_replace("'", "''", $file) . "'");
echo "Backup written: $file (" . round(filesize($file) / 1024) . " KB)\nCopy it OFF this server (cloud drive, another machine).\n";
// keep the newest 30 backups in the default folder
$all = glob(BASE_PATH . '/storage/backups/vw_*.sqlite'); rsort($all);
if ($dir === BASE_PATH . '/storage/backups') foreach (array_slice($all, 30) as $old) @unlink($old);
