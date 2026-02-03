<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
$u = requireLogin();
$pdo = db();

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

// 権限制御
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
?>
<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <title>出荷一覧</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body>
  <h1>出荷一覧</h1>
  <p><a href="index.php">←ホーム</a> / <a href="shipment_new.php">＋出荷入力</a> / <a href="shipment_summary.php">出荷集計</a></p>

  <form method="get">
    <label>From <input type="date" name="from" value="<?=e((string)$from)?>"></label>
    <label>To <input type="date" name="to" value="<?=e((string)$to)?>"></label>
    <br><br>

    <?php if ($isAdmin): ?>
      <select name="user_id">
        <option value="0">ユーザー：すべて</option>
        <?php foreach ($users as $uu): ?>
          <option value="<?= (int)$uu['id'] ?>" <?= $userId===(int)$uu['id']?'selected':'' ?>>
            <?= e($uu['name']) ?>（<?= e($uu['role']) ?>）
          </option>
        <?php endforeach; ?>
      </select>
    <?php endif; ?>

    <select name="field_id">
      <option value="0">圃場：すべて</option>
      <?php foreach ($fields as $f): ?>
        <option value="<?= (int)$f['id'] ?>" <?= $field===(int)$f['id']?'selected':'' ?>>
          <?= e($f['label']) ?>
        </option>
      <?php endforeach; ?>
    </select>

    <select name="crop_id">
      <option value="0">品目：すべて</option>
      <?php foreach ($crops as $c): ?>
        <option value="<?= (int)$c['id'] ?>" <?= $crop===(int)$c['id']?'selected':'' ?>>
          <?= e($c['name']) ?>
        </option>
      <?php endforeach; ?>
    </select>

    <select name="unit">
      <option value="">単位：すべて</option>
      <option value="box" <?= $unit==='box'?'selected':'' ?>>箱</option>
      <option value="kg"  <?= $unit==='kg'?'selected':'' ?>>kg</option>
    </select>

    <button>絞り込み</button>
  </form>

  <hr>

  <?php foreach ($rows as $r): ?>
    <div style="padding:10px 0;border-bottom:1px solid #ddd">
      <div>
        <b><?=e($r['date'])?></b>
        <?php if ($isAdmin): ?> / <?=e($r['user_name'])?><?php endif; ?>
        / <?=e($r['field_label'])?>
        <?php if (!empty($r['plot'])): ?> / <?=e((string)$r['plot'])?><?php endif; ?>
        / <?=e($r['crop_name'])?>
      </div>
      <div>
        数量：<?= e((string)$r['qty']) ?> <?= e(unitLabel((string)$r['unit'])) ?>
      </div>
      <?php if (!empty($r['memo'])): ?><div><?=nl2br(e((string)$r['memo']))?></div><?php endif; ?>
    </div>
  <?php endforeach; ?>
</body>
</html>
