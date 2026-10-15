-- Profile pictures (stored in the database so they survive redeploys and are included in backups) and DB-backed sessions.
CREATE TABLE user_avatars (
  user_id INTEGER PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
  mime TEXT NOT NULL,
  data BLOB NOT NULL,
  bytes INTEGER NOT NULL,
  updated_at TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE TABLE sessions (
  id TEXT PRIMARY KEY,
  data TEXT NOT NULL,
  updated_at INTEGER NOT NULL
);
CREATE INDEX idx_sessions_updated ON sessions(updated_at);
INSERT INTO settings (key, value) VALUES ('public_identity','masked');
