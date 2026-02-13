PRAGMA foreign_keys = ON;

INSERT OR IGNORE INTO users (id, name, email, password_hash, role, created_at)
VALUES
  (1, 'Admin', 'admin@example.com', '$2y$12$xCX7cdX5yU5tpfCwVS2Z5OyVfJuJXnvWIlB067RDYuoPbCgWIjb.W', 'admin', datetime('now'));

INSERT OR IGNORE INTO fields (id, label, created_at)
VALUES
  (1, '北ハウス', datetime('now')),
  (2, '南ハウス', datetime('now')),
  (3, '露地A', datetime('now'));

INSERT OR IGNORE INTO crops (id, name, created_at)
VALUES
  (1, 'トマト', datetime('now')),
  (2, 'きゅうり', datetime('now')),
  (3, 'ピーマン', datetime('now'));

INSERT OR IGNORE INTO tasks (id, name, created_at)
VALUES
  (1, 'その他', datetime('now')),
  (2, '定植', datetime('now')),
  (3, '収穫', datetime('now')),
  (4, '防除', datetime('now'));
