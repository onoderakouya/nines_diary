<?php
declare(strict_types=1);

function handleMultiUpload(string $fieldName): array {
  $out = [];
  if (empty($_FILES[$fieldName])) return $out;

  $names = $_FILES[$fieldName]['name'];
  $tmp   = $_FILES[$fieldName]['tmp_name'];
  $errs  = $_FILES[$fieldName]['error'];

  for ($i=0; $i<count($names); $i++) {
    if ($errs[$i] !== UPLOAD_ERR_OK) continue;

    $ext = strtolower(pathinfo($names[$i], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg','jpeg','png','webp','gif'], true)) continue;

    $fname = bin2hex(random_bytes(16)) . '.' . $ext;
    $destRel = 'uploads/' . $fname;
    $destAbs = __DIR__ . '/' . $destRel;

    if (move_uploaded_file($tmp[$i], $destAbs)) $out[] = $destRel;
  }
  return $out;
}
