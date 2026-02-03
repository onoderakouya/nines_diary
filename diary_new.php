<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
$u = requireLogin();
$pdo = db();

$fields = $pdo->query("SELECT id,label FROM fields ORDER BY label")->fetchAll();
$crops  = $pdo->query("SELECT id,name FROM crops ORDER BY id")->fetchAll();

// 作業内容（標準リスト）
$workOptions = [
  '育苗','定植','潅水','整枝','誘引','収穫','農薬散布','耕作','追肥','芽かき','葉かき',
  '摘果','摘花','畝立て','マルチ張り','受粉処理','抜根','圃場の片付け','その他'
];

// かんたん天気：手入力 + 温度（自動化は後でAPI化）
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $date = $_POST['date'] ?? '';
  $field_id = (int)($_POST['field_id'] ?? 0);
  $crop_id  = (int)($_POST['crop_id'] ?? 0);

  $work_main = trim((string)($_POST['work_main'] ?? ''));
  $work_other = trim((string)($_POST['work_other'] ?? ''));

  $minutes = (int)($_POST['minutes'] ?? 0);
  $weather = trim((string)($_POST['weather'] ?? ''));
  $temp_c  = $_POST['temp_c'] === '' ? null : (float)$_POST['temp_c'];
  $memo    = trim((string)($_POST['memo'] ?? ''));

  // 作業内容の確定（その他なら自由入力必須）
  if ($work_main === 'その他') {
    $work_content = $work_other;
  } else {
    $work_content = $work_main;
  }

  if (!$date || !$field_id || !$crop_id || $work_main==='' || $minutes <= 0 || $work_content==='') {
    $err = '必須項目を入力してください（作業時間は1分以上、作業内容は必須）';
  } elseif (!in_array($work_main, $workOptions, true)) {
    $err = '作業内容が不正です。';
  } else {
    $stmt = $pdo->prepare("INSERT INTO diary_entries
      (user_id,date,field_id,crop_id,work_content,minutes,weather,temp_c,memo,created_at)
      VALUES (:user_id,:date,:field_id,:crop_id,:work_content,:minutes,:weather,:temp_c,:memo,:created_at)
    ");
    $stmt->execute([
      ':user_id'=>$u['id'],
      ':date'=>$date,
      ':field_id'=>$field_id,
      ':crop_id'=>$crop_id,
      ':work_content'=>$work_content,
      ':minutes'=>$minutes,
      ':weather'=>$weather ?: null,
      ':temp_c'=>$temp_c,
      ':memo'=>$memo ?: null,
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
  <script>
    function toggleOther(){
      const sel = document.getElementById('work_main');
      const box = document.getElementById('work_other_box');
      if(sel.value === 'その他'){
        box.style.display = 'block';
        document.getElementById('work_other').required = true;
      }else{
        box.style.display = 'none';
        document.getElementById('work_other').required = false;
      }
    }
    window.addEventListener('DOMContentLoaded', toggleOther);
  </script>
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
      <div class="hint">※区画は次のアップデートで追加できます（例：区画1）</div>
    </label>

    <label>品目*<br>
      <select name="crop_id" required>
        <option value="">選択</option>
        <?php foreach ($crops as $c): ?>
          <option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>

    <label>作業内容*（標準）<br>
      <select id="work_main" name="work_main" required onchange="toggleOther()">
        <option value="">選択</option>
        <?php foreach ($workOptions as $w): ?>
          <option value="<?=e($w)?>"><?=e($w)?></option>
        <?php endforeach; ?>
      </select>
    </label>

    <div id="work_other_box" style="display:none">
      <label>作業内容（その他の自由入力）*<br>
        <input id="work_other" name="work_other" placeholder="例：支柱の修繕、ハウス補修 など">
      </label>
    </div>

    <div class="row">
      <div>
        <label>作業時間（分）*<br>
          <input type="number" name="minutes" min="1" step="1" required placeholder="例：45">
        </label>
      </div>
      <div>
        <label>天気（任意）<br>
          <input name="weather" placeholder="例：晴れ/曇り/雨">
        </label>
      </div>
    </div>

    <label>気温（℃）（任意）<br>
      <input type="number" name="temp_c" step="0.1" placeholder="例：23.5">
      <div class="hint">※ここも後で自動入力できます（API連携）</div>
    </label>

    <label>メモ（任意）<br>
      <textarea name="memo" rows="4" placeholder="病害虫の気配、気づき、資材の残量など"></textarea>
    </label>

    <button>保存</button>
  </form>
</body>
</html>
