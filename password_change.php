<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';

$u = requireLogin();
$pdo = db();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $current = (string)($_POST['current_password'] ?? '');
  $new1    = (string)($_POST['new_password'] ?? '');
  $new2    = (string)($_POST['new_password2'] ?? '');

  // 現在パス確認
  $st = $pdo->prepare("SELECT password_hash FROM users WHERE id = :id");
  $st->execute([':id' => $u['id']]);
  $row = $st->fetch();

  if (!$row || !password_verify($current, $row['password_hash'])) {
    $error = '現在のパスワードが違います。';
  } elseif ($new1 === '' || $new2 === '') {
    $error = '新しいパスワードを入力してください。';
  } elseif ($new1 !== $new2) {
    $error = '新しいパスワード（確認）が一致しません。';
  } elseif (strlen($new1) < 10) {
    $error = '新しいパスワードは10文字以上にしてください。';
  } else {
    // ざっくり強度チェック（必須ではないが最低ライン）
    $hasLetter = preg_match('/[A-Za-z]/', $new1) === 1;
    $hasDigit  = preg_match('/\d/', $new1) === 1;

    if (!$hasLetter || !$hasDigit) {
      $error = '新しいパスワードは英字と数字を両方含めてください。';
    } else {
      $hash = password_hash($new1, PASSWORD_DEFAULT);
      $up = $pdo->prepare("UPDATE users SET password_hash = :hash WHERE id = :id");
      $up->execute([':hash' => $hash, ':id' => $u['id']]);

      $success = 'パスワードを変更しました。';

      // セッションを最新情報に更新（念のため）
      $st2 = $pdo->prepare("SELECT id,name,email,role FROM users WHERE id = :id");
      $st2->execute([':id' => $u['id']]);
      $_SESSION['user'] = $st2->fetch();
    }
  }
}
?>
<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <title>パスワード変更</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body>
  <h1>パスワード変更</h1>
  <p><a href="index.php">←ホーム</a></p>

  <p style="color:#555">ログイン中：<?=e($u['name'])?>（<?=e($u['email'])?>）</p>

  <?php if ($error): ?>
    <p style="color:red"><?=e($error)?></p>
  <?php endif; ?>

  <?php if ($success): ?>
    <p style="color:green"><?=e($success)?></p>
  <?php endif; ?>

  <form method="post" style="max-width:520px">
    <label>現在のパスワード*<br>
      <input type="password" name="current_password" required style="width:100%">
    </label><br><br>

    <label>新しいパスワード*（10文字以上・英字+数字）<br>
      <input type="password" name="new_password" required style="width:100%">
    </label><br><br>

    <label>新しいパスワード（確認）*<br>
      <input type="password" name="new_password2" required style="width:100%">
    </label><br><br>

    <button type="submit">変更する</button>
  </form>

  <hr>
  <p style="color:#666">
    ※仮パスワードでログインしたら、なるべく早めに変更してください。
  </p>
</body>
</html>
