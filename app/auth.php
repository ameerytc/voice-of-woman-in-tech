<?php
declare(strict_types=1);

const PART_COND = "NOT EXISTS (SELECT 1 FROM user_roles r0 WHERE r0.user_id = u.id)";
const ROLE_PERMS = [
    'super_admin' => ['*'],
    'quiz_manager' => ['participants.view', 'participants.phone', 'participants.export', 'participants.manage', 'quizzes.manage', 'announcements.manage', 'submissions.view', 'results.view', 'reports.view'],
    'results_reviewer' => ['participants.view', 'participants.phone', 'submissions.view', 'submissions.review', 'results.manage', 'results.view', 'reports.view'],
    'report_viewer' => ['reports.view', 'results.view'],
];

function current_user(): ?array {
    static $cache = [];
    $id = (int)($_SESSION['uid'] ?? 0);
    if (!$id) return null;
    if (!array_key_exists($id, $cache)) {
        $u = db_one('SELECT * FROM users WHERE id=?', [$id]);
        $cache[$id] = ($u && $u['status'] === 'active') ? $u : null;
        if (!$cache[$id]) { unset($_SESSION['uid']); }
    }
    return $cache[$id];
}
function user_roles(int $uid): array {
    return array_column(db_all('SELECT r.name FROM user_roles ur JOIN roles r ON r.id=ur.role_id WHERE ur.user_id=?', [$uid]), 'name');
}
function is_admin(): bool { $u = current_user(); return $u && user_roles((int)$u['id']) !== []; }
function has_perm(string $perm): bool {
    $u = current_user();
    if (!$u) return false;
    foreach (user_roles((int)$u['id']) as $r) {
        $p = ROLE_PERMS[$r] ?? [];
        if (in_array('*', $p, true) || in_array($perm, $p, true)) return true;
    }
    return false;
}
function require_login(): array {
    $u = current_user();
    if (!$u) { $_SESSION['intended'] = $_SERVER['REQUEST_URI'] ?? '/dashboard'; redirect('/login'); }
    if (user_roles((int)$u['id']) !== []) redirect('/admin');
    if ((int)$u['must_change_password'] === 1 && request_path() !== '/account/password' && request_path() !== '/logout') redirect('/account/password');
    return $u;
}
/** Admin gate: logged in, has at least one role, and (optionally) a specific permission. Checked on every request. */
function require_admin(string $perm = ''): array {
    $u = current_user();
    if (!$u) redirect('/admin/login');
    if (user_roles((int)$u['id']) === []) abort(403);
    if ((int)$u['must_change_password'] === 1 && request_path() !== '/account/password' && request_path() !== '/logout') redirect('/account/password');
    if ($perm !== '' && !has_perm($perm)) abort(403, 'Your role does not have permission to do this.');
    return $u;
}
function login_user(array $u): void {
    session_regenerate_id(true);
    $_SESSION['uid'] = (int)$u['id'];
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
    db_exec('UPDATE users SET last_login_at=? WHERE id=?', [now(), $u['id']]);
}
function logout_user(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) { $c = session_get_cookie_params(); setcookie(session_name(), '', time() - 3600, $c['path'], '', (bool)$c['secure'], true); }
    session_destroy();
}
function throttled(string $email, string $ip): bool {
    $since = gmdate('Y-m-d H:i:s', time() - 900);
    $byEmail = (int)db_val('SELECT COUNT(*) FROM login_attempts WHERE email=? AND success=0 AND created_at>=?', [strtolower($email), $since]);
    $byIp = (int)db_val('SELECT COUNT(*) FROM login_attempts WHERE ip=? AND success=0 AND created_at>=?', [$ip, $since]);
    return $byEmail >= 5 || $byIp >= 25;
}
function record_login(string $email, bool $ok): void {
    db_exec('INSERT INTO login_attempts(email,ip,success) VALUES(?,?,?)', [strtolower($email), client_ip(), $ok ? 1 : 0]);
    if ($ok) db_exec('DELETE FROM login_attempts WHERE email=? AND success=0', [strtolower($email)]);
    db_exec('DELETE FROM login_attempts WHERE created_at < ?', [gmdate('Y-m-d H:i:s', time() - 86400 * 2)]);
}
function attempt_login(string $email, string $password): array {
    $email = strtolower(trim($email));
    if (throttled($email, client_ip())) return [null, 'Too many failed attempts. Please wait 15 minutes and try again.'];
    $u = db_one('SELECT * FROM users WHERE email=?', [$email]);
    $hash = $u['password_hash'] ?? '$2y$10$usesomesillystringforsaltYQWYkT3hD6vZ0g6o8uT6mV4kHqW2K';
    $ok = password_verify($password, $hash) && $u;
    if (!$ok || $u['status'] !== 'active') {
        record_login($email, false);
        return [null, ($ok && $u['status'] !== 'active') ? 'This account is not active. Please contact the organisation.' : 'Incorrect email or password.'];
    }
    record_login($email, true);
    if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT)) db_exec('UPDATE users SET password_hash=? WHERE id=?', [password_hash($password, PASSWORD_DEFAULT), $u['id']]);
    return [$u, null];
}
