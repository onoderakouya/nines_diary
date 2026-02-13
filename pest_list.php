<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
$u = requireLogin();
$pdo = db();
$csrf = csrfToken();
$flashSuccess = getFlash('success');
$flashError = getFlash('error');

$where = " WHERE p.user_id = :uid ";
$params = [':uid'=>$u['id']];

$from = $_GET['from'] ?? '';
$to   = $_GET['to'] ?? '';

if ($from) { $where .= " AND p.date >= :from "; $params[':from']=$from; }
if ($to)   { $where .= " AND p.date <= :to ";   $params[':to']=$to; }

$sql = "
SELECT p.*, f.label AS field_label, c.name AS crop_name
FROM pests p
JOIN fields f ON f.id=p.field_id
JOIN crops c ON c.id=p.crop_id
{$where}
ORDER BY p.date DESC, p.id DESC
LIMIT 300
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

// CSVリンク
$csv = "pest_export.php"
  . "?from=" . urlencode((string)$from)
  . "&to=" . urlencode((string)$to);
?>
<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <title>病害虫履歴</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="app.css">
</head>
<body>

<div class="topbar">
  <div class="topbar-inner">
    <div class="title">病害虫履歴</div>
    <div class="actions">
      <a class="btn primary" href="pest_new.php">＋記録</a>
      <a class="btn" href="<?=e($csv)?>">CSV</a>
      <a class="btn ghost" href="index.php">ホーム</a>
    </div>
  </div>
</div>

<div class="container">

  <?php if ($flashSuccess): ?>
    <div class="card" style="border-color:#86efac;background:#f0fdf4;color:#166534">
      <?= e($flashSuccess) ?>
    </div>
  <?php endif; ?>

  <?php if ($flashError): ?>
    <div class="card error">
      <?= e($flashError) ?>
    </div>
  <?php endif; ?>

  <div class="card">
    <form method="get">
      <div class="form-grid">
        <div class="form-row">
          <label class="form-label">From</label>
          <input class="form-control" type="date" name="from" value="<?=e((string)$from)?>">
        </div>
        <div class="form-row">
          <label class="form-label">To</label>
          <input class="form-control" type="date" name="to" value="<?=e((string)$to)?>">
        </div>
      </div>

      <div class="filter-actions table-actions">
        <button class="btn primary" type="submit">絞り込み</button>
        <a class="btn btn-secondary" href="pest_list.php">リセット</a>
      </div>
    </form>
  </div>

  <div class="card meta-summary">
    <div>
      <div class="muted">表示件数</div>
      <div style="font-size:18px;font-weight:900"><?= number_format(count($rows)) ?> 件</div>
    </div>
    <div class="muted">※最新300件まで</div>
  </div>

  <?php if (!$rows): ?>
    <div class="card"><div class="muted">該当データがありません。</div></div>
  <?php endif; ?>

  <?php foreach ($rows as $r): ?>
    <div class="card">
      <div class="list-row-media">
        <?php if (!empty($r['photo_path'])): ?>
          <div style="flex:0 0 220px">
            <img
              src="<?=e((string)$r['photo_path'])?>"
              alt="病害虫写真"
              style="width:100%;border-radius:10px;border:1px solid #e5e7eb"
            >
          </div>
        <?php endif; ?>

        <div class="list-main">
          <div class="list-row">
            <div>
              <div class="card-title">
                <?=e((string)$r['date'])?>
              </div>
              <div class="kv">
                <?=e((string)$r['field_label'])?> / <?=e((string)$r['crop_name'])?>
              </div>
            </div>
            <div class="badge">病害虫</div>
          </div>

          <div class="section-sm">
            <div class="muted" style="font-weight:700;margin-bottom:4px">症状</div>
            <div><?= nl2br(e((string)$r['symptom_text'])) ?></div>
          </div>

          <?php if (!empty($r['action_text'])): ?>
            <div class="section-sm">
              <div class="muted" style="font-weight:700;margin-bottom:4px">対応</div>
              <div><?= nl2br(e((string)$r['action_text'])) ?></div>
            </div>
          <?php endif; ?>

          <div class="section-sm" style="display:flex;justify-content:flex-end;gap:8px;align-items:center;flex-wrap:wrap">
            <a class="btn" href="pest_edit.php?id=<?= (int)$r['id'] ?>">編集</a>
            <form method="post" action="delete.php" onsubmit="return confirm('このデータを削除します。よろしいですか？');">
              <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
              <input type="hidden" name="type" value="pest">
              <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <input type="hidden" name="redirect" value="pest_list.php">
              <button class="btn" style="border-color:#fecaca;color:#b91c1c" type="submit">削除</button>
            </form>
          </div>
        </div>
      </div>
    </div>
  <?php endforeach; ?>

</div>
<script src="app.js"></script>

</body>
</html>
