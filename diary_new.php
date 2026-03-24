<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/ui.php';

$u = requireLogin();
$pdo = db();

$fields = $pdo->query("SELECT id,label FROM fields ORDER BY label")->fetchAll();
$fieldLabelOrder = ['1-1','1-2','1-3','1-4','1-5','1-6','1-7','1-8','1-9','1-10','2-1','2-2','2-3','2-4','2-5','2-6','2-7','2-8','2-9','2-10','3-1','3-2','3-3','3-4','3-5'];
foreach ($fields as $idx => &$field) {
  $field['display_label'] = $fieldLabelOrder[$idx] ?? (string)$field['label'];
}
unset($field);

$crops = $pdo->query("SELECT id,name FROM crops ORDER BY id")->fetchAll();

$workOptions = [
  '育苗','定植','潅水','整枝','誘引','収穫','農薬散布','耕作','追肥',
  '芽かき','葉かき','摘果','摘花','畝立て','マルチ張り',
  '受粉処理','抜根','圃場の片付け','その他'
];

$savedPlotNames = [];
$savePlotNameStmt = null;

$hasUserFieldNames = (bool)$pdo->query(
  "SELECT 1 FROM sqlite_master WHERE type='table' AND name='user_field_names'"
)->fetchColumn();

if ($hasUserFieldNames) {
  $plotNameOptions = $pdo->prepare(
    "SELECT name FROM user_field_names WHERE user_id = :uid ORDER BY name"
  );
  $plotNameOptions->execute([':uid' => $u['id']]);
  $savedPlotNames = $plotNameOptions->fetchAll(PDO::FETCH_COLUMN);

  $savePlotNameStmt = $pdo->prepare(
    "INSERT OR IGNORE INTO user_field_names (user_id,name,created_at)
     VALUES (:uid,:name,:created_at)"
  );
}

$err = '';
$dateValue = date('Y-m-d');
$plotValue = '';
$fieldIdValue = 0;
$cropIdValue = 0;
$minutesValue = '';
$weatherValue = '';
$tempValue = '';
$memoValue = '';
$workMainValues = [];
$workOtherValue = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $date = $_POST['date'] ?? '';
  $dateValue = $date !== '' ? $date : $dateValue;
  $field_id = (int)($_POST['field_id'] ?? 0);
  $fieldIdValue = $field_id;
  $plot = trim((string)($_POST['plot'] ?? ''));
  $plotValue = $plot;
  $crop_id = (int)($_POST['crop_id'] ?? 0);
  $cropIdValue = $crop_id;

  $workMainPost = $_POST['work_main'] ?? [];
  if (!is_array($workMainPost)) {
    $workMainPost = [$workMainPost];
  }
  $workMainValues = array_values(array_filter(array_map(
    static fn($v): string => trim((string)$v),
    $workMainPost
  ), static fn($v): bool => $v !== ''));
  $workMainValues = array_values(array_unique($workMainValues));
  $work_other = trim((string)($_POST['work_other'] ?? ''));
  $workOtherValue = $work_other;

  $minutes = (int)($_POST['minutes'] ?? 0);
  $minutesValue = (string)($_POST['minutes'] ?? '');
  $weather = trim((string)($_POST['weather'] ?? ''));
  $weatherValue = $weather;
  $tempValue = (string)($_POST['temp_c'] ?? '');
  $temp_c = $tempValue === '' ? null : (float)$tempValue;
  $memo = trim((string)($_POST['memo'] ?? ''));
  $memoValue = $memo;

  $workContents = [];
  foreach ($workMainValues as $work) {
    if ($work === 'その他') {
      if ($work_other !== '') {
        $workContents[] = $work_other;
      }
      continue;
    }
    $workContents[] = $work;
  }
  $work_content = implode('、', array_values(array_unique($workContents)));

  if (!$date || !$field_id || !$crop_id || $work_content === '' || $minutes <= 0) {
    $err = '必須項目を入力してください（作業時間は1分以上）';
  } else {
    $stmt = $pdo->prepare(
      "INSERT INTO diary_entries (user_id,date,field_id,plot,crop_id,work_content,minutes,weather,temp_c,memo,created_at)
       VALUES (:user_id,:date,:field_id,:plot,:crop_id,:work_content,:minutes,:weather,:temp_c,:memo,:created_at)"
    );

    $stmt->execute([
      ':user_id' => $u['id'],
      ':date' => $date,
      ':field_id' => $field_id,
      ':plot' => ($plot !== '' ? $plot : null),
      ':crop_id' => $crop_id,
      ':work_content' => $work_content,
      ':minutes' => $minutes,
      ':weather' => ($weather !== '' ? $weather : null),
      ':temp_c' => $temp_c,
      ':memo' => ($memo !== '' ? $memo : null),
      ':created_at' => date('c'),
    ]);

    if ($plot !== '' && $savePlotNameStmt !== null) {
      $savePlotNameStmt->execute([
        ':uid' => $u['id'],
        ':name' => $plot,
        ':created_at' => date('c'),
      ]);
      $savedPlotNames[] = $plot;
      $savedPlotNames = array_values(array_unique($savedPlotNames));
      sort($savedPlotNames, SORT_STRING);
    }

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
    function formatWeekdayJa(dateStr){
      if (!dateStr) return '';
      const d = new Date(dateStr + 'T00:00:00');
      if (Number.isNaN(d.getTime())) return '';
      return d.toLocaleDateString('ja-JP', { weekday: 'long' });
    }

    function updateWeekday(){
      const input = document.getElementById('date');
      const label = document.getElementById('date_weekday');
      if (!input || !label) return;
      const weekday = formatWeekdayJa(input.value);
      label.textContent = weekday ? `（${weekday}）` : '';
    }

    function toggleOther(){
      const checks = document.querySelectorAll('input[name="work_main[]"]');
      const box = document.getElementById('work_other_box');
      if (!checks.length || !box) return;
      const hasOther = Array.from(checks).some((check) => check.checked && check.value === 'その他');
      box.style.display = hasOther ? 'block' : 'none';
    }

    window.addEventListener('DOMContentLoaded', () => {
      const dateInput = document.getElementById('date');
      if (dateInput && !dateInput.value) {
        dateInput.value = new Date().toISOString().slice(0, 10);
      }
      toggleOther();
      updateWeekday();
      dateInput?.addEventListener('input', updateWeekday);
      dateInput?.addEventListener('change', updateWeekday);
    });
  </script>
</head>
<body>
<?php renderGlobalTopbar($u); ?>

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
      <?= e($err) ?>
    </div>
  <?php endif; ?>

  <form method="post">
    <div class="card">
      <div class="grid">
        <div>
          <label>日付<span class="req">*</span> <span id="date_weekday" class="hint"></span></label>
          <input id="date" type="date" name="date" value="<?= e($dateValue) ?>" required>
        </div>

        <div>
          <label>圃場（ハウス）<span class="req">*</span></label>
          <select name="field_id" required>
            <option value="">選択</option>
            <?php foreach ($fields as $f): ?>
              <option value="<?= (int)$f['id'] ?>" <?= ((int)$f['id'] === $fieldIdValue) ? 'selected' : '' ?>><?= e((string)$f['display_label']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div>
          <label>区画（任意）</label>
          <input name="plot" list="saved_plot_names" value="<?= e($plotValue) ?>" placeholder="例：区画1">
          <div class="hint">※自由入力（過去に使った圃場名は候補表示されます）</div>
          <datalist id="saved_plot_names">
            <?php foreach ($savedPlotNames as $name): ?>
              <option value="<?= e((string)$name) ?>"></option>
            <?php endforeach; ?>
          </datalist>
        </div>

        <div>
          <label>品目<span class="req">*</span></label>
          <select name="crop_id" required>
            <option value="">選択</option>
            <?php foreach ($crops as $c): ?>
              <option value="<?= (int)$c['id'] ?>" <?= ((int)$c['id'] === $cropIdValue) ? 'selected' : '' ?>><?= e($c['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
    </div>

    <div class="card">
      <label>作業内容<span class="req">*</span></label>
      <div id="work_main" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:8px">
        <?php foreach ($workOptions as $w): ?>
          <label style="display:flex;align-items:center;gap:6px;padding:8px;border:1px solid #ddd;border-radius:8px;cursor:pointer">
            <input
              type="checkbox"
              name="work_main[]"
              value="<?= e($w) ?>"
              <?= in_array($w, $workMainValues, true) ? 'checked' : '' ?>
              onchange="toggleOther()"
            >
            <span><?= e($w) ?></span>
          </label>
        <?php endforeach; ?>
      </div>
      <div class="hint">※複数選択できます（タップ・クリックでチェック）</div>

      <div id="work_other_box" style="display:none;margin-top:10px">
        <label>作業内容（自由入力）</label>
        <input name="work_other" value="<?= e($workOtherValue) ?>" placeholder="例：支柱補修など">
      </div>
    </div>

    <div class="card">
      <div class="grid">
        <div>
          <label>作業時間（分）<span class="req">*</span></label>
          <input type="number" name="minutes" min="1" value="<?= e($minutesValue) ?>" placeholder="例：45" required>
        </div>
        <div>
          <label>天気（任意）</label>
          <input name="weather" value="<?= e($weatherValue) ?>" placeholder="晴れ / 曇り / 雨">
        </div>
        <div>
          <label>気温（℃・任意）</label>
          <input type="number" name="temp_c" step="0.1" value="<?= e($tempValue) ?>" placeholder="例：23.5">
        </div>
      </div>
    </div>

    <div class="card">
      <label>メモ（任意）</label>
      <textarea name="memo" placeholder="病害虫の兆候・気づきなど"><?= e($memoValue) ?></textarea>
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
