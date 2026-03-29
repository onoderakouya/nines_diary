<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/ui.php';

$admin = requireAdmin();
$pdo = db();

function roleLabel(string $r): string {
  return $r === 'admin' ? '管理者' : '研修生';
}

$allowedRoles = ['admin', 'trainee'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $token = (string)($_POST['csrf_token'] ?? '');
  if (!verifyCsrfToken($token)) {
    http_response_code(400);
    exit('不正なリクエストです。');
  }

  $targetId = (int)($_POST['user_id'] ?? 0);
  $action = (string)($_POST['action'] ?? 'update_role');
  $newRole = (string)($_POST['role'] ?? '');

  if ($targetId <= 0) {
    setFlash('error', '入力内容が不正です。');
    header('Location: admin_user_list.php');
    exit;
  }

  if ((int)$admin['id'] === $targetId) {
    setFlash('error', '自分自身の権限は変更できません。');
    header('Location: admin_user_list.php');
    exit;
  }

  $st = $pdo->prepare('SELECT id, role FROM users WHERE id = :id');
  $st->execute([':id' => $targetId]);
  $target = $st->fetch();

  if (!$target) {
    setFlash('error', '対象ユーザーが見つかりません。');
    header('Location: admin_user_list.php');
    exit;
  }

  if ($action === 'delete') {
    $del = $pdo->prepare('DELETE FROM users WHERE id = :id');
    $del->execute([':id' => $targetId]);
    setFlash('ok', 'ユーザーを削除しました。');
  } else {
    if (!in_array($newRole, $allowedRoles, true)) {
      setFlash('error', '入力内容が不正です。');
      header('Location: admin_user_list.php');
      exit;
    }

    $currentRole = (string)$target['role'];
    if ($currentRole !== $newRole) {
      $up = $pdo->prepare('UPDATE users SET role = :role WHERE id = :id');
      $up->execute([
        ':role' => $newRole,
        ':id' => $targetId,
      ]);
      setFlash('ok', 'ユーザー権限を更新しました。');
    } else {
      setFlash('ok', '権限は変更されていません。');
    }
  }

  header('Location: admin_user_list.php');
  exit;
}

$rows = $pdo->query("SELECT id,name,email,role,created_at FROM users ORDER BY created_at DESC")->fetchAll();
$okMessage = getFlash('ok');
$errorMessage = getFlash('error');
?>
<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <title>ユーザー一覧（管理者）</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="app.css">
  <style>
    .admin-users-page{
      max-width:980px;
      margin:0 auto;
      padding:16px 14px 24px;
    }
    .admin-users-header{
      text-align:center;
      margin-bottom:14px;
    }
    .admin-users-links{
      display:flex;
      justify-content:center;
      align-items:center;
      gap:8px;
      flex-wrap:wrap;
      margin:0;
    }
    .admin-users-table-wrap{
      width:100%;
      overflow-x:auto;
    }
    .admin-users-table{
      min-width:700px;
    }
    .admin-users-role-form{
      display:flex;
      gap:6px;
      align-items:center;
    }
    .admin-users-actions{
      display:flex;
      align-items:center;
      gap:10px;
      flex-wrap:wrap;
    }
    .admin-users-delete-form{
      margin:0;
    }
    .admin-users-delete-form button{
      background:#fff2f2;
      border:1px solid #d98282;
      color:#8e2222;
    }
    .admin-users-delete-form button:hover{
      background:#ffe4e4;
    }
    .admin-users-role-form select{
      min-width:86px;
    }
    .admin-users-flash{
      max-width:740px;
      margin:0 auto 12px;
      padding:10px 12px;
      border-radius:8px;
      font-size:14px;
    }
    .admin-users-flash.ok{
      background:#e6ffef;
      border:1px solid #93d7af;
      color:#135a2c;
    }
    .admin-users-flash.error{
      background:#ffecec;
      border:1px solid #e09c9c;
      color:#7a1f1f;
    }
    .admin-users-note{
      color:#666;
      margin-top:10px;
      text-align:center;
    }
    @media (max-width:640px){
      .admin-users-page{
        padding:12px 10px 20px;
      }
      .admin-users-header h1{
        font-size:22px;
        line-height:1.3;
      }
      .admin-users-table{
        min-width:620px;
      }
    }
  </style>
</head>
<body>
<?php renderGlobalTopbar($admin); ?>
  <main class="admin-users-page">
    <header class="admin-users-header">
      <h1>ユーザー一覧（管理者）</h1>
      <p class="admin-users-links"><a href="index.php">←ホーム</a> / <a href="admin_user_new.php">＋研修生ユーザー追加</a></p>
    </header>

    <?php if ($okMessage): ?>
      <p class="admin-users-flash ok"><?= e($okMessage) ?></p>
    <?php endif; ?>
    <?php if ($errorMessage): ?>
      <p class="admin-users-flash error"><?= e($errorMessage) ?></p>
    <?php endif; ?>

    <section class="card">
      <div class="admin-users-table-wrap">
        <table class="table admin-users-table">
          <thead>
          <tr>
            <th>ID</th>
            <th>名前</th>
            <th>メール</th>
            <th>種別</th>
            <th>作成日</th>
            <th>操作</th>
          </tr>
          </thead>
          <tbody>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td><?= (int)$r['id'] ?></td>
              <td><?= e($r['name']) ?></td>
              <td><?= e($r['email']) ?></td>
              <td><?= e(roleLabel((string)$r['role'])) ?></td>
              <td><?= e((string)$r['created_at']) ?></td>
              <td>
                <div class="admin-users-actions">
                  <form method="post" class="admin-users-role-form">
                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                    <input type="hidden" name="action" value="update_role">
                    <input type="hidden" name="user_id" value="<?= (int)$r['id'] ?>">
                    <select name="role" <?= ((int)$admin['id'] === (int)$r['id']) ? 'disabled' : '' ?>>
                      <option value="trainee" <?= ((string)$r['role'] === 'trainee') ? 'selected' : '' ?>>研修生</option>
                      <option value="admin" <?= ((string)$r['role'] === 'admin') ? 'selected' : '' ?>>管理者</option>
                    </select>
                    <button type="submit" <?= ((int)$admin['id'] === (int)$r['id']) ? 'disabled' : '' ?>>権限変更</button>
                  </form>
                  <a href="admin_reset_password.php?id=<?= (int)$r['id'] ?>">パス再発行</a>
                  <?php if ((int)$admin['id'] !== (int)$r['id']): ?>
                    <form method="post" class="admin-users-delete-form" onsubmit="return confirm('このユーザーを削除します。よろしいですか？');">
                      <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="user_id" value="<?= (int)$r['id'] ?>">
                      <button type="submit">削除</button>
                    </form>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>

    <p class="admin-users-note">
      ※自分自身の権限は変更できません。
    </p>
  </main>
</body>
</html>
