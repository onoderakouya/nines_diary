<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';

if (php_sapi_name() !== 'cli') {
  // ブラウザから叩いてOK（初回だけ）
  header('Content-Type: text/plain; charset=utf-8');
}

@mkdir(__DIR__ . '/data', 0777, true);
@mkdir(__DIR__ . '/uploads', 0777, true);

$pdo = db();

// テーブル
$pdo->exec("
CREATE TABLE IF NOT EXISTS users (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL,
  email TEXT NOT NULL UNIQUE,
  password_hash TEXT NOT NULL,
  role TEXT NOT NULL DEFAULT 'trainee', -- trainee|admin
  created_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS fields (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  house TEXT NOT NULL,
  section TEXT, -- 今回は未使用でもOK
  label TEXT NOT NULL UNIQUE
);

CREATE TABLE IF NOT EXISTS crops (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL UNIQUE
);

CREATE TABLE IF NOT EXISTS tasks (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL UNIQUE
);

CREATE TABLE IF NOT EXISTS diary_entries (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL,
  date TEXT NOT NULL, -- YYYY-MM-DD
  field_id INTEGER NOT NULL,
  crop_id INTEGER NOT NULL,
  task_id INTEGER NOT NULL,
  minutes INTEGER NOT NULL,
  memo TEXT,
  weather_code TEXT,
  temp_c REAL,
  created_at TEXT NOT NULL,
  FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY(field_id) REFERENCES fields(id) ON DELETE CASCADE,
  FOREIGN KEY(crop_id) REFERENCES crops(id) ON DELETE CASCADE,
  FOREIGN KEY(task_id) REFERENCES tasks(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS diary_photos (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  diary_id INTEGER NOT NULL,
  path TEXT NOT NULL,
  created_at TEXT NOT NULL,
  FOREIGN KEY(diary_id) REFERENCES diary_entries(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS shipments (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL,
  date TEXT NOT NULL,
  field_id INTEGER NOT NULL,
  crop_id INTEGER NOT NULL,
  quantity REAL NOT NULL,
  unit TEXT NOT NULL CHECK(unit IN ('box','kg')),
  note TEXT,
  created_at TEXT NOT NULL,
  FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY(field_id) REFERENCES fields(id) ON DELETE CASCADE,
  FOREIGN KEY(crop_id) REFERENCES crops(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS materials (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL,
  date TEXT NOT NULL,
  field_id INTEGER, -- nullable
  crop_id INTEGER,  -- nullable
  item_name TEXT NOT NULL,
  amount REAL,
  unit TEXT,
  cost_yen INTEGER NOT NULL,
  note TEXT,
  created_at TEXT NOT NULL,
  FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY(field_id) REFERENCES fields(id) ON DELETE SET NULL,
  FOREIGN KEY(crop_id) REFERENCES crops(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS pests (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL,
  date TEXT NOT NULL,
  field_id INTEGER NOT NULL,
  crop_id INTEGER NOT NULL,
  symptom_text TEXT NOT NULL,
  action_text TEXT,
  photo_path TEXT,
  created_at TEXT NOT NULL,
  FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY(field_id) REFERENCES fields(id) ON DELETE CASCADE,
  FOREIGN KEY(crop_id) REFERENCES crops(id) ON DELETE CASCADE
);
");

// 初期データ投入（重複は無視）
function upsertSimple(string $table, array $values): void {
  $pdo = db();
  $stmt = $pdo->prepare("INSERT OR IGNORE INTO {$table}(name) VALUES(:name)");
  foreach ($values as $v) $stmt->execute([':name' => $v]);
}

$crops = ['トマト','ピーマン','ナス','ミニトマト','サツマイモ'];
upsertSimple('crops', $crops);

$tasks = [
  '育苗','定植','潅水','整枝','誘引','収穫','農薬散布','耕作','追肥','芽かき','葉かき',
  '摘果','摘花','畝立て','マルチ張り','受粉処理','抜根','圃場の片付け'
];
upsertSimple('tasks', $tasks);

// 圃場（ハウスだけ。区画は後で拡張）
$houses = ['ハウスA','ハウスB','ハウスC','ハウスD','ハウスE'];
$stmt = $pdo->prepare("INSERT OR IGNORE INTO fields(house, section, label) VALUES(:house, :section, :label)");
foreach ($houses as $h) {
  $stmt->execute([':house'=>$h, ':section'=>null, ':label'=>$h]);
}

// ユーザー作成（例：ナインズ太郎）
$email = 'ninesfarm999@gmail.com';
$pass  = 'ChangeMe123!'; // 初回ログイン後に変える想定
$name  = 'ナインズ太郎';
$role  = 'admin'; // あなたは管理者にしておく（全員分集計できる）

$hash = password_hash($pass, PASSWORD_DEFAULT);
$now = date('c');

$pdo->prepare("INSERT OR IGNORE INTO users(name,email,password_hash,role,created_at)
VALUES(:name,:email,:hash,:role,:created_at)")
->execute([
  ':name'=>$name, ':email'=>$email, ':hash'=>$hash, ':role'=>$role, ':created_at'=>$now
]);

echo "初期化完了。\n";
echo "管理者ログイン: {$email}\n";
echo "初期パスワード: {$pass}\n";
echo "終わったら init.php は削除 or アクセス制限推奨。\n";
