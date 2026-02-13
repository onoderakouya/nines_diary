<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
$u = requireLogin();
?>
<!doctype html>
<meta charset="utf-8">
<title>入力ガイド</title>

<h1>入力ガイド（研修生向け）</h1>
<p><a href="index.php">←ホーム</a></p>

<h2>日誌</h2>
<ul>
  <li>作業内容は「選択」を優先</li>
  <li>作業時間は分単位（30分→30）</li>
  <li>メモは短くてOK（気づきが大事）</li>
</ul>

<h2>病害虫</h2>
<ul>
  <li>まず「タグ」を選ぶ（分類用）</li>
  <li>詳細は見たままを短く</li>
  <li>写真があれば必ず撮る</li>
</ul>

<h2>出荷</h2>
<ul>
  <li>単位（箱 / kg）を間違えない</li>
  <li>数量は整数でOK</li>
</ul>

<h2>資材費</h2>
<ul>
  <li>資材名は候補から選ぶ</li>
  <li>金額は税込で入力</li>
</ul>

<p style="color:#666;font-size:13px">
※このデータは、後で出荷・病害虫・収量の分析に使われます。<br>
「きれいに書く」より「揃えて書く」を意識してください。
</p>
