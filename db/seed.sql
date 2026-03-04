PRAGMA foreign_keys = ON;

INSERT OR IGNORE INTO users (id, name, email, password_hash, role, created_at)
VALUES
  (1, 'Admin', 'admin@example.com', '$2y$12$xCX7cdX5yU5tpfCwVS2Z5OyVfJuJXnvWIlB067RDYuoPbCgWIjb.W', 'admin', datetime('now'));

INSERT INTO fields (id, label, created_at)
VALUES
  (1, '1-1', datetime('now')),(2, '1-2', datetime('now')),(3, '1-3', datetime('now')),(4, '1-4', datetime('now')),(5, '1-5', datetime('now')),(6, '1-6', datetime('now')),(7, '1-7', datetime('now')),(8, '1-8', datetime('now')),(9, '1-9', datetime('now')),(10, '1-10', datetime('now')),(11, '2-1', datetime('now')),(12, '2-2', datetime('now')),(13, '2-3', datetime('now')),(14, '2-4', datetime('now')),(15, '2-5', datetime('now')),(16, '2-6', datetime('now')),(17, '2-7', datetime('now')),(18, '2-8', datetime('now')),(19, '2-9', datetime('now')),(20, '2-10', datetime('now')),(21, '3-1', datetime('now')),(22, '3-2', datetime('now')),(23, '3-3', datetime('now')),(24, '3-4', datetime('now')),(25, '3-5', datetime('now'))
ON CONFLICT(id) DO UPDATE SET label = excluded.label;

-- crop master seed data
INSERT OR IGNORE INTO crops (id, name, created_at)
VALUES
  (1, 'トマト', datetime('now')),
  (2, 'きゅうり', datetime('now')),
  (3, 'ピーマン', datetime('now')),
  (4, 'なす', datetime('now'));

INSERT OR IGNORE INTO tasks (id, name, created_at)
VALUES
  (1, 'その他', datetime('now')),
  (2, '定植', datetime('now')),
  (3, '収穫', datetime('now')),
  (4, '防除', datetime('now'));
