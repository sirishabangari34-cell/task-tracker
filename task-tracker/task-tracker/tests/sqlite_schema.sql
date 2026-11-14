-- SQLite equivalent of database.sql — used ONLY by the automated test harness.
CREATE TABLE users (
  id            INTEGER PRIMARY KEY AUTOINCREMENT,
  name          TEXT NOT NULL,
  email         TEXT NOT NULL UNIQUE,
  password_hash TEXT NOT NULL,
  created_at    TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE tasks (
  id          INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id     INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
  title       TEXT NOT NULL,
  description TEXT NULL,
  status      TEXT NOT NULL DEFAULT 'Pending'  CHECK (status IN ('Pending','In Progress','Completed')),
  priority    TEXT NOT NULL DEFAULT 'Medium'   CHECK (priority IN ('Low','Medium','High')),
  due_date    TEXT NULL,
  created_at  TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_tasks_user_status  ON tasks (user_id, status);
CREATE INDEX idx_tasks_user_created ON tasks (user_id, created_at);
