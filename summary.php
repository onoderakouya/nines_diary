<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';

$admin = requireAdmin();
$pdo = db();

$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to'] ?? date('Y-m-d');

function q(PDO $pdo, string $sql, array $params): array {
  $st = $pdo->prepare($sql);
  $st->execute($params);
  return $st->fetchAll();
}

$params = [':from'=>$from, ':to'=>$to];

// 1) 出荷実績 集計（品目×単位）
$ship = q($pdo, "
SELECT c.name AS crop, s.unit, SUM(s.quantity) AS total_qty
FROM shipments s
JOIN crops c ON c.id=s.crop_id
WHERE s.date BETWEEN :from AND :to
GROUP BY c.name, s.unit
ORDER BY c.id, s.unit
", $params);

// 2) 資材費 集計（合計＋品目別＋圃場別）
$mat_total = q($pdo, "
SELECT SUM(cost_yen) AS total_yen
FROM materials
WHERE date BETWEEN :from AND :to
", $params);

$mat_by_crop = q($pdo, "
SELECT COALESCE(c.name,'(未設定)') AS crop, SUM(m.cost_yen) AS total_yen
FROM materials m
LEFT JOIN crops c ON c.id=m.crop_id
WHERE m.date BETWEEN :from AND :to
GROUP BY crop
ORDER BY total_yen DESC
", $params);

$mat_by_field = q($pdo, "
SELECT COALESCE(f.label,'(未設定)') AS field, SUM(m.cost_yen) AS total_yen
FROM materials m
LEFT JOIN fields f ON f.id=m.field_id
WHERE m.date BETWEEN :from AND :to
GROUP BY field
ORDER BY total_yen DESC
", $params);

// 3) 病害虫（件数：品目×圃場、最新10件）
$pest_count = q($pdo, "
SELECT c.name AS crop, f.label AS field, COUNT(*) AS cnt
FROM pests p
JOIN crops c ON c.id=p.crop_id
JOIN fields f ON f.id=p.field_id
WHERE p.date BETWEEN :from AND :to
GROUP BY c.name, f.label
ORDER BY cnt DESC
", $params);

$pest_recent = q($pdo, "
SELECT p.date, u.name AS user, c.name AS crop, f.label AS field, p.symptom_text
FROM pests p
JOIN users u ON u.id=p.user_id
JOIN crops c ON c.id=p.crop_id
JOIN fields f ON f.id=p.field_id
WHERE p.date BETWEEN :from AND :to
ORDER BY p.date DESC, p.id DESC
LIMIT 10
", $params);

function unitLabel(string $u): string { return $u==='box' ? '箱' : 'kg'; }
?>
<!doctype html><meta charset="utf-8">
<title>集計（管理者）</title>
<h1>集計（管理者）</h1>
<p><a href="index.php">←ホーム</a></p>

<form method="get">
  <label>From <input type="date" name="from" value="<?=e($from)?>"></label>
  <label>To <input type="date" name="to" value="<?=e($to)?>"></label>
  <button>表示</button>
</form>

<hr>

<h2>出荷実績（期間合計）</h2>
<table border="1" cellpadding="6" style="border-collapse:collapse">
  <tr><th>品目</th><th>単位</th><th>合計</th></tr>
  <?php foreach ($ship as $r): ?>
    <tr>
      <td><?=e($r['crop'])?></td>
      <td><?=e(unitLabel((string)$r['unit']))?></td>
      <td><?=e((string)$r['total_qty'])?></td>
    </tr>
  <?php endforeach; ?>
</table>

<h2>資材費（期間合計）</h2>
<p><b>合計：</b> ¥<?=number_format((int)($mat_total[0]['total_yen'] ?? 0))?></p>

<h3>資材費：品目別</h3>
<table border="1" cellpadding="6" style="border-collapse:collapse">
  <tr><th>品目</th><th>合計</th></tr>
  <?php foreach ($mat_by_crop as $r): ?>
    <tr><td><?=e($r['crop'])?></td><td>¥<?=number_format((int)$r['total_yen'])?></td></tr>
  <?php endforeach; ?>
</table>

<h3>資材費：圃場別</h3>
<table border="1" cellpadding="6" style="border-collapse:collapse">
  <tr><th>圃場</th><th>合計</th></tr>
  <?php foreach ($mat_by_field as $r): ?>
    <tr><td><?=e($r['field'])?></td><td>¥<?=number_format((int)$r['total_yen'])?></td></tr>
  <?php endforeach; ?>
</table>

<h2>病害虫（件数）</h2>
<table border="1" cellpadding="6" style="border-collapse:collapse">
  <tr><th>品目</th><th>圃場</th><th>件数</th></tr>
  <?php foreach ($pest_count as $r): ?>
    <tr>
      <td><?=e($r['crop'])?></td>
      <td><?=e($r['field'])?></td>
      <td><?=e((string)$r['cnt'])?></td>
    </tr>
  <?php endforeach; ?>
</table>

<h3>病害虫：最新10件（概要）</h3>
<div style="color:#555">※詳細は各研修生の「病害虫一覧」にあります（本人だけ閲覧）。管理者はここで全体傾向だけ確認。</div>
<?php foreach ($pest_recent as $r): ?>
  <div style="padding:10px 0;border-bottom:1px solid #ddd">
    <div><b><?=e($r['date'])?></b> / <?=e($r['user'])?> / <?=e($r['field'])?> / <?=e($r['crop'])?></div>
    <div><?=nl2br(e(mb_strimwidth($r['symptom_text'], 0, 200, '…', 'UTF-8')))?></div>
  </div>
<?php endforeach; ?>
