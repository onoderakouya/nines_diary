<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
$u = requireLogin();
$pdo = db();

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

  <div class="card">
    <form method="get">
      <div class="grid">
        <div>
          <label>From</label>
          <input type="date" name="from" value="<?=e((string)$from)?>">
        </div>
        <div>
          <label>To</label>
          <input type="date" name="to" value="<?=e((string)$to)?>">
        </div>
      </div>

      <div style="margin-top:12px;display:flex;gap:10px;flex-wrap:wrap">
        <button class="btn primary" type="submit">絞り込み</button>
        <a class="btn" href="pest_list.php">リセット</a>
      </div>
    </form>
  </div>

  <div class="card" style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;align-items:center">
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
      <div style="display:flex;gap:14px;flex-wrap:wrap">
        <?php if (!empty($r['photo_path'])): ?>
          <div style="flex:0 0 220px">
            <img
              src="<?=e((string)$r['photo_path'])?>"
              alt="病害虫写真"
              style="width:100%;border-radius:10px;border:1px solid #e5e7eb"
            >
          </div>
        <?php endif; ?>

        <div style="flex:1;min-width:220px">
          <div style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;align-items:flex-start">
            <div>
              <div style="font-size:16px;font-weight:900">
                <?=e((string)$r['date'])?>
              </div>
              <div class="kv">
                <?=e((string)$r['field_label'])?> / <?=e((string)$r['crop_name'])?>
              </div>
            </div>
            <div class="badge">病害虫</div>
          </div>

          <div style="margin-top:10px">
            <div class="muted" style="font-weight:700;margin-bottom:4px">症状</div>
            <div><?= nl2br(e((string)$r['symptom_text'])) ?></div>
          </div>

          <?php if (!empty($r['action_text'])): ?>
            <div style="margin-top:10px">
              <div class="muted" style="font-weight:700;margin-bottom:4px">対応</div>
              <div><?= nl2br(e((string)$r['action_text'])) ?></div>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  <?php endforeach; ?>

</div>
<script src="app.js"></script>

</body>
</html>
