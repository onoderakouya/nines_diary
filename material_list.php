<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
$u = requireLogin();
$pdo = db();

$where = " WHERE m.user_id = :uid ";
$params = [':uid'=>$u['id']];

$from = $_GET['from'] ?? '';
$to   = $_GET['to'] ?? '';

if ($from) { $where .= " AND m.date >= :from "; $params[':from']=$from; }
if ($to)   { $where .= " AND m.date <= :to ";   $params[':to']=$to; }

$sql = "
SELECT m.*, f.label AS field_label, c.name AS crop_name
FROM materials m
LEFT JOIN fields f ON f.id=m.field_id
LEFT JOIN crops c ON c.id=m.crop_id
{$where}
ORDER BY m.date DESC, m.id DESC
LIMIT 300
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

// CSVリンク
$csv = "material_export.php"
  . "?from=" . urlencode((string)$from)
  . "&to=" . urlencode((string)$to);
?>
<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <title>資材費実績</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="app.css">
</head>
<body>

<div class="topbar">
  <div class="topbar-inner">
    <div class="title">資材費実績</div>
    <div class="actions">
      <a class="btn primary" href="material_new.php">＋入力</a>
      <a class="btn" href="<?=e($csv)?>">CSV</a>
      <a class="btn ghost" href="index.php">ホーム</a>
    </div>
  </div>
</div>

<div class="container">

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
        <a class="btn btn-secondary" href="material_list.php">リセット</a>
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
      <div class="list-row">
        <div class="list-main">
          <div class="card-title">
            <?=e((string)$r['date'])?>
          </div>
          <div class="kv">
            圃場: <?=e((string)($r['field_label'] ?? '—'))?>
            / 品目: <?=e((string)($r['crop_name'] ?? '—'))?>
          </div>

          <div class="section-sm" style="font-size:18px;font-weight:900">
            <?=e((string)$r['item_name'])?>
          </div>

          <?php if ($r['amount'] !== null): ?>
            <div class="muted help-text">
              数量：<?=e((string)$r['amount'])?> <?=e((string)($r['unit'] ?? ''))?>
            </div>
          <?php endif; ?>

          <?php if (!empty($r['note'])): ?>
            <div class="section-sm">
              <div class="muted" style="font-weight:700;margin-bottom:4px">メモ</div>
              <div><?= nl2br(e((string)$r['note'])) ?></div>
            </div>
          <?php endif; ?>
        </div>

        <div class="list-side">
          <div class="muted">金額</div>
          <div style="font-size:22px;font-weight:900">
            ¥<?=number_format((int)$r['cost_yen'])?>
          </div>
          <div class="badge" style="margin-top:8px">資材費</div>
        </div>
      </div>
    </div>
  <?php endforeach; ?>

</div>
<script src="app.js"></script>

</body>
</html>
