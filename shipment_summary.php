<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';

requireAdmin();
$pdo = db();

$from  = trim((string)($_GET['from'] ?? ''));
$to    = trim((string)($_GET['to'] ?? ''));
$user  = (int)($_GET['user_id'] ?? 0);
$field = (int)($_GET['field_id'] ?? 0);
$crop  = (int)($_GET['crop_id'] ?? 0);

$where = " WHERE 1=1 ";
$params = [];

if ($from !== '') { $where .= " AND s.date >= :from "; $params[':from'] = $from; }
if ($to   !== '') { $where .= " AND s.date <= :to ";   $params[':to']   = $to; }
if ($user)  { $where .= " AND s.user_id = :uid ";   $params[':uid'] = $user; }
if ($field) { $where .= " AND s.field_id = :field "; $params[':field'] = $field; }
if ($crop)  { $where .= " AND s.crop_id = :crop ";   $params[':crop'] = $crop; }

$users  = $pdo->query("SELECT id,name,role FROM users ORDER BY role DESC, name ASC")->fetchAll();
$fields = $pdo->query("SELECT id,label FROM fields ORDER BY label")->fetchAll();
$crops  = $pdo->query("SELECT id,name FROM crops ORDER BY id")->fetchAll();

function fetchAll(PDO $pdo, string $sql, array $params): array {
  $st = $pdo->prepare($sql);
  $st->execute($params);
  return $st->fetchAll();
}

$byCropUnit = fetchAll($pdo, "
  SELECT c.name AS crop_name, s.unit, COUNT(*) AS cnt, COALESCE(SUM(s.qty),0) AS qty_sum
  FROM shipments s
  JOIN crops c ON c.id = s.crop_id
  {$where}
  GROUP BY s.crop_id, s.unit
  ORDER BY crop_name ASC, s.unit ASC
", $params);

$byDateUnit = fetchAll($pdo, "
  SELECT s.date, s.unit, COUNT(*) AS cnt, COALESCE(SUM(s.qty),0) AS qty_sum
  FROM shipments s
  {$where}
  GROUP BY s.date, s.unit
  ORDER BY s.date DESC, s.unit ASC
  LIMIT 90
", $params);

$byUserUnit = fetchAll($pdo, "
  SELECT u.name AS user_name, s.unit, COUNT(*) AS cnt, COALESCE(SUM(s.qty),0) AS qty_sum
  FROM shipments s
  JOIN users u ON u.id = s.user_id
  {$where}
  GROUP BY s.user_id, s.unit
  ORDER BY qty_sum DESC
", $params);

$byFieldPlotUnit = fetchAll($pdo, "
  SELECT f.label AS field_label, COALESCE(s.plot,'') AS plot, s.unit,
         COUNT(*) AS cnt, COALESCE(SUM(s.qty),0) AS qty_sum
  FROM shipments s
  JOIN fields f ON f.id = s.field_id
  {$where}
  GROUP BY s.field_id, s.plot, s.unit
  ORDER BY qty_sum DESC, cnt DESC
  LIMIT 30
", $params);

function unitLabel(string $u): string { return $u === 'box' ? '箱' : 'kg'; }
?>
<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <title>出荷集計（管理者）</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    table{border-collapse:collapse;width:100%;max-width:980px}
    th,td{border:1px solid #ddd;padding:8px}
    th{background:#f6f6f6;text-align:left}
    .muted{color:#666;font-size:12px}
    .section{margin:20px 0}
  </style>
</head>
<body>
  <h1>出荷集計（管理者）</h1>
  <p><a href="index.php">←ホーム</a> / <a href="shipment_list.php">出荷一覧</a></p>

  <form method="get">
    <label>From <input type="date" name="from" value="<?=e($from)?>"></label>
    <label>To <input type="date" name="to" value="<?=e($to)?>"></label><br><br>

    <select name="user_id">
      <option value="0">ユーザー：すべて</option>
      <?php foreach ($users as $uu): ?>
        <option value="<?= (int)$uu['id'] ?>" <?= $user===(int)$uu['id']?'selected':'' ?>>
          <?= e($uu['name']) ?>（<?= e($uu['role']) ?>）
        </option>
      <?php endforeach; ?>
    </select>

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

    <button>絞り込み</button>
    <span class="muted">※箱/kgは別々に集計します</span>
  </form>

  <div class="section">
    <h2>品目別 × 単位</h2>
    <table>
      <tr><th>品目</th><th>単位</th><th>合計</th><th>件数</th></tr>
      <?php foreach ($byCropUnit as $r): ?>
        <tr>
          <td><?=e($r['crop_name'])?></td>
          <td><?=e(unitLabel((string)$r['unit']))?></td>
          <td><?=e((string)$r['qty_sum'])?></td>
          <td><?= (int)$r['cnt'] ?></td>
        </tr>
      <?php endforeach; ?>
    </table>
  </div>

  <div class="section">
    <h2>日別 × 単位（直近90件）</h2>
    <table>
      <tr><th>日付</th><th>単位</th><th>合計</th><th>件数</th></tr>
      <?php foreach ($byDateUnit as $r): ?>
        <tr>
          <td><?=e($r['date'])?></td>
          <td><?=e(unitLabel((string)$r['unit']))?></td>
          <td><?=e((string)$r['qty_sum'])?></td>
          <td><?= (int)$r['cnt'] ?></td>
        </tr>
      <?php endforeach; ?>
    </table>
  </div>

  <div class="section">
    <h2>研修生別 × 単位</h2>
    <table>
      <tr><th>ユーザー</th><th>単位</th><th>合計</th><th>件数</th></tr>
      <?php foreach ($byUserUnit as $r): ?>
        <tr>
          <td><?=e($r['user_name'])?></td>
          <td><?=e(unitLabel((string)$r['unit']))?></td>
          <td><?=e((string)$r['qty_sum'])?></td>
          <td><?= (int)$r['cnt'] ?></td>
        </tr>
      <?php endforeach; ?>
    </table>
  </div>

  <div class="section">
    <h2>圃場×区画 × 単位（上位30）</h2>
    <table>
      <tr><th>圃場</th><th>区画</th><th>単位</th><th>合計</th><th>件数</th></tr>
      <?php foreach ($byFieldPlotUnit as $r): ?>
        <tr>
          <td><?=e($r['field_label'])?></td>
          <td><?= $r['plot']!=='' ? e((string)$r['plot']) : '<span class="muted">（未入力）</span>' ?></td>
          <td><?=e(unitLabel((string)$r['unit']))?></td>
          <td><?=e((string)$r['qty_sum'])?></td>
          <td><?= (int)$r['cnt'] ?></td>
        </tr>
      <?php endforeach; ?>
    </table>
  </div>
</body>
</html>
