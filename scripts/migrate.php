<?php
// Usage: php scripts/migrate.php — applies pending schema migrations (safe to run repeatedly; never drops data).
require __DIR__ . '/_cli.php';
echo 'Driver: ' . db_driver() . (db_is_pg() ? ' (PostgreSQL)' : ' (SQLite file ' . db_path() . ')') . "\n";
$done = run_migrations();
echo $done ? 'Applied: ' . implode(', ', $done) . "\n" : "Database is up to date.\n";
