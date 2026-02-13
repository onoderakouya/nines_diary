<?php
declare(strict_types=1);

/**
 * Excelで文字化けしづらいCSV出力（UTF-8 BOM付き）
 */
function csv_download(string $filename): void {
  header('Content-Type: text/csv; charset=UTF-8');
  header('Content-Disposition: attachment; filename="' . rawurlencode($filename) . '"');
  header('Pragma: no-cache');
  header('Expires: 0');
  // UTF-8 BOM
  echo "\xEF\xBB\xBF";
}

/**
 * CSV 1行出力（fputcsvの代替）
 * ※ダブルクォート/改行/カンマを安全にエスケープ
 */
function csv_row(array $cols): void {
  $escaped = [];
  foreach ($cols as $c) {
    $s = (string)$c;
    $s = str_replace(["\r\n", "\r"], "\n", $s);
    $needQuote = (strpbrk($s, ",\"\n") !== false);
    $s = str_replace('"', '""', $s);
    $escaped[] = $needQuote ? "\"{$s}\"" : $s;
  }
  echo implode(',', $escaped) . "\n";
}
