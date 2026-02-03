<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
$u = requireLogin();
$pdo = db();

$fields = $pdo->query("SELECT id,label FROM fields ORDER BY label")->fetchAll();
$crops  = $pdo->query("SELECT id,name FROM crops ORDER BY id")->fetchAll();

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $date = $_POST['date'] ?? '';
  $field_id = (int)($_POST['field_id'] ?? 0);
  $crop_id  = (int)($_POST['crop_id'] ?? 0);

  $item_name = trim((string)($_POST['item_name'] ?? ''));
  $amount = $_POST['amount'] === '' ? null : (float)$_POST['amount'];
  $unit   = trim((string)($_POST['unit'] ?? ''));
  $cost_yen = (int)($_POST['cost_yen'] ?? 0);
  $note = trim((string)($_POST['note'] ?? ''));

  // 圃場 or 品目 どっちか一方は必須（両方でもOK）
  if (!$date || $item_name==='' || $cost_yen<=0 || ($field_id===0 && $crop_id===0)) {
    $err = '必須項目を入力してください（圃場か品目のどちらかは必須、金額は1円以上）';
  } else {
    $stmt = $pdo->prepare("INSERT INTO materials
      (user_id,date,field_id,crop_id,item_name,amount,unit,cost_yen,note,created_at)
      VALUES (:user_id,:date,:field_id,:crop_id,:item_name,:amount,:unit,:cost_yen,:note,:created_at)
    ");
    $stmt->execute([
      ':user_id'=>$u['id'],
      ':date'=>$date,
      ':field_id'=>$field_id ?: null,
      ':crop_id'=>$crop_id ?: null,
      ':item_name'=>$item_name,
      ':amount'=>$amount,
      ':unit'=>$unit ?: null,
      ':cost_yen'=>$cost_yen,
      ':note'=>$note ?: null,
      ':created_at'=>date('c'),
    ]);
    header('Location: material_list.php');
    exit;
  }
}
?>
<!doctype html><meta charset="utf-8">
<title>資材費 追加</title>
<h1>資材費 追加</h1>
<p><a href="index.php">←ホーム</a></p>
<?php if ($err): ?><p style="color:red"><?=e($err)?></p><?php endif; ?>

<form method="post">
  <label>日付*<br>
    <input type="date" name="date" value="<?=e(date('Y-m-d'))?>" required>
  </label><br><br>

  <label>圃場（任意）<br>
    <select name="field_id">
      <option value="0">選択しない</option>
      <?php foreach ($fields as $f): ?>
        <option value="<?= (int)$f['id'] ?>"><?= e($f['label']) ?></option>
      <?php endforeach; ?>
    </select>
  </label><br><br>

  <label>品目（任意）<br>
    <select name="crop_id">
      <option value="0">選択しない</option>
      <?php foreach ($crops as $c): ?>
        <option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <p style="margin:6px 0;color:#555">※圃場か品目のどちらかは必須（両方でもOK）</p>

  <label>資材名*<br>
    <input name="item_name" required style="width:320px">
  </label><br><br>

  <label>量（任意）<br>
    <input type="number" name="amount" step="0.01" min="0">
  </label><br><br>

  <label>単位（任意）<br>
    <input name="unit" placeholder="kg/L/袋 など">
  </label><br><br>

  <label>金額（円）*<br>
    <input type="number" name="cost_yen" min="1" step="1" required>
  </label><br><br>

  <label>メモ（任意）<br>
    <input name="note" style="width:320px">
  </label><br><br>

  <button>保存</button>
</form>
