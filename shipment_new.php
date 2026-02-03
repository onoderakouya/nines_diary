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
  $quantity = (float)($_POST['quantity'] ?? 0);
  $unit     = $_POST['unit'] ?? '';
  $note     = trim((string)($_POST['note'] ?? ''));

  if (!$date || !$field_id || !$crop_id || $quantity <= 0 || !in_array($unit, ['box','kg'], true)) {
    $err = '必須項目を入力してください（数量は0より大きく）';
  } else {
    $stmt = $pdo->prepare("INSERT INTO shipments
      (user_id,date,field_id,crop_id,quantity,unit,note,created_at)
      VALUES (:user_id,:date,:field_id,:crop_id,:quantity,:unit,:note,:created_at)
    ");
    $stmt->execute([
      ':user_id'=>$u['id'],
      ':date'=>$date,
      ':field_id'=>$field_id,
      ':crop_id'=>$crop_id,
      ':quantity'=>$quantity,
      ':unit'=>$unit,
      ':note'=>$note ?: null,
      ':created_at'=>date('c'),
    ]);
    header('Location: shipment_list.php');
    exit;
  }
}
?>
<!doctype html><meta charset="utf-8">
<title>出荷実績 追加</title>
<h1>出荷実績 追加</h1>
<p><a href="index.php">←ホーム</a></p>
<?php if ($err): ?><p style="color:red"><?=e($err)?></p><?php endif; ?>

<form method="post">
  <label>日付*<br>
    <input type="date" name="date" value="<?=e(date('Y-m-d'))?>" required>
  </label><br><br>

  <label>圃場*<br>
    <select name="field_id" required>
      <option value="">選択</option>
      <?php foreach ($fields as $f): ?>
        <option value="<?= (int)$f['id'] ?>"><?= e($f['label']) ?></option>
      <?php endforeach; ?>
    </select>
  </label><br><br>

  <label>品目*<br>
    <select name="crop_id" required>
      <option value="">選択</option>
      <?php foreach ($crops as $c): ?>
        <option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </label><br><br>

  <label>数量*<br>
    <input type="number" name="quantity" step="0.01" min="0.01" required>
  </label><br><br>

  <label>単位*<br>
    <select name="unit" required>
      <option value="box">箱</option>
      <option value="kg">kg</option>
    </select>
  </label><br><br>

  <label>メモ<br>
    <input name="note" type="text" style="width:320px">
  </label><br><br>

  <button>保存</button>
</form>
