<?php
// Usage: php scripts/reset_password.php   — server-side recovery of any account's password (requires server access).
require __DIR__ . '/_cli.php';
$email = strtolower(ask('Account email'));
$u = db_one('SELECT * FROM users WHERE email=?', [$email]);
if (!$u) { fwrite(STDERR, "No such account.\n"); exit(1); }
$pw = ask('New password', true);
if ($e = valid_password($pw)) { fwrite(STDERR, "$e\n"); exit(1); }
db_exec('UPDATE users SET password_hash=?, must_change_password=1, status=\'active\', updated_at=? WHERE id=?', [password_hash($pw, PASSWORD_DEFAULT), now(), $u['id']]);
$GLOBALS['_audit_user'] = ['id' => $u['id'], 'full_name' => 'CLI recovery'];
audit('password_reset_cli', 'user', $u['id']);
echo "Password reset. The user must change it at next login.\n";
