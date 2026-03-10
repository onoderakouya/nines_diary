<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

$error = '';
$email = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email = trim((string)($_POST['email'] ?? ''));
  $pass  = (string)($_POST['password'] ?? '');

  $stmt = db()->prepare('SELECT id,name,email,password_hash,role FROM users WHERE email=:email');
  $stmt->execute([':email' => $email]);
  $user = $stmt->fetch();

  if ($user && password_verify($pass, $user['password_hash'])) {
    $_SESSION['user'] = $user;
    header('Location: index.php');
    exit;
  }

  $error = 'メールまたはパスワードが違います';
}
?>
<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>ログイン | ナインズファーム 農業日誌</title>
  <style>
    :root {
      color-scheme: light;
      --bg-start: #f6f9ff;
      --bg-end: #e9f4ec;
      --accent: #2f8f6b;
      --accent-dark: #256f54;
      --text: #20312a;
      --muted: #666666;
      --title-text: #3f3f3f;
      --card-bg: #f4f4f4;
      --border: rgba(0, 0, 0, 0.12);
      --shadow: 0 14px 24px rgba(0, 0, 0, 0.22);
      --error-bg: #ffe9e9;
      --error-text: #b53b3b;
    }

    * {
      box-sizing: border-box;
    }

    body {
      margin: 0;
      min-height: 100vh;
      font-family: "Inter", "Hiragino Kaku Gothic ProN", "Yu Gothic", sans-serif;
      background:
        radial-gradient(circle at 10% 15%, rgba(56, 189, 121, 0.25), transparent 40%),
        radial-gradient(circle at 90% 10%, rgba(92, 132, 255, 0.22), transparent 32%),
        linear-gradient(135deg, var(--bg-start), var(--bg-end));
      color: var(--text);
      display: grid;
      place-items: center;
      padding: 24px;
    }

    .login-card {
      width: min(360px, 100%);
      background: var(--card-bg);
      border: 1px solid var(--border);
      border-radius: 22px;
      box-shadow: var(--shadow);
      padding: 20px 18px 24px;
    }

    .logo {
      display: block;
      width: min(232px, 94%);
      margin: 0 auto 6px;
      height: auto;
    }

    .title {
      margin: 0;
      text-align: center;
      font-size: clamp(1.95rem, 2.4vw, 2.1rem);
      font-weight: 400;
      letter-spacing: 0.06em;
      color: var(--title-text);
      font-family: "プレゼンス体", "Hiragino Mincho ProN", "Yu Mincho", serif;
    }

    .subtitle {
      margin: 8px 0 20px;
      text-align: center;
      color: var(--muted);
      font-size: 0.9rem;
      font-family: "プレゼンス体", "Hiragino Mincho ProN", "Yu Mincho", serif;
    }

    .error {
      margin: 0 0 16px;
      padding: 10px 12px;
      border-radius: 10px;
      background: var(--error-bg);
      color: var(--error-text);
      font-size: 0.9rem;
      border: 1px solid rgba(181, 59, 59, 0.2);
    }

    .field {
      margin-bottom: 14px;
    }

    .field label {
      display: block;
      margin-bottom: 7px;
      font-size: 0.88rem;
      color: #3f3f3f;
      font-weight: 600;
    }

    .field input {
      width: 100%;
      border: 1px solid #d5d5d5;
      border-radius: 4px;
      padding: 10px 12px;
      font-size: 1rem;
      background: #ffffff;
      color: var(--text);
      outline: none;
      transition: border-color 0.2s ease, box-shadow 0.2s ease;
    }

    .field input:focus {
      border-color: rgba(47, 143, 107, 0.7);
      box-shadow: 0 0 0 3px rgba(47, 143, 107, 0.15);
    }

    .button {
      width: 100%;
      margin-top: 6px;
      border: 0;
      border-radius: 4px;
      padding: 10px 16px;
      font-size: 2rem;
      font-weight: 700;
      letter-spacing: 0.04em;
      color: #fff;
      background: linear-gradient(90deg, var(--accent), #45c59a);
      cursor: pointer;
      box-shadow: 0 3px 7px rgba(0, 0, 0, 0.35);
      font-family: "プレゼンス体", "Hiragino Kaku Gothic ProN", "Yu Gothic", sans-serif;
      line-height: 1.1;
    }

    .button:hover {
      background: linear-gradient(90deg, var(--accent-dark), var(--accent));
    }
  </style>
</head>
<body>
  <main class="login-card">
    <img class="logo" src="assets/logo.png" alt="NINE'S DIARY ロゴ">
    <h1 class="title">ナインズファーム 農業日誌</h1>
    <p class="subtitle">作業記録の入力・確認はログインしてください</p>

    <?php if ($error): ?>
      <p class="error"><?= e($error) ?></p>
    <?php endif; ?>

    <form method="post">
      <div class="field">
        <label for="email">メールアドレス</label>
        <input id="email" name="email" type="email" value="<?= e($email) ?>" autocomplete="email" required>
      </div>
      <div class="field">
        <label for="password">パスワード</label>
        <input id="password" name="password" type="password" autocomplete="current-password" required>
      </div>
      <button class="button" type="submit">ログイン</button>
    </form>
  </main>
</body>
</html>
