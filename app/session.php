<?php
declare(strict_types=1);

/** Sessions in the database (not on disk): they survive Railway redeploys and work with more than one instance. */
final class DbSessionHandler implements SessionHandlerInterface, SessionUpdateTimestampHandlerInterface {
    public function open(string $path, string $name): bool { return true; }
    public function close(): bool { return true; }
    public function read(string $id): string|false {
        try { $r = db_val('SELECT data FROM sessions WHERE id=?', [$id]); return $r === null ? '' : (string)base64_decode((string)$r); }
        catch (Throwable $e) { app_log('session read: ' . $e->getMessage()); return ''; }
    }
    public function write(string $id, string $data): bool {
        try {
            db_exec('INSERT INTO sessions(id,data,updated_at) VALUES(?,?,?) ON CONFLICT(id) DO UPDATE SET data=excluded.data, updated_at=excluded.updated_at', [$id, base64_encode($data), time()]);
            return true;
        } catch (Throwable $e) { app_log('session write: ' . $e->getMessage()); return false; }
    }
    public function destroy(string $id): bool { try { db_exec('DELETE FROM sessions WHERE id=?', [$id]); } catch (Throwable $e) {} return true; }
    public function gc(int $max_lifetime): int|false { try { return db_exec('DELETE FROM sessions WHERE updated_at < ?', [time() - $max_lifetime]); } catch (Throwable $e) { return 0; } }
    public function validateId(string $id): bool { try { return db_val('SELECT 1 FROM sessions WHERE id=?', [$id]) !== null; } catch (Throwable $e) { return false; } }
    public function updateTimestamp(string $id, string $data): bool { return $this->write($id, $data); }
}
