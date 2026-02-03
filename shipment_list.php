<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
$u = requireLogin();
$pdo = db();

$where = " WHERE s.user_id = :uid ";
$params = [':uid'=>$u['id']];

$from = $_GET['from'] ?? '';
$to   = $_GET['to'] ?? '';

if ($from) { $where .= " AND s.date >= :from "; $params[':from']=$from; }
if ($to)   { $where .= " AND s.date <= :to ";   $params[':to']=$to; }

$sql = "
SELECT s.*, f.label AS field_label, c.name AS crop_name
FROM shipments s
JOIN fields f ON f.id=s.field_id
JOIN crops c ON c.id=s.crop_id
{$where}
ORDER BY s.date DESC, s.id DESC
LIMIT 300
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

function unitLabel(string $u): string { return $u==='box' ? '箱' : 'kg'; }
?>
<!doctype html><meta charset="utf-8">
<title>出荷実績 一覧</title>
<h1>出荷実績 一覧</h1>
<p><a href="index.php">←ホーム</a> / <a href="shipment_new.php">＋追加</a></p>

<form method="get">
  <label>From <input type="date" name="from" value="<?=e($from)?>"></label>
  <label>To <input type="date" name="to" value="<?=e($to)?>"></label>
  <button>絞り込み</button>
</form>

<hr>
<?php foreach ($rows as $r): ?>
  <div style="padding:10px 0;border-bottom:1px solid #ddd">
    <div><b><?=e($r['date'])?></b> / <?=e($r['field_label'])?> / <?=e($r['crop_name'])?></div>
    <div><?=e((string)$r['quantity'])?> <?=e(unitLabel((string)$r['unit']))?></div>
    <?php if (!empty($r['note'])): ?><div><?=e($r['note'])?></div><?php endif; ?>
  </div>
<?php endforeach; ?>
