<?php
declare(strict_types=1);

class UserError extends Exception {}

function env(string $k, $d = null) { if (isset($_ENV[$k])) return $_ENV[$k]; $v = getenv($k); return $v === false ? $d : $v; }
function base_path(string $p = ''): string { return BASE_PATH . ($p === '' ? '' : '/' . ltrim($p, '/')); }
function url(string $p = ''): string { return rtrim((string)env('APP_BASE_PATH', ''), '/') . '/' . ltrim($p, '/'); }
function asset(string $p): string { return url('assets/' . ltrim($p, '/')); }
function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function app_log(string $m): void {
    error_log('[vw] ' . $m);   // stderr -> Railway logs / Apache error log
    if (env('APP_ENV', 'production') !== 'production') @file_put_contents(BASE_PATH . '/storage/logs/app.log', '[' . gmdate('c') . '] ' . $m . "\n", FILE_APPEND | LOCK_EX);
}

/* ---------- time (storage = UTC text, display = APP_TIMEZONE) ---------- */
function now(): string { return gmdate('Y-m-d H:i:s'); }
function ts(?string $utc): int { return $utc ? (int)strtotime($utc . ' UTC') : 0; }
function app_tz(): DateTimeZone { return new DateTimeZone((string)env('APP_TIMEZONE', 'Africa/Lagos')); }
function fmt_dt(?string $utc, string $f = 'd M Y, H:i'): string {
    if (!$utc) return '—';
    $d = new DateTime($utc, new DateTimeZone('UTC'));
    $d->setTimezone(app_tz());
    return $d->format($f);
}
function local_to_utc(string $local): ?string {
    $d = DateTime::createFromFormat('Y-m-d\TH:i', $local, app_tz());
    if (!$d) return null;
    $d->setTimezone(new DateTimeZone('UTC'));
    return $d->format('Y-m-d H:i:s');
}
function utc_to_local_input(?string $utc): string { return $utc ? fmt_dt($utc, 'Y-m-d\TH:i') : ''; }
function fmt_duration(?int $s): string { if ($s === null) return '—'; return sprintf('%02d:%02d', intdiv($s, 60), $s % 60); }
function num($n): string { $s = number_format((float)$n, 2, '.', ''); return rtrim(rtrim($s, '0'), '.'); }

/* ---------- request ---------- */
function is_post(): bool { return ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'; }
function post(string $k, string $d = ''): string { $v = $_POST[$k] ?? $d; return is_string($v) ? trim($v) : $d; }
function get(string $k, string $d = ''): string { $v = $_GET[$k] ?? $d; return is_string($v) ? trim($v) : $d; }
function is_json_request(): bool { return str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') || str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json'); }
function json_input(): array { $r = json_decode(file_get_contents('php://input') ?: '', true); return is_array($r) ? $r : []; }
function json_out(array $d, int $code = 200): never {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($d, JSON_UNESCAPED_UNICODE);
    exit;
}
/** Behind Railway's proxy the real visitor is the entry added by the trusted proxy (TRUSTED_PROXY_HOPS, default 0 = use REMOTE_ADDR). */
function client_ip(): string {
    $hops = (int)env('TRUSTED_PROXY_HOPS', '0');
    if ($hops > 0 && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $parts = array_map('trim', explode(',', (string)$_SERVER['HTTP_X_FORWARDED_FOR']));
        $i = count($parts) - $hops;
        if ($i >= 0 && filter_var($parts[$i], FILTER_VALIDATE_IP)) return $parts[$i];
    }
    return (string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
}
function redirect(string $path): never { header('Location: ' . (str_starts_with($path, 'http') ? $path : url($path))); exit; }
function back(string $fallback = '/'): never { redirect($fallback); }

/* ---------- flash, old input, CSRF ---------- */
function flash(string $type, string $msg): void { $_SESSION['flash'][] = [$type, $msg]; }
function flashes_peek(): bool { return !empty($_SESSION['flash']); }
function flashes(): array { $f = $_SESSION['flash'] ?? []; unset($_SESSION['flash']); return $f; }
function keep_old(?array $src = null): void { $o = $src ?? $_POST; unset($o['password'], $o['password_confirm'], $o['current_password'], $o['new_password'], $o['_csrf']); $_SESSION['old'] = $o; }
function old(string $k, $d = '') { $v = $_SESSION['old'][$k] ?? $d; return is_scalar($v) ? $v : $d; }
function csrf_token(): string { if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32)); return $_SESSION['csrf']; }
function csrf_field(): string { return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">'; }
function csrf_check(): void {
    $t = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!is_string($t) || !hash_equals(csrf_token(), $t)) {
        if (is_json_request()) json_out(['ok' => false, 'error' => 'Session expired. Refresh the page.'], 419);
        http_response_code(419);
        render_error(419, 'Your session expired or the form was invalid. Please go back, refresh the page and try again.');
        exit;
    }
}

/* ---------- views ---------- */
function view(string $name, array $vars = [], string $layout = 'participant'): void {
    extract($vars, EXTR_SKIP);
    ob_start();
    require base_path("views/$name.php");
    $content = ob_get_clean();
    require base_path("views/layouts/$layout.php");
}
function render_error(int $code, string $msg): void {
    http_response_code($code);
    try { view('error', ['code' => $code, 'msg' => $msg], 'public'); }
    catch (Throwable $t) { echo '<h1>Error ' . (int)$code . '</h1><p>' . e($msg) . '</p>'; }
}
function abort(int $code, string $msg = ''): never {
    if (is_json_request()) json_out(['ok' => false, 'error' => $msg ?: 'Error'], $code);
    $m = [403 => 'You do not have permission to open this page.', 404 => 'The page you are looking for does not exist.'];
    render_error($code, $msg ?: ($m[$code] ?? 'Error'));
    exit;
}

/* ---------- validation ---------- */
function valid_email(string $e): bool { return strlen($e) <= 190 && filter_var($e, FILTER_VALIDATE_EMAIL) !== false; }
function normalize_phone(string $raw): ?string {
    $s = preg_replace('/[\s\-().]/', '', $raw);
    if (preg_match('/^\+?234([789][01]\d{8})$/', $s, $m)) return '+234' . $m[1];
    if (preg_match('/^0([789][01]\d{8})$/', $s, $m)) return '+234' . $m[1];
    if (preg_match('/^\+[1-9]\d{7,14}$/', $s)) return $s;
    return null;
}
function valid_password(string $p): ?string {
    if (strlen($p) < 8) return 'Password must be at least 8 characters.';
    if (!preg_match('/[A-Za-z]/', $p) || !preg_match('/\d/', $p)) return 'Password must contain letters and numbers.';
    if (strlen($p) > 200) return 'Password is too long.';
    return null;
}
function mask_name(string $n): string { $p = preg_split('/\s+/', trim($n)); return count($p) < 2 ? $n : $p[0] . ' ' . mb_substr(end($p), 0, 1) . '.'; }

/* ---------- settings, audit, notifications ---------- */
function setting(string $k, string $d = ''): string { $v = db_val('SELECT value FROM settings WHERE key=?', [$k]); return $v === null ? $d : (string)$v; }
function set_setting(string $k, string $v): void { db_exec('INSERT INTO settings(key,value) VALUES(?,?) ON CONFLICT(key) DO UPDATE SET value=excluded.value', [$k, $v]); }
function audit(string $action, ?string $type = null, $id = null, ?string $detail = null): void {
    $u = $GLOBALS['_audit_user'] ?? current_user();
    db_exec('INSERT INTO audit_logs(user_id,actor_name,action,entity_type,entity_id,detail,ip) VALUES(?,?,?,?,?,?,?)',
        [$u['id'] ?? null, $u['full_name'] ?? 'system', $action, $type, $id === null ? null : (string)$id, $detail, client_ip()]);
}
function notify_user(int $uid, string $title, string $body = '', ?string $link = null, ?string $key = null): void {
    db_exec('INSERT INTO notifications(user_id,title,body,link,dedupe_key) VALUES(?,?,?,?,?) ON CONFLICT DO NOTHING', [$uid, $title, $body, $link, $key]);
}
function notify_all_participants(string $title, string $body, ?string $link, ?string $key): void {
    db_exec("INSERT INTO notifications(user_id,title,body,link,dedupe_key)
             SELECT u.id, CAST(? AS TEXT), CAST(? AS TEXT), CAST(? AS TEXT), CAST(? AS TEXT) FROM users u WHERE u.status='active' AND " . PART_COND . " ON CONFLICT DO NOTHING", [$title, $body, $link, $key]);
}

/* ---------- UI helpers ---------- */
const ELIG_LABELS = ['eligible' => 'Eligible', 'pending_review' => 'Pending Review', 'disqualified' => 'Disqualified', 'timed_out' => 'Timed Out', 'incomplete' => 'Incomplete'];
function badge(string $key, ?string $label = null): string {
    $cls = ['eligible' => 'ok', 'published' => 'ok', 'active' => 'ok', 'submitted' => 'ok', 'open' => 'ok', 'official' => 'ok', 'resolved' => 'ok',
        'disqualified' => 'bad', 'suspended' => 'bad', 'closed' => 'bad', 'deleted' => 'bad', 'rejected' => 'bad',
        'pending_review' => 'warn', 'timed_out' => 'warn', 'scheduled' => 'info', 'upcoming' => 'info', 'in_progress' => 'info', 'open_request' => 'warn', 'superseded' => 'muted'][$key] ?? 'muted';
    $label ??= ELIG_LABELS[$key] ?? ucfirst(str_replace('_', ' ', $key));
    return '<span class="badge b-' . $cls . '">' . e($label) . '</span>';
}
function qs(array $over = []): string { $q = array_merge($_GET, $over); foreach ($q as $k => $v) if ($v === '' || $v === null) unset($q[$k]); return $q ? '?' . http_build_query($q) : ''; }
function paginate(int $total, int $per = 25): array {
    $pages = max(1, (int)ceil($total / $per));
    $page = min($pages, max(1, (int)get('page', '1')));
    return ['page' => $page, 'pages' => $pages, 'per' => $per, 'offset' => ($page - 1) * $per, 'total' => $total];
}
function pager(array $p, string $path): string {
    if ($p['pages'] <= 1) return '';
    $h = '<nav class="pager" aria-label="Pagination">';
    if ($p['page'] > 1) $h .= '<a class="btn btn-sm btn-outline" href="' . e(url($path) . qs(['page' => $p['page'] - 1])) . '">‹ Previous</a>';
    $h .= '<span>Page ' . $p['page'] . ' of ' . $p['pages'] . ' (' . $p['total'] . ' records)</span>';
    if ($p['page'] < $p['pages']) $h .= '<a class="btn btn-sm btn-outline" href="' . e(url($path) . qs(['page' => $p['page'] + 1])) . '">Next ›</a>';
    return $h . '</nav>';
}
function bars(array $labels, array $values, string $suffix = '', ?float $max = null): string {
    $max = $max ?? max(1, ...array_map('floatval', $values ?: [0]));
    if (!$labels) return '<p class="empty">No data yet.</p>';
    $h = '<ul class="bars">';
    foreach ($labels as $i => $l) {
        $v = (float)$values[$i];
        $h .= '<li><span class="bl">' . e($l) . '</span><span class="bt"><i style="width:' . round(min(100, $v / $max * 100), 1) . '%"></i></span><b>' . e(num($v)) . e($suffix) . '</b></li>';
    }
    return $h . '</ul>';
}
function csv_safe($v) { if (!is_string($v) || $v === '') return $v; return strpos("=+-@\t\r", $v[0]) !== false ? "'" . $v : $v; }
function csv_out(string $filename, array $headers, iterable $rows): never {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . preg_replace('/[^A-Za-z0-9_.-]/', '_', $filename) . '"');
    header('Cache-Control: no-store');
    $o = fopen('php://output', 'w');
    fwrite($o, "\xEF\xBB\xBF");
    fputcsv($o, $headers, ',', '"', '\\');
    foreach ($rows as $r) fputcsv($o, array_map('csv_safe', $r), ',', '"', '\\');
    fclose($o);
    exit;
}

/* ---------- router ---------- */
$GLOBALS['ROUTES'] = [];
function route(string $method, string $pattern, callable $h): void {
    $re = '#^' . preg_replace('#\{(\w+)\}#', '(?P<$1>[^/]+)', $pattern) . '$#';
    $GLOBALS['ROUTES'][] = [$method, $re, $h];
}
function request_path(): string {
    $p = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $base = rtrim((string)env('APP_BASE_PATH', ''), '/');
    if ($base !== '' && str_starts_with($p, $base)) $p = substr($p, strlen($base)) ?: '/';
    $p = rawurldecode($p);
    return $p === '/' ? '/' : rtrim($p, '/');
}
function dispatch(): void {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $path = request_path();
    $pathMatched = false;
    foreach ($GLOBALS['ROUTES'] as [$m, $re, $h]) {
        if (!preg_match($re, $path, $mm)) continue;
        $pathMatched = true;
        if ($m !== $method) continue;
        if ($method === 'POST') csrf_check();
        $params = array_filter($mm, 'is_string', ARRAY_FILTER_USE_KEY);
        $h($params);
        return;
    }
    if ($pathMatched) abort(405, 'Method not allowed.');
    abort(404);
}
