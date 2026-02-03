<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/upload.php';

$u = requireLogin();
$pdo = db();

$fields = $pdo->query("SELECT id,label FROM fields ORDER BY label")->fetchAll();
$crops  = $pdo->query("SELECT id,name FROM crops ORDER BY id")->fetchAll();

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $date = $_POST['date'] ?? '';
  $field_id = (int)($_POST['field_id'] ?? 0);
  $crop_id  = (int)($_POST['crop_id'] ?? 0);
  $symptom  = trim((string)($_POST['symptom_text'] ?? ''));
  $action   = trim((string)($_POST['action_text'] ?? ''));

  if (!$date || !$field_id || !$crop_id || $symptom==='') {
    $err = '必須項目を入力してください（症状は必須）';
  } else {
    $photoPath = null;
    if (!empty($_FILES['photo']['name'])) {
      $paths = handleMultiUpload('photo'); // 使い回し（単数でも配列）
      $photoPath = $paths[0] ?? null;
    }

    $stmt = $pdo->prepare("INSERT INTO pests
      (user_id,date,field_id,crop_id,symptom_text,action_text,photo_path,created_at)
      VALUES (:user_id,:date,:field_id,:crop_id,:symptom,:action,:photo,:created_at)
    ");
    $stmt->execute([
      ':user_id'=>$u['id'],
      ':date'=>$date,
      ':field_id'=>$field_id,
      ':crop_id'=>$crop_id,
      ':symptom'=>$symptom,
      ':action'=>$action ?: null,
      ':photo'=>$photoPath,
      ':created_at'=>date('c'),
    ]);
    header('Location: pest_list.php');
    exit;
  }
}
?>
<!doctype html><meta charset="utf-8">
<title>病害虫 追加</title>
<h1>病害虫 追加</h1>
<p><a href="index.php">←ホーム</a></p>
<?php if ($err): ?><p style="color:red"><?=e($err)?></p><?php endif; ?>

<form method="post" enctype="multipart/form-data">
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

  <label>症状（自由入力）*<br>
    <textarea name="symptom_text" rows="4" cols="40" required></textarea>
  </label><br><br>

  <label>対応（任意）<br>
    <textarea name="action_text" rows="3" cols="40"></textarea>
  </label><br><br>

  <label>写真（任意）<br>
    <input type="file" name="photo[]" accept="image/*">
  </label><br><br>

  <button>保存</button>
</form>
