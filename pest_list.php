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
?>
<!doctype html>
<meta charset="utf-8">
<title>病害虫履歴</title>

<h1>病害虫履歴</h1>
<p><a href="index.php">←ホーム</a> / <a href="pest_new.php">＋病害虫記録</a></p>

<form method="get">
  <label>From <input type="date" name="from" value="<?=e($from)?>"></label>
  <label>To <input type="date" name="to" value="<?=e($to)?>"></label>
  <button>絞り込み</button>
</form>

<hr>

<?php foreach ($rows as $r): ?>
  <div style="padding:10px 0;border-bottom:1px solid #ddd">
    <div><b><?=e($r['date'])?></b> / <?=e($r['field_label'])?> / <?=e($r['crop_name'])?></div>
    <div><b>症状:</b><br><?=nl2br(e((string)$r['symptom_text']))?></div>

    <?php if (!empty($r['action_text'])): ?>
      <div><b>対応:</b><br><?=nl2br(e((string)$r['action_text']))?></div>
    <?php endif; ?>

    <?php if (!empty($r['photo_path'])): ?>
      <div><img src="<?=e((string)$r['photo_path'])?>" style="max-width:320px;border:1px solid #ccc"></div>
    <?php endif; ?>
  </div>
<?php endforeach; ?>
