<?php
// Usage: php scripts/create_admin.php   — one-time creation of the first Super Administrator. Disables itself afterwards.
require __DIR__ . '/_cli.php';
if (!db_ready()) { fwrite(STDERR, "Run php scripts/migrate.php first.\n"); exit(1); }
if (setting('setup_complete') === '1' || db_val("SELECT 1 FROM user_roles ur JOIN roles r ON r.id=ur.role_id WHERE r.name='super_admin' LIMIT 1")) {
    fwrite(STDERR, "Initial setup is already complete and has been disabled. Add more administrators from the dashboard (Administrator Accounts).\nLost access? Use: php scripts/reset_password.php\n"); exit(1);
}
$name = ask('Full name'); $email = strtolower(ask('Email address'));
if ($name === '' || !valid_email($email)) { fwrite(STDERR, "Invalid name or email.\n"); exit(1); }
$pw = ask('Password (min 8, letters and numbers)', true); $pw2 = ask('Confirm password', true);
if ($pw !== $pw2) { fwrite(STDERR, "Passwords do not match.\n"); exit(1); }
if ($e = valid_password($pw)) { fwrite(STDERR, "$e\n"); exit(1); }
tx(function () use ($name, $email, $pw) {
    if (db_val('SELECT 1 FROM users WHERE email=?', [$email])) throw new RuntimeException('That email already exists.');
    $id = db_insert('INSERT INTO users(full_name,email,password_hash) VALUES(?,?,?)', [$name, $email, password_hash($pw, PASSWORD_DEFAULT)]);
    db_exec("INSERT INTO user_roles(user_id,role_id) SELECT CAST(? AS INTEGER), id FROM roles WHERE name='super_admin'", [$id]);
    set_setting('setup_complete', '1');
    $GLOBALS['_audit_user'] = ['id' => $id, 'full_name' => $name];
    audit('initial_admin_created', 'user', $id, 'via CLI setup');
});
echo "Super Administrator created. Sign in at /admin/login. This setup script is now disabled.\n";
