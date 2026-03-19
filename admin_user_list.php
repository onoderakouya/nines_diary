<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/ui.php';

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
                  <a href="admin_reset_password.php?id=<?= (int)$r['id'] ?>">パス再発行</a>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>

    <p class="admin-users-note">
      ※削除・パスワード再発行も必要なら追加できます（誤操作防止のため確認ダイアログ付きにするのがおすすめ）。
    </p>
  </main>
</body>
</html>
