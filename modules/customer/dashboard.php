<?php
require_once __DIR__ . '/../../includes/auth.php';
requireLogin([ROLE_CUSTOMER]);
$user = currentUser();

// ── Fetch customer data ───────────────────────────────────────────────────────
// Recent orders
$ordStmt = db()->prepare(
    'SELECT o.order_code, o.status, o.total_price, o.dp_amount, o.created_at, o.pickup_date,
            p.name AS product_name
     FROM orders o
     LEFT JOIN products p ON o.product_id = p.id
     WHERE o.customer_id = ?
     ORDER BY o.created_at DESC LIMIT 6'
);
$ordStmt->execute([$user['id']]);
$orders = $ordStmt->fetchAll();

// Catalog
$catStmt = db()->query(
    'SELECT pr.*, pc.name AS category_name
     FROM products pr
     LEFT JOIN product_categories pc ON pr.category_id = pc.id
     WHERE pr.is_active = 1 ORDER BY pr.id DESC LIMIT 12'
);
$products = $catStmt->fetchAll();

// Unread notifications count
$notifStmt = db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0');
$notifStmt->execute([$user['id']]);
$unreadCount = (int)$notifStmt->fetchColumn();

// Status label helper
$statusLabels = [
    'menunggu_konfirmasi' => ['label'=>'Menunggu Konfirmasi','color'=>'warning'],
    'dikonfirmasi'        => ['label'=>'Dikonfirmasi',       'color'=>'info'],
    'ditolak'             => ['label'=>'Ditolak',            'color'=>'danger'],
    'dp_menunggu'         => ['label'=>'DP Menunggu',        'color'=>'warning'],
    'dp_diverifikasi'     => ['label'=>'DP Terverifikasi',   'color'=>'success'],
    'pemeriksaan_stok'    => ['label'=>'Cek Stok',           'color'=>'info'],
    'produksi'            => ['label'=>'Produksi',           'color'=>'primary'],
    'qc'                  => ['label'=>'QC',                 'color'=>'secondary'],
    'jadwal_ambil'        => ['label'=>'Siap Diambil',       'color'=>'success'],
    'selesai'             => ['label'=>'Selesai',            'color'=>'success'],
    'revisi'              => ['label'=>'Revisi',             'color'=>'warning'],
    'dibatalkan'          => ['label'=>'Dibatalkan',         'color'=>'danger'],
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<meta name="theme-color" content="#003366">
<title>Dashboard Pelanggan – <?= APP_NAME ?></title>

<!-- Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,400&display=swap" rel="stylesheet">

<!-- Bootstrap 5 -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

<style>
/* ─── Design Tokens ─────────────────────────────────── */
:root {
  --navy:       #003366;
  --navy-dark:  #001f40;
  --navy-mid:   #004080;
  --ivory:      #FFFFE0;
  --ivory-dim:  #FFFFC8;
  --gold:       #C9A84C;
  --gold-light: #E8C97A;
  --surface:    #f5f6fa;
  --card-bg:    #ffffff;
  --text-main:  #1a1d23;
  --text-muted: #6b7280;
  --border:     #e5e7eb;
  --radius:     14px;
  --radius-sm:  8px;
  --nav-h:      64px;
  --bottom-bar: 68px;
  --ff-display: 'Playfair Display', Georgia, serif;
  --ff-body:    'DM Sans', system-ui, sans-serif;
  --shadow-sm:  0 1px 4px rgba(0,51,102,.08);
  --shadow-md:  0 4px 20px rgba(0,51,102,.12);
  --shadow-lg:  0 12px 40px rgba(0,51,102,.18);
}

/* ─── Reset / Base ──────────────────────────────────── */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html { scroll-behavior: smooth; -webkit-tap-highlight-color: transparent; }
body {
  font-family: var(--ff-body);
  background: var(--surface);
  color: var(--text-main);
  min-height: 100vh;
  padding-bottom: var(--bottom-bar);
}

/* ─── Top Nav (desktop) ─────────────────────────────── */
.top-nav {
  position: sticky; top: 0; z-index: 900;
  background: var(--navy);
  height: var(--nav-h);
  display: flex; align-items: center;
  padding: 0 1.5rem;
  box-shadow: 0 2px 16px rgba(0,0,0,.25);
}
.top-nav .brand {
  font-family: var(--ff-display);
  color: var(--ivory);
  font-size: 1.25rem;
  flex: 1;
}
.top-nav .brand span { color: var(--gold-light); }
.nav-links { display: flex; gap: .25rem; align-items: center; }
.nav-links a {
  color: rgba(255,255,224,.75);
  text-decoration: none;
  padding: .45rem .85rem;
  border-radius: 8px;
  font-size: .88rem;
  font-weight: 500;
  transition: background .15s, color .15s;
  display: flex; align-items: center; gap: .4rem;
}
.nav-links a:hover, .nav-links a.active {
  background: rgba(255,255,224,.12);
  color: var(--ivory);
}
.nav-actions { display: flex; align-items: center; gap: .75rem; margin-left: 1rem; }
.notif-btn {
  position: relative; background: none; border: none;
  color: rgba(255,255,224,.8); font-size: 1.3rem; cursor: pointer;
  padding: .35rem; border-radius: 50%; transition: background .15s;
}
.notif-btn:hover { background: rgba(255,255,224,.12); }
.notif-badge {
  position: absolute; top: 0; right: 0;
  background: #ef4444; color: #fff;
  font-size: .6rem; font-weight: 700;
  min-width: 16px; height: 16px;
  border-radius: 8px; padding: 0 3px;
  display: flex; align-items: center; justify-content: center;
  border: 2px solid var(--navy);
}
.avatar-nav {
  width: 36px; height: 36px; border-radius: 50%;
  background: var(--gold); color: var(--navy);
  font-weight: 700; font-size: .85rem;
  display: flex; align-items: center; justify-content: center;
  border: 2px solid rgba(255,255,224,.3);
  cursor: pointer; text-decoration: none;
}

/* ─── Bottom Nav (mobile) ───────────────────────────── */
.bottom-nav {
  display: none;
  position: fixed; bottom: 0; left: 0; right: 0; z-index: 900;
  background: var(--navy);
  height: var(--bottom-bar);
  border-top: 1px solid rgba(255,255,224,.1);
  box-shadow: 0 -4px 20px rgba(0,0,0,.2);
}
.bottom-nav-inner {
  display: flex; height: 100%;
}
.bn-item {
  flex: 1; display: flex; flex-direction: column;
  align-items: center; justify-content: center;
  color: rgba(255,255,224,.55);
  text-decoration: none; font-size: .65rem; font-weight: 500;
  gap: 3px; position: relative;
  transition: color .15s;
  border: none; background: none; cursor: pointer;
}
.bn-item i { font-size: 1.3rem; }
.bn-item.active, .bn-item:hover {
  color: var(--ivory);
}
.bn-item.active::before {
  content: '';
  position: absolute; top: 0; left: 50%; transform: translateX(-50%);
  width: 32px; height: 3px;
  background: var(--gold-light);
  border-radius: 0 0 4px 4px;
}

/* ─── Hero Banner ───────────────────────────────────── */
.hero-banner {
  background: linear-gradient(135deg, var(--navy-dark) 0%, var(--navy) 50%, var(--navy-mid) 100%);
  padding: 2.5rem 1.5rem 3.5rem;
  position: relative; overflow: hidden;
}
.hero-banner::before {
  content: '';
  position: absolute; top: -60px; right: -60px;
  width: 260px; height: 260px;
  background: radial-gradient(circle, rgba(201,168,76,.15) 0%, transparent 70%);
  border-radius: 50%;
}
.hero-banner::after {
  content: '';
  position: absolute; bottom: -80px; left: -40px;
  width: 200px; height: 200px;
  background: radial-gradient(circle, rgba(255,255,224,.06) 0%, transparent 70%);
  border-radius: 50%;
}
.hero-greeting { color: rgba(255,255,224,.7); font-size: .85rem; font-weight: 400; }
.hero-name {
  font-family: var(--ff-display);
  color: var(--ivory);
  font-size: 1.75rem;
  line-height: 1.2;
  margin: .2rem 0 .75rem;
}
.hero-name span { color: var(--gold-light); }
.hero-stats {
  display: flex; gap: .75rem; flex-wrap: wrap;
}
.hero-stat {
  background: rgba(255,255,224,.1);
  border: 1px solid rgba(255,255,224,.15);
  border-radius: 10px;
  padding: .6rem 1rem;
  color: var(--ivory);
}
.hero-stat .stat-val { font-size: 1.3rem; font-weight: 700; line-height: 1; }
.hero-stat .stat-lbl { font-size: .72rem; color: rgba(255,255,224,.6); margin-top: 2px; }

/* ─── Section Titles ────────────────────────────────── */
.section-title {
  font-family: var(--ff-display);
  font-size: 1.25rem;
  color: var(--navy);
  margin-bottom: .25rem;
}
.section-subtitle { color: var(--text-muted); font-size: .82rem; }

/* ─── Quick Action Cards ────────────────────────────── */
.quick-actions { display: grid; grid-template-columns: repeat(4,1fr); gap: .75rem; }
.qa-card {
  background: var(--card-bg);
  border-radius: var(--radius);
  padding: 1.1rem .75rem .9rem;
  text-align: center;
  text-decoration: none;
  color: var(--text-main);
  box-shadow: var(--shadow-sm);
  border: 1px solid var(--border);
  transition: transform .15s, box-shadow .15s;
  display: block;
}
.qa-card:hover { transform: translateY(-2px); box-shadow: var(--shadow-md); color: var(--text-main); }
.qa-icon {
  width: 48px; height: 48px; border-radius: 12px;
  background: var(--ivory);
  display: flex; align-items: center; justify-content: center;
  margin: 0 auto .6rem;
  font-size: 1.3rem; color: var(--navy);
}
.qa-label { font-size: .78rem; font-weight: 600; color: var(--navy); }

/* ─── Order Status Cards ─────────────────────────────── */
.order-card {
  background: var(--card-bg);
  border-radius: var(--radius);
  border: 1px solid var(--border);
  box-shadow: var(--shadow-sm);
  padding: 1rem 1.1rem;
  margin-bottom: .75rem;
  display: flex; align-items: flex-start; gap: .85rem;
  transition: box-shadow .15s;
}
.order-card:hover { box-shadow: var(--shadow-md); }
.order-icon {
  width: 44px; height: 44px; flex-shrink: 0;
  border-radius: 10px;
  background: var(--ivory);
  display: flex; align-items: center; justify-content: center;
  font-size: 1.2rem; color: var(--navy);
}
.order-info { flex: 1; min-width: 0; }
.order-code { font-size: .78rem; color: var(--text-muted); font-weight: 500; }
.order-name { font-weight: 600; font-size: .92rem; color: var(--text-main); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.order-price { font-size: .82rem; color: var(--navy); font-weight: 600; margin-top: 2px; }
.order-action { flex-shrink: 0; }
.badge-status { font-size: .68rem; font-weight: 600; padding: .3em .65em; border-radius: 6px; }

/* ─── Catalog Grid ──────────────────────────────────── */
.catalog-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem; }
.product-card {
  background: var(--card-bg);
  border-radius: var(--radius);
  overflow: hidden;
  border: 1px solid var(--border);
  box-shadow: var(--shadow-sm);
  transition: transform .2s, box-shadow .2s;
  cursor: pointer;
}
.product-card:hover { transform: translateY(-3px); box-shadow: var(--shadow-md); }
.product-thumb {
  height: 160px;
  background: linear-gradient(135deg, var(--ivory) 0%, #f0f0d0 100%);
  display: flex; align-items: center; justify-content: center;
  font-size: 3rem; color: var(--navy);
  position: relative;
}
.product-thumb img { width:100%; height:100%; object-fit:cover; }
.product-custom-badge {
  position: absolute; top: .5rem; right: .5rem;
  background: var(--navy); color: var(--ivory);
  font-size: .65rem; font-weight: 700;
  padding: .2rem .5rem; border-radius: 5px;
  text-transform: uppercase; letter-spacing: .5px;
}
.product-info { padding: .85rem; }
.product-cat { font-size: .7rem; color: var(--gold); font-weight: 700; text-transform: uppercase; letter-spacing: .5px; margin-bottom: .2rem; }
.product-name { font-weight: 600; font-size: .92rem; color: var(--text-main); line-height: 1.3; margin-bottom: .35rem; }
.product-price { color: var(--navy); font-weight: 700; font-size: 1rem; }
.product-price small { font-weight: 400; color: var(--text-muted); font-size: .72rem; }
.btn-order {
  background: var(--navy); color: var(--ivory);
  border: none; border-radius: 8px;
  padding: .45rem .8rem; font-size: .8rem; font-weight: 600;
  width: 100%; margin-top: .5rem;
  transition: background .15s;
  cursor: pointer;
}
.btn-order:hover { background: var(--navy-mid); }

/* ─── Profile Card ──────────────────────────────────── */
.profile-card {
  background: var(--card-bg);
  border-radius: var(--radius);
  border: 1px solid var(--border);
  box-shadow: var(--shadow-sm);
  overflow: hidden;
}
.profile-header {
  background: linear-gradient(135deg, var(--navy) 0%, var(--navy-mid) 100%);
  padding: 2rem 1.5rem 1.5rem;
  text-align: center;
  position: relative;
}
.profile-avatar {
  width: 80px; height: 80px; border-radius: 50%;
  background: var(--gold); color: var(--navy);
  font-family: var(--ff-display);
  font-size: 2rem; font-weight: 700;
  display: flex; align-items: center; justify-content: center;
  margin: 0 auto .75rem;
  border: 3px solid rgba(255,255,224,.3);
  box-shadow: 0 4px 16px rgba(0,0,0,.2);
}
.profile-name { font-family: var(--ff-display); font-size: 1.3rem; color: var(--ivory); }
.profile-email { font-size: .82rem; color: rgba(255,255,224,.65); margin-top: .2rem; }
.profile-body { padding: 1.5rem; }
.profile-row {
  display: flex; align-items: center; gap: .85rem;
  padding: .85rem 0;
  border-bottom: 1px solid var(--border);
}
.profile-row:last-child { border-bottom: none; }
.profile-row-icon {
  width: 38px; height: 38px; border-radius: 9px;
  background: var(--ivory); color: var(--navy);
  display: flex; align-items: center; justify-content: center;
  font-size: 1rem; flex-shrink: 0;
}
.profile-row-label { font-size: .75rem; color: var(--text-muted); }
.profile-row-value { font-size: .9rem; font-weight: 500; color: var(--text-main); }
.btn-logout {
  background: none; border: 2px solid #ef4444;
  color: #ef4444; border-radius: 10px;
  padding: .65rem; width: 100%; font-weight: 600;
  transition: background .15s, color .15s;
  cursor: pointer;
}
.btn-logout:hover { background: #ef4444; color: #fff; }

/* ─── Content Sections ──────────────────────────────── */
.main-content { max-width: 1200px; margin: 0 auto; padding: 1.5rem; }
.content-section { display: none; }
.content-section.active { display: block; }

/* ─── Page wrapper for desktop sidebar layout ────────── */
@media (min-width: 992px) {
  body { padding-bottom: 0; }
  .bottom-nav { display: none; }
  .top-nav .nav-links { display: flex; }

  .desktop-layout {
    display: grid;
    grid-template-columns: 240px 1fr;
    max-width: 1400px;
    margin: 0 auto;
    gap: 0;
  }
  .sidebar {
    display: block;
    background: var(--card-bg);
    border-right: 1px solid var(--border);
    min-height: calc(100vh - var(--nav-h));
    padding: 1.5rem 1rem;
    position: sticky;
    top: var(--nav-h);
    height: calc(100vh - var(--nav-h));
    overflow-y: auto;
  }
  .sidebar-nav { list-style: none; padding: 0; margin: 0; }
  .sidebar-nav li a, .sidebar-nav li button {
    display: flex; align-items: center; gap: .75rem;
    padding: .65rem 1rem; border-radius: 10px;
    color: var(--text-muted); font-size: .88rem; font-weight: 500;
    text-decoration: none; border: none; background: none; width: 100%;
    cursor: pointer; transition: all .15s;
  }
  .sidebar-nav li a:hover, .sidebar-nav li button:hover {
    background: var(--ivory); color: var(--navy);
  }
  .sidebar-nav li a.active, .sidebar-nav li button.active {
    background: var(--navy); color: var(--ivory);
    box-shadow: var(--shadow-md);
  }
  .sidebar-nav li a.active i, .sidebar-nav li button.active i { color: var(--gold-light); }
  .sidebar-divider { height: 1px; background: var(--border); margin: 1rem 0; }
  .sidebar-section-label {
    font-size: .7rem; text-transform: uppercase; letter-spacing: 1px;
    color: var(--text-muted); padding: 0 1rem; margin-bottom: .5rem;
  }
  .main-content { padding: 2rem; }
  .catalog-grid { grid-template-columns: repeat(3, 1fr); }
  .quick-actions { grid-template-columns: repeat(4, 1fr); }
}

/* ─── Mobile Responsive ─────────────────────────────── */
@media (max-width: 991px) {
  .bottom-nav { display: flex; }
  .top-nav .nav-links { display: none; }
  .top-nav .nav-actions { display: flex; }
  .sidebar { display: none; }
  .desktop-layout { display: block; }
  .hero-name { font-size: 1.4rem; }
  .quick-actions { grid-template-columns: repeat(4, 1fr); gap: .5rem; }
  .qa-icon { width: 40px; height: 40px; font-size: 1.1rem; }
}

@media (max-width: 480px) {
  .catalog-grid { grid-template-columns: repeat(2, 1fr); gap: .6rem; }
  .product-thumb { height: 130px; }
  .quick-actions { grid-template-columns: repeat(4, 1fr); gap: .4rem; }
  .qa-card { padding: .7rem .4rem .6rem; }
  .qa-icon { width: 36px; height: 36px; font-size: 1rem; margin-bottom: .4rem; }
  .qa-label { font-size: .7rem; }
}

/* ─── Notification Toast ────────────────────────────── */
.toast-container { position: fixed; bottom: calc(var(--bottom-bar) + 1rem); right: 1rem; z-index: 9999; }
@media (min-width: 992px) { .toast-container { bottom: 1.5rem; } }

/* ─── Animations ────────────────────────────────────── */
@keyframes fadeUp {
  from { opacity:0; transform: translateY(16px); }
  to   { opacity:1; transform: translateY(0); }
}
.fade-up { animation: fadeUp .35s ease both; }
.delay-1 { animation-delay: .06s; }
.delay-2 { animation-delay: .12s; }
.delay-3 { animation-delay: .18s; }
.delay-4 { animation-delay: .24s; }

/* ─── Empty state ───────────────────────────────────── */
.empty-state {
  text-align: center; padding: 3rem 1rem;
  color: var(--text-muted);
}
.empty-state i { font-size: 3rem; margin-bottom: .75rem; display: block; color: #d1d5db; }
.empty-state p { font-size: .88rem; }
</style>
</head>
<body>

<!-- ═══ TOP NAV ════════════════════════════════════════════════════════════ -->
<nav class="top-nav">
  <div class="brand"><i class="bi bi-scissors me-2"></i><span>Butik Menik</span> Modeste</div>
  <div class="nav-links d-none d-lg-flex">
    <a href="#" class="active" data-section="home"    onclick="showSection('home',this)">   <i class="bi bi-house"></i>Beranda</a>
    <a href="#" data-section="catalog"  onclick="showSection('catalog',this)"> <i class="bi bi-grid"></i>Katalog</a>
    <a href="#" data-section="orders"   onclick="showSection('orders',this)">  <i class="bi bi-bag"></i>Pesanan</a>
    <a href="#" data-section="profile"  onclick="showSection('profile',this)"> <i class="bi bi-person"></i>Profil</a>
  </div>
  <div class="nav-actions ms-auto">
    <button class="notif-btn" onclick="showSection('notifications',this)" title="Notifikasi">
      <i class="bi bi-bell"></i>
      <?php if ($unreadCount > 0): ?>
      <span class="notif-badge"><?= $unreadCount > 9 ? '9+' : $unreadCount ?></span>
      <?php endif; ?>
    </button>
    <a href="?section=profile" class="avatar-nav" onclick="showSection('profile',this)">
      <?= strtoupper(mb_substr($user['name'], 0, 1)) ?>
    </a>
  </div>
</nav>

<!-- ═══ DESKTOP LAYOUT ═════════════════════════════════════════════════════ -->
<div class="desktop-layout">

  <!-- Sidebar (desktop only) -->
  <aside class="sidebar">
    <p class="sidebar-section-label">Menu Utama</p>
    <ul class="sidebar-nav">
      <li><a href="#" class="active" data-section="home"   onclick="showSection('home',this)">   <i class="bi bi-house-fill"></i>Beranda</a></li>
      <li><a href="#" data-section="catalog"  onclick="showSection('catalog',this)"> <i class="bi bi-grid-fill"></i>Katalog Produk</a></li>
      <li><a href="#" data-section="orders"   onclick="showSection('orders',this)">  <i class="bi bi-bag-fill"></i>Pesanan Saya</a></li>
    </ul>
    <div class="sidebar-divider"></div>
    <p class="sidebar-section-label">Akun</p>
    <ul class="sidebar-nav">
      <li><a href="#" data-section="profile"       onclick="showSection('profile',this)">      <i class="bi bi-person-fill"></i>Profil</a></li>
      <li><a href="#" data-section="notifications" onclick="showSection('notifications',this)"><i class="bi bi-bell-fill"></i>Notifikasi <?php if($unreadCount): ?><span class="badge bg-danger ms-auto"><?= $unreadCount ?></span><?php endif; ?></a></li>
      <li><button onclick="confirmLogout()" style="color:#ef4444"><i class="bi bi-box-arrow-right"></i>Keluar</button></li>
    </ul>
  </aside>

  <!-- Main -->
  <main>
    <!-- ── HERO (only on home) ── -->
    <div id="hero-section" class="hero-banner fade-up">
      <?php
        $totalOrders   = count($orders);
        $activeOrders  = count(array_filter($orders, fn($o) => !in_array($o['status'],['selesai','dibatalkan'])));
        $selesaiOrders = count(array_filter($orders, fn($o) => $o['status'] === 'selesai'));
      ?>
      <p class="hero-greeting">Selamat datang kembali,</p>
      <h1 class="hero-name"><?= htmlspecialchars(explode(' ', $user['name'])[0]) ?> <span>✨</span></h1>
      <div class="hero-stats">
        <div class="hero-stat fade-up delay-1">
          <div class="stat-val"><?= $totalOrders ?></div>
          <div class="stat-lbl">Total Pesanan</div>
        </div>
        <div class="hero-stat fade-up delay-2">
          <div class="stat-val"><?= $activeOrders ?></div>
          <div class="stat-lbl">Pesanan Aktif</div>
        </div>
        <div class="hero-stat fade-up delay-3">
          <div class="stat-val"><?= $selesaiOrders ?></div>
          <div class="stat-lbl">Selesai</div>
        </div>
      </div>
    </div>

    <!-- ═══ CONTENT ════════════════════════════════════════════════════════ -->
    <div class="main-content">

      <!-- ── HOME ── -->
      <section id="section-home" class="content-section active">

        <!-- Quick Actions -->
        <div class="mb-4 fade-up delay-1">
          <h2 class="section-title">Aksi Cepat</h2>
          <p class="section-subtitle mb-3">Apa yang ingin kamu lakukan?</p>
          <div class="quick-actions">
            <a href="new-order.php" class="qa-card">
              <div class="qa-icon"><i class="bi bi-plus-circle-fill"></i></div>
              <div class="qa-label">Pesan Baru</div>
            </a>
            <a href="#" class="qa-card" onclick="showSection('orders',null)">
              <div class="qa-icon"><i class="bi bi-bag-fill"></i></div>
              <div class="qa-label">Pesanan</div>
            </a>
            <a href="#" class="qa-card" onclick="showSection('catalog',null)">
              <div class="qa-icon"><i class="bi bi-grid-fill"></i></div>
              <div class="qa-label">Katalog</div>
            </a>
            <a href="#" class="qa-card" onclick="showSection('profile',null)">
              <div class="qa-icon"><i class="bi bi-person-fill"></i></div>
              <div class="qa-label">Profil</div>
            </a>
          </div>
        </div>

        <!-- Recent Orders -->
        <div class="fade-up delay-2">
          <div class="d-flex align-items-center justify-content-between mb-2">
            <div>
              <h2 class="section-title">Pesanan Terkini</h2>
              <p class="section-subtitle">Status pesanan kamu</p>
            </div>
            <a href="#" class="text-decoration-none" style="font-size:.82rem;color:var(--navy);font-weight:600" onclick="showSection('orders',null)">Lihat Semua →</a>
          </div>

          <?php if (empty($orders)): ?>
          <div class="empty-state">
            <i class="bi bi-bag-x"></i>
            <p>Belum ada pesanan. Yuk mulai pesan!</p>
            <a href="new-order.php" class="btn btn-sm mt-2" style="background:var(--navy);color:var(--ivory);border-radius:8px">Buat Pesanan</a>
          </div>
          <?php else: ?>
          <?php foreach (array_slice($orders, 0, 3) as $i => $o):
            $sl = $statusLabels[$o['status']] ?? ['label'=>$o['status'],'color'=>'secondary'];
          ?>
          <div class="order-card fade-up" style="animation-delay:<?= ($i*.07) ?>s">
            <div class="order-icon"><i class="bi bi-shirt-fill"></i></div>
            <div class="order-info">
              <div class="order-code"><?= htmlspecialchars($o['order_code']) ?></div>
              <div class="order-name"><?= htmlspecialchars($o['product_name'] ?? 'Pesanan Custom') ?></div>
              <div class="order-price">Rp <?= number_format($o['total_price'], 0, ',', '.') ?></div>
            </div>
            <div class="order-action">
              <span class="badge-status bg-<?= $sl['color'] ?> text-<?= $sl['color']==='warning'?'dark':'white' ?>">
                <?= $sl['label'] ?>
              </span>
              <div class="mt-2 text-end">
                <a href="order-detail.php?code=<?= urlencode($o['order_code']) ?>" class="btn btn-sm btn-outline-secondary" style="font-size:.72rem;border-radius:7px">Detail</a>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
          <?php endif; ?>
        </div>

        <!-- Featured Catalog -->
        <div class="mt-4 fade-up delay-3">
          <div class="d-flex align-items-center justify-content-between mb-2">
            <div>
              <h2 class="section-title">Produk Unggulan</h2>
              <p class="section-subtitle">Pilihan terbaik dari kami</p>
            </div>
            <a href="#" class="text-decoration-none" style="font-size:.82rem;color:var(--navy);font-weight:600" onclick="showSection('catalog',null)">Semua →</a>
          </div>
          <div class="catalog-grid">
          <?php foreach (array_slice($products, 0, 4) as $p): ?>
            <div class="product-card" onclick="openProduct(<?= $p['id'] ?>)">
              <div class="product-thumb">
                <?php if ($p['image']): ?>
                  <img src="<?= htmlspecialchars(APP_URL.'/uploads/'.$p['image']) ?>" alt="">
                <?php else: ?>
                  <i class="bi bi-bag-heart"></i>
                <?php endif; ?>
                <?php if ($p['is_custom']): ?>
                  <span class="product-custom-badge">Custom</span>
                <?php endif; ?>
              </div>
              <div class="product-info">
                <div class="product-cat"><?= htmlspecialchars($p['category_name'] ?? '') ?></div>
                <div class="product-name"><?= htmlspecialchars($p['name']) ?></div>
                <div class="product-price">Rp <?= number_format($p['base_price'],0,',','.') ?> <?php if($p['is_custom']): ?><small>/ mulai dari</small><?php endif; ?></div>
                <button class="btn-order" onclick="event.stopPropagation();pesan(<?= $p['id'] ?>)">Pesan Sekarang</button>
              </div>
            </div>
          <?php endforeach; ?>
          </div>
        </div>
      </section>

      <!-- ── CATALOG ── -->
      <section id="section-catalog" class="content-section">
        <div class="mb-3">
          <h2 class="section-title">Katalog Produk</h2>
          <p class="section-subtitle">Semua produk Butik Menik Modeste</p>
        </div>
        <!-- Search -->
        <div class="input-group mb-4" style="border-radius:10px;overflow:hidden;box-shadow:var(--shadow-sm)">
          <span class="input-group-text" style="background:var(--ivory);border-color:var(--border)"><i class="bi bi-search text-secondary"></i></span>
          <input type="text" id="catalog-search" class="form-control" placeholder="Cari produk..." oninput="filterCatalog()" style="border-color:var(--border)">
        </div>
        <!-- Filter pills -->
        <div class="d-flex gap-2 flex-wrap mb-3" id="category-filters">
          <button class="btn btn-sm filter-pill active" data-cat="" onclick="setCatFilter(this,'')">Semua</button>
          <?php
            $catQ = db()->query('SELECT * FROM product_categories');
            foreach ($catQ as $cat):
          ?>
          <button class="btn btn-sm filter-pill" data-cat="<?= $cat['id'] ?>" onclick="setCatFilter(this,<?= $cat['id'] ?>)"><?= htmlspecialchars($cat['name']) ?></button>
          <?php endforeach; ?>
        </div>
        <div class="catalog-grid" id="catalog-grid">
          <?php foreach ($products as $p): ?>
          <div class="product-card" data-cat="<?= $p['category_id'] ?>" data-name="<?= strtolower(htmlspecialchars($p['name'])) ?>" onclick="openProduct(<?= $p['id'] ?>)">
            <div class="product-thumb">
              <?php if ($p['image']): ?>
                <img src="<?= htmlspecialchars(APP_URL.'/uploads/'.$p['image']) ?>" alt="">
              <?php else: ?>
                <i class="bi bi-bag-heart"></i>
              <?php endif; ?>
              <?php if ($p['is_custom']): ?>
                <span class="product-custom-badge">Custom</span>
              <?php endif; ?>
            </div>
            <div class="product-info">
              <div class="product-cat"><?= htmlspecialchars($p['category_name'] ?? '') ?></div>
              <div class="product-name"><?= htmlspecialchars($p['name']) ?></div>
              <div class="product-price">Rp <?= number_format($p['base_price'],0,',','.') ?><?php if($p['is_custom']): ?> <small>/ mulai dari</small><?php endif; ?></div>
              <button class="btn-order" onclick="event.stopPropagation();pesan(<?= $p['id'] ?>)">Pesan Sekarang</button>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </section>

      <!-- ── ORDERS ── -->
      <section id="section-orders" class="content-section">
        <div class="mb-3 d-flex align-items-center justify-content-between">
          <div>
            <h2 class="section-title">Pesanan Saya</h2>
            <p class="section-subtitle">Riwayat dan status pesanan</p>
          </div>
          <a href="new-order.php" class="btn btn-sm" style="background:var(--navy);color:var(--ivory);border-radius:9px;font-weight:600;font-size:.82rem">
            <i class="bi bi-plus-lg me-1"></i>Pesan Baru
          </a>
        </div>
        <!-- Status filter -->
        <div class="d-flex gap-2 flex-wrap mb-3">
          <button class="btn btn-sm filter-pill active" onclick="filterOrders(this,'all')">Semua</button>
          <button class="btn btn-sm filter-pill" onclick="filterOrders(this,'active')">Aktif</button>
          <button class="btn btn-sm filter-pill" onclick="filterOrders(this,'selesai')">Selesai</button>
          <button class="btn btn-sm filter-pill" onclick="filterOrders(this,'dibatalkan')">Dibatalkan</button>
        </div>
        <div id="orders-list">
        <?php if (empty($orders)): ?>
          <div class="empty-state"><i class="bi bi-bag-x"></i><p>Belum ada pesanan.</p></div>
        <?php else: ?>
        <?php foreach ($orders as $i => $o):
          $sl = $statusLabels[$o['status']] ?? ['label'=>$o['status'],'color'=>'secondary'];
          $isActive = !in_array($o['status'],['selesai','dibatalkan']) ? 'active-order' : '';
        ?>
          <div class="order-card <?= $isActive ?> order-status-<?= $o['status'] ?> fade-up" style="animation-delay:<?= ($i*.05) ?>s">
            <div class="order-icon"><i class="bi bi-shirt-fill"></i></div>
            <div class="order-info">
              <div class="order-code"><?= htmlspecialchars($o['order_code']) ?> · <?= date('d M Y', strtotime($o['created_at'])) ?></div>
              <div class="order-name"><?= htmlspecialchars($o['product_name'] ?? 'Pesanan Custom') ?></div>
              <div class="order-price">Rp <?= number_format($o['total_price'],0,',','.') ?></div>
              <?php if ($o['pickup_date']): ?>
              <div style="font-size:.75rem;color:var(--gold);margin-top:3px"><i class="bi bi-calendar-check me-1"></i>Ambil: <?= date('d M Y', strtotime($o['pickup_date'])) ?></div>
              <?php endif; ?>
            </div>
            <div class="order-action d-flex flex-column align-items-end gap-2">
              <span class="badge-status bg-<?= $sl['color'] ?> text-<?= $sl['color']==='warning'?'dark':'white' ?>"><?= $sl['label'] ?></span>
              <a href="order-detail.php?code=<?= urlencode($o['order_code']) ?>" class="btn btn-sm btn-outline-secondary" style="font-size:.72rem;border-radius:7px">Detail</a>
              <?php if (in_array($o['status'],['dp_menunggu','menunggu_konfirmasi'])): ?>
              <a href="payment.php?code=<?= urlencode($o['order_code']) ?>" class="btn btn-sm" style="font-size:.72rem;border-radius:7px;background:var(--navy);color:var(--ivory)">Bayar DP</a>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
        <?php endif; ?>
        </div>
      </section>

      <!-- ── PROFILE ── -->
      <section id="section-profile" class="content-section">
        <div class="mb-3">
          <h2 class="section-title">Profil Saya</h2>
          <p class="section-subtitle">Kelola informasi akun</p>
        </div>
        <div class="profile-card mb-3">
          <div class="profile-header">
            <div class="profile-avatar"><?= strtoupper(mb_substr($user['name'],0,1)) ?></div>
            <div class="profile-name"><?= htmlspecialchars($user['name']) ?></div>
            <div class="profile-email"><?= htmlspecialchars($user['email']) ?></div>
          </div>
          <div class="profile-body">
            <div class="profile-row">
              <div class="profile-row-icon"><i class="bi bi-person"></i></div>
              <div><div class="profile-row-label">Nama Lengkap</div><div class="profile-row-value"><?= htmlspecialchars($user['name']) ?></div></div>
            </div>
            <div class="profile-row">
              <div class="profile-row-icon"><i class="bi bi-envelope"></i></div>
              <div><div class="profile-row-label">Email</div><div class="profile-row-value"><?= htmlspecialchars($user['email']) ?></div></div>
            </div>
            <div class="profile-row">
              <div class="profile-row-icon"><i class="bi bi-shield-check"></i></div>
              <div><div class="profile-row-label">Role</div><div class="profile-row-value">Pelanggan</div></div>
            </div>
            <div class="profile-row">
              <div class="profile-row-icon"><i class="bi bi-bag-check"></i></div>
              <div><div class="profile-row-label">Total Pesanan</div><div class="profile-row-value"><?= $totalOrders ?> pesanan</div></div>
            </div>
          </div>
        </div>
        <div class="d-grid gap-2">
          <a href="edit-profile.php" class="btn fw-600" style="background:var(--navy);color:var(--ivory);border-radius:10px;font-weight:600">
            <i class="bi bi-pencil me-2"></i>Edit Profil
          </a>
          <a href="change-password.php" class="btn btn-outline-secondary" style="border-radius:10px;font-weight:600">
            <i class="bi bi-lock me-2"></i>Ganti Password
          </a>
          <button class="btn-logout" onclick="confirmLogout()">
            <i class="bi bi-box-arrow-right me-2"></i>Keluar
          </button>
        </div>
      </section>

      <!-- ── NOTIFICATIONS ── -->
      <section id="section-notifications" class="content-section">
        <div class="mb-3"><h2 class="section-title">Notifikasi</h2></div>
        <div id="notif-list">
          <div class="empty-state"><i class="bi bi-bell-slash"></i><p>Belum ada notifikasi.</p></div>
        </div>
      </section>

    </div><!-- /main-content -->
  </main>
</div><!-- /desktop-layout -->

<!-- ═══ BOTTOM NAV (mobile) ════════════════════════════════════════════════ -->
<nav class="bottom-nav">
  <div class="bottom-nav-inner">
    <button class="bn-item active" data-section="home"          onclick="showSection('home',this)">        <i class="bi bi-house-fill"></i>Beranda</button>
    <button class="bn-item"        data-section="catalog"       onclick="showSection('catalog',this)">     <i class="bi bi-grid-fill"></i>Katalog</button>
    <button class="bn-item"        data-section="orders"        onclick="showSection('orders',this)">      <i class="bi bi-bag-fill"></i>Pesanan</button>
    <button class="bn-item"        data-section="notifications" onclick="showSection('notifications',this)">
      <span class="position-relative d-inline-block">
        <i class="bi bi-bell-fill"></i>
        <?php if ($unreadCount > 0): ?><span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size:.5rem"><?= $unreadCount ?></span><?php endif; ?>
      </span>
      Notif
    </button>
    <button class="bn-item"        data-section="profile"       onclick="showSection('profile',this)">     <i class="bi bi-person-fill"></i>Profil</button>
  </div>
</nav>

<!-- Modal logout confirm -->
<div class="modal fade" id="logoutModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content" style="border-radius:16px;overflow:hidden">
      <div class="modal-body text-center p-4">
        <div style="font-size:2.5rem;margin-bottom:.5rem">👋</div>
        <h6 class="fw-bold">Keluar dari akun?</h6>
        <p class="text-muted small">Kamu akan diarahkan ke halaman login.</p>
        <div class="d-flex gap-2 justify-content-center mt-3">
          <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal" style="border-radius:8px">Batal</button>
          <a href="<?= APP_URL ?>/logout.php" class="btn btn-sm" style="background:#ef4444;color:#fff;border-radius:8px">Ya, Keluar</a>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ── Section Routing ──────────────────────────────────────────────────────────
const sections = ['home','catalog','orders','profile','notifications'];

function showSection(name, trigger) {
  sections.forEach(s => {
    document.getElementById('section-' + s)?.classList.remove('active');
  });
  document.getElementById('section-' + name)?.classList.add('active');

  // Hero visibility
  const hero = document.getElementById('hero-section');
  if (hero) hero.style.display = (name === 'home') ? '' : 'none';

  // Update bottom nav active
  document.querySelectorAll('.bn-item').forEach(el => {
    el.classList.toggle('active', el.dataset.section === name);
  });

  // Update sidebar active
  document.querySelectorAll('.sidebar-nav a, .sidebar-nav button').forEach(el => {
    el.classList.toggle('active', el.dataset.section === name);
  });

  // Update desktop nav links
  document.querySelectorAll('.nav-links a').forEach(el => {
    el.classList.toggle('active', el.dataset.section === name);
  });

  window.scrollTo({top: 0, behavior: 'smooth'});
  return false;
}

// ── Catalog Filter ───────────────────────────────────────────────────────────
let activeCat = '';
function filterCatalog() {
  const q = document.getElementById('catalog-search').value.toLowerCase();
  document.querySelectorAll('#catalog-grid .product-card').forEach(card => {
    const nameMatch = card.dataset.name.includes(q);
    const catMatch  = !activeCat || card.dataset.cat === activeCat;
    card.style.display = (nameMatch && catMatch) ? '' : 'none';
  });
}
function setCatFilter(btn, catId) {
  activeCat = catId ? String(catId) : '';
  document.querySelectorAll('.filter-pill').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  filterCatalog();
}

// ── Order Filter ─────────────────────────────────────────────────────────────
function filterOrders(btn, type) {
  document.querySelectorAll('.filter-pill').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  document.querySelectorAll('#orders-list .order-card').forEach(card => {
    if (type === 'all') { card.style.display = ''; return; }
    if (type === 'active')    { card.style.display = card.classList.contains('active-order') ? '' : 'none'; return; }
    card.style.display = card.classList.contains('order-status-' + type) ? '' : 'none';
  });
}

// ── Product Actions ──────────────────────────────────────────────────────────
function openProduct(id) {
  window.location.href = 'product-detail.php?id=' + id;
}
function pesan(id) {
  window.location.href = 'new-order.php?product_id=' + id;
}

// ── Logout ───────────────────────────────────────────────────────────────────
function confirmLogout() {
  new bootstrap.Modal(document.getElementById('logoutModal')).show();
}

// ── Filter pill style ─────────────────────────────────────────────────────────
document.querySelectorAll('.filter-pill').forEach(btn => {
  btn.style.borderRadius = '20px';
  btn.style.fontSize = '.78rem';
  btn.style.fontWeight = '600';
  btn.style.padding = '.35rem .9rem';
  btn.style.transition = 'all .15s';
  if (!btn.classList.contains('active')) {
    btn.style.background = '#f3f4f6';
    btn.style.color = '#6b7280';
    btn.style.border = '1px solid #e5e7eb';
  } else {
    btn.style.background = 'var(--navy)';
    btn.style.color = 'var(--ivory)';
    btn.style.border = '1px solid var(--navy)';
  }
  btn.addEventListener('click', () => {
    document.querySelectorAll(`.filter-pill`).forEach(b => {
      b.style.background = '#f3f4f6';
      b.style.color = '#6b7280';
      b.style.border = '1px solid #e5e7eb';
    });
    btn.style.background = 'var(--navy)';
    btn.style.color = 'var(--ivory)';
    btn.style.border = '1px solid var(--navy)';
  });
});

// ── Notifications (live fetch) ───────────────────────────────────────────────
async function loadNotifications() {
  try {
    const res  = await fetch('../../api/notifications.php');
    const data = await res.json();
    const el   = document.getElementById('notif-list');
    if (!data.length) return;
    el.innerHTML = data.map(n => `
      <div class="order-card mb-2 ${n.is_read ? '' : 'border-start border-4 border-warning'}">
        <div class="order-icon"><i class="bi bi-bell${n.is_read ? '' : '-fill'}" style="color:var(--navy)"></i></div>
        <div class="order-info">
          <div class="order-code">${n.created_at}</div>
          <div class="order-name">${n.title}</div>
          <div style="font-size:.8rem;color:#6b7280">${n.body}</div>
        </div>
      </div>`).join('');
  } catch(e) { console.warn('Notif fetch failed', e); }
}
loadNotifications();
</script>
</body>
</html>
