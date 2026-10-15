-- Voice of Women in Tech: initial schema (SQLite). All timestamps are UTC 'YYYY-MM-DD HH:MM:SS'.
CREATE TABLE age_groups (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  label TEXT NOT NULL UNIQUE,
  sort_order INTEGER NOT NULL DEFAULT 0,
  is_active INTEGER NOT NULL DEFAULT 1
);

CREATE TABLE users (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  full_name TEXT NOT NULL,
  email TEXT NOT NULL UNIQUE COLLATE NOCASE,
  phone TEXT,
  password_hash TEXT NOT NULL,
  age_group_id INTEGER REFERENCES age_groups(id) ON DELETE SET NULL,
  state TEXT,
  gender TEXT,
  status TEXT NOT NULL DEFAULT 'active' CHECK (status IN ('active','inactive','suspended','deleted')),
  must_change_password INTEGER NOT NULL DEFAULT 0,
  admin_seen_attempt_id INTEGER NOT NULL DEFAULT 0,
  last_login_at TEXT,
  created_at TEXT NOT NULL DEFAULT (datetime('now')),
  updated_at TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE INDEX idx_users_status ON users(status);
CREATE INDEX idx_users_created ON users(created_at);

CREATE TABLE roles (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL UNIQUE,
  label TEXT NOT NULL
);
CREATE TABLE user_roles (
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  role_id INTEGER NOT NULL REFERENCES roles(id) ON DELETE CASCADE,
  PRIMARY KEY (user_id, role_id)
);

CREATE TABLE quizzes (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  title TEXT NOT NULL,
  description TEXT NOT NULL DEFAULT '',
  category TEXT NOT NULL DEFAULT 'General',
  instructions TEXT NOT NULL DEFAULT '',
  duration_minutes INTEGER NOT NULL DEFAULT 10 CHECK (duration_minutes BETWEEN 1 AND 600),
  opens_at TEXT NOT NULL,
  closes_at TEXT NOT NULL,
  max_attempts INTEGER NOT NULL DEFAULT 1 CHECK (max_attempts >= 1),
  shuffle_questions INTEGER NOT NULL DEFAULT 0,
  shuffle_options INTEGER NOT NULL DEFAULT 0,
  allow_back INTEGER NOT NULL DEFAULT 1,
  allow_change INTEGER NOT NULL DEFAULT 1,
  resume_policy TEXT NOT NULL DEFAULT 'resume' CHECK (resume_policy IN ('resume','no_resume')),
  passing_percent INTEGER CHECK (passing_percent IS NULL OR passing_percent BETWEEN 0 AND 100),
  score_visibility TEXT NOT NULL DEFAULT 'immediate' CHECK (score_visibility IN ('immediate','after_close','after_publish')),
  answers_visibility TEXT NOT NULL DEFAULT 'after_publish' CHECK (answers_visibility IN ('never','after_close','after_publish')),
  visibility_policy TEXT NOT NULL DEFAULT 'review' CHECK (visibility_policy IN ('warn','review','disqualify')),
  visibility_threshold INTEGER NOT NULL DEFAULT 3 CHECK (visibility_threshold >= 1),
  timeout_counts INTEGER NOT NULL DEFAULT 1,
  tiebreak TEXT NOT NULL DEFAULT 'time' CHECK (tiebreak IN ('time','none')),
  assessment_type TEXT NOT NULL DEFAULT 'standalone' CHECK (assessment_type IN ('standalone','pre_test','post_test')),
  assessment_group TEXT,
  status TEXT NOT NULL DEFAULT 'draft' CHECK (status IN ('draft','scheduled','published','closed','archived')),
  created_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
  created_at TEXT NOT NULL DEFAULT (datetime('now')),
  updated_at TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE INDEX idx_quizzes_status ON quizzes(status);

CREATE TABLE questions (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  quiz_id INTEGER NOT NULL REFERENCES quizzes(id) ON DELETE CASCADE,
  type TEXT NOT NULL CHECK (type IN ('mcq','tf')),
  body TEXT NOT NULL,
  marks INTEGER NOT NULL DEFAULT 1 CHECK (marks BETWEEN 1 AND 100),
  sort_order INTEGER NOT NULL DEFAULT 0,
  explanation TEXT NOT NULL DEFAULT '',
  created_at TEXT NOT NULL DEFAULT (datetime('now')),
  updated_at TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE INDEX idx_questions_quiz ON questions(quiz_id, sort_order);

CREATE TABLE question_options (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  question_id INTEGER NOT NULL REFERENCES questions(id) ON DELETE CASCADE,
  body TEXT NOT NULL,
  is_correct INTEGER NOT NULL DEFAULT 0,
  sort_order INTEGER NOT NULL DEFAULT 0
);
CREATE INDEX idx_options_question ON question_options(question_id);

-- Immutable copy of questions, answer key and rules as they were when an attempt began.
CREATE TABLE quiz_versions (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  quiz_id INTEGER NOT NULL REFERENCES quizzes(id) ON DELETE CASCADE,
  version_no INTEGER NOT NULL,
  content_hash TEXT NOT NULL,
  snapshot_json TEXT NOT NULL,
  total_marks INTEGER NOT NULL,
  created_at TEXT NOT NULL DEFAULT (datetime('now')),
  UNIQUE (quiz_id, version_no)
);
CREATE INDEX idx_versions_hash ON quiz_versions(quiz_id, content_hash);

CREATE TABLE quiz_attempts (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  quiz_id INTEGER NOT NULL REFERENCES quizzes(id) ON DELETE RESTRICT,
  quiz_version_id INTEGER NOT NULL REFERENCES quiz_versions(id) ON DELETE RESTRICT,
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
  attempt_no INTEGER NOT NULL,
  status TEXT NOT NULL DEFAULT 'in_progress' CHECK (status IN ('in_progress','submitted','timed_out','abandoned')),
  started_at TEXT NOT NULL,
  deadline_at TEXT NOT NULL,
  submitted_at TEXT,
  duration_seconds INTEGER,
  rules_ack_at TEXT NOT NULL,
  loads INTEGER NOT NULL DEFAULT 0,
  layout_json TEXT NOT NULL,
  marks_earned REAL NOT NULL DEFAULT 0,
  marks_adjustment REAL NOT NULL DEFAULT 0,
  final_marks REAL NOT NULL DEFAULT 0,
  max_marks INTEGER NOT NULL DEFAULT 0,
  percentage REAL NOT NULL DEFAULT 0,
  eligibility TEXT NOT NULL DEFAULT 'incomplete' CHECK (eligibility IN ('eligible','pending_review','disqualified','timed_out','incomplete')),
  eligibility_reason TEXT,
  created_at TEXT NOT NULL DEFAULT (datetime('now')),
  updated_at TEXT NOT NULL DEFAULT (datetime('now')),
  UNIQUE (quiz_id, user_id, attempt_no)
);
CREATE INDEX idx_attempts_user ON quiz_attempts(user_id);
CREATE INDEX idx_attempts_quiz ON quiz_attempts(quiz_id, status);
CREATE INDEX idx_attempts_elig ON quiz_attempts(eligibility);
CREATE INDEX idx_attempts_deadline ON quiz_attempts(status, deadline_at);

-- No foreign keys to questions/options on purpose: editing a quiz must never alter history.
CREATE TABLE attempt_answers (
  attempt_id INTEGER NOT NULL REFERENCES quiz_attempts(id) ON DELETE CASCADE,
  question_id INTEGER NOT NULL,
  option_id INTEGER NOT NULL,
  is_correct INTEGER,
  marks_awarded INTEGER,
  answered_at TEXT NOT NULL DEFAULT (datetime('now')),
  PRIMARY KEY (attempt_id, question_id)
);

CREATE TABLE attempt_grants (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  quiz_id INTEGER NOT NULL REFERENCES quizzes(id) ON DELETE CASCADE,
  extra_attempts INTEGER NOT NULL DEFAULT 1,
  reason TEXT NOT NULL,
  granted_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
  created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE integrity_events (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  attempt_id INTEGER NOT NULL REFERENCES quiz_attempts(id) ON DELETE CASCADE,
  event_type TEXT NOT NULL,
  detail TEXT,
  created_at TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE INDEX idx_events_attempt ON integrity_events(attempt_id, event_type);

CREATE TABLE eligibility_reviews (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  attempt_id INTEGER NOT NULL REFERENCES quiz_attempts(id) ON DELETE CASCADE,
  decision TEXT NOT NULL CHECK (decision IN ('eligible','pending_review','disqualified','timed_out','incomplete')),
  reason TEXT NOT NULL,
  reviewer_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
  is_system INTEGER NOT NULL DEFAULT 0,
  created_at TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE INDEX idx_reviews_attempt ON eligibility_reviews(attempt_id);

CREATE TABLE official_results (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  quiz_id INTEGER NOT NULL REFERENCES quizzes(id) ON DELETE RESTRICT,
  result_version INTEGER NOT NULL,
  quiz_version_id INTEGER REFERENCES quiz_versions(id) ON DELETE RESTRICT,
  rules_json TEXT NOT NULL,
  prizes INTEGER NOT NULL DEFAULT 3,
  status TEXT NOT NULL DEFAULT 'official' CHECK (status IN ('official','superseded')),
  correction_reason TEXT,
  published_at TEXT,
  finalized_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
  finalized_by_name TEXT NOT NULL,
  finalized_at TEXT NOT NULL DEFAULT (datetime('now')),
  UNIQUE (quiz_id, result_version)
);
CREATE TABLE official_result_entries (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  result_id INTEGER NOT NULL REFERENCES official_results(id) ON DELETE CASCADE,
  final_rank INTEGER,
  user_id INTEGER,
  participant_name TEXT NOT NULL,
  attempt_id INTEGER NOT NULL,
  quiz_version_id INTEGER,
  score REAL NOT NULL,
  max_marks INTEGER NOT NULL,
  percentage REAL NOT NULL,
  duration_seconds INTEGER,
  eligibility TEXT NOT NULL,
  note TEXT,
  badge TEXT
);
CREATE INDEX idx_entries_result ON official_result_entries(result_id, final_rank);

CREATE TABLE announcements (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  title TEXT NOT NULL,
  body TEXT NOT NULL,
  created_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
  created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE notifications (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  title TEXT NOT NULL,
  body TEXT NOT NULL DEFAULT '',
  link TEXT,
  dedupe_key TEXT,
  read_at TEXT,
  created_at TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE INDEX idx_notif_user ON notifications(user_id, read_at);
CREATE UNIQUE INDEX idx_notif_dedupe ON notifications(user_id, dedupe_key) WHERE dedupe_key IS NOT NULL;

CREATE TABLE audit_logs (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
  actor_name TEXT,
  action TEXT NOT NULL,
  entity_type TEXT,
  entity_id TEXT,
  detail TEXT,
  ip TEXT,
  created_at TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE INDEX idx_audit_created ON audit_logs(created_at);

CREATE TABLE settings (
  key TEXT PRIMARY KEY,
  value TEXT NOT NULL
);

CREATE TABLE password_reset_tokens (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  token_hash TEXT NOT NULL UNIQUE,
  expires_at TEXT NOT NULL,
  used_at TEXT,
  created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE login_attempts (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  email TEXT NOT NULL,
  ip TEXT NOT NULL,
  success INTEGER NOT NULL DEFAULT 0,
  created_at TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE INDEX idx_login_email ON login_attempts(email, created_at);
CREATE INDEX idx_login_ip ON login_attempts(ip, created_at);

CREATE TABLE data_requests (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  type TEXT NOT NULL CHECK (type IN ('correction','deletion')),
  message TEXT NOT NULL,
  status TEXT NOT NULL DEFAULT 'open' CHECK (status IN ('open','resolved','rejected')),
  handled_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
  handled_at TEXT,
  created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

INSERT INTO roles (name, label) VALUES
 ('super_admin','Super Administrator'),
 ('quiz_manager','Quiz Manager'),
 ('results_reviewer','Results Reviewer'),
 ('report_viewer','Read-only Report Viewer');

INSERT INTO age_groups (label, sort_order) VALUES
 ('12–17 years',1),('18–25 years',2),('26–35 years',3),('36–40 years',4),('Above 40 years',5);

INSERT INTO settings (key, value) VALUES
 ('org_name','Voice of Women in Tech'),
 ('collect_gender','0'),
 ('setup_complete','0');
