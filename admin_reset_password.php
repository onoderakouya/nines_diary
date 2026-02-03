<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';

$admin = requireAdmin();
$pdo = db();

function genTempPassword(int $len = 12): string {
  $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%';
  $out = '';
  for ($i=0; $i<$len; $i++) $out .= $chars[random_int(0, strlen($chars)-1)];
  return $out;
}

$error = '';
$success = '';
$issued = null;

$userId = (int)($_GET['id'] ?? 0);

// ユーザー取得（研修生だけを対象にしてもいいが、まずは全員から）
$target = null;
if ($userId) {
  $st = $pdo->prepare("SELECT id,name,email,role FROM users WHERE id = :id");
  $st->execute([':id'=>$userId]);
  $target = $st->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $userId = (int)($_POST['id'] ?? 0);

  $st = $pdo->prepare("SELECT id,name,email,role FROM users WHERE id = :id");
  $st->execute([':id'=>$userId]);
  $target = $st->fetch();

  if (!$target) {
    $error = '対象ユーザーが見つかりません。';
  } else {
    $temp = genTempPassword(12);
    $hash = password_hash($temp, PASSWORD_DEFAULT);

    $up = $pdo->prepare("UPDATE users SET password_hash = :hash WHERE id = :id");
    $up->execute([':hash'=>$hash, ':id'=>$userId]);

    $issued = [
      'name' => $target['name'],
      'email' => $target['email'],
      'temp_password' => $temp
    ];
    $success = '仮パスワードを再発行しました（この画面で一度だけ表示）。';
  }
}
?>
<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <title>パスワード再発行（管理者）</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body>
  <h1>パスワード再発行（管理者）</h1>
  <p><a href="admin_user_list.php">←ユーザー一覧へ</a></p>

  <?php if ($error): ?><p style="color:red"><?=e($error)?></p><?php endif; ?>
  <?php if ($success): ?><p style="color:green"><?=e($success)?></p><?php endif; ?>

  <?php if ($issued): ?>
    <div style="border:1px solid #ccc;padding:12px;margin:12px 0;background:#f8f8f8">
      <div>名前：<?=e($issued['name'])?></div>
      <div>メール：<?=e($issued['email'])?></div>
      <div><b>新しい仮パスワード：</b><span style="font-size:1.2em"><?=e($issued['temp_password'])?></span></div>
      <p style="color:#555;margin:8px 0 0 0">
        ※この仮パスワードは今この画面でしか表示されません。必ず控えて本人に共有してください。
      </p>
    </div>
  <?php endif; ?>

  <?php if (!$target): ?>
    <p style="color:#666">ユーザー一覧から対象ユーザーのIDを指定して開いてください。</p>
  <?php else: ?>
    <h2>対象</h2>
    <div>名前：<?=e($target['name'])?></div>
    <div>メール：<?=e($target['email'])?></div>
    <div>種別：<?=e($target['role'])?></div>

    <form method="post" style="margin-top:12px">
      <input type="hidden" name="id" value="<?= (int)$target['id'] ?>">
      <button type="submit" onclick="return confirm('仮パスワードを再発行します。よろしいですか？');">
        仮パスワードを再発行
      </button>
    </form>
  <?php endif; ?>
</body>
</html>
