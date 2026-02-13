<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';

$admin = requireAdmin();
$pdo = db();

function genTempPassword(int $len = 12): string {
  // 人間が打ちやすい＆強度もそこそこ（記号は控えめ）
  $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%';
  $out = '';
  for ($i=0; $i<$len; $i++) {
    $out .= $chars[random_int(0, strlen($chars)-1)];
  }
  return $out;
}

$created = null;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $name  = trim((string)($_POST['name'] ?? ''));
  $email = trim((string)($_POST['email'] ?? ''));

  if ($name === '' || $email === '') {
    $error = '名前とメールアドレスは必須です。';
  } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $error = 'メールアドレスの形式が正しくありません。';
  } else {
    // 既に存在するかチェック
    $st = $pdo->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
    $st->execute([':email' => $email]);
    if ($st->fetch()) {
      $error = 'このメールアドレスは既に登録されています。';
    } else {
      $tempPass = genTempPassword(12);
      $hash = password_hash($tempPass, PASSWORD_DEFAULT);

      $ins = $pdo->prepare("INSERT INTO users(name,email,password_hash,role,created_at)
        VALUES(:name,:email,:hash,'trainee',:created_at)
      ");
      $ins->execute([
        ':name' => $name,
        ':email' => $email,
        ':hash' => $hash,
        ':created_at' => date('c'),
      ]);

      $created = [
        'name' => $name,
        'email' => $email,
        'temp_password' => $tempPass, // 画面に一度だけ表示
      ];
    }
  }
}
?>
<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <title>研修生ユーザー追加（管理者）</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="app.css">

</head>
<body>
  <h1>研修生ユーザー追加（管理者）</h1>
  <p><a href="index.php">←ホーム</a> / <a href="admin_user_list.php">研修生一覧</a></p>

  <?php if ($error): ?>
    <p style="color:red"><?=e($error)?></p>
  <?php endif; ?>

  <?php if ($created): ?>
    <div style="border:1px solid #ccc;padding:12px;margin:12px 0;background:#f8f8f8">
      <h2 style="margin:0 0 8px 0">作成しました</h2>
      <div>名前：<?=e($created['name'])?></div>
      <div>メール：<?=e($created['email'])?></div>
      <div><b>仮パスワード：</b><span style="font-size:1.2em"><?=e($created['temp_password'])?></span></div>
      <p style="color:#555;margin:8px 0 0 0">
        ※この仮パスワードは今この画面でしか表示されません。必ず控えて研修生に共有してください。
      </p>
    </div>
  <?php endif; ?>

  <form method="post" style="max-width:520px">
    <label>研修生名（ニックネーム可）*<br>
      <input name="name" required style="width:100%" placeholder="例：ナインズ次郎">
    </label><br><br>

    <label>メールアドレス*<br>
      <input name="email" type="email" required style="width:100%" placeholder="例：example@gmail.com">
    </label><br><br>

    <button type="submit">作成（仮パス発行）</button>
  </form>

  <hr>
  <p style="color:#666">
    研修生は「自分の記録だけ閲覧」、管理者は「集計で全体傾向を見る」運用を想定しています。
  </p>
</body>
</html>
