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
  return $pdo;
}
