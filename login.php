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
  <title>ログイン｜NINE'S DIARY</title>
  <title>ログイン | ナインズファーム 農業日誌</title>
  <style>
    :root {
      color-scheme: light;
      --bg-start: #f6f9ff;
      --bg-end: #e9f4ec;
      --accent: #2f8f6b;
      --accent-dark: #256f54;
      --text: #20312a;
      --muted: #5b6a63;
      --card-bg: rgba(255, 255, 255, 0.85);
      --border: rgba(47, 143, 107, 0.18);
      --shadow: 0 20px 55px rgba(34, 83, 66, 0.18);
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
      width: min(420px, 100%);
      background: var(--card-bg);
      backdrop-filter: blur(12px);
      border: 1px solid var(--border);
      border-radius: 22px;
      box-shadow: var(--shadow);
      padding: 34px 28px 30px;
    }

    .title {
      margin: 0;
      font-size: clamp(1.35rem, 2.6vw, 1.8rem);
      font-weight: 700;
      letter-spacing: 0.03em;
    }

    .subtitle {
      margin: 8px 0 26px;
      color: var(--muted);
      font-size: 0.95rem;
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
      margin-bottom: 16px;
    }

    .field label {
      display: block;
      margin-bottom: 7px;
      font-size: 0.88rem;
      color: var(--muted);
      font-weight: 600;
    }

    .field input {
      width: 100%;
      border: 1px solid #d6e4dc;
      border-radius: 12px;
      padding: 12px 14px;
      font-size: 1rem;
      background: #ffffff;
      color: var(--text);
      outline: none;
      transition: border-color 0.2s ease, box-shadow 0.2s ease;
    }

    .field input:focus {
      border-color: rgba(47, 143, 107, 0.7);
      box-shadow: 0 0 0 4px rgba(47, 143, 107, 0.16);
    }

    .button {
      width: 100%;
      margin-top: 8px;
      border: 0;
      border-radius: 12px;
      padding: 12px 16px;
      font-size: 1rem;
      font-weight: 700;
      color: #fff;
      background: linear-gradient(135deg, var(--accent), #4bbd8f);
      cursor: pointer;
      transition: transform 0.18s ease, box-shadow 0.18s ease, background 0.2s ease;
      box-shadow: 0 10px 22px rgba(47, 143, 107, 0.28);
    }

    .button:hover {
      transform: translateY(-1px);
      background: linear-gradient(135deg, var(--accent-dark), var(--accent));
    }

    .button:active {
      transform: translateY(0);
    }
  </style>
</head>
<body>
  <main class="login-card">
    <h1 class="title">NINE'S DIARY</h1>
    <h1 class="title">ナインズファーム 農業日誌</h1>
    <p class="subtitle">作業記録の入力・確認にはログインしてください。</p>

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
