<?php
declare(strict_types=1);
// Single application entry point. Works with `php -S localhost:8000 -t public public/index.php` and Apache/Nginx rewrites.
if (PHP_SAPI === 'cli-server') {
    $p = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    if ($p !== '/' && is_file(__DIR__ . $p) && basename($p) !== 'index.php') return false;
}
require dirname(__DIR__) . '/app/bootstrap.php';
if (request_path() === '/health') {   // readiness probe for Railway: no secrets, no data
    header('Content-Type: application/json'); header('Cache-Control: no-store');
    $dbUp = false; $ready = false;
    try { $dbUp = db_ping(); $ready = $dbUp && db_ready(); } catch (Throwable $e) { app_log('health: ' . $e->getMessage()); }
    http_response_code($ready ? 200 : 503);
    echo json_encode(['status' => $ready ? 'ok' : 'unavailable', 'database' => $dbUp ? 'up' : 'down', 'schema' => $ready ? 'ready' : 'pending']);
    exit;
}
if (!db_ready()) {
    http_response_code(503);
    echo '<!doctype html><meta charset="utf-8"><title>Setup required</title><body style="font-family:sans-serif;max-width:560px;margin:3rem auto;padding:1rem"><h1>Setup required</h1><p>The database has not been created yet. Run <code>php scripts/migrate.php</code> and <code>php scripts/create_admin.php</code> (see README.md).</p></body>';
    exit;
}
foreach (glob(BASE_PATH . '/app/controllers/*.php') as $f) require $f;
dispatch();
