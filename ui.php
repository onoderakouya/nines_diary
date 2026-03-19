<?php
declare(strict_types=1);

function currentNavKey(): string {
  $script = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));

  if ($script === 'index.php') {
    return 'dashboard';
  }
  if (str_starts_with($script, 'diary_')) {
    return 'diary';
  }
  if (str_starts_with($script, 'shipment_')) {
    return 'shipment';
  }
  if (str_starts_with($script, 'material_')) {
    return 'material';
  }
  if (str_starts_with($script, 'pest_')) {
    return 'pest';
  }

  return '';
}

function renderGlobalTopbar(array $u): void {
  $active = currentNavKey();
  $isDashboard = $active === 'dashboard';
  $isDiary = $active === 'diary';
  $isShipment = $active === 'shipment';
  $isMaterial = $active === 'material';
  $isPest = $active === 'pest';
  ?>
  <div class="topbar dashboard-topbar">
    <div class="topbar-inner">
      <a class="dashboard-brand" href="index.php" aria-label="NINE'S DIARY ホーム">
        <img src="assets/logo_top.png" alt="NINE'S DIARY" class="dashboard-brand-logo" onerror="this.style.display='none';this.nextElementSibling.style.display='inline';">
        <span class="dashboard-brand-fallback" style="display:none;">NINE'S DIARY</span>
      </a>
      <nav class="dashboard-nav" aria-label="グローバルナビゲーション">
        <a class="dashboard-nav-item <?=$isDashboard ? 'is-active' : ''?>" href="index.php">ダッシュボード</a>
        <a class="dashboard-nav-item <?=$isDiary ? 'is-active' : ''?>" href="diary_list.php">日誌</a>
        <a class="dashboard-nav-item <?=$isShipment ? 'is-active' : ''?>" href="shipment_list.php">出荷</a>
        <a class="dashboard-nav-item <?=$isMaterial ? 'is-active' : ''?>" href="material_list.php">資材費</a>
        <a class="dashboard-nav-item <?=$isPest ? 'is-active' : ''?>" href="pest_list.php">病害虫</a>
      </nav>
      <div class="dashboard-user-menu">
        <span class="dashboard-user-icon" aria-hidden="true">👤</span>
        <span class="dashboard-user-name"><?=e($u['name'])?></span>
        <span class="dashboard-user-arrow" aria-hidden="true">▼</span>
        <div class="dashboard-user-dropdown">
          <a href="password_change.php">PW変更</a>
          <a href="logout.php">ログアウト</a>
        </div>
      </div>
    </div>
  </div>
  <?php
}
