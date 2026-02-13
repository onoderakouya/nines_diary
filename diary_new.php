<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
$u = requireLogin();
$pdo = db();

$fields = $pdo->query("SELECT id,label FROM fields ORDER BY label")->fetchAll();
$crops  = $pdo->query("SELECT id,name FROM crops ORDER BY id")->fetchAll();

$workOptions = [
  '育苗','定植','潅水','整枝','誘引','収穫','農薬散布','耕作','追肥',
  '芽かき','葉かき','摘果','摘花','畝立て','マルチ張り',
  '受粉処理','抜根','圃場の片付け','その他'
];

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $date      = $_POST['date'] ?? '';
  $field_id = (int)($_POST['field_id'] ?? 0);
  $plot     = trim((string)($_POST['plot'] ?? ''));
  $crop_id  = (int)($_POST['crop_id'] ?? 0);

  $work_main  = trim((string)($_POST['work_main'] ?? ''));
  $work_other = trim((string)($_POST['work_other'] ?? ''));

  $minutes = (int)($_POST['minutes'] ?? 0);
  $weather = trim((string)($_POST['weather'] ?? ''));
  $temp_c  = $_POST['temp_c'] === '' ? null : (float)$_POST['temp_c'];
  $memo    = trim((string)($_POST['memo'] ?? ''));

  $work_content = ($work_main === 'その他') ? $work_other : $work_main;

  if (!$date || !$field_id || !$crop_id || $work_content === '' || $minutes <= 0) {
    $err = '必須項目を入力してください（作業時間は1分以上）';
  } else {
    $stmt = $pdo->prepare("
      INSERT INTO diary_entries
      (user_id,date,field_id,plot,crop_id,work_content,minutes,weather,temp_c,memo,created_at)
      VALUES
      (:user_id,:date,:field_id,:plot,:crop_id,:work_content,:minutes,:weather,:temp_c,:memo,:created_at)
    ");
    $stmt->execute([
      ':user_id'=>$u['id'],
      ':date'=>$date,
      ':field_id'=>$field_id,
      ':plot'=>($plot!==''?$plot:null),
      ':crop_id'=>$crop_id,
      ':work_content'=>$work_content,
      ':minutes'=>$minutes,
      ':weather'=>$weather?:null,
      ':temp_c'=>$temp_c,
      ':memo'=>$memo?:null,
      ':created_at'=>date('c'),
    ]);
header('Location: diary_list.php?toast=' . rawurlencode('保存しました'));
exit;

  }
}
?>
<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <title>日誌入力</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="app.css">
<script defer src="app.js"></script>

  <script>
    function toggleOther(){
      const sel = document.getElementById('work_main');
      const box = document.getElementById('work_other_box');
      box.style.display = (sel.value === 'その他') ? 'block' : 'none';
    }
    window.addEventListener('DOMContentLoaded', toggleOther);
  </script>
</head>
<body>

<div class="topbar">
  <div class="topbar-inner">
    <div class="title">日誌入力</div>
    <div class="actions">
      <a class="btn" href="diary_list.php">一覧</a>
      <a class="btn ghost" href="index.php">ホーム</a>
    </div>
  </div>
</div>

<div class="container">

  <?php if ($err): ?>
    <div class="card" style="border-color:#dc2626;color:#dc2626">
      <?=e($err)?>
    </div>
  <?php endif; ?>

  <form method="post">
    <div class="card">
      <div class="grid">
        <div>
          <label>日付<span class="req">*</span></label>
          <input type="date" name="date" value="<?=e(date('Y-m-d'))?>" required>
        </div>

        <div>
          <label>圃場（ハウス）<span class="req">*</span></label>
          <select name="field_id" required>
            <option value="">選択</option>
            <?php foreach ($fields as $f): ?>
              <option value="<?= (int)$f['id'] ?>"><?= e($f['label']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div>
          <label>区画（任意）</label>
          <input name="plot" placeholder="例：区画1">
          <div class="hint">※自由入力（表記は揃えるのがおすすめ）</div>
        </div>

        <div>
          <label>品目<span class="req">*</span></label>
          <select name="crop_id" required>
            <option value="">選択</option>
            <?php foreach ($crops as $c): ?>
              <option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
    </div>

    <div class="card">
      <label>作業内容<span class="req">*</span></label>
      <select id="work_main" name="work_main" required onchange="toggleOther()">
        <option value="">選択</option>
        <?php foreach ($workOptions as $w): ?>
          <option value="<?=e($w)?>"><?=e($w)?></option>
        <?php endforeach; ?>
      </select>

      <div id="work_other_box" style="display:none;margin-top:10px">
        <label>作業内容（自由入力）</label>
        <input name="work_other" placeholder="例：支柱補修など">
      </div>
    </div>

    <div class="card">
      <div class="grid">
        <div>
          <label>作業時間（分）<span class="req">*</span></label>
          <input type="number" name="minutes" min="1" placeholder="例：45" required>
        </div>
        <div>
          <label>天気（任意）</label>
          <input name="weather" placeholder="晴れ / 曇り / 雨">
        </div>
        <div>
          <label>気温（℃・任意）</label>
          <input type="number" name="temp_c" step="0.1" placeholder="例：23.5">
        </div>
      </div>
    </div>

    <div class="card">
      <label>メモ（任意）</label>
      <textarea name="memo" placeholder="病害虫の兆候・気づきなど"></textarea>
    </div>

    <div class="card">
      <button class="btn primary" style="width:100%;font-size:18px;padding:14px">
        保存する
      </button>
    </div>
  </form>

</div>
</body>
</html>
