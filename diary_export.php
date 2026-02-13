<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/csv.php';

$u = requireLogin();
$pdo = db();
$isAdmin = isAdmin($u);

$from = trim((string)($_GET['from'] ?? ''));
$to   = trim((string)($_GET['to'] ?? ''));
$userId = (int)($_GET['user_id'] ?? 0);

$field = (int)($_GET['field_id'] ?? 0);
$crop  = (int)($_GET['crop_id'] ?? 0);
$task  = (int)($_GET['task_id'] ?? 0);
$plot  = trim((string)($_GET['plot'] ?? ''));

$where = " WHERE 1=1 ";
$params = [];

if ($from !== '') { $where .= " AND d.date >= :from "; $params[':from'] = $from; }
if ($to   !== '') { $where .= " AND d.date <= :to ";   $params[':to']   = $to; }

if ($field) { $where .= " AND d.field_id = :field "; $params[':field'] = $field; }
if ($crop)  { $where .= " AND d.crop_id = :crop ";   $params[':crop']  = $crop; }
if ($task)  { $where .= " AND d.task_id = :task ";   $params[':task']  = $task; }
if ($plot !== '') { $where .= " AND d.plot = :plot "; $params[':plot'] = $plot; }

if ($isAdmin) {
  if ($userId) { $where .= " AND d.user_id = :uid "; $params[':uid'] = $userId; }
} else {
  $where .= " AND d.user_id = :uid ";
  $params[':uid'] = $u['id'];
}

$sql = "
SELECT
  d.date,
  u.name AS user_name,
  f.label AS field_label,
  COALESCE(d.plot,'') AS plot,
  c.name AS crop_name,
  t.name AS task_name,
  d.minutes,
  COALESCE(d.weather_code,'') AS weather_code,
  COALESCE(d.temp_c,'') AS temp_c,
  COALESCE(d.memo,'') AS memo,
  d.created_at
FROM diary_entries d
JOIN users u ON u.id = d.user_id
JOIN fields f ON f.id = d.field_id
JOIN crops  c ON c.id = d.crop_id
JOIN tasks  t ON t.id = d.task_id
{$where}
ORDER BY d.date DESC, d.id DESC
";

$st = $pdo->prepare($sql);
$st->execute($params);

$filename = 'diary_' . date('Ymd_His') . '.csv';
csv_download($filename);

csv_row(['日付','ユーザー','圃場','区画','品目','作業','分','天気コード','気温(max)','メモ','作成日時']);

while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
  csv_row([
    $r['date'],
    $r['user_name'],
    $r['field_label'],
    $r['plot'],
    $r['crop_name'],
    $r['task_name'],
    $r['minutes'],
    $r['weather_code'],
    $r['temp_c'],
    $r['memo'],
    $r['created_at'],
  ]);
}
exit;
