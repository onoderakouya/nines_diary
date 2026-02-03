<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/weather.php';

$u = requireLogin();
$pdo = db();

$fields = $pdo->query("SELECT id,label FROM fields ORDER BY label")->fetchAll();
$crops  = $pdo->query("SELECT id,name FROM crops ORDER BY id")->fetchAll();
$tasks  = $pdo->query("SELECT id,name FROM tasks ORDER BY id")->fetchAll();

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $date = $_POST['date'] ?? '';
  $field_id = (int)($_POST['field_id'] ?? 0);
  $crop_id  = (int)($_POST['crop_id'] ?? 0);
  $task_id  = (int)($_POST['task_id'] ?? 0);
  $minutes  = (int)($_POST['minutes'] ?? 0);
  $memo     = trim((string)($_POST['memo'] ?? ''));

  if (!$date || !$field_id || !$crop_id || !$task_id || $minutes <= 0) {
    $err = '必須項目を入力してください（作業時間は1分以上）';
  } else {
    $w = fetchWeather($date);
    $stmt = $pdo->prepare("INSERT INTO diary_entries
      (user_id,date,field_id,crop_id,task_id,minutes,memo,weather_code,temp_c,created_at)
      VALUES (:user_id,:date,:field_id,:crop_id,:task_id,:minutes,:memo,:weather_code,:temp_c,:created_at)
    ");
    $stmt->execute([
      ':user_id'=>$u['id'],
      ':date'=>$date,
      ':field_id'=>$field_id,
      ':crop_id'=>$crop_id,
      ':task_id'=>$task_id,
      ':minutes'=>$minutes,
      ':memo'=>$memo ?: null,
      ':weather_code'=>$w['weather_code'],
      ':temp_c'=>$w['temp_c'],
      ':created_at'=>date('c')
    ]);
    $diaryId = (int)$pdo->lastInsertId();

    // 写真（任意）
    if (!empty($_FILES['photos']['name'][0])) {
      require_once __DIR__ . '/upload.php';
      $paths = handleMultiUpload('photos');
      $ins = $pdo->prepare("INSERT INTO diary_photos(diary_id,path,created_at) VALUES(:diary_id,:path,:created_at)");
      foreach ($paths as $p) $ins->execute([':diary_id'=>$diaryId, ':path'=>$p, ':created_at'=>date('c')]);
    }

    header('Location: diary_list.php');
    exit;
  }
}
?>
<!doctype html><meta charset="utf-8">
<title>日誌追加</title>
<h1>日誌追加</h1>
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

  <label>作業内容*<br>
    <select name="task_id" required>
      <option value="">選択</option>
      <?php foreach ($tasks as $t): ?>
        <option value="<?= (int)$t['id'] ?>"><?= e($t['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </label><br><br>

  <label>作業時間（分）*<br>
    <input type="number" name="minutes" min="1" step="1" required>
  </label><br><br>

  <label>メモ<br>
    <textarea name="memo" rows="4" cols="40"></textarea>
  </label><br><br>

  <label>写真（任意・複数可）<br>
    <input type="file" name="photos[]" multiple accept="image/*">
  </label><br><br>

  <button>保存</button>
</form>
