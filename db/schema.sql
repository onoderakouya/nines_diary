PRAGMA foreign_keys = ON;

CREATE TABLE IF NOT EXISTS users (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL,
  email TEXT NOT NULL UNIQUE,
  password_hash TEXT NOT NULL,
  role TEXT NOT NULL DEFAULT 'trainee' CHECK (role IN ('admin','trainee')),
  created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS fields (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  label TEXT NOT NULL UNIQUE,
  created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS crops (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL UNIQUE,
  created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS tasks (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL UNIQUE,
  created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS diary_entries (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL,
  date TEXT NOT NULL,
  field_id INTEGER NOT NULL,
  plot TEXT,
  crop_id INTEGER NOT NULL,
  task_id INTEGER NOT NULL DEFAULT 1,
  work_content TEXT,
  minutes INTEGER NOT NULL CHECK (minutes > 0),
  weather_code TEXT,
  weather TEXT,
  temp_c REAL,
  memo TEXT,
  created_at TEXT NOT NULL DEFAULT (datetime('now')),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (field_id) REFERENCES fields(id) ON DELETE RESTRICT,
  FOREIGN KEY (crop_id) REFERENCES crops(id) ON DELETE RESTRICT,
  FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE RESTRICT
);

CREATE TABLE IF NOT EXISTS materials (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL,
  date TEXT NOT NULL,
  field_id INTEGER,
  crop_id INTEGER,
  item_name TEXT NOT NULL,
  amount REAL,
  unit TEXT,
  cost_yen INTEGER NOT NULL DEFAULT 0 CHECK (cost_yen >= 0),
  note TEXT,
  created_at TEXT NOT NULL DEFAULT (datetime('now')),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (field_id) REFERENCES fields(id) ON DELETE SET NULL,
  FOREIGN KEY (crop_id) REFERENCES crops(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS pests (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL,
  date TEXT NOT NULL,
  field_id INTEGER NOT NULL,
  crop_id INTEGER NOT NULL,
  symptom_tag TEXT,
  symptom_text TEXT,
  action_text TEXT,
  photo_path TEXT,
  created_at TEXT NOT NULL DEFAULT (datetime('now')),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (field_id) REFERENCES fields(id) ON DELETE RESTRICT,
  FOREIGN KEY (crop_id) REFERENCES crops(id) ON DELETE RESTRICT
);

CREATE TABLE IF NOT EXISTS shipments (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL,
  date TEXT NOT NULL,
  field_id INTEGER NOT NULL,
  plot TEXT,
  crop_id INTEGER NOT NULL,
  quantity REAL NOT NULL CHECK (quantity > 0),
  qty REAL,
  unit TEXT NOT NULL CHECK (unit IN ('box','kg')),
  note TEXT,
  created_at TEXT NOT NULL DEFAULT (datetime('now')),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (field_id) REFERENCES fields(id) ON DELETE RESTRICT,
  FOREIGN KEY (crop_id) REFERENCES crops(id) ON DELETE RESTRICT
);

CREATE TRIGGER IF NOT EXISTS shipments_sync_qty_after_insert
AFTER INSERT ON shipments
FOR EACH ROW
WHEN NEW.qty IS NULL
BEGIN
  UPDATE shipments SET qty = NEW.quantity WHERE id = NEW.id;
END;

CREATE TRIGGER IF NOT EXISTS shipments_sync_qty_after_update
AFTER UPDATE OF quantity ON shipments
FOR EACH ROW
BEGIN
  UPDATE shipments SET qty = NEW.quantity WHERE id = NEW.id;
END;

CREATE INDEX IF NOT EXISTS idx_users_role ON users(role);
CREATE INDEX IF NOT EXISTS idx_diary_entries_user_date ON diary_entries(user_id, date DESC);
CREATE INDEX IF NOT EXISTS idx_diary_entries_field_crop ON diary_entries(field_id, crop_id);
CREATE INDEX IF NOT EXISTS idx_diary_entries_task_id ON diary_entries(task_id);
CREATE INDEX IF NOT EXISTS idx_materials_user_date ON materials(user_id, date DESC);
CREATE INDEX IF NOT EXISTS idx_materials_field_crop ON materials(field_id, crop_id);
CREATE INDEX IF NOT EXISTS idx_pests_user_date ON pests(user_id, date DESC);
CREATE INDEX IF NOT EXISTS idx_pests_field_crop ON pests(field_id, crop_id);
CREATE INDEX IF NOT EXISTS idx_pests_symptom_tag ON pests(symptom_tag);
CREATE INDEX IF NOT EXISTS idx_shipments_user_date ON shipments(user_id, date DESC);
CREATE INDEX IF NOT EXISTS idx_shipments_field_crop_unit ON shipments(field_id, crop_id, unit);
