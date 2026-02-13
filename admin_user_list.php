<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';

$admin = requireAdmin();
$pdo = db();

$rows = $pdo->query("SELECT id,name,email,role,created_at FROM users ORDER BY created_at DESC")->fetchAll();

function roleLabel(string $r): string {
  return $r === 'admin' ? '管理者' : '研修生';
}
?>
<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <title>ユーザー一覧（管理者）</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="app.css">

</head>
<body>
  <h1>ユーザー一覧（管理者）</h1>
  <p><a href="index.php">←ホーム</a> / <a href="admin_user_new.php">＋研修生ユーザー追加</a></p>

  <table border="1" cellpadding="6" style="border-collapse:collapse; width:100%; max-width:900px">
    <tr>
      <th>ID</th>
      <th>名前</th>
      <th>メール</th>
      <th>種別</th>
      <th>作成日</th>
      <th>操作</th>

    </tr>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><?= (int)$r['id'] ?></td>
        <td><?= e($r['name']) ?></td>
        <td><?= e($r['email']) ?></td>
        <td><?= e(roleLabel((string)$r['role'])) ?></td>
        <td><?= e((string)$r['created_at']) ?></td>
        <td>
            <a href="admin_reset_password.php?id=<?= (int)$r['id'] ?>">パス再発行</a>
        </td>

      </tr>
    <?php endforeach; ?>
  </table>

  <p style="color:#666;margin-top:10px">
    ※削除・パスワード再発行も必要なら追加できます（誤操作防止のため確認ダイアログ付きにするのがおすすめ）。
  </p>
</body>
</html>
