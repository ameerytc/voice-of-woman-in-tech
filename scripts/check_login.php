<?php
// Usage: php scripts/check_login.php — confirms an existing account can log in against the CURRENT database (e.g. PostgreSQL after migration).
require __DIR__ . '/_cli.php';
$email = strtolower(ask('Email')); $pw = ask('Password', true);
$u = db_one('SELECT * FROM users WHERE email=?', [$email]);
if (!$u) { echo "FAIL: no such account in " . db_driver() . ".\n"; exit(1); }
echo password_verify($pw, $u['password_hash']) ? "OK: password verifies on " . db_driver() . "; status={$u['status']}; roles=" . (implode(',', user_roles((int)$u['id'])) ?: 'participant') . "\n" : "FAIL: password does not verify.\n";
