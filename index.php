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
</head>
<body>
  <h1>ホーム</h1>
  <p>ログイン中: <?=e($u['name'])?>（<?=e($u['role'])?>）</p>

  <ul>
    <li><a href="diary_new.php">日誌を追加</a></li>
    <li><a href="diary_list.php">日誌一覧</a></li>

    <li><a href="shipment_list.php">出荷一覧</a></li>
    <li><a href="shipment_new.php">出荷入力</a></li>


    <li><a href="shipment_new.php">出荷実績を追加</a></li>
    <li><a href="shipment_list.php">出荷実績一覧</a></li>

    <li><a href="material_new.php">資材費を追加</a></li>
    <li><a href="material_list.php">資材費一覧</a></li>

    <li><a href="pest_new.php">病害虫を追加</a></li>
    <li><a href="pest_list.php">病害虫一覧</a></li>

    <li><a href="password_change.php">パスワード変更</a></li>


    <?php if (isAdmin($u)): ?>
      <li><a href="summary.php">集計（管理者）</a></li>
      <li><a href="shipment_summary.php">出荷集計（管理者）</a></li>
      <li><a href="admin_user_new.php">研修生ユーザー追加（管理者）</a></li>
      <li><a href="admin_user_list.php">ユーザー一覧（管理者）</a></li>
    <?php endif; ?>

    <li><a href="logout.php">ログアウト</a></li>
  </ul>
</body>
</html>
