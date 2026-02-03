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
?>
<!doctype html>
<meta charset="utf-8">
<title>日誌実績</title>

<h1>日誌実績</h1>
<p><a href="index.php">←ホーム</a> / <a href="diary_new.php">＋日誌入力</a></p>

<form method="get">
  <label>From <input type="date" name="from" value="<?=e($from)?>"></label>
  <label>To <input type="date" name="to" value="<?=e($to)?>"></label><br><br>

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

  <select name="task_id">
    <option value="0">作業：すべて</option>
    <?php foreach ($tasks as $t): ?>
      <option value="<?= (int)$t['id'] ?>" <?= $task===(int)$t['id']?'selected':'' ?>>
        <?= e($t['name']) ?>
      </option>
    <?php endforeach; ?>
  </select>

  <label style="margin-left:8px;">
    区画 <input type="text" name="plot" value="<?=e($plot)?>" placeholder="例：区画1" style="width:120px">
  </label>

  <button>絞り込み</button>
</form>

<hr>

<?php foreach ($rows as $r): ?>
  <div style="padding:10px 0;border-bottom:1px solid #ddd">
    <div>
      <b><?=e($r['date'])?></b>
      / <?=e($r['field_label'])?>
      <?php if (!empty($r['plot'])): ?> / <?=e((string)$r['plot'])?><?php endif; ?>
      / <?=e($r['crop_name'])?>
      / <?=e($r['task_name'])?>
    </div>
    <div>作業時間: <?= (int)$r['minutes'] ?> 分 / 天気コード: <?=e((string)($r['weather_code'] ?? ''))?> / 気温(max): <?=e((string)($r['temp_c'] ?? ''))?></div>
    <?php if (!empty($r['memo'])): ?><div><?=nl2br(e((string)$r['memo']))?></div><?php endif; ?>
  </div>
<?php endforeach; ?>
