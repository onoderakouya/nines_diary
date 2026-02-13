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
$unit  = (string)($_GET['unit'] ?? '');

$where = " WHERE 1=1 ";
$params = [];

if ($from !== '') { $where .= " AND s.date >= :from "; $params[':from'] = $from; }
if ($to   !== '') { $where .= " AND s.date <= :to ";   $params[':to']   = $to; }

if ($field) { $where .= " AND s.field_id = :field "; $params[':field'] = $field; }
if ($crop)  { $where .= " AND s.crop_id = :crop ";   $params[':crop']  = $crop; }
if (in_array($unit, ['box','kg'], true)) { $where .= " AND s.unit = :unit "; $params[':unit'] = $unit; }

if ($isAdmin) {
  if ($userId) { $where .= " AND s.user_id = :uid "; $params[':uid'] = $userId; }
} else {
  $where .= " AND s.user_id = :uid ";
  $params[':uid'] = $u['id'];
}

$sql = "
SELECT
  s.date,
  u.name AS user_name,
  f.label AS field_label,
  COALESCE(s.plot,'') AS plot,
  c.name AS crop_name,
  s.qty,
  s.unit,
  COALESCE(s.memo,'') AS memo,
  s.created_at
FROM shipments s
JOIN users  u ON u.id = s.user_id
JOIN fields f ON f.id = s.field_id
JOIN crops  c ON c.id = s.crop_id
{$where}
ORDER BY s.date DESC, s.id DESC
";

$st = $pdo->prepare($sql);
$st->execute($params);

$filename = 'shipments_' . date('Ymd_His') . '.csv';
csv_download($filename);

csv_row(['日付','ユーザー','圃場','区画','品目','数量','単位','メモ','作成日時']);

while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
  csv_row([
    $r['date'],
    $r['user_name'],
    $r['field_label'],
    $r['plot'],
    $r['crop_name'],
    $r['qty'],
    $r['unit'],
    $r['memo'],
    $r['created_at'],
  ]);
}
exit;
