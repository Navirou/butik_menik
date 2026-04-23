<?php
/**
 * includes/layout-admin.php
 * Shared header + sidebar for Owner, Staff, Supplier panels.
 *
 * Required vars before include:
 *   $pageTitle  – string, shown in <title> and topbar
 *   $activeMenu – string, matches a bm-nav-item's data-menu attribute
 *   $role       – 'owner' | 'staff' | 'supplier'
 *   $user       – currentUser() array
 *   $notifCount – (optional) int unread notifications
 */

$notifCount = $notifCount ?? 0;
$roleLabels = ['owner' => 'Owner Panel', 'staff' => 'Staff Produksi', 'supplier' => 'Supplier Portal'];
$roleLabel  = $roleLabels[$role ?? 'owner'] ?? 'Panel';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($pageTitle ?? 'Dashboard') ?> – <?= APP_NAME ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,400&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="<?= APP_URL ?>/assets/css/global.css" rel="stylesheet">
</head>
<body>

<!-- Sidebar Overlay (mobile) -->
<div class="bm-sidebar-overlay" id="bm-overlay"></div>

<!-- ═══ SIDEBAR ═══════════════════════════════════════════════ -->
<aside class="bm-sidebar" id="bm-sidebar">
  <div class="bm-sidebar-brand">
    <div class="app-name"><i class="bi bi-scissors me-2"></i><?= APP_NAME ?></div>
    <div class="app-role"><?= $roleLabel ?></div>
  </div>
  <nav class="bm-sidebar-nav">

  <?php if (($role ?? '') === 'owner'): ?>
    <span class="bm-nav-section">Utama</span>
    <a href="<?= APP_URL ?>/modules/owner/dashboard.php"     class="bm-nav-item" data-menu="dashboard">    <i class="bi bi-speedometer2"></i>Dashboard</a>
    <a href="<?= APP_URL ?>/modules/owner/orders.php"        class="bm-nav-item" data-menu="orders">       <i class="bi bi-bag-fill"></i>Semua Pesanan</a>
    <a href="<?= APP_URL ?>/modules/owner/production.php"    class="bm-nav-item" data-menu="production">   <i class="bi bi-tools"></i>Monitor Produksi</a>
    <span class="bm-nav-section">Keuangan</span>
    <a href="<?= APP_URL ?>/modules/owner/payments.php"      class="bm-nav-item" data-menu="payments">     <i class="bi bi-credit-card-fill"></i>Pembayaran</a>
    <a href="<?= APP_URL ?>/modules/owner/reports.php"       class="bm-nav-item" data-menu="reports">      <i class="bi bi-bar-chart-fill"></i>Laporan</a>
    <span class="bm-nav-section">Operasional</span>
    <a href="<?= APP_URL ?>/modules/owner/stock.php"         class="bm-nav-item" data-menu="stock">        <i class="bi bi-boxes"></i>Stok Bahan</a>
    <a href="<?= APP_URL ?>/modules/owner/suppliers.php"     class="bm-nav-item" data-menu="suppliers">    <i class="bi bi-truck-flatbed"></i>Supplier</a>
    <span class="bm-nav-section">Sistem</span>
    <a href="<?= APP_URL ?>/modules/owner/users.php"         class="bm-nav-item" data-menu="users">        <i class="bi bi-people-fill"></i>Manajemen User</a>

  <?php elseif (($role ?? '') === 'staff'): ?>
    <span class="bm-nav-section">Produksi</span>
    <a href="<?= APP_URL ?>/modules/staff/dashboard.php"     class="bm-nav-item" data-menu="dashboard">    <i class="bi bi-speedometer2"></i>Dashboard</a>
    <a href="<?= APP_URL ?>/modules/staff/orders.php"        class="bm-nav-item" data-menu="orders">       <i class="bi bi-bag-fill"></i>Antrian Pesanan</a>
    <a href="<?= APP_URL ?>/modules/staff/production.php"    class="bm-nav-item" data-menu="production">   <i class="bi bi-tools"></i>Update Produksi</a>
    <a href="<?= APP_URL ?>/modules/staff/qc.php"            class="bm-nav-item" data-menu="qc">           <i class="bi bi-clipboard-check-fill"></i>Quality Control</a>
    <span class="bm-nav-section">Inventori</span>
    <a href="<?= APP_URL ?>/modules/staff/stock.php"         class="bm-nav-item" data-menu="stock">        <i class="bi bi-boxes"></i>Cek Stok</a>

  <?php elseif (($role ?? '') === 'supplier'): ?>
    <span class="bm-nav-section">Portal</span>
    <a href="<?= APP_URL ?>/modules/supplier/dashboard.php"  class="bm-nav-item" data-menu="dashboard">    <i class="bi bi-speedometer2"></i>Dashboard</a>
    <a href="<?= APP_URL ?>/modules/supplier/requests.php"   class="bm-nav-item" data-menu="requests">     <i class="bi bi-list-task"></i>Permintaan Bahan</a>
    <a href="<?= APP_URL ?>/modules/supplier/invoices.php"   class="bm-nav-item" data-menu="invoices">     <i class="bi bi-file-earmark-text-fill"></i>Faktur Saya</a>
  <?php endif; ?>

    <div class="bm-divider mt-3"></div>
    <a href="<?= APP_URL ?>/logout.php" class="bm-nav-item" style="color:rgba(239,68,68,.8)">
      <i class="bi bi-box-arrow-right" style="color:rgba(239,68,68,.7)"></i>Keluar
    </a>
  </nav>
</aside>

<!-- ═══ MAIN WRAPPER ══════════════════════════════════════════ -->
<div class="bm-main-wrapper">

  <!-- Topbar -->
  <header class="bm-topbar">
    <button class="bm-hamburger" onclick="bmToggleSidebar()"><i class="bi bi-list"></i></button>
    <span class="bm-topbar-title"><?= htmlspecialchars($pageTitle ?? 'Dashboard') ?></span>
    <div class="bm-topbar-actions">
      <a href="<?= APP_URL ?>/modules/<?= $role ?>/notifications.php" class="bm-topbar-icon-btn" title="Notifikasi">
        <i class="bi bi-bell"></i>
        <?php if ($notifCount > 0): ?><span class="bm-notif-dot"></span><?php endif; ?>
      </a>
      <a href="<?= APP_URL ?>/modules/<?= $role ?>/profile.php" class="bm-user-chip">
        <span class="avatar"><?= strtoupper(mb_substr($user['name'] ?? 'U', 0, 1)) ?></span>
        <span class="d-none d-sm-inline"><?= htmlspecialchars(explode(' ', $user['name'] ?? '')[0]) ?></span>
      </a>
    </div>
  </header>

  <!-- Page Content starts here -->
  <main class="bm-content">

<?php
// Set active nav item
echo "<script>
  document.addEventListener('DOMContentLoaded',()=>{
    document.querySelectorAll('.bm-nav-item[data-menu]').forEach(el=>{
      el.classList.toggle('active', el.dataset.menu === " . json_encode($activeMenu ?? '') . ");
    });
  });
</script>";
?>
