<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
$u = requireLogin();
$pdo = db();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
  http_response_code(404);
  echo '対象の資材データが見つかりません。';
  exit;
}

$fields = $pdo->query("SELECT id,label FROM fields ORDER BY label")->fetchAll();
$crops  = $pdo->query("SELECT id,name FROM crops ORDER BY id")->fetchAll();

$stmt = $pdo->prepare('SELECT * FROM materials WHERE user_id = :uid AND id = :id');
$stmt->execute([':uid' => $u['id'], ':id' => $id]);
$row = $stmt->fetch();
if (!$row) {
  http_response_code(404);
  echo '対象の資材データが見つからないか、編集権限がありません。';
  exit;
}

$err = '';
$date = (string)$row['date'];
$field_id = (int)($row['field_id'] ?? 0);
$crop_id = (int)($row['crop_id'] ?? 0);
$item_name = (string)$row['item_name'];
$amount = $row['amount'] === null ? '' : (string)$row['amount'];
$unit = (string)($row['unit'] ?? '');
$cost_yen = (string)$row['cost_yen'];
$note = (string)($row['note'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    echo '不正なリクエストです（CSRFトークンが無効です）。';
    exit;
  }

  $date = (string)($_POST['date'] ?? '');
  $field_id = (int)($_POST['field_id'] ?? 0);
  $crop_id = (int)($_POST['crop_id'] ?? 0);
  $item_name = trim((string)($_POST['item_name'] ?? ''));
  $amount = trim((string)($_POST['amount'] ?? ''));
  $unit = trim((string)($_POST['unit'] ?? ''));
  $cost_yen = (string)($_POST['cost_yen'] ?? '0');
  $note = trim((string)($_POST['note'] ?? ''));

  $amountVal = $amount !== '' ? (float)$amount : null;

  if (!$date || $item_name === '' || (int)$cost_yen <= 0) {
    $err = '必須項目を入力してください（資材名・日付・金額）';
  } else {
    $up = $pdo->prepare('UPDATE materials SET date=:date, field_id=:field_id, crop_id=:crop_id, item_name=:item_name, amount=:amount, unit=:unit, cost_yen=:cost_yen, note=:note WHERE id=:id AND user_id=:uid');
    $up->execute([
      ':date' => $date,
      ':field_id' => $field_id ?: null,
      ':crop_id' => $crop_id ?: null,
      ':item_name' => $item_name,
      ':amount' => $amountVal,
      ':unit' => $unit !== '' ? $unit : null,
      ':cost_yen' => (int)$cost_yen,
      ':note' => $note !== '' ? $note : null,
      ':id' => $id,
      ':uid' => $u['id'],
    ]);

    setFlash('success', '更新しました');
    header('Location: material_list.php');
    exit;
  }
}

$csrf = csrfToken();
?>
<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <title>資材費編集</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="app.css">
  <script defer src="app.js"></script>
</head>
<body>
<div class="topbar">
  <div class="topbar-inner">
    <div class="title">資材費編集</div>
    <div class="actions">
      <a class="btn" href="material_list.php">一覧</a>
      <a class="btn ghost" href="index.php">ホーム</a>
    </div>
  </div>
</div>

<div class="container page form-page">
  <h1 class="form-page-title page-header">資材費入力フォーム</h1>

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
          <label class="form-label">圃場（任意）</label>
          <select class="form-input form-control" name="field_id">
            <option value="0">—</option>
            <?php foreach ($fields as $f): ?>
              <option value="<?= (int)$f['id'] ?>" <?= $field_id === (int)$f['id'] ? 'selected' : '' ?>><?= e($f['label']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-row">
          <label class="form-label">品目（任意）</label>
          <select class="form-input form-control" name="crop_id">
            <option value="0">—</option>
            <?php foreach ($crops as $c): ?>
              <option value="<?= (int)$c['id'] ?>" <?= $crop_id === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
    </div>

    <div class="card form-section">
      <h2 class="form-section-title card-title">費用情報</h2>
      <div class="form-grid">
        <div class="form-row">
          <label class="form-label">資材名<span class="req">*</span></label>
          <input class="form-input form-control" name="item_name" value="<?=e($item_name)?>" required placeholder="例：農薬A / 肥料B / つる下げ紐 など">
          <div class="hint help-text">※表記が揃うと「資材名トップ10」が正確になります</div>
        </div>
        <div class="form-row">
          <label class="form-label">金額（円）<span class="req">*</span></label>
          <input class="form-input form-control" type="number" name="cost_yen" min="1" step="1" value="<?=e($cost_yen)?>" required placeholder="例：3500">
          <div class="hint help-text">※税込でOK（運用を統一）</div>
        </div>
      </div>
    </div>

    <div class="card form-section">
      <h2 class="form-section-title card-title">任意入力</h2>
      <div class="form-grid">
        <div class="form-row">
          <label class="form-label">数量（任意）</label>
          <input class="form-input form-control" type="number" name="amount" step="0.1" value="<?=e($amount)?>" placeholder="例：2">
        </div>
        <div class="form-row">
          <label class="form-label">単位（任意）</label>
          <input class="form-input form-control" name="unit" value="<?=e($unit)?>" placeholder="例：袋 / 本 / L / kg など">
        </div>
      </div>
      <div class="hint help-text">※数量と単位は「分かるときだけ」でOK</div>
    </div>

    <div class="card form-section">
      <div class="form-row">
        <label class="form-label">メモ（任意）</label>
        <textarea class="form-input form-control" name="note" placeholder="例：〇〇用、△△で使用、残量など"><?=e($note)?></textarea>
      </div>
    </div>

    <div class="card form-section form-actions">
      <button class="btn primary btn-primary" type="submit">更新する</button>
    </div>

  </form>
</div>
</body>
</html>
