<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/ui.php';
$u = requireLogin();
$pdo = db();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
  http_response_code(404);
  echo '対象の日誌データが見つかりません。';
  exit;
}

$fields = $pdo->query("SELECT id,label FROM fields ORDER BY CAST(substr(label,1,instr(label,'-')-1) AS INTEGER), CAST(substr(label,instr(label,'-')+1) AS INTEGER), id")->fetchAll();
$crops  = $pdo->query("SELECT id,name FROM crops ORDER BY id")->fetchAll();

$workOptions = [
  '育苗','定植','潅水','整枝','誘引','収穫','農薬散布','耕作','追肥',
  '芽かき','葉かき','摘果','摘花','畝立て','マルチ張り',
  '受粉処理','抜根','圃場の片付け','その他'
];

$stmt = $pdo->prepare('SELECT * FROM diary_entries WHERE user_id = :uid AND id = :id');
$stmt->execute([':uid' => $u['id'], ':id' => $id]);
$row = $stmt->fetch();
if (!$row) {
  http_response_code(404);
  echo '対象の日誌データが見つからないか、編集権限がありません。';
  exit;
}

$err = '';
$date = (string)$row['date'];
$field_id = (int)$row['field_id'];
$plot = (string)($row['plot'] ?? '');
$crop_id = (int)$row['crop_id'];
$cropSelectionValue = (string)$crop_id;
$cropOtherValue = '';
$minutes = (int)$row['minutes'];
$weather = (string)($row['weather'] ?? '');
$temp_c = $row['temp_c'] === null ? '' : (string)$row['temp_c'];
$memo = (string)($row['memo'] ?? '');
$existingWorkContent = trim((string)($row['work_content'] ?? ''));
$workMainValues = [];
$workOther = '';
if ($existingWorkContent !== '') {
  $parts = array_values(array_filter(array_map(
    static fn($v): string => trim((string)$v),
    preg_split('/[、,]/u', $existingWorkContent) ?: []
  ), static fn($v): bool => $v !== ''));

  foreach ($parts as $part) {
    if (in_array($part, $workOptions, true) && $part !== 'その他') {
      $workMainValues[] = $part;
      continue;
    }
    if ($part !== '') {
      $workOther = $part;
      $workMainValues[] = 'その他';
    }
  }
}
$workMainValues = array_values(array_unique($workMainValues));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    echo '不正なリクエストです（CSRFトークンが無効です）。';
    exit;
  }

  $date = (string)($_POST['date'] ?? '');
  $field_id = (int)($_POST['field_id'] ?? 0);
  $plot = trim((string)($_POST['plot'] ?? ''));
  $crop_id = (int)($_POST['crop_id'] ?? 0);
  $cropSelectionValue = trim((string)($_POST['crop_id'] ?? ''));
  $cropOtherValue = trim((string)($_POST['crop_other'] ?? ''));
  $crop_id = $cropSelectionValue === 'other'
    ? findOrCreateCropId($pdo, $cropOtherValue)
    : (int)$cropSelectionValue;
  $workMainPost = $_POST['work_main'] ?? [];
  if (!is_array($workMainPost)) {
    $workMainPost = [$workMainPost];
  }
  $workMainValues = array_values(array_filter(array_map(
    static fn($v): string => trim((string)$v),
    $workMainPost
  ), static fn($v): bool => $v !== ''));
  $workMainValues = array_values(array_unique($workMainValues));
  $workOther = trim((string)($_POST['work_other'] ?? ''));
  $minutes = (int)($_POST['minutes'] ?? 0);
  $weather = trim((string)($_POST['weather'] ?? ''));
  $temp_c = ($_POST['temp_c'] ?? '') === '' ? '' : (string)(float)$_POST['temp_c'];
  $memo = trim((string)($_POST['memo'] ?? ''));

  $workContents = [];
  foreach ($workMainValues as $work) {
    if ($work === 'その他') {
      if ($workOther !== '') {
        $workContents[] = $workOther;
      }
      continue;
    }
    $workContents[] = $work;
  }
  $workContent = implode('、', array_values(array_unique($workContents)));

  if (!$date || !$field_id || !$crop_id || $workContent === '' || $minutes <= 0) {
    $err = '必須項目を入力してください（作業時間は1分以上）';
  } else {
    $up = $pdo->prepare('UPDATE diary_entries SET date=:date, field_id=:field_id, plot=:plot, crop_id=:crop_id, work_content=:work_content, minutes=:minutes, weather=:weather, temp_c=:temp_c, memo=:memo WHERE id=:id AND user_id=:uid');
    $up->execute([
      ':date' => $date,
      ':field_id' => $field_id,
      ':plot' => $plot !== '' ? $plot : null,
      ':crop_id' => $crop_id,
      ':work_content' => $workContent,
      ':minutes' => $minutes,
      ':weather' => $weather !== '' ? $weather : null,
      ':temp_c' => $temp_c === '' ? null : (float)$temp_c,
      ':memo' => $memo !== '' ? $memo : null,
      ':id' => $id,
      ':uid' => $u['id'],
    ]);

    setFlash('success', '更新しました');
    header('Location: diary_list.php');
    exit;
  }
}

$csrf = csrfToken();
?>
<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <title>日誌編集</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="app.css">
  <script defer src="app.js"></script>
  <style>
    .work-options{
      display:grid;
      grid-template-columns:repeat(auto-fit,minmax(150px,1fr));
      gap:10px;
      margin-top:10px;
    }
    .work-option{
      display:flex;
      align-items:center;
      gap:10px;
      padding:12px 14px;
      border:1px solid #d1d5db;
      border-radius:10px;
      cursor:pointer;
      background:#f8fafc;
      transition:all .15s ease;
    }
    .work-option:hover{
      border-color:#22c55e;
      background:#f0fdf4;
      box-shadow:0 3px 10px rgba(34,197,94,.12);
    }
    .work-option input[type="checkbox"]{
      width:20px;
      height:20px;
      accent-color:#16a34a;
      flex:0 0 auto;
    }
    .work-option-text{
      font-size:18px;
      font-weight:700;
      line-height:1.3;
      color:#0f172a;
    }
  </style>
  <script>
    function toggleOther(){
      const checks = document.querySelectorAll('input[name="work_main[]"]');
      const box = document.getElementById('work_other_box');
      if (!checks.length || !box) return;
      const hasOther = Array.from(checks).some((check) => check.checked && check.value === 'その他');
      box.style.display = hasOther ? 'block' : 'none';
    }
    function toggleCropOther(){
      const sel = document.querySelector('select[name="crop_id"]');
      const box = document.getElementById('crop_other_box');
      if (!sel || !box) return;
      box.style.display = sel.value === 'other' ? 'block' : 'none';
    }
    window.addEventListener('DOMContentLoaded', () => {
      toggleOther();
      toggleCropOther();
    });
  </script>
</head>
<body>
<?php renderGlobalTopbar($u); ?>

<div class="topbar">
  <div class="topbar-inner">
    <div class="title">日誌編集</div>
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
    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
    <div class="card">
      <div class="grid">
        <div>
          <label>日付<span class="req">*</span></label>
          <input type="date" name="date" value="<?=e($date)?>" required>
        </div>

        <div>
          <label>圃場（ハウス）<span class="req">*</span></label>
          <select name="field_id" required>
            <option value="">選択</option>
            <?php foreach ($fields as $f): ?>
              <option value="<?= (int)$f['id'] ?>" <?= $field_id === (int)$f['id'] ? 'selected' : '' ?>><?= e($f['label']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div>
          <label>区画（任意）</label>
          <input name="plot" value="<?=e($plot)?>" placeholder="例：区画1">
          <div class="hint">※自由入力（表記は揃えるのがおすすめ）</div>
        </div>

        <div>
          <label>品目<span class="req">*</span></label>
          <select name="crop_id" required onchange="toggleCropOther()">
            <option value="">選択</option>
            <?php foreach ($crops as $c): ?>
              <option value="<?= (int)$c['id'] ?>" <?= ($cropSelectionValue !== 'other' && $crop_id === (int)$c['id']) ? 'selected' : '' ?>><?= e($c['name']) ?></option>
            <?php endforeach; ?>
            <option value="other" <?= $cropSelectionValue === 'other' ? 'selected' : '' ?>>その他（自由入力）</option>
          </select>
          <div id="crop_other_box" style="display:none;margin-top:10px">
            <input name="crop_other" value="<?=e($cropOtherValue)?>" placeholder="品目名を入力">
          </div>
        </div>
      </div>
    </div>

    <div class="card">
      <label>作業内容<span class="req">*</span></label>
      <div id="work_main" class="work-options">
        <?php foreach ($workOptions as $w): ?>
          <label class="work-option">
            <input
              type="checkbox"
              name="work_main[]"
              value="<?= e($w) ?>"
              <?= in_array($w, $workMainValues, true) ? 'checked' : '' ?>
              onchange="toggleOther()"
            >
            <span class="work-option-text"><?= e($w) ?></span>
          </label>
        <?php endforeach; ?>
      </div>
      <div class="hint">※複数選択できます（タップ・クリックでチェック）</div>

      <div id="work_other_box" style="display:none;margin-top:10px">
        <label>作業内容（自由入力）</label>
        <input name="work_other" value="<?=e($workOther)?>" placeholder="例：支柱補修など">
      </div>
    </div>

    <div class="card">
      <div class="grid">
        <div>
          <label>作業時間（分）<span class="req">*</span></label>
          <input type="number" name="minutes" min="1" value="<?= (int)$minutes ?>" placeholder="例：45" required>
        </div>
        <div>
          <label>天気（任意）</label>
          <input name="weather" value="<?=e($weather)?>" placeholder="晴れ / 曇り / 雨">
        </div>
        <div>
          <label>気温（℃・任意）</label>
          <input type="number" name="temp_c" step="0.1" value="<?=e($temp_c)?>" placeholder="例：23.5">
        </div>
      </div>
    </div>

    <div class="card">
      <label>メモ（任意）</label>
      <textarea name="memo" placeholder="病害虫の兆候・気づきなど"><?=e($memo)?></textarea>
    </div>

    <div class="card">
      <button class="btn primary" style="width:100%;font-size:18px;padding:14px">更新する</button>
    </div>
  </form>
</div>
</body>
</html>
