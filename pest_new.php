<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
$u = requireLogin();
$pdo = db();

$fields = $pdo->query("SELECT id,label FROM fields ORDER BY label")->fetchAll();
$crops  = $pdo->query("SELECT id,name FROM crops ORDER BY id")->fetchAll();

// タグ候補（必要なら増やせます）
$tagOptions = [
  'ハダニ','アブラムシ','うどんこ病','灰色かび','疫病','葉かび','その他'
];

$err = '';

function saveUploadedImage(string $key, string $uploadDir, string &$err): ?string {
  if (!isset($_FILES[$key]) || $_FILES[$key]['error'] === UPLOAD_ERR_NO_FILE) return null;
  if ($_FILES[$key]['error'] !== UPLOAD_ERR_OK) { $err = '写真のアップロードに失敗しました'; return null; }

  $tmp = $_FILES[$key]['tmp_name'];
  $name = (string)$_FILES[$key]['name'];
  $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
  if (!in_array($ext, ['jpg','jpeg','png','webp'], true)) { $err = '写真は jpg/png/webp のみ対応です'; return null; }

  if (!is_dir($uploadDir)) @mkdir($uploadDir, 0755, true);

  $fn = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
  $path = rtrim($uploadDir, '/').'/'.$fn;

  if (!move_uploaded_file($tmp, $path)) { $err = '写真の保存に失敗しました'; return null; }

  // 公開パス（このアプリ直下の uploads を想定）
  return 'uploads/' . $fn;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $date     = $_POST['date'] ?? '';
  $field_id = (int)($_POST['field_id'] ?? 0);
  $crop_id  = (int)($_POST['crop_id'] ?? 0);

  $tag      = trim((string)($_POST['symptom_tag'] ?? ''));
  $symptom  = trim((string)($_POST['symptom_text'] ?? ''));
  $action   = trim((string)($_POST['action_text'] ?? ''));

  // 写真（任意）
  $photo_path = null;
  $uploadDir = __DIR__ . '/uploads';
  $photo_path = saveUploadedImage('photo', $uploadDir, $err);

  if (!$date || !$field_id || !$crop_id || $symptom === '') {
    $err = $err ?: '必須項目を入力してください（症状は必須）';
  } else {
    // タグは任意（空でもOK）
    $st = $pdo->prepare("
      INSERT INTO pests (user_id,date,field_id,crop_id,symptom_tag,symptom_text,action_text,photo_path,created_at)
      VALUES (:user_id,:date,:field_id,:crop_id,:tag,:symptom,:action,:photo,:created_at)
    ");
    $st->execute([
      ':user_id'=>$u['id'],
      ':date'=>$date,
      ':field_id'=>$field_id,
      ':crop_id'=>$crop_id,
      ':tag'=>($tag!==''?$tag:null),
      ':symptom'=>$symptom,
      ':action'=>($action!==''?$action:null),
      ':photo'=>($photo_path!==''?$photo_path:null),
      ':created_at'=>date('c'),
    ]);
header('Location: pest_list.php?toast=' . rawurlencode('保存しました'));
exit;

  }
}
?>
<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <title>病害虫記録</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="app.css">
<script defer src="app.js"></script>

</head>
<body>

<div class="topbar">
  <div class="topbar-inner">
    <div class="title">病害虫記録</div>
    <div class="actions">
      <a class="btn" href="pest_list.php">履歴</a>
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

  <div class="card" style="background:#fff8f8;border-color:#f3c4c4">
    <b>入力のコツ</b><br>
    ・「状態 → 広がり → 変化」の順で短く書くと後で役に立ちます<br>
    ・写真があると特定が速い（任意だけど推奨）
  </div>

  <form method="post" enctype="multipart/form-data">

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
      <label>タグ（任意）</label>
      <select name="symptom_tag">
        <option value="">—</option>
        <?php foreach ($tagOptions as $t): ?>
          <option value="<?=e($t)?>"><?=e($t)?></option>
        <?php endforeach; ?>
      </select>
      <div class="hint">※集計で効くので、分かる範囲で選ぶと強いです</div>
    </div>

    <div class="card">
      <label>症状<span class="req">*</span></label>
      <textarea name="symptom_text" required placeholder="例：
・下葉に白い粉
・入口付近から広がる
・昨日より悪化"></textarea>
    </div>

    <div class="card">
      <label>対応（任意）</label>
      <textarea name="action_text" placeholder="例：
・被害葉を除去
・薬剤Aを500倍で散布"></textarea>
    </div>

    <div class="card">
      <label>写真（任意）</label>
      <input type="file" name="photo" accept="image/*">
      <div class="hint">※jpg/png/webp対応。病害虫は写真が“証拠”になります。</div>
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
