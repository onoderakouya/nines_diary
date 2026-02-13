<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
$u = requireLogin();
$pdo = db();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
  http_response_code(404);
  echo '対象の出荷データが見つかりません。';
  exit;
}

$fields = $pdo->query("SELECT id,label FROM fields ORDER BY label")->fetchAll();
$crops  = $pdo->query("SELECT id,name FROM crops ORDER BY id")->fetchAll();

$stmt = $pdo->prepare('SELECT * FROM shipments WHERE user_id = :uid AND id = :id');
$stmt->execute([':uid' => $u['id'], ':id' => $id]);
$row = $stmt->fetch();
if (!$row) {
  http_response_code(404);
  echo '対象の出荷データが見つからないか、編集権限がありません。';
  exit;
}

$err = '';
$date = (string)$row['date'];
$field_id = (int)$row['field_id'];
$plot = (string)($row['plot'] ?? '');
$crop_id = (int)$row['crop_id'];
$quantity = (string)$row['quantity'];
$unit = (string)$row['unit'];
$note = (string)($row['note'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    echo '不正なリクエストです（CSRFトークンが無効です）。';
    exit;
  }

  $date = (string)($_POST['date'] ?? '');
  $field_id = (int)($_POST['field_id'] ?? 0);
  $plot = trim((string)($_POST['plot'] ?? ''));
  $crop_id = (int)($_POST['crop_id'] ?? 0);
  $quantity = (string)($_POST['quantity'] ?? '0');
  $unit = (string)($_POST['unit'] ?? '');
  $note = trim((string)($_POST['note'] ?? ''));

  if (!$date || !$field_id || !$crop_id || (float)$quantity <= 0 || !in_array($unit, ['box','kg'], true)) {
    $err = '必須項目を入力してください（数量は0より大きく、単位は箱/kg）';
  } else {
    $up = $pdo->prepare('UPDATE shipments SET date=:date, field_id=:field_id, plot=:plot, crop_id=:crop_id, quantity=:quantity, unit=:unit, note=:note WHERE id=:id AND user_id=:uid');
    $up->execute([
      ':date' => $date,
      ':field_id' => $field_id,
      ':plot' => $plot !== '' ? $plot : null,
      ':crop_id' => $crop_id,
      ':quantity' => (float)$quantity,
      ':unit' => $unit,
      ':note' => $note !== '' ? $note : null,
      ':id' => $id,
      ':uid' => $u['id'],
    ]);

    setFlash('success', '更新しました');
    header('Location: shipment_list.php');
    exit;
  }
}

$csrf = csrfToken();
?>
<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <title>出荷編集</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="app.css">
  <script defer src="app.js"></script>
</head>
<body>
<div class="topbar">
  <div class="topbar-inner">
    <div class="title">出荷編集</div>
    <div class="actions">
      <a class="btn" href="shipment_list.php">一覧</a>
      <a class="btn ghost" href="index.php">ホーム</a>
    </div>
  </div>
</div>

<div class="container page form-page">
  <h1 class="form-page-title page-header">出荷入力フォーム</h1>

  <?php if ($err): ?>
    <div class="card form-section error-message error-summary"><?=e($err)?></div>
  <?php endif; ?>

  <form method="post">
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
          <label class="form-label">区画（任意）</label>
          <input class="form-input form-control" name="plot" value="<?=e($plot)?>" placeholder="例：区画1">
          <div class="hint help-text">※表記を揃えると集計が強くなります</div>
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
      <h2 class="form-section-title card-title">出荷内容</h2>
      <div class="form-grid">
        <div class="form-row">
          <label class="form-label">数量<span class="req">*</span></label>
          <input class="form-input form-control" type="number" name="quantity" step="0.1" min="0.1" required value="<?=e($quantity)?>" placeholder="例：10">
          <div class="hint help-text">※箱の場合は整数でもOK（10箱→10）</div>
        </div>
        <div class="form-row">
          <label class="form-label">単位<span class="req">*</span></label>
          <select class="form-input form-control" name="unit" required>
            <option value="">選択</option>
            <option value="box" <?= $unit === 'box' ? 'selected' : '' ?>>箱</option>
            <option value="kg" <?= $unit === 'kg' ? 'selected' : '' ?>>kg</option>
          </select>
          <div class="hint help-text">※箱 / kg を必ず選択</div>
        </div>
      </div>
    </div>

    <div class="card form-section">
      <h2 class="form-section-title card-title">補足情報</h2>
      <div class="form-row">
        <label class="form-label">メモ（任意）</label>
        <textarea class="form-input form-control" name="note" placeholder="例：等級・出荷先・備考など"><?=e($note)?></textarea>
      </div>
    </div>

    <div class="card form-section form-actions">
      <button class="btn primary btn-primary" type="submit">更新する</button>
    </div>
  </form>
</div>
</body>
</html>
