<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
$u = requireLogin();
$pdo = db();

$where = " WHERE d.user_id = :uid ";
$params = [':uid'=>$u['id']];

$from  = $_GET['from'] ?? '';
$to    = $_GET['to'] ?? '';
$field = (int)($_GET['field_id'] ?? 0);
$crop  = (int)($_GET['crop_id'] ?? 0);
$task  = (int)($_GET['task_id'] ?? 0);
$plot  = trim((string)($_GET['plot'] ?? ''));

if ($from)  { $where .= " AND d.date >= :from ";     $params[':from']  = $from; }
if ($to)    { $where .= " AND d.date <= :to ";       $params[':to']    = $to; }
if ($field) { $where .= " AND d.field_id = :field "; $params[':field'] = $field; }
if ($crop)  { $where .= " AND d.crop_id = :crop ";   $params[':crop']  = $crop; }
if ($task)  { $where .= " AND d.task_id = :task ";   $params[':task']  = $task; }
if ($plot !== '') { $where .= " AND d.plot = :plot "; $params[':plot'] = $plot; } // 完全一致

$fields = $pdo->query("SELECT id,label FROM fields ORDER BY label")->fetchAll();
$crops  = $pdo->query("SELECT id,name FROM crops ORDER BY id")->fetchAll();
$tasks  = $pdo->query("SELECT id,name FROM tasks ORDER BY id")->fetchAll();

$sql = "
SELECT d.*, f.label as field_label, c.name as crop_name, t.name as task_name
FROM diary_entries d
JOIN fields f ON f.id=d.field_id
JOIN crops c ON c.id=d.crop_id
JOIN tasks t ON t.id=d.task_id
{$where}
ORDER BY d.date DESC, d.id DESC
LIMIT 200
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

// CSVリンク（絞り込み条件を引き継ぐ）
$csv = "diary_export.php"
  . "?from=" . urlencode((string)$from)
  . "&to=" . urlencode((string)$to)
  . "&field_id=" . (int)$field
  . "&crop_id=" . (int)$crop
  . "&task_id=" . (int)$task
  . "&plot=" . urlencode((string)$plot);
?>
<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <title>日誌実績</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="app.css">
</head>
<body>

<div class="topbar">
  <div class="topbar-inner">
    <div class="title">日誌実績</div>
    <div class="actions">
      <a class="btn primary" href="diary_new.php">＋入力</a>
      <a class="btn" href="<?=e($csv)?>">CSV</a>
      <a class="btn ghost" href="index.php">ホーム</a>
    </div>
  </div>
</div>

<div class="container">

  <div class="card">
    <form method="get">
      <div class="grid">
        <div>
          <label>From</label>
          <input type="date" name="from" value="<?=e((string)$from)?>">
        </div>
        <div>
          <label>To</label>
          <input type="date" name="to" value="<?=e((string)$to)?>">
        </div>

        <div>
          <label>圃場</label>
          <select name="field_id">
            <option value="0">すべて</option>
            <?php foreach ($fields as $f): ?>
              <option value="<?= (int)$f['id'] ?>" <?= $field===(int)$f['id']?'selected':'' ?>>
                <?= e($f['label']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div>
          <label>品目</label>
          <select name="crop_id">
            <option value="0">すべて</option>
            <?php foreach ($crops as $c): ?>
              <option value="<?= (int)$c['id'] ?>" <?= $crop===(int)$c['id']?'selected':'' ?>>
                <?= e($c['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div>
          <label>作業</label>
          <select name="task_id">
            <option value="0">すべて</option>
            <?php foreach ($tasks as $t): ?>
              <option value="<?= (int)$t['id'] ?>" <?= $task===(int)$t['id']?'selected':'' ?>>
                <?= e($t['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div>
          <label>区画（完全一致）</label>
          <input type="text" name="plot" value="<?=e((string)$plot)?>" placeholder="例：区画1">
        </div>
      </div>

      <div style="margin-top:12px;display:flex;gap:10px;flex-wrap:wrap">
        <button class="btn primary" type="submit">絞り込み</button>
        <a class="btn" href="diary_list.php">リセット</a>
      </div>

      <div class="hint" style="margin-top:8px">
        ※区画は「区画1」のように表記が揃うと検索が強くなります
      </div>
    </form>
  </div>

  <div class="card" style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;align-items:center">
    <div>
      <div class="muted">表示件数</div>
      <div style="font-size:18px;font-weight:900"><?= number_format(count($rows)) ?> 件</div>
    </div>
    <div class="muted">
      ※最新200件まで
    </div>
  </div>

  <?php if (!$rows): ?>
    <div class="card">
      <div class="muted">該当データがありません。</div>
    </div>
  <?php endif; ?>

  <?php foreach ($rows as $r): ?>
    <div class="card">
      <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;align-items:flex-start">
        <div>
          <div style="font-size:16px;font-weight:900">
            <?=e((string)$r['date'])?>
          </div>
          <div class="kv">
            <?=e((string)$r['field_label'])?>
            <?php if (!empty($r['plot'])): ?> / <?=e((string)$r['plot'])?><?php endif; ?>
            / <?=e((string)$r['crop_name'])?>
          </div>
        </div>

        <div style="text-align:right">
          <div class="badge"><?=e((string)$r['task_name'])?></div>
          <div style="margin-top:6px;font-size:18px;font-weight:900">
            <?= (int)$r['minutes'] ?> 分
          </div>
          <?php if (!empty($r['temp_c'])): ?>
            <div class="muted">気温 <?=e((string)$r['temp_c'])?>℃</div>
          <?php endif; ?>
        </div>
      </div>

      <?php if (!empty($r['weather']) || !empty($r['weather_code'])): ?>
        <div class="muted" style="margin-top:8px">
          天気：<?= e((string)($r['weather'] ?? '')) ?>
          <?php if (!empty($r['weather_code'])): ?>（code: <?=e((string)$r['weather_code'])?>）<?php endif; ?>
        </div>
      <?php endif; ?>

      <?php if (!empty($r['memo'])): ?>
        <div style="margin-top:10px">
          <div class="muted" style="font-weight:700;margin-bottom:4px">メモ</div>
          <div><?= nl2br(e((string)$r['memo'])) ?></div>
        </div>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>

</div>
<script src="app.js"></script>

</body>
</html>
