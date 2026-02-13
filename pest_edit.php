<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
$u = requireLogin();
$pdo = db();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
  http_response_code(404);
  echo '対象の病害虫データが見つかりません。';
  exit;
}

$fields = $pdo->query("SELECT id,label FROM fields ORDER BY label")->fetchAll();
$crops  = $pdo->query("SELECT id,name FROM crops ORDER BY id")->fetchAll();
$tagOptions = [
  'ハダニ','アブラムシ','うどんこ病','灰色かび','疫病','葉かび','その他'
];

$stmt = $pdo->prepare('SELECT * FROM pests WHERE user_id = :uid AND id = :id');
$stmt->execute([':uid' => $u['id'], ':id' => $id]);
$row = $stmt->fetch();
if (!$row) {
  http_response_code(404);
  echo '対象の病害虫データが見つからないか、編集権限がありません。';
  exit;
}

function saveUploadedImage(string $key, string $uploadDir, string &$err): ?string {
  if (!isset($_FILES[$key]) || $_FILES[$key]['error'] === UPLOAD_ERR_NO_FILE) return null;
  if ($_FILES[$key]['error'] !== UPLOAD_ERR_OK) { $err = '写真のアップロードに失敗しました'; return null; }

  $tmp = (string)$_FILES[$key]['tmp_name'];
  $name = (string)$_FILES[$key]['name'];
  $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
  if (!in_array($ext, ['jpg','jpeg','png','webp'], true)) { $err = '写真は jpg/png/webp のみ対応です'; return null; }

  if (!is_dir($uploadDir)) {
    @mkdir($uploadDir, 0755, true);
  }

  $fn = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
  $path = rtrim($uploadDir, '/').'/'.$fn;

  if (!move_uploaded_file($tmp, $path)) { $err = '写真の保存に失敗しました'; return null; }

  return 'uploads/' . $fn;
}

$err = '';
$date = (string)$row['date'];
$field_id = (int)$row['field_id'];
$crop_id = (int)$row['crop_id'];
$tag = (string)($row['symptom_tag'] ?? '');
$symptom = (string)($row['symptom_text'] ?? '');
$action = (string)($row['action_text'] ?? '');
$photo_path = (string)($row['photo_path'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    echo '不正なリクエストです（CSRFトークンが無効です）。';
    exit;
  }

  $date = (string)($_POST['date'] ?? '');
  $field_id = (int)($_POST['field_id'] ?? 0);
  $crop_id = (int)($_POST['crop_id'] ?? 0);
  $tag = trim((string)($_POST['symptom_tag'] ?? ''));
  $symptom = trim((string)($_POST['symptom_text'] ?? ''));
  $action = trim((string)($_POST['action_text'] ?? ''));

  $newPhoto = saveUploadedImage('photo', __DIR__ . '/uploads', $err);
  if ($newPhoto !== null) {
    $photo_path = $newPhoto;
  }

  if (!$date || !$field_id || !$crop_id || $symptom === '') {
    $err = $err ?: '必須項目を入力してください（症状は必須）';
  } elseif ($err === '') {
    $up = $pdo->prepare('UPDATE pests SET date=:date, field_id=:field_id, crop_id=:crop_id, symptom_tag=:tag, symptom_text=:symptom, action_text=:action, photo_path=:photo WHERE id=:id AND user_id=:uid');
    $up->execute([
      ':date' => $date,
      ':field_id' => $field_id,
      ':crop_id' => $crop_id,
      ':tag' => $tag !== '' ? $tag : null,
      ':symptom' => $symptom,
      ':action' => $action !== '' ? $action : null,
      ':photo' => $photo_path !== '' ? $photo_path : null,
      ':id' => $id,
      ':uid' => $u['id'],
    ]);

    setFlash('success', '更新しました');
    header('Location: pest_list.php');
    exit;
  }
}

$csrf = csrfToken();
?>
<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <title>病害虫編集</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="app.css">
  <script defer src="app.js"></script>
</head>
<body>
<div class="topbar">
  <div class="topbar-inner">
    <div class="title">病害虫編集</div>
    <div class="actions">
      <a class="btn" href="pest_list.php">履歴</a>
      <a class="btn ghost" href="index.php">ホーム</a>
    </div>
  </div>
</div>

<div class="container page form-page">
  <h1 class="form-page-title page-header">病害虫記録フォーム</h1>

  <?php if ($err): ?>
    <div class="card form-section error-message error-summary"><?=e($err)?></div>
  <?php endif; ?>

  <div class="card form-section form-note">
    <b>入力のコツ</b><br>
    ・「状態 → 広がり → 変化」の順で短く書くと後で役に立ちます<br>
    ・写真があると特定が速い（任意だけど推奨）
  </div>

  <form method="post" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">

    <div class="card form-section">
      <h2 class="form-section-title card-title">基本情報</h2>
      <div class="form-grid">
        <div class="form-row">
          <label class="form-label">日付<span class="req">*</span></label>
          <input class="form-input form-control" type="date" name="date" value="<?=e($date)?>" required>
        </div>

        <div class="form-row">
          <label class="form-label">圃場（ハウス）<span class="req">*</span></label>
          <select class="form-input form-control" name="field_id" required>
            <option value="">選択</option>
            <?php foreach ($fields as $f): ?>
              <option value="<?= (int)$f['id'] ?>" <?= $field_id === (int)$f['id'] ? 'selected' : '' ?>><?= e($f['label']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-row">
          <label class="form-label">品目<span class="req">*</span></label>
          <select class="form-input form-control" name="crop_id" required>
            <option value="">選択</option>
            <?php foreach ($crops as $c): ?>
              <option value="<?= (int)$c['id'] ?>" <?= $crop_id === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
    </div>

    <div class="card form-section">
      <h2 class="form-section-title card-title">症状の記録</h2>
      <div class="form-row">
        <label class="form-label">タグ（任意）</label>
        <select class="form-input form-control" name="symptom_tag">
          <option value="">—</option>
          <?php foreach ($tagOptions as $t): ?>
            <option value="<?=e($t)?>" <?= $tag === $t ? 'selected' : '' ?>><?=e($t)?></option>
          <?php endforeach; ?>
        </select>
        <div class="hint help-text">※集計で効くので、分かる範囲で選ぶと強いです</div>
      </div>
    </div>

    <div class="card form-section">
      <div class="form-row">
        <label class="form-label">症状<span class="req">*</span></label>
        <textarea class="form-input form-control" name="symptom_text" required placeholder="例：
・下葉に白い粉
・入口付近から広がる
・昨日より悪化"><?=e($symptom)?></textarea>
      </div>
    </div>

    <div class="card form-section">
      <div class="form-row">
        <label class="form-label">対応（任意）</label>
        <textarea class="form-input form-control" name="action_text" placeholder="例：
・被害葉を除去
・薬剤Aを500倍で散布"><?=e($action)?></textarea>
      </div>
    </div>

    <div class="card form-section">
      <div class="form-row">
        <label class="form-label">写真（任意）</label>
        <input class="form-input form-control" type="file" name="photo" accept="image/*">
        <?php if ($photo_path !== ''): ?>
          <div class="hint help-text">現在の写真: <?= e($photo_path) ?></div>
        <?php endif; ?>
        <div class="hint help-text">※jpg/png/webp対応。病害虫は写真が“証拠”になります。</div>
      </div>
    </div>

    <div class="card form-section form-actions">
      <button class="btn primary btn-primary" type="submit">更新する</button>
    </div>

  </form>
</div>
</body>
</html>
