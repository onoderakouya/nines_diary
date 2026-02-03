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
?>
<!doctype html><meta charset="utf-8">
<title>資材費 一覧</title>
<h1>資材費 一覧</h1>
<p><a href="index.php">←ホーム</a> / <a href="material_new.php">＋追加</a></p>

<form method="get">
  <label>From <input type="date" name="from" value="<?=e($from)?>"></label>
  <label>To <input type="date" name="to" value="<?=e($to)?>"></label>
  <button>絞り込み</button>
</form>

<hr>
<?php foreach ($rows as $r): ?>
  <div style="padding:10px 0;border-bottom:1px solid #ddd">
    <div><b><?=e($r['date'])?></b> / 圃場: <?=e($r['field_label'] ?? '—')?> / 品目: <?=e($r['crop_name'] ?? '—')?></div>
    <div><?=e($r['item_name'])?>
      <?php if ($r['amount'] !== null): ?>
        （<?=e((string)$r['amount'])?> <?=e((string)($r['unit'] ?? ''))?>）
      <?php endif; ?>
    </div>
    <div>¥<?=number_format((int)$r['cost_yen'])?></div>
    <?php if (!empty($r['note'])): ?><div><?=e($r['note'])?></div><?php endif; ?>
  </div>
<?php endforeach; ?>
