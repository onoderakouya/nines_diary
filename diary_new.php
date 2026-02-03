<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
$u = requireLogin();
$pdo = db();

$fields = $pdo->query("SELECT id,label FROM fields ORDER BY label")->fetchAll();
$crops  = $pdo->query("SELECT id,name FROM crops ORDER BY id")->fetchAll();
$tasks  = $pdo->query("SELECT id,name FROM tasks ORDER BY id")->fetchAll();

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $date = $_POST['date'] ?? '';
  $field_id = (int)($_POST['field_id'] ?? 0);
  $plot = trim((string)($_POST['plot'] ?? '')); // 区画（任意）
  $crop_id  = (int)($_POST['crop_id'] ?? 0);
  $task_id  = (int)($_POST['task_id'] ?? 0);

  $minutes = (int)($_POST['minutes'] ?? 0);

  // 既存実装に寄せる：weather_code を使ってるならここも合わせる
  // いったん自由入力の "weather" も残したい場合はDB側の列に合わせてください。
  $weather_code = trim((string)($_POST['weather_code'] ?? '')); // 任意
  $temp_c  = $_POST['temp_c'] === '' ? null : (float)$_POST['temp_c'];
  $memo    = trim((string)($_POST['memo'] ?? ''));

  if (!$date || !$field_id || !$crop_id || !$task_id || $minutes <= 0) {
    $err = '必須項目を入力してください（作業時間は1分以上、作業は必須）';
  } else {
    $stmt = $pdo->prepare("INSERT INTO diary_entries
      (user_id,date,field_id,plot,crop_id,task_id,minutes,weather_code,temp_c,memo,created_at)
      VALUES (:user_id,:date,:field_id,:plot,:crop_id,:task_id,:minutes,:weather_code,:temp_c,:memo,:created_at)
    ");
    $stmt->execute([
      ':user_id'=>$u['id'],
      ':date'=>$date,
      ':field_id'=>$field_id,
      ':plot'=>($plot !== '' ? $plot : null),
      ':crop_id'=>$crop_id,
      ':task_id'=>$task_id,
      ':minutes'=>$minutes,
      ':weather_code'=>($weather_code !== '' ? $weather_code : null),
      ':temp_c'=>$temp_c,
      ':memo'=>($memo !== '' ? $memo : null),
      ':created_at'=>date('c'),
    ]);

    header('Location: diary_list.php');
    exit;
  }
}
?>
<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <title>日誌追加</title>
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
  <h1>日誌追加</h1>
  <p><a href="index.php">←ホーム</a></p>
  <?php if ($err): ?><p style="color:red"><?=e($err)?></p><?php endif; ?>

  <form method="post">
    <label>日付*<br>
      <input type="date" name="date" value="<?=e(date('Y-m-d'))?>" required>
    </label>

    <label>圃場*（ハウス）<br>
      <select name="field_id" required>
        <option value="">選択</option>
        <?php foreach ($fields as $f): ?>
          <option value="<?= (int)$f['id'] ?>"><?= e($f['label']) ?></option>
        <?php endforeach; ?>
      </select>
      <div class="hint">※区画は自由入力（例：区画1）</div>
    </label>

    <label>区画（任意）<br>
      <input name="plot" placeholder="例：区画1 / 1 / 東側 など" value="<?=e($_POST['plot'] ?? '')?>">
      <div class="hint">※集計を揃えるなら「区画1, 区画2…」のように統一がおすすめ</div>
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

    <label>作業*（標準）<br>
      <select name="task_id" required>
        <option value="">選択</option>
        <?php foreach ($tasks as $t): ?>
          <option value="<?= (int)$t['id'] ?>" <?= ((int)($_POST['task_id'] ?? 0) === (int)$t['id'])?'selected':'' ?>>
            <?= e($t['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </label>

    <div class="row">
      <div>
        <label>作業時間（分）*<br>
          <input type="number" name="minutes" min="1" step="1" required
                 placeholder="例：45" value="<?=e((string)($_POST['minutes'] ?? ''))?>">
        </label>
      </div>
      <div>
        <label>天気コード（任意）<br>
          <input name="weather_code" placeholder="例：晴れ/曇り/雨" value="<?=e((string)($_POST['weather_code'] ?? ''))?>">
        </label>
      </div>
    </div>

    <label>気温(max)（℃）（任意）<br>
      <input type="number" name="temp_c" step="0.1" placeholder="例：23.5" value="<?=e((string)($_POST['temp_c'] ?? ''))?>">
    </label>

    <label>メモ（任意）<br>
      <textarea name="memo" rows="4" placeholder="病害虫の気配、気づき、資材の残量など"><?=e((string)($_POST['memo'] ?? ''))?></textarea>
    </label>

    <button>保存</button>
  </form>
</body>
</html>
