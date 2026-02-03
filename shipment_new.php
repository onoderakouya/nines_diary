<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
$u = requireLogin();
$pdo = db();

$fields = $pdo->query("SELECT id,label FROM fields ORDER BY label")->fetchAll();
$crops  = $pdo->query("SELECT id,name FROM crops ORDER BY id")->fetchAll();

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $date = (string)($_POST['date'] ?? '');
  $field_id = (int)($_POST['field_id'] ?? 0);
  $plot = trim((string)($_POST['plot'] ?? '')); // 任意
  $crop_id = (int)($_POST['crop_id'] ?? 0);
  $qty = (float)($_POST['qty'] ?? 0);
  $unit = (string)($_POST['unit'] ?? '');
  $memo = trim((string)($_POST['memo'] ?? ''));

  // plot軽い正規化
  $plot = preg_replace('/\s+/u', ' ', str_replace('　', ' ', $plot));
  $plot = trim((string)$plot);

  if ($date === '' || !$field_id || !$crop_id || $qty <= 0 || !in_array($unit, ['box','kg'], true)) {
    $err = '必須項目を入力してください（数量は0より大きく、単位は箱/kg）。';
  } else {
    $st = $pdo->prepare("
      INSERT INTO shipments (user_id,date,field_id,plot,crop_id,qty,unit,memo,created_at)
      VALUES (:uid,:date,:field,:plot,:crop,:qty,:unit,:memo,:created)
    ");
    $st->execute([
      ':uid' => $u['id'],
      ':date' => $date,
      ':field' => $field_id,
      ':plot' => ($plot !== '' ? $plot : null),
      ':crop' => $crop_id,
      ':qty' => $qty,
      ':unit' => $unit,
      ':memo' => ($memo !== '' ? $memo : null),
      ':created' => date('c'),
    ]);

    header('Location: shipment_list.php');
    exit;
  }
}

function unitLabel(string $u): string {
  return $u === 'box' ? '箱' : 'kg';
}
?>
<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <title>出荷入力</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    label{display:block;margin:10px 0}
    input,select,textarea{max-width:420px;width:100%}
    .row{display:flex;gap:12px;flex-wrap:wrap}
    .row > div{flex:1;min-width:180px}
    .hint{color:#666;font-size:12px}
  </style>
</head>
<body>
  <h1>出荷入力</h1>
  <p><a href="index.php">←ホーム</a> / <a href="shipment_list.php">出荷一覧</a></p>

  <?php if ($err): ?><p style="color:red"><?=e($err)?></p><?php endif; ?>

  <form method="post">
    <label>日付*<br>
      <input type="date" name="date" value="<?=e((string)($_POST['date'] ?? date('Y-m-d')))?>" required>
    </label>

    <label>圃場*（ハウス）<br>
      <select name="field_id" required>
        <option value="">選択</option>
        <?php foreach ($fields as $f): ?>
          <option value="<?= (int)$f['id'] ?>" <?= ((int)($_POST['field_id'] ?? 0) === (int)$f['id'])?'selected':'' ?>>
            <?= e($f['label']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </label>

    <label>区画（任意）<br>
      <input name="plot" placeholder="例：区画1" value="<?=e((string)($_POST['plot'] ?? ''))?>">
      <div class="hint">※日誌と同じ表記に揃えると集計が綺麗になります</div>
    </label>

    <label>品目*<br>
      <select name="crop_id" required>
        <option value="">選択</option>
        <?php foreach ($crops as $c): ?>
          <option value="<?= (int)$c['id'] ?>" <?= ((int)($_POST['crop_id'] ?? 0) === (int)$c['id'])?'selected':'' ?>>
            <?= e($c['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </label>

    <div class="row">
      <div>
        <label>数量*<br>
          <input type="number" name="qty" min="0.1" step="0.1" required placeholder="例：12" value="<?=e((string)($_POST['qty'] ?? ''))?>">
        </label>
      </div>
      <div>
        <label>単位*<br>
          <select name="unit" required>
            <option value="">選択</option>
            <option value="box" <?= ((string)($_POST['unit'] ?? '') === 'box')?'selected':'' ?>>箱</option>
            <option value="kg"  <?= ((string)($_POST['unit'] ?? '') === 'kg')?'selected':'' ?>>kg</option>
          </select>
        </label>
      </div>
    </div>

    <label>メモ（任意）<br>
      <textarea name="memo" rows="3" placeholder="等級、出荷先、備考など"><?=e((string)($_POST['memo'] ?? ''))?></textarea>
    </label>

    <button>保存</button>
  </form>
</body>
</html>
