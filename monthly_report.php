<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
$u = requireLogin();
requireAdmin();
$pdo = db();

function ymd(string $s): string { return $s; }

// 対象月（YYYY-MM）。未指定なら当月
$ym = trim((string)($_GET['ym'] ?? date('Y-m')));
if (!preg_match('/^\d{4}-\d{2}$/', $ym)) $ym = date('Y-m');

$from = $ym . '-01';
$to   = $ym . '-31'; // SQLiteなのでざっくり末日。WHEREは文字列比較でもOK（YYYY-MM-DD）

// 期間パラメータ
$params = [':from'=>$from, ':to'=>$to];

// ---------- 出荷（数量：箱/kg 別） ----------
$shipByCropUnit = $pdo->prepare("
  SELECT
    c.name AS crop_name,
    s.unit,
    SUM(s.quantity) AS total_qty,
    COUNT(*) AS cnt
  FROM shipments s
  JOIN crops c ON c.id = s.crop_id
  WHERE s.date >= :from AND s.date <= :to
  GROUP BY s.crop_id, s.unit
  ORDER BY c.name ASC, s.unit ASC
");
$shipByCropUnit->execute($params);
$shipRows = $shipByCropUnit->fetchAll();

// ---------- 資材費（合計/資材名トップ） ----------
$matTotal = $pdo->prepare("
  SELECT COALESCE(SUM(cost_yen),0) AS total_yen
  FROM materials
  WHERE date >= :from AND date <= :to
");
$matTotal->execute($params);
$matTotalYen = (int)($matTotal->fetchColumn() ?: 0);

$matTop = $pdo->prepare("
  SELECT item_name, SUM(cost_yen) AS yen, COUNT(*) AS cnt
  FROM materials
  WHERE date >= :from AND date <= :to
  GROUP BY item_name
  ORDER BY yen DESC
  LIMIT 10
");
$matTop->execute($params);
$matTopRows = $matTop->fetchAll();

// ---------- 病害虫（タグトップ/圃場×タグトップ） ----------
$pestTop = $pdo->prepare("
  SELECT
    COALESCE(NULLIF(trim(symptom_tag),''), '（未設定）') AS tag,
    COUNT(*) AS cnt
  FROM pests
  WHERE date >= :from AND date <= :to
  GROUP BY tag
  ORDER BY cnt DESC
  LIMIT 10
");
$pestTop->execute($params);
$pestTopRows = $pestTop->fetchAll();

$pestFieldTop = $pdo->prepare("
  SELECT
    f.label AS field_label,
    f.id AS field_id,
    COALESCE(NULLIF(trim(p.symptom_tag),''), '（未設定）') AS tag,
    COUNT(*) AS cnt
  FROM pests p
  JOIN fields f ON f.id = p.field_id
  WHERE p.date >= :from AND p.date <= :to
  GROUP BY p.field_id, tag
  ORDER BY cnt DESC
  LIMIT 10
");
$pestFieldTop->execute($params);
$pestFieldTopRows = $pestFieldTop->fetchAll();

// ---------- 日誌（作業時間トップ：作業/品目） ----------
$diaryTotalMin = $pdo->prepare("
  SELECT COALESCE(SUM(minutes),0) AS total_min
  FROM diary_entries
  WHERE date >= :from AND date <= :to
");
$diaryTotalMin->execute($params);
$totalMin = (int)($diaryTotalMin->fetchColumn() ?: 0);

$diaryByTask = $pdo->prepare("
  SELECT
    t.name AS task_name,
    SUM(d.minutes) AS total_min,
    COUNT(*) AS cnt
  FROM diary_entries d
  JOIN tasks t ON t.id = d.task_id
  WHERE d.date >= :from AND d.date <= :to
  GROUP BY d.task_id
  ORDER BY total_min DESC
  LIMIT 10
");
$diaryByTask->execute($params);
$diaryByTaskRows = $diaryByTask->fetchAll();

$diaryByCrop = $pdo->prepare("
  SELECT
    c.name AS crop_name,
    SUM(d.minutes) AS total_min,
    COUNT(*) AS cnt
  FROM diary_entries d
  JOIN crops c ON c.id = d.crop_id
  WHERE d.date >= :from AND d.date <= :to
  GROUP BY d.crop_id
  ORDER BY total_min DESC
  LIMIT 10
");
$diaryByCrop->execute($params);
$diaryByCropRows = $diaryByCrop->fetchAll();

// 便利リンク（この月でフィルタ済みの一覧へ）
$linkDiary    = "diary_list.php?from=".urlencode($from)."&to=".urlencode($to);
$linkShip     = "shipment_list.php?from=".urlencode($from)."&to=".urlencode($to);
$linkMaterial = "material_list.php?from=".urlencode($from)."&to=".urlencode($to);
$linkPest     = "pest_list.php?from=".urlencode($from)."&to=".urlencode($to);
?>
<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <title>月次レポート <?=e($ym)?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="app.css">

  <style>
    body{max-width:980px;margin:0 auto;padding:12px;font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}
    table{border-collapse:collapse;width:100%}
    th,td{border:1px solid #ddd;padding:6px;font-size:14px}
    th{background:#f6f8fa;text-align:left}
    .kpi{display:flex;gap:12px;flex-wrap:wrap;margin:12px 0}
    .card{border:1px solid #ddd;border-radius:8px;padding:10px;min-width:220px;background:#fff}
    .muted{color:#666;font-size:12px}
    .row{display:flex;gap:10px;flex-wrap:wrap;align-items:center}
    a{color:#0366d6;text-decoration:none}
    a:hover{text-decoration:underline}
  </style>
</head>
<body>
  <h1>月次レポート <?=e($ym)?></h1>
  <p class="row">
    <a href="index.php">←ホーム</a>
    <span class="muted">対象期間：<?=e($from)?> 〜 <?=e($to)?></span>
  </p>

  <form method="get" class="row" style="margin:12px 0">
    <label>対象月（YYYY-MM）
      <input name="ym" value="<?=e($ym)?>" placeholder="例：2026-02" style="width:120px">
    </label>
    <button>表示</button>
  </form>

  <div class="kpi">
    <div class="card">
      <div class="muted">作業時間 合計</div>
      <div style="font-size:22px"><b><?= number_format((int)round($totalMin/60)) ?></b> 時間</div>
      <div class="muted"><?= number_format($totalMin) ?> 分</div>
      <div class="muted"><a href="<?=e($linkDiary)?>">日誌一覧（この月）</a></div>
    </div>
    <div class="card">
      <div class="muted">資材費 合計</div>
      <div style="font-size:22px"><b>¥<?= number_format($matTotalYen) ?></b></div>
      <div class="muted"><a href="<?=e($linkMaterial)?>">資材費一覧（この月）</a></div>
    </div>
    <div class="card">
      <div class="muted">病害虫 記録件数</div>
      <div style="font-size:22px"><b><?= number_format(array_sum(array_map(fn($r)=>(int)$r['cnt'],$pestTopRows))) ?></b> 件</div>
      <div class="muted"><a href="<?=e($linkPest)?>">病害虫一覧（この月）</a></div>
    </div>
  </div>

  <h2>出荷（品目×単位）</h2>
  <p class="muted"><a href="<?=e($linkShip)?>">出荷一覧（この月）</a></p>
  <table>
    <tr><th>品目</th><th>単位</th><th>数量合計</th><th>入力件数</th></tr>
    <?php foreach ($shipRows as $r): ?>
      <tr>
        <td><?=e((string)$r['crop_name'])?></td>
        <td><?=e((string)$r['unit'])?></td>
        <td><?=e((string)$r['total_qty'])?></td>
        <td><?=e((string)$r['cnt'])?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$shipRows): ?>
      <tr><td colspan="4" class="muted">この月の出荷データはありません</td></tr>
    <?php endif; ?>
  </table>

  <h2>資材費トップ10（資材名）</h2>
  <table>
    <tr><th>資材名</th><th>合計(円)</th><th>回数</th></tr>
    <?php foreach ($matTopRows as $r): ?>
      <tr>
        <td><?=e((string)$r['item_name'])?></td>
        <td>¥<?=number_format((int)$r['yen'])?></td>
        <td><?= (int)$r['cnt'] ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$matTopRows): ?>
      <tr><td colspan="3" class="muted">この月の資材費データはありません</td></tr>
    <?php endif; ?>
  </table>

  <h2>病害虫トップ10（タグ）</h2>
  <table>
    <tr><th>タグ</th><th>件数</th><th>履歴</th></tr>
    <?php foreach ($pestTopRows as $r): ?>
      <?php
        $tag = (string)$r['tag'];
        $lnk = "pest_list.php?from=".urlencode($from)."&to=".urlencode($to)."&tag=".urlencode($tag);
      ?>
      <tr>
        <td><?=e($tag)?></td>
        <td><?= (int)$r['cnt'] ?></td>
        <td><a href="<?=e($lnk)?>">見る</a></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$pestTopRows): ?>
      <tr><td colspan="3" class="muted">この月の病害虫データはありません</td></tr>
    <?php endif; ?>
  </table>

  <h2>病害虫トップ10（圃場×タグ）</h2>
  <table>
    <tr><th>圃場</th><th>タグ</th><th>件数</th><th>履歴</th></tr>
    <?php foreach ($pestFieldTopRows as $r): ?>
      <?php
        $tag = (string)$r['tag'];
        $fieldId = (int)$r['field_id'];
        $lnk = "pest_list.php?from=".urlencode($from)."&to=".urlencode($to)."&field_id=".$fieldId."&tag=".urlencode($tag);
      ?>
      <tr>
        <td><?=e((string)$r['field_label'])?></td>
        <td><?=e($tag)?></td>
        <td><?= (int)$r['cnt'] ?></td>
        <td><a href="<?=e($lnk)?>">見る</a></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$pestFieldTopRows): ?>
      <tr><td colspan="4" class="muted">この月の病害虫データはありません</td></tr>
    <?php endif; ?>
  </table>

  <h2>日誌：作業時間トップ10（作業）</h2>
  <table>
    <tr><th>作業</th><th>合計(分)</th><th>合計(時間)</th><th>件数</th></tr>
    <?php foreach ($diaryByTaskRows as $r): ?>
      <tr>
        <td><?=e((string)$r['task_name'])?></td>
        <td><?= (int)$r['total_min'] ?></td>
        <td><?= number_format(((int)$r['total_min'])/60, 1) ?></td>
        <td><?= (int)$r['cnt'] ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$diaryByTaskRows): ?>
      <tr><td colspan="4" class="muted">この月の日誌データはありません</td></tr>
    <?php endif; ?>
  </table>

  <h2>日誌：作業時間トップ10（品目）</h2>
  <table>
    <tr><th>品目</th><th>合計(分)</th><th>合計(時間)</th><th>件数</th></tr>
    <?php foreach ($diaryByCropRows as $r): ?>
      <tr>
        <td><?=e((string)$r['crop_name'])?></td>
        <td><?= (int)$r['total_min'] ?></td>
        <td><?= number_format(((int)$r['total_min'])/60, 1) ?></td>
        <td><?= (int)$r['cnt'] ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$diaryByCropRows): ?>
      <tr><td colspan="4" class="muted">この月の日誌データはありません</td></tr>
    <?php endif; ?>
  </table>

  <p class="muted" style="margin-top:18px">
    ※「月次レポート」は管理者だけ閲覧できます。<br>
    月初にこのページを開くだけで、先月の概況がまとまって見えます。
  </p>
</body>
</html>
