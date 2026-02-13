<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';

$u = requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo 'Method Not Allowed';
  exit;
}

if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
  setFlash('error', '不正なリクエストです。時間をおいて再度お試しください。');
  header('Location: index.php');
  exit;
}

$type = (string)($_POST['type'] ?? '');
$id = (int)($_POST['id'] ?? 0);
$redirect = (string)($_POST['redirect'] ?? 'index.php');

$targets = [
  'diary' => ['table' => 'diary_entries', 'redirect' => 'diary_list.php'],
  'shipment' => ['table' => 'shipments', 'redirect' => 'shipment_list.php'],
  'material' => ['table' => 'materials', 'redirect' => 'material_list.php'],
  'pest' => ['table' => 'pests', 'redirect' => 'pest_list.php'],
];

if (!isset($targets[$type]) || $id <= 0) {
  setFlash('error', '削除対象の指定が不正です。');
  header('Location: index.php');
  exit;
}

$allowedRedirects = [
  'diary_list.php',
  'shipment_list.php',
  'material_list.php',
  'pest_list.php',
];
if (!in_array($redirect, $allowedRedirects, true)) {
  $redirect = $targets[$type]['redirect'];
}

$pdo = db();
$sql = sprintf('DELETE FROM %s WHERE user_id = :uid AND id = :id', $targets[$type]['table']);
$stmt = $pdo->prepare($sql);
$stmt->execute([
  ':uid' => $u['id'],
  ':id' => $id,
]);

if ($stmt->rowCount() > 0) {
  setFlash('success', 'データを削除しました。');
} else {
  setFlash('error', '削除できませんでした。対象が存在しないか、権限がありません。');
}

header('Location: ' . $redirect);
exit;
