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
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="app.css">
</head>
<body>

<div class="topbar">
  <div class="topbar-inner">
    <div class="title">ナインズ農業日誌</div>
    <div class="actions">
      <a class="btn ghost" href="password_change.php">PW変更</a>
      <a class="btn" href="logout.php">ログアウト</a>
    </div>
  </div>
</div>

<div class="container">
  <div class="card">
    <div style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;align-items:center">
      <div>
        <div class="muted">ログイン中</div>
        <div style="font-size:18px;font-weight:800"><?=e($u['name'])?> <span class="badge"><?=e($u['role'])?></span></div>
      </div>
      <a class="btn" href="guide.php">入力ガイド</a>
    </div>
  </div>

  <div class="grid">
    <div class="card">
      <div style="font-weight:900;margin-bottom:8px">日誌</div>
      <div class="actions">
        <a class="btn primary" href="diary_new.php">＋入力</a>
        <a class="btn" href="diary_list.php">一覧</a>
      </div>
    </div>

    <div class="card">
      <div style="font-weight:900;margin-bottom:8px">出荷</div>
      <div class="actions">
        <a class="btn primary" href="shipment_new.php">＋入力</a>
        <a class="btn" href="shipment_list.php">一覧</a>
      </div>
    </div>

    <div class="card">
      <div style="font-weight:900;margin-bottom:8px">資材費</div>
      <div class="actions">
        <a class="btn primary" href="material_new.php">＋入力</a>
        <a class="btn" href="material_list.php">一覧</a>
      </div>
    </div>

    <div class="card">
      <div style="font-weight:900;margin-bottom:8px">病害虫</div>
      <div class="actions">
        <a class="btn primary" href="pest_new.php">＋記録</a>
        <a class="btn" href="pest_list.php">履歴</a>
      </div>
    </div>
  </div>

  <?php if (isAdmin($u)): ?>
    <div class="card">
      <div style="font-weight:900;margin-bottom:8px">管理者</div>
      <div class="actions">
        <a class="btn" href="summary.php">集計</a>
        <a class="btn" href="monthly_report.php">月次レポート</a>
        <a class="btn" href="admin_user_new.php">研修生追加</a>
        <a class="btn" href="admin_user_list.php">ユーザー一覧</a>
      </div>
    </div>
  <?php endif; ?>

  <p class="muted">畑で使う前提なので、ボタンは大きめ・迷子になりにくい設計にしています。</p>
</div>

</body>
</html>
