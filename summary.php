<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';

$admin = requireAdmin();
$pdo = db();

/**
 * 絞り込み条件
 */
$from  = trim((string)($_GET['from'] ?? ''));
$to    = trim((string)($_GET['to'] ?? ''));
$user  = (int)($_GET['user_id'] ?? 0);
$field = (int)($_GET['field_id'] ?? 0);
$crop  = (int)($_GET['crop_id'] ?? 0);
$task  = (int)($_GET['task_id'] ?? 0);

$where = " WHERE 1=1 ";
$params = [];

if ($from !== '') { $where .= " AND d.date >= :from "; $params[':from'] = $from; }
if ($to   !== '') { $where .= " AND d.date <= :to ";   $params[':to']   = $to; }
if ($user)  { $where .= " AND d.user_id = :uid ";   $params[':uid']   = $user; }
if ($field) { $where .= " AND d.field_id = :field "; $params[':field'] = $field; }
if ($crop)  { $where .= " AND d.crop_id = :crop ";   $params[':crop']  = $crop; }
if ($task)  { $where .= " AND d.task_id = :task ";   $params[':task']  = $task; }

// マスタ
$users  = $pdo->query("SELECT id,name,email,role FROM users ORDER BY role DESC, name ASC")->fetchAll();
$fields = $pdo->query("SELECT id,label FROM fields ORDER BY label")->fetchAll();
$crops  = $pdo->query("SELECT id,name FROM crops ORDER BY id")->fetchAll();
$tasks  = $pdo->query("SELECT id,name FROM tasks ORDER BY id")->fetchAll();

function fetchAllAssoc(PDO $pdo, string $sql, array $params): array {
  $st = $pdo->prepare($sql);
  $st->execute($params);
  return $st->fetchAll();
}

/**
 * 総計（合計分、件数）
 */
$total = fetchAllAssoc($pdo, "
  SELECT
    COUNT(*) as cnt,
    COALESCE(SUM(d.minutes),0) as minutes_sum
  FROM diary_entries d
  {$where}
", $params);
$totalCnt = (int)($total[0]['cnt'] ?? 0);
$totalMin = (int)($total[0]['minutes_sum'] ?? 0);

// ===== 病害虫：タグ×圃場ランキング（管理者） =====
$from = trim((string)($_GET['from'] ?? ''));
$to   = trim((string)($_GET['to'] ?? ''));

$pw = " WHERE 1=1 ";
$pp = [];
if ($from !== '') { $pw .= " AND p.date >= :from "; $pp[':from'] = $from; }
if ($to   !== '') { $pw .= " AND p.date <= :to ";   $pp[':to']   = $to; }

$st = $pdo->prepare("
  SELECT
    COALESCE(NULLIF(trim(p.symptom_tag),''), '（未設定）') AS tag,
    f.label AS field_label,
    f.id AS field_id,
    COUNT(*) AS cnt
  FROM pests p
  JOIN fields f ON f.id = p.field_id
  {$pw}
  GROUP BY tag, p.field_id
  ORDER BY cnt DESC, field_label ASC, tag ASC
  LIMIT 30
");
$st->execute($pp);
$pestRank = $st->fetchAll();

// ===== 病害虫：タグ×品目ランキング =====
$from = trim((string)($_GET['from'] ?? ''));
$to   = trim((string)($_GET['to'] ?? ''));

$pw = " WHERE 1=1 ";
$pp = [];
if ($from !== '') { $pw .= " AND p.date >= :from "; $pp[':from'] = $from; }
if ($to   !== '') { $pw .= " AND p.date <= :to ";   $pp[':to']   = $to; }

$st = $pdo->prepare("
  SELECT
    COALESCE(NULLIF(trim(p.symptom_tag),''), '（未設定）') AS tag,
    c.name AS crop_name,
    c.id AS crop_id,
    COUNT(*) AS cnt
  FROM pests p
  JOIN crops c ON c.id = p.crop_id
  {$pw}
  GROUP BY tag, p.crop_id
  ORDER BY cnt DESC, crop_name ASC, tag ASC
  LIMIT 30
");
$st->execute($pp);
$pestByCrop = $st->fetchAll();


// ===== 病害虫：月別（季節性） =====
$pw = " WHERE 1=1 ";
$pp = [];
if ($from !== '') { $pw .= " AND p.date >= :from "; $pp[':from'] = $from; }
if ($to   !== '') { $pw .= " AND p.date <= :to ";   $pp[':to']   = $to; }

$st = $pdo->prepare("
  SELECT
    strftime('%Y-%m', p.date) AS ym,
    COALESCE(NULLIF(trim(p.symptom_tag),''), '（未設定）') AS tag,
    COUNT(*) AS cnt
  FROM pests p
  {$pw}
  GROUP BY ym, tag
  ORDER BY ym DESC, cnt DESC
  LIMIT 60
");
$st->execute($pp);
$pestByMonth = $st->fetchAll();




/**
 * 作業別集計
 */
$byTask = fetchAllAssoc($pdo, "
  SELECT
    t.name as label,
    COUNT(*) as cnt,
    COALESCE(SUM(d.minutes),0) as minutes_sum
  FROM diary_entries d
  JOIN tasks t ON t.id = d.task_id
  {$where}
  GROUP BY d.task_id
  ORDER BY minutes_sum DESC, cnt DESC, label ASC
", $params);

/**
 * 品目別集計
 */
$byCrop = fetchAllAssoc($pdo, "
  SELECT
    c.name as label,
    COUNT(*) as cnt,
    COALESCE(SUM(d.minutes),0) as minutes_sum
  FROM diary_entries d
  JOIN crops c ON c.id = d.crop_id
  {$where}
  GROUP BY d.crop_id
  ORDER BY minutes_sum DESC, cnt DESC, label ASC
", $params);

/**
 * 圃場別集計
 */
$byField = fetchAllAssoc($pdo, "
  SELECT
    f.label as label,
    COUNT(*) as cnt,
    COALESCE(SUM(d.minutes),0) as minutes_sum
  FROM diary_entries d
  JOIN fields f ON f.id = d.field_id
  {$where}
  GROUP BY d.field_id
  ORDER BY minutes_sum DESC, cnt DESC, label ASC
", $params);

/**
 * 圃場×区画（plot）別（上位）
 */
$byFieldPlot = fetchAllAssoc($pdo, "
  SELECT
    f.label as field_label,
    COALESCE(d.plot,'') as plot,
    COUNT(*) as cnt,
    COALESCE(SUM(d.minutes),0) as minutes_sum
  FROM diary_entries d
  JOIN fields f ON f.id = d.field_id
  {$where}
  GROUP BY d.field_id, d.plot
  ORDER BY minutes_sum DESC, cnt DESC, field_label ASC, plot ASC
  LIMIT 30
", $params);

/**
 * 研修生別集計（管理者含むが、必要ならWHEREでrole='trainee'に絞れます）
 */
$byUser = fetchAllAssoc($pdo, "
  SELECT
    u.name as label,
    COUNT(*) as cnt,
    COALESCE(SUM(d.minutes),0) as minutes_sum
  FROM diary_entries d
  JOIN users u ON u.id = d.user_id
  {$where}
  GROUP BY d.user_id
  ORDER BY minutes_sum DESC, cnt DESC, label ASC
", $params);

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function mmToHM(int $minutes): string {
  $h = intdiv($minutes, 60);
  $m = $minutes % 60;
  return sprintf('%d:%02d', $h, $m);
}
?>
<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <title>集計（管理者）</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="app.css">

  <style>
    body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial}
    table{border-collapse:collapse;width:100%;max-width:980px}
    th,td{border:1px solid #ddd;padding:8px}
    th{background:#f6f6f6;text-align:left}
    .wrap{max-width:1040px}
    .muted{color:#666;font-size:12px}
    .cards{display:flex;gap:10px;flex-wrap:wrap;margin:10px 0 18px}
    .card{border:1px solid #ddd;border-radius:8px;padding:10px;min-width:220px}
    .big{font-size:20px;font-weight:700}
    .section{margin:22px 0}
    .grid{display:grid;grid-template-columns:1fr;gap:18px}
    @media (min-width: 900px){
      .grid{grid-template-columns:1fr 1fr}
    }
  </style>
</head>
<body>
<div class="wrap">
  <h1>集計（管理者）</h1>
  <p><a href="index.php">←ホーム</a></p>

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

    <select name="task_id">
      <option value="0">作業：すべて</option>
      <?php foreach ($tasks as $t): ?>
        <option value="<?= (int)$t['id'] ?>" <?= $task === (int)$t['id'] ? 'selected' : '' ?>>
          <?=h($t['name'])?>
        </option>
      <?php endforeach; ?>
    </select>

    <button>絞り込み</button>
    <span class="muted">※最大200件制限なし（集計なので全件対象）</span>
  </form>

  <div class="cards">
    <div class="card">
      <div class="muted">対象件数</div>
      <div class="big"><?= $totalCnt ?> 件</div>
    </div>
    <div class="card">
      <div class="muted">合計作業時間</div>
      <div class="big"><?= mmToHM($totalMin) ?>（<?= $totalMin ?>分）</div>
    </div>
    <div class="card">
      <div class="muted">平均</div>
      <div class="big">
        <?php
          $avg = $totalCnt ? (int)round($totalMin / $totalCnt) : 0;
          echo mmToHM($avg) . "（{$avg}分/件）";
        ?>
      </div>
    </div>
  </div>

  <div class="grid">

    <div class="section">
      <h2>作業別（tasks）</h2>
      <table>
        <tr><th>作業</th><th>合計</th><th>件数</th><th>平均/件</th></tr>
        <?php foreach ($byTask as $r): ?>
          <?php $m = (int)$r['minutes_sum']; $c = (int)$r['cnt']; $a = $c? (int)round($m/$c):0; ?>
          <tr>
            <td><?=h($r['label'])?></td>
            <td><?=h(mmToHM($m))?>（<?= $m ?>分）</td>
            <td><?= $c ?></td>
            <td><?=h(mmToHM($a))?>（<?= $a ?>分）</td>
          </tr>
        <?php endforeach; ?>
      </table>
    </div>

    <div class="section">
      <h2>品目別（crops）</h2>
      <table>
        <tr><th>品目</th><th>合計</th><th>件数</th><th>平均/件</th></tr>
        <?php foreach ($byCrop as $r): ?>
          <?php $m = (int)$r['minutes_sum']; $c = (int)$r['cnt']; $a = $c? (int)round($m/$c):0; ?>
          <tr>
            <td><?=h($r['label'])?></td>
            <td><?=h(mmToHM($m))?>（<?= $m ?>分）</td>
            <td><?= $c ?></td>
            <td><?=h(mmToHM($a))?>（<?= $a ?>分）</td>
          </tr>
        <?php endforeach; ?>
      </table>
    </div>

    <div class="section">
      <h2>圃場別（fields）</h2>
      <table>
        <tr><th>圃場</th><th>合計</th><th>件数</th><th>平均/件</th></tr>
        <?php foreach ($byField as $r): ?>
          <?php $m = (int)$r['minutes_sum']; $c = (int)$r['cnt']; $a = $c? (int)round($m/$c):0; ?>
          <tr>
            <td><?=h($r['label'])?></td>
            <td><?=h(mmToHM($m))?>（<?= $m ?>分）</td>
            <td><?= $c ?></td>
            <td><?=h(mmToHM($a))?>（<?= $a ?>分）</td>
          </tr>
        <?php endforeach; ?>
      </table>
    </div>

    <div class="section">
      <h2>研修生別（users）</h2>
      <table>
        <tr><th>ユーザー</th><th>合計</th><th>件数</th><th>平均/件</th></tr>
        <?php foreach ($byUser as $r): ?>
          <?php $m = (int)$r['minutes_sum']; $c = (int)$r['cnt']; $a = $c? (int)round($m/$c):0; ?>
          <tr>
            <td><?=h($r['label'])?></td>
            <td><?=h(mmToHM($m))?>（<?= $m ?>分）</td>
            <td><?= $c ?></td>
            <td><?=h(mmToHM($a))?>（<?= $a ?>分）</td>
          </tr>
        <?php endforeach; ?>
      </table>
      <h2>病害虫：月別（季節性）</h2>
<table border="1" cellpadding="6" style="border-collapse:collapse;max-width:980px;width:100%">
  <tr>
    <th>年月</th>
    <th>タグ</th>
    <th>件数</th>
    <th>履歴</th>
  </tr>
  <?php foreach ($pestByMonth as $r): ?>
    <?php
      $ym = (string)$r['ym']; // YYYY-MM
      $tag = (string)$r['tag'];
      $link = "pest_list.php"
        . "?from=" . urlencode($ym . "-01")
        . "&to=" . urlencode($ym . "-31")
        . "&tag=" . urlencode($tag);
    ?>
    <tr>
      <td><?= e($ym) ?></td>
      <td><?= e($tag) ?></td>
      <td><?= (int)$r['cnt'] ?></td>
      <td><a href="<?=e($link)?>">見る</a></td>
    </tr>
  <?php endforeach; ?>
</table>

    </div>

  </div>

  <div class="section">
    <h2>圃場×区画（上位30）</h2>
    <table>
      <tr><th>圃場</th><th>区画</th><th>合計</th><th>件数</th><th>平均/件</th></tr>
      <?php foreach ($byFieldPlot as $r): ?>
        <?php
          $m = (int)$r['minutes_sum']; $c = (int)$r['cnt']; $a = $c? (int)round($m/$c):0;
          $plotLabel = (string)$r['plot'];
        ?>
        <tr>
          <td><?=h($r['field_label'])?></td>
          <td><?= $plotLabel !== '' ? h($plotLabel) : '<span class="muted">（未入力）</span>' ?></td>
          <td><?=h(mmToHM($m))?>（<?= $m ?>分）</td>
          <td><?= $c ?></td>
          <td><?=h(mmToHM($a))?>（<?= $a ?>分）</td>
        </tr>
      <?php endforeach; ?>
    </table>
    <p class="muted">※区画が自由入力なので、表記が揃うほど集計が鋭くなります（datalist候補が効きます）。</p>

<h2>病害虫：タグ×品目（上位30）</h2>
<table border="1" cellpadding="6" style="border-collapse:collapse;max-width:980px;width:100%">
  <tr>
    <th>件数</th>
    <th>品目</th>
    <th>タグ</th>
    <th>履歴</th>
  </tr>
  <?php foreach ($pestByCrop as $r): ?>
    <?php
      $tag = (string)$r['tag'];
      $cropId = (int)$r['crop_id'];
      $link = "pest_list.php"
        . "?from=" . urlencode($from)
        . "&to=" . urlencode($to)
        . "&crop_id=" . $cropId
        . "&tag=" . urlencode($tag);
    ?>
    <tr>
      <td><?= (int)$r['cnt'] ?></td>
      <td><?= e((string)$r['crop_name']) ?></td>
      <td><?= e($tag) ?></td>
      <td><a href="<?=e($link)?>">見る</a></td>
    </tr>
  <?php endforeach; ?>
</table>



    <h2>病害虫：タグ×圃場（上位30）</h2>
<table border="1" cellpadding="6" style="border-collapse:collapse;max-width:980px;width:100%">
  <tr>
    <th>件数</th>
    <th>圃場</th>
    <th>タグ</th>
    <th>履歴</th>
  </tr>
  <?php foreach ($pestRank as $r): ?>
    <?php
      $tag = (string)$r['tag'];
      $fieldId = (int)$r['field_id'];
      // pest_listへ飛ぶ（期間も引き継ぎ）
      $link = "pest_list.php"
        . "?from=" . urlencode($from)
        . "&to=" . urlencode($to)
        . "&field_id=" . $fieldId
        . "&tag=" . urlencode($tag);
    ?>
    <tr>
      <td><?= (int)$r['cnt'] ?></td>
      <td><?= e((string)$r['field_label']) ?></td>
      <td><?= e($tag) ?></td>
      <td><a href="<?=e($link)?>">見る</a></td>
    </tr>
  <?php endforeach; ?>
</table>
<p style="color:#666;font-size:12px">※「見る」を押すと、その条件で病害虫履歴にジャンプします。</p>

  </div>

</div>
</body>
</html>
