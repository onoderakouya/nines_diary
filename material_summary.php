<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';

requireAdmin();
$pdo = db();

// フィルタ
$from  = trim((string)($_GET['from'] ?? ''));
$to    = trim((string)($_GET['to'] ?? ''));
$user  = (int)($_GET['user_id'] ?? 0);
$field = (int)($_GET['field_id'] ?? 0);
$crop  = (int)($_GET['crop_id'] ?? 0);

$where = " WHERE 1=1 ";
$params = [];

if ($from !== '') { $where .= " AND m.date >= :from "; $params[':from'] = $from; }
if ($to   !== '') { $where .= " AND m.date <= :to ";   $params[':to']   = $to; }
if ($user)  { $where .= " AND m.user_id = :uid ";   $params[':uid']   = $user; }
if ($field) { $where .= " AND m.field_id = :field "; $params[':field'] = $field; }
if ($crop)  { $where .= " AND m.crop_id = :crop ";   $params[':crop']  = $crop; }

// マスタ
$users  = $pdo->query("SELECT id,name,role FROM users ORDER BY role DESC, name ASC")->fetchAll();
$fields = $pdo->query("SELECT id,label FROM fields ORDER BY label")->fetchAll();
$crops  = $pdo->query("SELECT id,name FROM crops ORDER BY id")->fetchAll();

function fetchAllAssoc(PDO $pdo, string $sql, array $params): array {
  $st = $pdo->prepare($sql);
  $st->execute($params);
  return $st->fetchAll();
}

// 総計
$total = fetchAllAssoc($pdo, "
  SELECT
    COUNT(*) AS cnt,
    COALESCE(SUM(m.cost_yen),0) AS cost_sum
  FROM materials m
  {$where}
", $params);
$totalCnt = (int)($total[0]['cnt'] ?? 0);
$totalYen = (int)($total[0]['cost_sum'] ?? 0);

// 月別
$byMonth = fetchAllAssoc($pdo, "
  SELECT
    substr(m.date,1,7) AS ym,
    COUNT(*) AS cnt,
    COALESCE(SUM(m.cost_yen),0) AS cost_sum
  FROM materials m
  {$where}
  GROUP BY ym
  ORDER BY ym DESC
", $params);

// 品目別（NULLは「—」）
$byCrop = fetchAllAssoc($pdo, "
  SELECT
    COALESCE(c.name,'—') AS label,
    COUNT(*) AS cnt,
    COALESCE(SUM(m.cost_yen),0) AS cost_sum
  FROM materials m
  LEFT JOIN crops c ON c.id = m.crop_id
  {$where}
  GROUP BY label
  ORDER BY cost_sum DESC, cnt DESC, label ASC
", $params);

// 圃場別（NULLは「—」）
$byField = fetchAllAssoc($pdo, "
  SELECT
    COALESCE(f.label,'—') AS label,
    COUNT(*) AS cnt,
    COALESCE(SUM(m.cost_yen),0) AS cost_sum
  FROM materials m
  LEFT JOIN fields f ON f.id = m.field_id
  {$where}
  GROUP BY label
  ORDER BY cost_sum DESC, cnt DESC, label ASC
", $params);

// 研修生別
$byUser = fetchAllAssoc($pdo, "
  SELECT
    u.name AS label,
    COUNT(*) AS cnt,
    COALESCE(SUM(m.cost_yen),0) AS cost_sum
  FROM materials m
  JOIN users u ON u.id = m.user_id
  {$where}
  GROUP BY m.user_id
  ORDER BY cost_sum DESC, cnt DESC, label ASC
", $params);

// 資材名別（上位30）
$byItem = fetchAllAssoc($pdo, "
  SELECT
    m.item_name AS label,
    COUNT(*) AS cnt,
    COALESCE(SUM(m.cost_yen),0) AS cost_sum
  FROM materials m
  {$where}
  GROUP BY m.item_name
  ORDER BY cost_sum DESC, cnt DESC, label ASC
  LIMIT 30
", $params);

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <title>資材費集計（管理者）</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="app.css">

  <style>
    body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial}
    table{border-collapse:collapse;width:100%;max-width:980px}
    th,td{border:1px solid #ddd;padding:8px}
    th{background:#f6f6f6;text-align:left}
    .muted{color:#666;font-size:12px}
    .section{margin:20px 0}
    .cards{display:flex;gap:10px;flex-wrap:wrap;margin:10px 0 18px}
    .card{border:1px solid #ddd;border-radius:8px;padding:10px;min-width:220px}
    .big{font-size:20px;font-weight:700}
    .wrap{max-width:1040px}
  </style>
</head>
<body>
<div class="wrap">
  <h1>資材費集計（管理者）</h1>
  <p><a href="index.php">←ホーム</a> / <a href="material_list.php">資材費実績</a></p>

  <form method="get" style="margin:10px 0 12px">
    <label>From <input type="date" name="from" value="<?=h($from)?>"></label>
    <label>To <input type="date" name="to" value="<?=h($to)?>"></label>
    <br><br>

    <select name="user_id">
      <option value="0">ユーザー：すべて</option>
      <?php foreach ($users as $uu): ?>
        <option value="<?= (int)$uu['id'] ?>" <?= $user === (int)$uu['id'] ? 'selected' : '' ?>>
          <?=h($uu['name'])?>（<?=h($uu['role'])?>）
        </option>
      <?php endforeach; ?>
    </select>

    <select name="field_id">
      <option value="0">圃場：すべて</option>
      <?php foreach ($fields as $f): ?>
        <option value="<?= (int)$f['id'] ?>" <?= $field === (int)$f['id'] ? 'selected' : '' ?>>
          <?=h($f['label'])?>
        </option>
      <?php endforeach; ?>
    </select>

    <select name="crop_id">
      <option value="0">品目：すべて</option>
      <?php foreach ($crops as $c): ?>
        <option value="<?= (int)$c['id'] ?>" <?= $crop === (int)$c['id'] ? 'selected' : '' ?>>
          <?=h($c['name'])?>
        </option>
      <?php endforeach; ?>
    </select>

    <button>絞り込み</button>
  </form>

  <div class="cards">
    <div class="card">
      <div class="muted">対象件数</div>
      <div class="big"><?= $totalCnt ?> 件</div>
    </div>
    <div class="card">
      <div class="muted">合計金額</div>
      <div class="big">¥<?= number_format($totalYen) ?></div>
    </div>
    <div class="card">
      <div class="muted">平均</div>
      <div class="big">
        <?php $avg = $totalCnt ? (int)round($totalYen / $totalCnt) : 0; ?>
        ¥<?= number_format($avg) ?> / 件
      </div>
    </div>
  </div>

  <div class="section">
    <h2>月別</h2>
    <table>
      <tr><th>年月</th><th>合計</th><th>件数</th></tr>
      <?php foreach ($byMonth as $r): ?>
        <tr>
          <td><?=h($r['ym'])?></td>
          <td>¥<?= number_format((int)$r['cost_sum']) ?></td>
          <td><?= (int)$r['cnt'] ?></td>
        </tr>
      <?php endforeach; ?>
    </table>
  </div>

  <div class="section">
    <h2>品目別</h2>
    <table>
      <tr><th>品目</th><th>合計</th><th>件数</th></tr>
      <?php foreach ($byCrop as $r): ?>
        <tr>
          <td><?=h($r['label'])?></td>
          <td>¥<?= number_format((int)$r['cost_sum']) ?></td>
          <td><?= (int)$r['cnt'] ?></td>
        </tr>
      <?php endforeach; ?>
    </table>
  </div>

  <div class="section">
    <h2>圃場別</h2>
    <table>
      <tr><th>圃場</th><th>合計</th><th>件数</th></tr>
      <?php foreach ($byField as $r): ?>
        <tr>
          <td><?=h($r['label'])?></td>
          <td>¥<?= number_format((int)$r['cost_sum']) ?></td>
          <td><?= (int)$r['cnt'] ?></td>
        </tr>
      <?php endforeach; ?>
    </table>
  </div>

  <div class="section">
    <h2>研修生別</h2>
    <table>
      <tr><th>ユーザー</th><th>合計</th><th>件数</th></tr>
      <?php foreach ($byUser as $r): ?>
        <tr>
          <td><?=h($r['label'])?></td>
          <td>¥<?= number_format((int)$r['cost_sum']) ?></td>
          <td><?= (int)$r['cnt'] ?></td>
        </tr>
      <?php endforeach; ?>
    </table>
  </div>

  <div class="section">
    <h2>資材名別（上位30）</h2>
    <table>
      <tr><th>資材名</th><th>合計</th><th>件数</th></tr>
      <?php foreach ($byItem as $r): ?>
        <tr>
          <td><?=h($r['label'])?></td>
          <td>¥<?= number_format((int)$r['cost_sum']) ?></td>
          <td><?= (int)$r['cnt'] ?></td>
        </tr>
      <?php endforeach; ?>
    </table>
    <p class="muted">※「同じ資材」でも表記ゆれ（例：アミスター/アミスター20）があると分かれます。揃えるほど集計が鋭くなります。</p>
  </div>

</div>
</body>
</html>
