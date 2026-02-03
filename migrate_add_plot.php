<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';

$u = requireAdmin();
$pdo = db();

echo "<pre>";

try {
  // すでに plot があるか確認
  $cols = $pdo->query("PRAGMA table_info(diary_entries)")->fetchAll();
  $hasPlot = false;
  foreach ($cols as $c) {
    if (($c['name'] ?? '') === 'plot') { $hasPlot = true; break; }
  }

  if ($hasPlot) {
    echo "OK: diary_entries.plot は既に存在します。何もしません。\n";
  } else {
    $pdo->exec("ALTER TABLE diary_entries ADD COLUMN plot TEXT");
    echo "DONE: diary_entries に plot(TEXT) を追加しました。\n";
  }

  echo "\n次は migrate_add_plot.php を削除してください。\n";
} catch (Throwable $e) {
  echo "ERROR:\n";
  echo $e->getMessage() . "\n";
}

echo "</pre>";
