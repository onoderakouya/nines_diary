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
$tag   = trim((string)($_GET['tag'] ?? ''));

$where = " WHERE 1=1 ";
$params = [];

if ($from !== '') { $where .= " AND p.date >= :from "; $params[':from'] = $from; }
if ($to   !== '') { $where .= " AND p.date <= :to ";   $params[':to']   = $to; }

if ($field) { $where .= " AND p.field_id = :field "; $params[':field'] = $field; }
if ($crop)  { $where .= " AND p.crop_id  = :crop ";  $params[':crop']  = $crop; }
if ($tag !== '') { $where .= " AND p.symptom_tag = :tag "; $params[':tag'] = $tag; }

if ($isAdmin) {
  if ($userId) { $where .= " AND p.user_id = :uid "; $params[':uid'] = $userId; }
} else {
  $where .= " AND p.user_id = :uid ";
  $params[':uid'] = $u['id'];
}

$sql = "
SELECT
  p.date,
  u.name AS user_name,
  f.label AS field_label,
  c.name AS crop_name,
  COALESCE(p.symptom_tag,'') AS symptom_tag,
  COALESCE(p.symptom_text,'') AS symptom_text,
  COALESCE(p.action_text,'') AS action_text,
  COALESCE(p.photo_path,'') AS photo_path,
  p.created_at
FROM pests p
JOIN users  u ON u.id = p.user_id
JOIN fields f ON f.id = p.field_id
JOIN crops  c ON c.id = p.crop_id
{$where}
ORDER BY p.date DESC, p.id DESC
";

$st = $pdo->prepare($sql);
$st->execute($params);

$filename = 'pests_' . date('Ymd_His') . '.csv';
csv_download($filename);

csv_row(['日付','ユーザー','圃場','品目','タグ','症状','対応','写真パス','作成日時']);

while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
  csv_row([
    $r['date'],
    $r['user_name'],
    $r['field_label'],
    $r['crop_name'],
    $r['symptom_tag'],
    $r['symptom_text'],
    $r['action_text'],
    $r['photo_path'],
    $r['created_at'],
  ]);
}
exit;
