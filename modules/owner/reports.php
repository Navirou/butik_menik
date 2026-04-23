<?php
require_once __DIR__ . '/../../includes/auth.php';
requireLogin([ROLE_OWNER]);

$user = currentUser(); $role = 'owner'; $pageTitle = 'Laporan'; $activeMenu = 'reports'; $notifCount = 0;
$db = db();

$period = $_GET['period'] ?? 'monthly';
$year   = (int)($_GET['year'] ?? date('Y'));

// Revenue by month
$revenue = $db->prepare(
    "SELECT MONTH(created_at) AS m, DATE_FORMAT(created_at,'%b') AS label, COALESCE(SUM(amount),0) AS total
     FROM payments WHERE status='diverifikasi' AND YEAR(created_at)=? GROUP BY MONTH(created_at) ORDER BY m"
);
$revenue->execute([$year]); $revenueData = $revenue->fetchAll();

// Orders by status
$orderStats = $db->query("SELECT status, COUNT(*) AS cnt FROM orders GROUP BY status")->fetchAll();

// Top products
$topProducts = $db->query(
    "SELECT p.name, COUNT(o.id) AS cnt, COALESCE(SUM(o.total_price),0) AS revenue
     FROM orders o JOIN products p ON o.product_id=p.id
     WHERE o.status NOT IN ('ditolak','dibatalkan')
     GROUP BY p.id ORDER BY cnt DESC LIMIT 5"
)->fetchAll();

// Summary totals
$totalRevenue = $db->prepare("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='diverifikasi' AND YEAR(created_at)=?");
$totalRevenue->execute([$year]); $totalRev = (float)$totalRevenue->fetchColumn();
$totalOrdersYear = $db->prepare("SELECT COUNT(*) FROM orders WHERE YEAR(created_at)=?");
$totalOrdersYear->execute([$year]); $totalOrdYear = $totalOrdersYear->fetchColumn();
$completedYear = $db->prepare("SELECT COUNT(*) FROM orders WHERE status='selesai' AND YEAR(created_at)=?");
$completedYear->execute([$year]); $compYear = $completedYear->fetchColumn();

// Build 12-month array
$months = []; $revByMonth = array_column($revenueData, 'total', 'm');
for ($i = 1; $i <= 12; $i++) {
    $months[] = ['label' => date('M', mktime(0,0,0,$i,1)), 'total' => $revByMonth[$i] ?? 0];
}

require_once __DIR__ . '/../../includes/layout-admin.php';
?>

<div class="bm-page-header d-flex justify-content-between align-items-start gap-3">
  <div><h1>Laporan Keuangan & Operasional</h1><p>Pantau performa bisnis Butik Menik Modeste</p></div>
  <form class="d-flex gap-2 align-items-center" style="flex-shrink:0">
    <select name="year" class="bm-form-control" onchange="this.form.submit()" style="width:auto">
      <?php for ($y = date('Y'); $y >= date('Y')-3; $y--): ?>
      <option value="<?= $y ?>" <?= $y==$year?'selected':'' ?>>Tahun <?= $y ?></option>
      <?php endfor; ?>
    </select>
  </form>
</div>

<!-- Summary Stats -->
<div class="row g-3 mb-4">
  <div class="col-sm-4">
    <div class="bm-stat-card text-center">
      <div class="bm-stat-icon mx-auto" style="background:#d1fae5"><i class="bi bi-cash-stack" style="color:#065f46"></i></div>
      <div class="bm-stat-val">Rp <?= number_format($totalRev/1e6,1) ?>Jt</div>
      <div class="bm-stat-lbl">Total Pendapatan <?= $year ?></div>
    </div>
  </div>
  <div class="col-sm-4">
    <div class="bm-stat-card text-center">
      <div class="bm-stat-icon mx-auto" style="background:#e8f0fe"><i class="bi bi-bag-fill" style="color:#003366"></i></div>
      <div class="bm-stat-val"><?= $totalOrdYear ?></div>
      <div class="bm-stat-lbl">Total Pesanan <?= $year ?></div>
    </div>
  </div>
  <div class="col-sm-4">
    <div class="bm-stat-card text-center">
      <div class="bm-stat-icon mx-auto" style="background:#fef9e7"><i class="bi bi-trophy-fill" style="color:#C9A84C"></i></div>
      <div class="bm-stat-val"><?= $totalOrdYear > 0 ? round($compYear/$totalOrdYear*100) : 0 ?>%</div>
      <div class="bm-stat-lbl">Completion Rate</div>
    </div>
  </div>
</div>

<div class="row g-3 mb-4">
  <!-- Revenue Chart -->
  <div class="col-lg-8">
    <div class="bm-card h-100">
      <div class="bm-card-header"><span class="bm-card-title"><i class="bi bi-graph-up me-2"></i>Pendapatan Per Bulan <?= $year ?></span></div>
      <div class="bm-card-body"><canvas id="revChart" height="180"></canvas></div>
    </div>
  </div>
  <!-- Order Status Pie -->
  <div class="col-lg-4">
    <div class="bm-card h-100">
      <div class="bm-card-header"><span class="bm-card-title"><i class="bi bi-pie-chart me-2"></i>Status Pesanan</span></div>
      <div class="bm-card-body"><canvas id="statusChart" height="200"></canvas></div>
    </div>
  </div>
</div>

<!-- Top Products -->
<div class="bm-card">
  <div class="bm-card-header"><span class="bm-card-title"><i class="bi bi-award me-2"></i>Produk Terlaris</span></div>
  <div class="table-responsive">
    <table class="bm-table">
      <thead><tr><th>#</th><th>Produk</th><th>Jumlah Pesanan</th><th>Total Pendapatan</th></tr></thead>
      <tbody>
      <?php foreach ($topProducts as $i => $p): ?>
      <tr>
        <td><span class="bm-badge bm-badge-gold"><?= $i+1 ?></span></td>
        <td style="font-weight:600"><?= htmlspecialchars($p['name']) ?></td>
        <td><?= $p['cnt'] ?> pesanan</td>
        <td style="font-weight:700;color:var(--navy)">Rp <?= number_format($p['revenue'],0,',','.') ?></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
// Revenue chart
new Chart(document.getElementById('revChart'), {
  type: 'line',
  data: {
    labels: <?= json_encode(array_column($months,'label')) ?>,
    datasets: [{
      label: 'Pendapatan', data: <?= json_encode(array_column($months,'total')) ?>,
      borderColor: '#003366', backgroundColor: 'rgba(0,51,102,.08)',
      borderWidth: 2.5, pointBackgroundColor: '#C9A84C', pointRadius: 4, fill: true, tension: .35
    }]
  },
  options: {
    responsive: true,
    plugins: { legend:{display:false}, tooltip:{callbacks:{label:c=>'Rp '+c.raw.toLocaleString('id-ID')}} },
    scales: {
      y: { beginAtZero:true, grid:{color:'#f0f0f0'}, ticks:{callback:v=>'Rp '+(v/1e3).toFixed(0)+'K',font:{size:11}} },
      x: { grid:{display:false}, ticks:{font:{size:11}} }
    }
  }
});
// Status pie
const statuses = <?= json_encode(array_values(array_map(fn($r)=>$r['status'], $orderStats))) ?>;
const counts   = <?= json_encode(array_values(array_map(fn($r)=>(int)$r['cnt'], $orderStats))) ?>;
new Chart(document.getElementById('statusChart'), {
  type: 'doughnut',
  data: {
    labels: statuses,
    datasets: [{ data: counts,
      backgroundColor:['#003366','#C9A84C','#10b981','#f59e0b','#ef4444','#3b82f6','#8b5cf6','#6b7280'],
      borderWidth: 2, borderColor: '#fff' }]
  },
  options: {
    responsive: true,
    plugins: { legend:{position:'bottom',labels:{font:{size:10},boxWidth:12,padding:8}} }
  }
});
</script>
<?php require_once __DIR__ . '/../../includes/layout-admin-footer.php'; ?>
