<?php
declare(strict_types=1);

function isDebugMode(): bool {
  $env = strtolower((string)(getenv('APP_ENV') ?: 'production'));
  return in_array($env, ['dev', 'local', 'development'], true);
}

function initErrorHandling(): void {
  static $initialized = false;
  if ($initialized) return;
  $initialized = true;

  if (isDebugMode()) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
  } else {
    ini_set('display_errors', '0');
  }
  ini_set('log_errors', '1');

  set_exception_handler(function (Throwable $e): void {
    error_log('[nines_diary] Uncaught exception: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
    if (!headers_sent()) {
      http_response_code(500);
    }
    if (isDebugMode()) {
      echo '<h1>Application Error</h1><pre>' . htmlspecialchars((string)$e, ENT_QUOTES, 'UTF-8') . '</pre>';
      return;
    }
    echo 'システムエラーが発生しました。時間をおいて再度お試しください。';
  });
}

initErrorHandling();

const DB_PATH = __DIR__ . '/data/app.sqlite';

// 九戸村（ざっくり仮。後で調整OK）
const KUNOHE_LAT = 40.20;
const KUNOHE_LON = 141.30;

function requiredTables(): array {
  return ['users', 'fields', 'crops', 'tasks', 'diary_entries', 'materials', 'pests', 'shipments'];
}

function initializeDatabase(PDO $pdo): void {
  $schemaPath = __DIR__ . '/db/schema.sql';
  $seedPath = __DIR__ . '/db/seed.sql';

  if (!is_file($schemaPath) || !is_readable($schemaPath)) {
    throw new RuntimeException('schema.sqlが読み込めません: ' . $schemaPath);
  }
  if (!is_file($seedPath) || !is_readable($seedPath)) {
    throw new RuntimeException('seed.sqlが読み込めません: ' . $seedPath);
  }

  $schemaSql = file_get_contents($schemaPath);
  $seedSql = file_get_contents($seedPath);
  if ($schemaSql === false || $seedSql === false) {
    throw new RuntimeException('schema.sql / seed.sql の読み込みに失敗しました');
  }

  $pdo->beginTransaction();
  try {
    $pdo->exec($schemaSql);
    $pdo->exec($seedSql);
    $pdo->commit();
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) {
      $pdo->rollBack();
    }
    throw $e;
  }
}

function ensureDatabaseInitialized(PDO $pdo): void {
  $existing = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
  if (!is_array($existing)) {
    throw new RuntimeException('テーブル状態の取得に失敗しました');
  }

  $missing = array_diff(requiredTables(), $existing);
  if ($missing === []) {
    return;
  }

  error_log('[nines_diary] Missing database tables detected: ' . implode(', ', $missing) . '. Running auto migration.');
  initializeDatabase($pdo);
}


function ensureStandardFields(PDO $pdo): void {
  $labels = [
    1 => '1-1', 2 => '1-2', 3 => '1-3', 4 => '1-4', 5 => '1-5',
    6 => '1-6', 7 => '1-7', 8 => '1-8', 9 => '1-9', 10 => '1-10',
    11 => '2-1', 12 => '2-2', 13 => '2-3', 14 => '2-4', 15 => '2-5',
    16 => '2-6', 17 => '2-7', 18 => '2-8', 19 => '2-9', 20 => '2-10',
    21 => '3-1', 22 => '3-2', 23 => '3-3', 24 => '3-4', 25 => '3-5',
    26 => '2-11', 27 => '3-6', 28 => '3-7',
  ];

  $stmt = $pdo->prepare(
    'INSERT INTO fields (id, label, created_at) VALUES (:id, :label, :created_at) '
    . 'ON CONFLICT(id) DO UPDATE SET label = excluded.label'
  );

  foreach ($labels as $id => $label) {
    $stmt->execute([
      ':id' => $id,
      ':label' => $label,
      ':created_at' => date('c'),
    ]);
  }
}

function ensureStandardCrops(PDO $pdo): void {
  $crops = [
    1 => 'トマト',
    2 => 'きゅうり',
    3 => 'ピーマン',
    4 => 'なす',
    5 => '抑制トマト',
  ];

  $stmt = $pdo->prepare(
    'INSERT INTO crops (id, name, created_at) VALUES (:id, :name, :created_at) '
    . 'ON CONFLICT(id) DO UPDATE SET name = excluded.name'
  );

  foreach ($crops as $id => $name) {
    $stmt->execute([
      ':id' => $id,
      ':name' => $name,
      ':created_at' => date('c'),
    ]);
  }
}

function findOrCreateCropId(PDO $pdo, string $cropName): int {
  $name = trim($cropName);
  if ($name === '') {
    return 0;
  }

  $find = $pdo->prepare('SELECT id FROM crops WHERE name = :name LIMIT 1');
  $find->execute([':name' => $name]);
  $id = (int)($find->fetchColumn() ?: 0);
  if ($id > 0) {
    return $id;
  }

  $insert = $pdo->prepare('INSERT INTO crops (name, created_at) VALUES (:name, :created_at)');
  $insert->execute([
    ':name' => $name,
    ':created_at' => date('c'),
  ]);

  return (int)$pdo->lastInsertId();
}

function db(): PDO {
  static $pdo = null;
  if ($pdo) return $pdo;

  $dataDir = dirname(DB_PATH);
  if (!is_dir($dataDir) && !mkdir($dataDir, 0775, true) && !is_dir($dataDir)) {
    throw new RuntimeException('dataディレクトリの作成に失敗しました: ' . $dataDir);
  }

  $pdo = new PDO('sqlite:' . DB_PATH);
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
  // 外部キー有効化
  $pdo->exec('PRAGMA foreign_keys = ON;');
  ensureDatabaseInitialized($pdo);
  ensureStandardFields($pdo);
  ensureStandardCrops($pdo);
  return $pdo;
}
