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

if ($from !== '') { $where .= " AND p.date >= :from "; $params[':from'] = $from; }
if ($to   !== '') { $where .= " AND p.date <= :to ";   $params[':to']   = $to; }
if ($user)  { $where .= " AND p.user_id = :uid ";   $params[':uid']   = $user; }
if ($field) { $where .= " AND p.field_id = :field "; $params[':field'] = $field; }
if ($crop)  { $where .= " AND p.crop_id = :crop ";   $params[':crop']  = $crop; }

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
  SELECT COUNT(*) AS cnt
  FROM pests p
  {$where}
", $params);
$totalCnt = (int)($total[0]['cnt'] ?? 0);

// 研修生別
$byUser = fetchAllAssoc($pdo, "
  SELECT u.name AS label, COUNT(*) AS cnt
  FROM pests p
  JOIN users u ON u.id = p.user_id
  {$where}
  GROUP BY p.user_id
  ORDER BY cnt DESC, label ASC
", $params);

// 圃場別
$byField = fetchAllAssoc($pdo, "
  SELECT f.label AS label, COUNT(*) AS cnt
  FROM pests p
  JOIN fields f ON f.id = p.field_id
  {$where}
  GROUP BY p.field_id
  ORDER BY cnt DESC, label ASC
", $params);

// 品目別
$byCrop = fetchAllAssoc($pdo, "
  SELECT c.name AS label, COUNT(*) AS cnt
  FROM pests p
  JOIN crops c ON c.id = p.crop_id
  {$where}
  GROUP BY p.crop_id
  ORDER BY cnt DESC, label ASC
", $params);

// 圃場×品目（上位）
$byFieldCrop = fetchAllAssoc($pdo, "
  SELECT f.label AS field_label, c.name AS crop_name, COUNT(*) AS cnt
  FROM pests p
  JOIN fields f ON f.id = p.field_id
  JOIN crops c ON c.id = p.crop_id
  {$where}
  GROUP BY p.field_id, p.crop_id
  ORDER BY cnt DESC, field_label ASC, crop_name ASC
  LIMIT 30
", $params);

// 症状タグの頻出（完全一致で集計）
// ※自由入力なので表記ゆれが出ます。将来はタグ化すると強い。
$byTag = fetchAllAssoc($pdo, "
  SELECT
    CASE
      WHEN length(trim(COALESCE(p.symptom_tag,'')))=0 THEN '（未設定）'
      ELSE trim(p.symptom_tag)
    END AS label,
    COUNT(*) AS cnt
  FROM pests p
  {$where}
  GROUP BY label
  ORDER BY cnt DESC
  LIMIT 30
", $params);



// 最新履歴（証拠：写真表示）
$latest = fetchAllAssoc($pdo, "
  SELECT p.*, u.name AS user_name, f.label AS field_label, c.name AS crop_name
  FROM pests p
  JOIN users u ON u.id = p.user_id
  JOIN fields f ON f.id = p.field_id
  JOIN crops c ON c.id = p.crop_id
  {$where}
  ORDER BY p.date DESC, p.id DESC
  LIMIT 30
", $params);

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <title>病害虫集計（管理者）</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="app.css">

  <style>
    body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial}
    table{border-collapse:collapse;width:100%;max-width:980px}
    th,td{border:1px solid #ddd;padding:8px;vertical-align:top}
    th{background:#f6f6f6;text-align:left}
    .muted{color:#666;font-size:12px}
    .section{margin:20px 0}
    .cards{display:flex;gap:10px;flex-wrap:wrap;margin:10px 0 18px}
    .card{border:1px solid #ddd;border-radius:8px;padding:10px;min-width:220px}
    .big{font-size:20px;font-weight:700}
    .wrap{max-width:1040px}
    img{max-width:320px;border:1px solid #ccc}
    .small{font-size:12px}
  </style>
</head>
<body>
<div class="wrap">
  <h1>病害虫集計（管理者）</h1>
  <p><a href="index.php">←ホーム</a> / <a href="pest_list.php">病害虫履歴</a></p>

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
    <span class="muted">※症状は自由入力なので、表記が揃うほど集計が鋭くなります</span>
  </form>

  <div class="cards">
    <div class="card">
      <div class="muted">対象件数</div>
      <div class="big"><?= $totalCnt ?> 件</div>
    </div>
  </div>

  <div class="section">
    <h2>圃場×品目（上位30）</h2>
    <table>
      <tr><th>圃場</th><th>品目</th><th>件数</th></tr>
      <?php foreach ($byFieldCrop as $r): ?>
        <tr>
          <td><?=h($r['field_label'])?></td>
          <td><?=h($r['crop_name'])?></td>
          <td><?= (int)$r['cnt'] ?></td>
        </tr>
      <?php endforeach; ?>
    </table>
  </div>

  <div class="section">
    <h2>症状の頻出（上位30）</h2>
    <table>
      <tr><th>症状（完全一致）</th><th>件数</th></tr>
      <?php foreach ($bySymptom as $r): ?>
        <tr>
          <td style="white-space:pre-wrap"><?=h($r['label'])?></td>
          <td><?= (int)$r['cnt'] ?></td>
        </tr>
      <?php endforeach; ?>
    </table>
    <p class="muted">※同じ症状でも文章が少し違うと別集計になります。将来的に「症状タグ」を追加すると超強いです。</p>
  </div>

  <div class="section">
    <h2>内訳</h2>
    <table>
      <tr>
        <th>研修生別</th><th>件数</th>
        <th>圃場別</th><th>件数</th>
        <th>品目別</th><th>件数</th>
      </tr>
      <?php
        $max = max(count($byUser), count($byField), count($byCrop));
        for ($i=0; $i<$max; $i++):
          $urow = $byUser[$i] ?? null;
          $frow = $byField[$i] ?? null;
          $crow = $byCrop[$i] ?? null;
      ?>
        <tr>
          <td><?= $urow ? h($urow['label']) : '' ?></td>
          <td><?= $urow ? (int)$urow['cnt'] : '' ?></td>
          <td><?= $frow ? h($frow['label']) : '' ?></td>
          <td><?= $frow ? (int)$frow['cnt'] : '' ?></td>
          <td><?= $crow ? h($crow['label']) : '' ?></td>
          <td><?= $crow ? (int)$crow['cnt'] : '' ?></td>
        </tr>
      <?php endfor; ?>
    </table>
  </div>

  <div class="section">
    <h2>最新履歴（直近30件）</h2>
    <table>
      <tr><th>日付</th><th>ユーザー</th><th>圃場/品目</th><th>症状</th><th>対応</th><th>写真</th></tr>
      <?php foreach ($latest as $r): ?>
        <tr>
          <td><?=h($r['date'])?></td>
          <td><?=h($r['user_name'])?></td>
          <td><?=h($r['field_label'])?><br><span class="small"><?=h($r['crop_name'])?></span></td>
          <td style="white-space:pre-wrap"><?=h((string)$r['symptom_text'])?></td>
          <td style="white-space:pre-wrap"><?=!empty($r['action_text']) ? h((string)$r['action_text']) : '<span class="muted">—</span>'?></td>
          <td>
            <?php if (!empty($r['photo_path'])): ?>
              <img src="<?=h((string)$r['photo_path'])?>" alt="photo">
            <?php else: ?>
              <span class="muted">—</span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </table>
  </div>

</div>
</body>
</html>
