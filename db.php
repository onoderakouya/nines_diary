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
  return $pdo;
}
