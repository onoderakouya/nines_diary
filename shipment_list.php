<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
$u = requireLogin();
$pdo = db();
$csrf = csrfToken();
$flashSuccess = getFlash('success');
$flashError = getFlash('error');

$isAdmin = isAdmin($u);

$where = " WHERE 1=1 ";
$params = [];

$from  = $_GET['from'] ?? '';
$to    = $_GET['to'] ?? '';
$field = (int)($_GET['field_id'] ?? 0);
$crop  = (int)($_GET['crop_id'] ?? 0);
$unit  = (string)($_GET['unit'] ?? '');
$userId= (int)($_GET['user_id'] ?? 0);

if ($from)  { $where .= " AND s.date >= :from "; $params[':from'] = $from; }
if ($to)    { $where .= " AND s.date <= :to ";   $params[':to']   = $to; }
if ($field) { $where .= " AND s.field_id = :field "; $params[':field'] = $field; }
if ($crop)  { $where .= " AND s.crop_id = :crop ";   $params[':crop']  = $crop; }
if (in_array($unit, ['box','kg'], true)) { $where .= " AND s.unit = :unit "; $params[':unit'] = $unit; }

if ($isAdmin) {
  if ($userId) { $where .= " AND s.user_id = :uid "; $params[':uid'] = $userId; }
} else {
  $where .= " AND s.user_id = :uid ";
  $params[':uid'] = $u['id'];
}

$fields = $pdo->query("SELECT id,label FROM fields ORDER BY label")->fetchAll();
$crops  = $pdo->query("SELECT id,name FROM crops ORDER BY id")->fetchAll();
$users  = $pdo->query("SELECT id,name,role FROM users ORDER BY role DESC, name ASC")->fetchAll();

$sql = "
SELECT s.*, f.label AS field_label, c.name AS crop_name, u.name AS user_name
FROM shipments s
JOIN fields f ON f.id = s.field_id
JOIN crops c ON c.id = s.crop_id
JOIN users u ON u.id = s.user_id
{$where}
ORDER BY s.date DESC, s.id DESC
LIMIT 300
";
$st = $pdo->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll();

function unitLabel(string $u): string {
  return $u === 'box' ? '箱' : 'kg';
}

// CSVリンク（絞り込み条件を引き継ぐ）
$csv = "shipment_export.php"
  . "?from=" . urlencode((string)$from)
  . "&to=" . urlencode((string)$to)
  . "&field_id=" . (int)$field
  . "&crop_id=" . (int)$crop
  . "&unit=" . urlencode((string)$unit);
if ($isAdmin) {
  $csv .= "&user_id=" . (int)$userId;
}
?>
<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <title>出荷実績</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="app.css">
</head>
<body>

<div class="topbar">
  <div class="topbar-inner">
    <div class="title">出荷実績</div>
    <div class="actions">
      <a class="btn primary" href="shipment_new.php">＋入力</a>
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
      <div class="form-grid filter-grid">
        <div class="form-row">
          <label class="form-label">From</label>
          <input class="form-control" type="date" name="from" value="<?=e((string)$from)?>">
        </div>
        <div class="form-row">
          <label class="form-label">To</label>
          <input class="form-control" type="date" name="to" value="<?=e((string)$to)?>">
        </div>

        <?php if ($isAdmin): ?>
        <div class="form-row">
          <label class="form-label">ユーザー</label>
          <select class="form-control" name="user_id">
            <option value="0">すべて</option>
            <?php foreach ($users as $uu): ?>
              <option value="<?= (int)$uu['id'] ?>" <?= $userId===(int)$uu['id']?'selected':'' ?>>
                <?= e($uu['name']) ?>（<?= e($uu['role']) ?>）
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php endif; ?>

        <div class="form-row">
          <label class="form-label">圃場</label>
          <select class="form-control" name="field_id">
            <option value="0">すべて</option>
            <?php foreach ($fields as $f): ?>
              <option value="<?= (int)$f['id'] ?>" <?= $field===(int)$f['id']?'selected':'' ?>>
                <?= e($f['label']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-row">
          <label class="form-label">品目</label>
          <select class="form-control" name="crop_id">
            <option value="0">すべて</option>
            <?php foreach ($crops as $c): ?>
              <option value="<?= (int)$c['id'] ?>" <?= $crop===(int)$c['id']?'selected':'' ?>>
                <?= e($c['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-row">
          <label class="form-label">単位</label>
          <select class="form-control" name="unit">
            <option value="">すべて</option>
            <option value="box" <?= $unit==='box'?'selected':'' ?>>箱</option>
            <option value="kg"  <?= $unit==='kg'?'selected':'' ?>>kg</option>
          </select>
        </div>
      </div>

      <div class="filter-actions table-actions">
        <button class="btn primary" type="submit">絞り込み</button>
        <a class="btn btn-secondary" href="shipment_list.php">リセット</a>
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
    <?php
      $qty = $r['quantity']; // ← DBに合わせて修正（重要）
      $unitText = unitLabel((string)$r['unit']);
    ?>
    <div class="card">
      <div class="list-row">
        <div>
          <div class="card-title">
            <?=e((string)$r['date'])?>
          </div>
          <div class="kv">
            <?php if ($isAdmin): ?><?=e((string)$r['user_name'])?> / <?php endif; ?>
            <?=e((string)$r['field_label'])?>
            <?php if (!empty($r['plot'])): ?> / <?=e((string)$r['plot'])?><?php endif; ?>
            / <?=e((string)$r['crop_name'])?>
          </div>
        </div>

        <div class="list-side">
          <div class="badge"><?=e($unitText)?></div>
          <div style="margin-top:6px;font-size:22px;font-weight:900">
            <?= e((string)$qty) ?>
          </div>
          <div class="muted">数量</div>
        </div>
      </div>

      <?php if (!empty($r['memo'])): ?>
        <div style="margin-top:10px">
          <div class="muted" style="font-weight:700;margin-bottom:4px">メモ</div>
          <div><?= nl2br(e((string)$r['memo'])) ?></div>
        </div>
      <?php endif; ?>

      <div class="section-sm" style="display:flex;justify-content:flex-end">
        <form method="post" action="delete.php" onsubmit="return confirm('このデータを削除します。よろしいですか？');">
          <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
          <input type="hidden" name="type" value="shipment">
          <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
          <input type="hidden" name="redirect" value="shipment_list.php">
          <button class="btn" style="border-color:#fecaca;color:#b91c1c" type="submit">削除</button>
        </form>
      </div>
    </div>
  <?php endforeach; ?>

</div>
<script src="app.js"></script>

</body>
</html>
