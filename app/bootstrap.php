<?php
declare(strict_types=1);
define('BASE_PATH', dirname(__DIR__));
date_default_timezone_set('UTC');

$envFile = BASE_PATH . '/.env';
if (is_file($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) continue;
        [$k, $v] = explode('=', $line, 2);
        $v = trim($v);
        if (strlen($v) >= 2 && ($v[0] === '"' || $v[0] === "'") && substr($v, -1) === $v[0]) $v = substr($v, 1, -1);
        if (!isset($_ENV[trim($k)])) $_ENV[trim($k)] = $v;
    }
}

require __DIR__ . '/helpers.php';
require __DIR__ . '/db.php';
require __DIR__ . '/session.php';
require __DIR__ . '/auth.php';
require __DIR__ . '/avatar.php';
require __DIR__ . '/ui.php';
require __DIR__ . '/mail.php';
require __DIR__ . '/engine.php';
require __DIR__ . '/results.php';
require __DIR__ . '/reports.php';

$isProd = env('APP_ENV', 'production') === 'production';
ini_set('display_errors', $isProd ? '0' : '1');
ini_set('log_errors', '1');
if (!$isProd) ini_set('error_log', BASE_PATH . '/storage/logs/php-error.log');   // production: PHP errors go to stderr (Railway logs)
error_reporting(E_ALL);

set_exception_handler(function (Throwable $e) {
    app_log('UNCAUGHT ' . get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    if (PHP_SAPI === 'cli') { fwrite(STDERR, $e->getMessage() . "\n"); exit(1); }
    if (!headers_sent()) http_response_code(500);
    if (is_json_request()) { header('Content-Type: application/json'); echo json_encode(['ok' => false, 'error' => 'Server error']); exit; }
    render_error(500, 'Something went wrong on our side. Please try again in a moment.');
    exit;
});

if (PHP_SAPI !== 'cli') {
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'");
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    if ($https) header('Strict-Transport-Security: max-age=31536000');
    $drv = strtolower((string)env('SESSION_DRIVER', db_is_pg() ? 'db' : 'file'));
    if ($drv === 'db') {
        session_set_save_handler(new DbSessionHandler(), true);
    } else {
        $dir = BASE_PATH . '/storage/sessions';
        if (!is_dir($dir)) @mkdir($dir, 0700, true);
        session_save_path($dir);
    }
    session_name('vwsid');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => $https, 'httponly' => true, 'samesite' => 'Lax']);
    session_start();
    if (isset($_SESSION['last']) && time() - (int)$_SESSION['last'] > 7200) { session_unset(); session_regenerate_id(true); }
    $_SESSION['last'] = time();
}
