<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/ui.php';

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
  <link rel="stylesheet" href="app.css">
</head>
<body>
<?php renderGlobalTopbar($u); ?>

<div class="container page">
  <h1 class="form-page-title">パスワード変更</h1>
  <p><a href="index.php">←ホーム</a></p>

  <p class="muted">ログイン中：<?=e($u['name'])?>（<?=e($u['email'])?>）</p>

  <?php if ($error): ?>
    <div class="card error"><?=e($error)?></div>
  <?php endif; ?>

  <?php if ($success): ?>
    <div class="card" style="border-color:#86efac;background:#f0fdf4;color:#166534"><?=e($success)?></div>
  <?php endif; ?>

  <form method="post" class="card form-section">
    <div class="form-row">
      <label class="form-label" for="current_password">現在のパスワード*</label>
      <input class="form-input" type="password" id="current_password" name="current_password" required>
    </div>

    <div class="form-row section-md">
      <label class="form-label" for="new_password">新しいパスワード*（10文字以上・英字+数字）</label>
      <input class="form-input" type="password" id="new_password" name="new_password" required>
    </div>

    <div class="form-row section-md">
      <label class="form-label" for="new_password2">新しいパスワード（確認）*</label>
      <input class="form-input" type="password" id="new_password2" name="new_password2" required>
    </div>

    <div class="form-actions section-md">
      <button class="btn btn-primary" type="submit">変更する</button>
    </div>
  </form>

  <p class="card form-note muted">
    ※仮パスワードでログインしたら、なるべく早めに変更してください。
  </p>
</div>
</body>
</html>
