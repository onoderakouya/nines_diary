<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/ui.php';
$pdo = db();
$u = requireLogin();

$today = date('Y-m-d');
$monthPrefix = date('Y-m-') . '%';

$todayMinutesStmt = $pdo->prepare('SELECT COALESCE(SUM(minutes), 0) FROM diary_entries WHERE user_id = ? AND date = ?');
$todayMinutesStmt->execute([(int)$u['id'], $today]);
$todayMinutes = (int)$todayMinutesStmt->fetchColumn();

$monthMinutesStmt = $pdo->prepare('SELECT COALESCE(SUM(minutes), 0) FROM diary_entries WHERE user_id = ? AND date LIKE ?');
$monthMinutesStmt->execute([(int)$u['id'], $monthPrefix]);
$monthMinutes = (int)$monthMinutesStmt->fetchColumn();

$fieldsCount = (int)$pdo->query('SELECT COUNT(*) FROM fields')->fetchColumn();
$cropsCount = (int)$pdo->query('SELECT COUNT(*) FROM crops')->fetchColumn();

require_once __DIR__ . '/weather.php';
$weatherData = fetchWeather($today);
$weatherCodeMap = [
  '0' => '晴れ',
  '1' => '晴れ',
  '2' => '晴れ時々くもり',
  '3' => 'くもり',
  '45' => '霧',
  '48' => '霧',
  '51' => '小雨',
  '53' => '雨',
  '55' => '強い雨',
  '56' => 'みぞれ',
  '57' => 'みぞれ',
  '61' => '小雨',
  '63' => '雨',
  '65' => '強い雨',
  '66' => 'みぞれ',
  '67' => 'みぞれ',
  '71' => '小雪',
  '73' => '雪',
  '75' => '大雪',
  '77' => '雪',
  '80' => 'にわか雨',
  '81' => '雨',
  '82' => '強いにわか雨',
  '85' => 'にわか雪',
  '86' => '雪',
  '95' => '雷雨',
  '96' => '雷雨',
  '99' => '雷雨',
];

$weatherCode = (string)($weatherData['weather_code'] ?? '');
$weatherLabel = $weatherCodeMap[$weatherCode] ?? '情報なし';
$weatherTemp = is_numeric($weatherData['temp_c'] ?? null) ? (string)round((float)$weatherData['temp_c']) . '℃' : '--℃';

function formatMinutesToHours(int $minutes): string {
  $hours = intdiv($minutes, 60);
  $mins = $minutes % 60;
  if ($hours <= 0) {
    return $mins . '分';
  }
  return $hours . '時間' . ($mins > 0 ? ' ' . $mins . '分' : '');
}
?>
<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <title>ホーム</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="app.css">
</head>
<body>

<?php renderGlobalTopbar($u); ?>


<div class="container dashboard-container">
  <section class="dashboard-hero card">
    <div class="dashboard-hero-content">
      <p class="dashboard-badge">Daily Farming Hub</p>
      <h1 class="dashboard-title">ダッシュボード</h1>
      <div class="dashboard-meta-row">
        <p class="dashboard-greeting">こんにちは、<?=e($u['name'])?>さん</p>
        <p class="dashboard-weather">今日の天気：<?=$weatherTemp?> <span aria-label="天気"><?=$weatherLabel?></span></p>
      </div>
    </div>
    <div class="dashboard-hero-glow" aria-hidden="true"></div>
  </section>

  <section class="dashboard-kpi-grid" aria-label="統計">
    <article class="card dashboard-kpi-card">
      <p class="dashboard-kpi-label">今日の作業</p>
      <p class="dashboard-kpi-value"><?=e(formatMinutesToHours($todayMinutes))?></p>
    </article>
    <article class="card dashboard-kpi-card">
      <p class="dashboard-kpi-label">圃場数</p>
      <p class="dashboard-kpi-value"><?=e((string)$fieldsCount)?> <span class="dashboard-kpi-unit">箇所</span></p>
    </article>
    <article class="card dashboard-kpi-card">
      <p class="dashboard-kpi-label">品目数</p>
      <p class="dashboard-kpi-value"><?=e((string)$cropsCount)?> <span class="dashboard-kpi-unit">品目</span></p>
    </article>
    <article class="card dashboard-kpi-card">
      <p class="dashboard-kpi-label">今月の作業時間</p>
      <p class="dashboard-kpi-value"><?=e(formatMinutesToHours($monthMinutes))?></p>
    </article>
  </section>

  <section class="dashboard-diary-row" aria-label="日誌メニュー">
    <div class="card dashboard-menu-card">
      <div class="dashboard-menu-title">🌱 日誌</div>
      <div class="actions dashboard-menu-actions">
        <a class="btn primary" href="diary_new.php">＋入力</a>
        <a class="btn" href="diary_list.php">一覧</a>
      </div>
    </div>
  </section>

  <section class="dashboard-submenu-grid" aria-label="メニュー補助">
    <div class="card dashboard-menu-card">
      <div class="dashboard-menu-title">🚚 出荷</div>
      <div class="actions dashboard-menu-actions">
        <a class="btn primary" href="shipment_new.php">＋入力</a>
        <a class="btn" href="shipment_list.php">一覧</a>
      </div>
    </div>

    <div class="card dashboard-menu-card">
      <div class="dashboard-menu-title">🧪 資材費</div>
      <div class="actions dashboard-menu-actions">
        <a class="btn primary" href="material_new.php">＋入力</a>
        <a class="btn" href="material_list.php">一覧</a>
      </div>
    </div>

    <div class="card dashboard-menu-card">
      <div class="dashboard-menu-title">🐛 病害虫</div>
      <div class="actions dashboard-menu-actions">
        <a class="btn primary" href="pest_new.php">＋入力</a>
        <a class="btn" href="pest_list.php">一覧</a>
      </div>
    </div>
  </section>

  <?php if (isAdmin($u)): ?>
    <div class="card dashboard-admin-card">
      <div class="dashboard-menu-title">管理者</div>
      <div class="actions">
        <a class="btn" href="summary.php">集計</a>
        <a class="btn" href="monthly_report.php">月次レポート</a>
        <a class="btn" href="admin_user_new.php">研修生追加</a>
        <a class="btn" href="admin_user_list.php">ユーザー一覧</a>
      </div>
    </div>
  <?php endif; ?>

  <p class="muted">畑で使う前提で、押しやすい大きさと余白に調整しています。<a href="guide.php">入力ガイド</a></p>
</div>

</body>
</html>
