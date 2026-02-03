<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
$u = requireLogin();
?>
<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <title>ホーム</title>
  <link rel="manifest" href="manifest.webmanifest">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <script>
  if ('serviceWorker' in navigator) navigator.serviceWorker.register('sw.js');
  </script>
  <style>
    body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial}
    h2{margin:18px 0 8px}
    ul{margin:6px 0 14px}
  </style>
</head>
<body>
  <h1>ホーム</h1>
  <p>ログイン中: <?=e($u['name'])?>（<?=e($u['role'])?>）</p>

  <h2>日誌</h2>
  <ul>
    <li><a href="diary_new.php">日誌入力</a></li>
    <li><a href="diary_list.php">日誌実績</a></li>
  </ul>

  <h2>出荷</h2>
  <ul>
    <li><a href="shipment_new.php">出荷入力</a></li>
    <li><a href="shipment_list.php">出荷実績</a></li>
    <?php if (isAdmin($u)): ?>
      <li><a href="shipment_summary.php">出荷集計（管理者）</a></li>
    <?php endif; ?>
  </ul>

  <h2>資材費</h2>
  <ul>
    <li><a href="material_new.php">資材費入力</a></li>
    <li><a href="material_list.php">資材費実績</a></li>
  </ul>

  <h2>病害虫</h2>
  <ul>
    <li><a href="pest_new.php">病害虫記録</a></li>
    <li><a href="pest_list.php">病害虫履歴</a></li>
  </ul>

  <h2>アカウント</h2>
  <ul>
    <li><a href="password_change.php">パスワード変更</a></li>
    <li><a href="logout.php">ログアウト</a></li>
  </ul>

  <?php if (isAdmin($u)): ?>
    <h2>管理者</h2>
    <ul>
      <li><a href="summary.php">作業時間集計（管理者）</a></li>
      <li><a href="admin_user_new.php">研修生ユーザー追加（管理者）</a></li>
      <li><a href="admin_user_list.php">ユーザー一覧（管理者）</a></li>
    </ul>
  <?php endif; ?>

</body>
</html>
