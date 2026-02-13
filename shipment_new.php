<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
$u = requireLogin();
$pdo = db();

$fields = $pdo->query("SELECT id,label FROM fields ORDER BY label")->fetchAll();
$crops  = $pdo->query("SELECT id,name FROM crops ORDER BY id")->fetchAll();

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $date     = $_POST['date'] ?? '';
  $field_id = (int)($_POST['field_id'] ?? 0);
  $plot     = trim((string)($_POST['plot'] ?? ''));
  $crop_id  = (int)($_POST['crop_id'] ?? 0);

  $quantity = (float)($_POST['quantity'] ?? 0);
  $unit     = (string)($_POST['unit'] ?? '');
  $note     = trim((string)($_POST['note'] ?? ''));

  if (!$date || !$field_id || !$crop_id || $quantity <= 0 || !in_array($unit, ['box','kg'], true)) {
    $err = '必須項目を入力してください（数量は0より大きく、単位は箱/kg）';
  } else {
    $st = $pdo->prepare("
      INSERT INTO shipments (user_id,date,field_id,plot,crop_id,quantity,unit,note,created_at)
      VALUES (:user_id,:date,:field_id,:plot,:crop_id,:quantity,:unit,:note,:created_at)
    ");
    $st->execute([
      ':user_id'=>$u['id'],
      ':date'=>$date,
      ':field_id'=>$field_id,
      ':plot'=>($plot!==''?$plot:null),
      ':crop_id'=>$crop_id,
      ':quantity'=>$quantity,
      ':unit'=>$unit,
      ':note'=>($note!==''?$note:null),
      ':created_at'=>date('c'),
    ]);
header('Location: shipment_list.php?toast=' . rawurlencode('保存しました'));
exit;

  }
}
?>
<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <title>出荷入力</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="app.css">
<script defer src="app.js"></script>

</head>
<body>

<div class="topbar">
  <div class="topbar-inner">
    <div class="title">出荷入力</div>
    <div class="actions">
      <a class="btn" href="shipment_list.php">一覧</a>
      <a class="btn ghost" href="index.php">ホーム</a>
    </div>
  </div>
</div>

<div class="container form-page">

  <h1 class="form-page-title">出荷入力フォーム</h1>

  <?php if ($err): ?>
    <div class="card form-section error-message">
      <?=e($err)?>
    </div>
  <?php endif; ?>

  <form method="post">
    <div class="card form-section">
      <h2 class="form-section-title">基本情報</h2>
      <div class="form-grid">
        <div class="form-row">
          <label class="form-label">日付<span class="req">*</span></label>
          <input class="form-input" type="date" name="date" value="<?=e(date('Y-m-d'))?>" required>
        </div>

        <div class="form-row">
          <label class="form-label">圃場（ハウス）<span class="req">*</span></label>
          <select class="form-input" name="field_id" required>
            <option value="">選択</option>
            <?php foreach ($fields as $f): ?>
              <option value="<?= (int)$f['id'] ?>"><?= e($f['label']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-row">
          <label class="form-label">区画（任意）</label>
          <input class="form-input" name="plot" placeholder="例：区画1">
          <div class="hint">※表記を揃えると集計が強くなります</div>
        </div>

        <div class="form-row">
          <label class="form-label">品目<span class="req">*</span></label>
          <select class="form-input" name="crop_id" required>
            <option value="">選択</option>
            <?php foreach ($crops as $c): ?>
              <option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
    </div>

    <div class="card form-section">
      <h2 class="form-section-title">出荷内容</h2>
      <div class="form-grid">
        <div class="form-row">
          <label class="form-label">数量<span class="req">*</span></label>
          <input class="form-input" type="number" name="quantity" step="0.1" min="0.1" required placeholder="例：10">
          <div class="hint">※箱の場合は整数でもOK（10箱→10）</div>
        </div>

        <div class="form-row">
          <label class="form-label">単位<span class="req">*</span></label>
          <select class="form-input" name="unit" required>
            <option value="">選択</option>
            <option value="box">箱</option>
            <option value="kg">kg</option>
          </select>
          <div class="hint">※箱 / kg を必ず選択</div>
        </div>
      </div>
    </div>

    <div class="card form-section">
      <h2 class="form-section-title">補足情報</h2>
      <div class="form-row">
        <label class="form-label">メモ（任意）</label>
        <textarea class="form-input" name="note" placeholder="例：等級・出荷先・備考など"></textarea>
      </div>
    </div>

    <div class="card form-section form-actions">
      <button class="btn primary btn-primary" type="submit">
        保存する
      </button>
    </div>
  </form>

</div>
</body>
</html>
