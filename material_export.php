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

$where = " WHERE 1=1 ";
$params = [];

if ($from !== '') { $where .= " AND m.date >= :from "; $params[':from'] = $from; }
if ($to   !== '') { $where .= " AND m.date <= :to ";   $params[':to']   = $to; }

if ($field) { $where .= " AND m.field_id = :field "; $params[':field'] = $field; }
if ($crop)  { $where .= " AND m.crop_id  = :crop ";  $params[':crop']  = $crop; }

if ($isAdmin) {
  if ($userId) { $where .= " AND m.user_id = :uid "; $params[':uid'] = $userId; }
} else {
  $where .= " AND m.user_id = :uid ";
  $params[':uid'] = $u['id'];
}

$sql = "
SELECT
  m.date,
  u.name AS user_name,
  COALESCE(f.label,'') AS field_label,
  COALESCE(c.name,'') AS crop_name,
  m.item_name,
  COALESCE(m.amount,'') AS amount,
  COALESCE(m.unit,'') AS unit,
  m.cost_yen,
  COALESCE(m.note,'') AS note,
  m.created_at
FROM materials m
JOIN users u ON u.id = m.user_id
LEFT JOIN fields f ON f.id = m.field_id
LEFT JOIN crops  c ON c.id = m.crop_id
{$where}
ORDER BY m.date DESC, m.id DESC
";

$st = $pdo->prepare($sql);
$st->execute($params);

$filename = 'materials_' . date('Ymd_His') . '.csv';
csv_download($filename);

csv_row(['日付','ユーザー','圃場','品目','資材名','数量','単位','金額(円)','メモ','作成日時']);

while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
  csv_row([
    $r['date'],
    $r['user_name'],
    $r['field_label'],
    $r['crop_name'],
    $r['item_name'],
    $r['amount'],
    $r['unit'],
    $r['cost_yen'],
    $r['note'],
    $r['created_at'],
  ]);
}
exit;
