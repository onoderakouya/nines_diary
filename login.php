<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email = trim((string)($_POST['email'] ?? ''));
  $pass  = (string)($_POST['password'] ?? '');

  $stmt = db()->prepare("SELECT id,name,email,password_hash,role FROM users WHERE email=:email");
  $stmt->execute([':email'=>$email]);
  $user = $stmt->fetch();

  if ($user && password_verify($pass, $user['password_hash'])) {
    $_SESSION['user'] = $user;
    header('Location: index.php');
    exit;
  } else {
    $error = 'メールまたはパスワードが違います';
  }
}
?>
<!doctype html><meta charset="utf-8">
<title>ログイン</title>
<h1>ナインズファーム 農業日誌</h1>
<?php if ($error): ?><p style="color:red"><?=e($error)?></p><?php endif; ?>
<form method="post">
  <label>メール<br><input name="email" type="email" required></label><br><br>
  <label>パスワード<br><input name="password" type="password" required></label><br><br>
  <button>ログイン</button>
</form>
