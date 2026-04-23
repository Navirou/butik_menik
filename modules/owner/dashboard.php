<?php
require_once __DIR__ . '/../../includes/auth.php';
requireLogin([ROLE_OWNER]);

$user       = currentUser();
$role       = 'owner';
$pageTitle  = 'Dashboard';
$activeMenu = 'dashboard';

$db = db();
$totalOrders    = $db->query("SELECT COUNT(*) FROM orders")->fetchColumn();
$activeOrders   = $db->query("SELECT COUNT(*) FROM orders WHERE status NOT IN ('selesai','dibatalkan','ditolak')")->fetchColumn();
$revenueMonth   = (float)$db->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='diverifikasi' AND MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW())")->fetchColumn();
$pendingPayment = $db->query("SELECT COUNT(*) FROM payments WHERE status='menunggu'")->fetchColumn();
$lowStock       = $db->query("SELECT COUNT(*) FROM materials WHERE current_stock <= min_stock")->fetchColumn();
$pendingSupply  = $db->query("SELECT COUNT(*) FROM material_requests WHERE status='pending'")->fetchColumn();
$notifCount     = 0;

$recentOrders = $db->query(
    "SELECT o.id, o.order_code, o.status, o.total_price, o.created_at,
            u.name AS customer_name, p.name AS product_name
     FROM orders o JOIN users u ON o.customer_id=u.id
     LEFT JOIN products p ON o.product_id=p.id
     ORDER BY o.created_at DESC LIMIT 8"
)->fetchAll();

$monthlyRevenue = $db->query(
    "SELECT DATE_FORMAT(created_at,'%b') AS mon, COALESCE(SUM(amount),0) AS total
     FROM payments WHERE status='diverifikasi' AND created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
     GROUP BY YEAR(created_at), MONTH(created_at) ORDER BY created_at ASC"
)->fetchAll();

$statusMap = [
    'menunggu_konfirmasi'=>['Menunggu','bm-badge-warning'],
    'dikonfirmasi'=>['Dikonfirmasi','bm-badge-info'],
    'ditolak'=>['Ditolak','bm-badge-danger'],
    'dp_menunggu'=>['DP Menunggu','bm-badge-warning'],
    'dp_diverifikasi'=>['DP OK','bm-badge-success'],
    'pemeriksaan_stok'=>['Cek Stok','bm-badge-info'],
    'produksi'=>['Produksi','bm-badge-navy'],
    'qc'=>['QC','bm-badge-muted'],
    'jadwal_ambil'=>['Siap Ambil','bm-badge-success'],
    'selesai'=>['Selesai','bm-badge-success'],
    'revisi'=>['Revisi','bm-badge-warning'],
    'dibatalkan'=>['Dibatalkan','bm-badge-danger'],
];

require_once __DIR__ . '/../../includes/layout-admin.php';
?>

<div class="bm-page-header">
  <h1>Selamat datang, <?= htmlspecialchars(explode(' ',$user['name'])[0]) ?> 👋</h1>
  <p><?= date('l, d F Y') ?> · Ringkasan operasional Butik Menik Modeste</p>
</div>

<div class="row g-3 mb-4">
<?php
$statCards = [
  ['bi-bag-fill','#e8f0fe','#003366',$totalOrders,'Total Pesanan','orders.php'],
  ['bi-activity','#fef9e7','#C9A84C',$activeOrders,'Pesanan Aktif','orders.php'],
  ['bi-cash-stack','#d1fae5','#065f46','Rp '.number_format($revenueMonth,0,',','.'),'Pendapatan Bulan Ini','reports.php'],
  ['bi-hourglass-split','#fee2e2','#991b1b',$pendingPayment,'Pembayaran Pending','payments.php'],
  ['bi-exclamation-triangle-fill','#fef3c7','#92400e',$lowStock,'Stok Kritis','stock.php'],
  ['bi-truck','#ede9fe','#5b21b6',$pendingSupply,'Permintaan Supplier','suppliers.php'],
];
foreach ($statCards as [$icon,$bg,$ic,$val,$lbl,$href]):
?>
<div class="col-6 col-md-4 col-xl-2">
  <a href="<?= $href ?>" class="bm-stat-card d-block text-decoration-none anim-fade-up">
    <div class="bm-stat-icon" style="background:<?= $bg ?>"><i class="bi <?= $icon ?>" style="color:<?= $ic ?>"></i></div>
    <div class="bm-stat-val"><?= $val ?></div>
    <div class="bm-stat-lbl"><?= $lbl ?></div>
  </a>
</div>
<?php endforeach; ?>
</div>

<div class="row g-3 mb-4">
  <div class="col-lg-8">
    <div class="bm-card h-100">
      <div class="bm-card-header">
        <span class="bm-card-title"><i class="bi bi-bar-chart me-2"></i>Pendapatan 6 Bulan Terakhir</span>
        <a href="reports.php" class="btn-navy" style="font-size:.78rem;padding:.35rem .8rem">Laporan Lengkap</a>
      </div>
      <div class="bm-card-body"><canvas id="revenueChart" height="200"></canvas></div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="bm-card h-100">
      <div class="bm-card-header"><span class="bm-card-title"><i class="bi bi-lightning-charge me-2"></i>Aksi Cepat</span></div>
      <div class="bm-card-body p-0">
      <?php foreach ([
        ['bi-bag-plus-fill','#003366','#e8f0fe','Konfirmasi Pesanan Baru','orders.php?filter=menunggu_konfirmasi'],
        ['bi-credit-card-fill','#065f46','#d1fae5','Verifikasi Pembayaran','payments.php?filter=menunggu'],
        ['bi-plus-circle-fill','#92400e','#fef3c7','Tambah Permintaan Bahan','stock-request.php'],
        ['bi-calendar-check-fill','#5b21b6','#ede9fe','Atur Jadwal Ambil','orders.php?filter=qc'],
        ['bi-people-fill','#1e40af','#dbeafe','Kelola Pengguna','users.php'],
      ] as [$qi,$qic,$qbg,$ql,$qh]): ?>
      <a href="<?= $qh ?>" class="d-flex align-items-center gap-3 px-4 py-3 text-decoration-none hover-row" style="border-bottom:1px solid var(--border)">
        <span style="width:34px;height:34px;border-radius:9px;background:<?= $qbg ?>;display:flex;align-items:center;justify-content:center;flex-shrink:0">
          <i class="bi <?= $qi ?>" style="color:<?= $qic ?>;font-size:.95rem"></i>
        </span>
        <span style="font-size:.85rem;font-weight:500;color:var(--text-main)"><?= $ql ?></span>
        <i class="bi bi-chevron-right ms-auto" style="color:#d1d5db;font-size:.75rem"></i>
      </a>
      <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<div class="bm-card anim-fade-up delay-2">
  <div class="bm-card-header">
    <span class="bm-card-title"><i class="bi bi-bag me-2"></i>Pesanan Terbaru</span>
    <a href="orders.php" class="btn-navy" style="font-size:.78rem;padding:.35rem .8rem">Semua Pesanan</a>
  </div>
  <div class="table-responsive">
    <table class="bm-table">
      <thead>
        <tr><th>Kode</th><th>Pelanggan</th><th>Produk</th><th>Total</th><th>Tanggal</th><th>Status</th><th>Aksi</th></tr>
      </thead>
      <tbody>
      <?php foreach ($recentOrders as $o):
        [$slbl,$scls] = $statusMap[$o['status']] ?? [ucfirst($o['status']),'bm-badge-muted'];
      ?>
      <tr>
        <td><a href="order-detail.php?id=<?= $o['id'] ?>" style="color:var(--navy);font-weight:700;text-decoration:none"><?= htmlspecialchars($o['order_code']) ?></a></td>
        <td><?= htmlspecialchars($o['customer_name']) ?></td>
        <td><?= htmlspecialchars($o['product_name'] ?? 'Custom') ?></td>
        <td style="font-weight:600">Rp <?= number_format($o['total_price'],0,',','.') ?></td>
        <td><?= date('d M Y', strtotime($o['created_at'])) ?></td>
        <td><span class="bm-badge <?= $scls ?>"><?= $slbl ?></span></td>
        <td><a href="order-detail.php?id=<?= $o['id'] ?>" class="btn-navy" style="font-size:.75rem;padding:.3rem .65rem">Detail</a></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
new Chart(document.getElementById('revenueChart'), {
  type: 'bar',
  data: {
    labels: <?= json_encode(array_column($monthlyRevenue,'mon')) ?>,
    datasets: [{
      label: 'Pendapatan',
      data: <?= json_encode(array_column($monthlyRevenue,'total')) ?>,
      backgroundColor: 'rgba(0,51,102,.75)',
      borderRadius: 7, borderSkipped: false,
    }]
  },
  options: {
    responsive: true,
    plugins: { legend: { display: false }, tooltip: { callbacks: { label: c => 'Rp '+c.raw.toLocaleString('id-ID') } } },
    scales: {
      y: { beginAtZero:true, grid:{color:'#f0f0f0'}, ticks:{callback:v=>'Rp '+(v/1e3).toFixed(0)+'K',font:{size:11}} },
      x: { grid:{display:false}, ticks:{font:{size:11}} }
    }
  }
});
document.querySelectorAll('.hover-row').forEach(el=>{
  el.addEventListener('mouseenter',()=>el.style.background='#f8f9ff');
  el.addEventListener('mouseleave',()=>el.style.background='');
});
</script>
<?php require_once __DIR__ . '/../../includes/layout-admin-footer.php'; ?>
