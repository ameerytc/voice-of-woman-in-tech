-- Profile pictures (stored in the database so they survive redeploys and are included in backups) and DB-backed sessions.
CREATE TABLE user_avatars (
  user_id BIGINT PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
  mime TEXT NOT NULL,
  data BYTEA NOT NULL,
  bytes INTEGER NOT NULL,
  updated_at TEXT NOT NULL DEFAULT (to_char(timezone('utc', now()), 'YYYY-MM-DD HH24:MI:SS'))
);
CREATE TABLE sessions (
  id TEXT PRIMARY KEY,
  data TEXT NOT NULL,
  updated_at BIGINT NOT NULL
);
CREATE INDEX idx_sessions_updated ON sessions(updated_at);
INSERT INTO settings (key, value) VALUES ('public_identity','masked');
