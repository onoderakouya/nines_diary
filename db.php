<?php
declare(strict_types=1);

const DB_PATH = __DIR__ . '/data/app.sqlite';

// 九戸村（ざっくり仮。後で調整OK）
const KUNOHE_LAT = 40.20;
const KUNOHE_LON = 141.30;

function db(): PDO {
  static $pdo = null;
  if ($pdo) return $pdo;

  $pdo = new PDO('sqlite:' . DB_PATH);
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
  // 外部キー有効化
  $pdo->exec('PRAGMA foreign_keys = ON;');
  return $pdo;
}
