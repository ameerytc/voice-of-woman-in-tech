<?php
declare(strict_types=1);

/* ---------- driver selection: PostgreSQL when DATABASE_URL / PG* variables exist (Railway), SQLite otherwise (local dev) ---------- */
function db_driver(): string {
    static $d = null;
    if ($d !== null) return $d;
    $x = strtolower((string)env('DB_DRIVER', ''));
    if ($x === '') $x = ((string)env('DATABASE_URL', '') !== '' || (string)env('PGHOST', '') !== '') ? 'pgsql' : 'sqlite';
    return $d = in_array($x, ['pgsql', 'postgres', 'postgresql'], true) ? 'pgsql' : 'sqlite';
}
function db_is_pg(): bool { return db_driver() === 'pgsql'; }
function sql_like(): string { return db_is_pg() ? 'ILIKE' : 'LIKE'; }
function sql_lock(): string { return db_is_pg() ? ' FOR UPDATE' : ''; }
function sql_str_agg(string $expr): string { return db_is_pg() ? "STRING_AGG($expr, ', ')" : "GROUP_CONCAT($expr, ', ')"; }

function db_path(): string {
    $p = (string)env('DB_PATH', 'storage/database/vw.sqlite');
    return (preg_match('#^([A-Za-z]:[\\\\/]|/)#', $p)) ? $p : BASE_PATH . '/' . $p;
}
/** Connection settings from DATABASE_URL or PGHOST/PGPORT/PGDATABASE/PGUSER/PGPASSWORD. Never logged. */
function pg_config(): array {
    $url = (string)env('DATABASE_URL', '');
    if ($url !== '') {
        $u = parse_url($url);
        if (!$u || empty($u['host'])) throw new RuntimeException('DATABASE_URL is not a valid PostgreSQL URL.');
        $c = ['host' => $u['host'], 'port' => (string)($u['port'] ?? 5432), 'dbname' => ltrim((string)($u['path'] ?? ''), '/'), 'user' => urldecode((string)($u['user'] ?? '')), 'password' => urldecode((string)($u['pass'] ?? ''))];
    } else {
        $c = ['host' => (string)env('PGHOST', 'localhost'), 'port' => (string)env('PGPORT', '5432'), 'dbname' => (string)env('PGDATABASE', ''), 'user' => (string)env('PGUSER', ''), 'password' => (string)env('PGPASSWORD', '')];
    }
    $c['sslmode'] = (string)env('PGSSLMODE', env('DB_SSLMODE', 'prefer'));
    return $c;
}
function db(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    $opts = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false];
    try {
        if (db_is_pg()) {
            $c = pg_config();
            $dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s;sslmode=%s;connect_timeout=8', $c['host'], $c['port'], $c['dbname'], $c['sslmode']);
            $pdo = new PDO($dsn, $c['user'], $c['password'], $opts);
            $schema = (string)env('DB_SCHEMA', '');
            if ($schema !== '' && preg_match('/^[a-z0-9_]+$/', $schema)) $pdo->exec('SET search_path TO ' . $schema);
        } else {
            $path = db_path();
            if (!is_dir(dirname($path))) @mkdir(dirname($path), 0700, true);
            $pdo = new PDO('sqlite:' . $path, null, null, $opts);
            $pdo->exec('PRAGMA foreign_keys = ON');
            $pdo->exec('PRAGMA busy_timeout = 8000');
            $pdo->exec('PRAGMA journal_mode = WAL');
            $pdo->exec('PRAGMA synchronous = NORMAL');
        }
    } catch (PDOException $e) {
        app_log('Database connection failed (' . db_driver() . '): ' . preg_replace('/password=\S+/i', 'password=***', $e->getMessage()));
        throw new RuntimeException('The database is not reachable.');
    }
    return $pdo;
}

/** Accepts positional (?) or named (:name) parameters; named ones are rewritten to positional so a name may repeat on every driver. */
function db_prepare_sql(string $sql, array $p): array {
    if (!$p || array_is_list($p)) return [$sql, array_values($p)];
    $vals = []; $out = ''; $len = strlen($sql); $i = 0;
    while ($i < $len) {
        $c = $sql[$i];
        if ($c === "'") {
            $j = $i + 1;
            while ($j < $len) { if ($sql[$j] === "'") { if (($sql[$j + 1] ?? '') === "'") { $j += 2; continue; } break; } $j++; }
            $out .= substr($sql, $i, $j - $i + 1); $i = $j + 1; continue;
        }
        if ($c === ':' && ($sql[$i + 1] ?? '') !== ':' && ($i === 0 || $sql[$i - 1] !== ':') && preg_match('/\G:([A-Za-z_]\w*)/', $sql, $m, 0, $i)) {
            if (!array_key_exists($m[1], $p)) throw new InvalidArgumentException('Missing SQL parameter: ' . $m[1]);
            $vals[] = $p[$m[1]]; $out .= '?'; $i += strlen($m[0]); continue;
        }
        $out .= $c; $i++;
    }
    return [$out, $vals];
}
function db_run(string $sql, array $p = []): PDOStatement {
    [$s, $v] = db_prepare_sql($sql, $p);
    $st = db()->prepare($s);
    $st->execute($v);
    return $st;
}
function db_all(string $sql, array $p = []): array { return db_run($sql, $p)->fetchAll(); }
function db_one(string $sql, array $p = []): ?array { $r = db_run($sql, $p)->fetch(); return $r === false ? null : $r; }
function db_val(string $sql, array $p = []) { $r = db_run($sql, $p)->fetchColumn(); return $r === false ? null : $r; }
function db_exec(string $sql, array $p = []): int { return db_run($sql, $p)->rowCount(); }
/** INSERT into a table whose primary key is `id`; returns the new id on both drivers. */
function db_insert(string $sql, array $p = []): int {
    if (db_is_pg()) return (int)db_val($sql . ' RETURNING id', $p);
    db_exec($sql, $p);
    return (int)db()->lastInsertId();
}
/** Write transaction. SQLite: BEGIN IMMEDIATE serialises writers. PostgreSQL: row locks (FOR UPDATE) + unique indexes do that. Not re-entrant. */
function tx(callable $fn) {
    $pdo = db();
    $pdo->exec(db_is_pg() ? 'BEGIN' : 'BEGIN IMMEDIATE');
    try { $r = $fn(); $pdo->exec('COMMIT'); return $r; }
    catch (Throwable $e) { try { $pdo->exec('ROLLBACK'); } catch (Throwable $x) {} throw $e; }
}
function db_ping(): bool { try { return (int)db_val('SELECT 1') === 1; } catch (Throwable $e) { return false; } }
function db_ready(): bool {
    db();
    try { db_val('SELECT 1 FROM users LIMIT 1'); return true; } catch (PDOException $e) { return false; }
}
function migrations_dir(): string { return BASE_PATH . '/database/migrations/' . (db_is_pg() ? 'pgsql' : 'sqlite'); }
/** Applies pending migrations once each, inside a transaction. On PostgreSQL an advisory lock stops two deploys migrating at the same time. */
function run_migrations(): array {
    $pdo = db();
    if (db_is_pg()) $pdo->exec('SELECT pg_advisory_lock(727274)');
    try {
        $pdo->exec('CREATE TABLE IF NOT EXISTS schema_migrations (name TEXT PRIMARY KEY, applied_at TEXT NOT NULL)');
        $done = array_column(db_all('SELECT name FROM schema_migrations'), 'name');
        $applied = [];
        $files = glob(migrations_dir() . '/*.sql');
        sort($files);
        foreach ($files as $f) {
            $name = basename($f);
            if (in_array($name, $done, true)) continue;
            $pdo->exec('BEGIN');
            try {
                $pdo->exec((string)file_get_contents($f));
                db_exec('INSERT INTO schema_migrations(name, applied_at) VALUES(?,?)', [$name, now()]);
                $pdo->exec('COMMIT');
            } catch (Throwable $e) { try { $pdo->exec('ROLLBACK'); } catch (Throwable $x) {} throw $e; }
            $applied[] = $name;
        }
        return $applied;
    } finally {
        if (db_is_pg()) $pdo->exec('SELECT pg_advisory_unlock(727274)');
    }
}
