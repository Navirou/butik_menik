<?php
require_once __DIR__ . '/../../includes/auth.php';
requireLogin([ROLE_OWNER]);

$user       = currentUser();
$role       = 'owner';
$pageTitle  = 'Manajemen Pesanan';
$activeMenu = 'orders';
$notifCount = 0;

$db = db();
$filterStatus = $_GET['filter'] ?? 'all';
$search       = trim($_GET['q'] ?? '');

$where  = 'WHERE 1=1';
$params = [];
if ($filterStatus !== 'all') { $where .= ' AND o.status=?'; $params[] = $filterStatus; }
if ($search) { $where .= ' AND (o.order_code LIKE ? OR u.name LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; }

$stmt = $db->prepare(
    "SELECT o.id, o.order_code, o.status, o.total_price, o.dp_amount, o.qty, o.created_at, o.estimated_done,
            u.name AS customer_name, u.phone AS customer_phone,
            p.name AS product_name
     FROM orders o
     JOIN users u ON o.customer_id=u.id
     LEFT JOIN products p ON o.product_id=p.id
     $where ORDER BY o.created_at DESC LIMIT 60"
);
$stmt->execute($params);
$orders = $stmt->fetchAll();

// Handle quick actions (confirm/reject)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['order_id'])) {
    $oid    = (int)$_POST['order_id'];
    $action = $_POST['action'];
    if ($action === 'confirm') {
        $db->prepare("UPDATE orders SET status='dikonfirmasi' WHERE id=?")->execute([$oid]);
        // Notify customer
        $custId = $db->prepare("SELECT customer_id FROM orders WHERE id=?");
        $custId->execute([$oid]);
        $cid = $custId->fetchColumn();
        if ($cid) $db->prepare("INSERT INTO notifications(user_id,title,body,type,ref_id) VALUES(?,?,?,?,?)")
            ->execute([$cid,'Pesanan Dikonfirmasi','Pesanan kamu telah dikonfirmasi dan sedang diproses.','order_update',$oid]);
    } elseif ($action === 'reject') {
        $db->prepare("UPDATE orders SET status='ditolak' WHERE id=?")->execute([$oid]);
    } elseif ($action === 'set_pickup') {
        $date  = $_POST['pickup_date'] ?? null;
        $ts    = $_POST['pickup_time_start'] ?? null;
        $te    = $_POST['pickup_time_end'] ?? null;
        $db->prepare("UPDATE orders SET status='jadwal_ambil', pickup_date=?, pickup_time_start=?, pickup_time_end=? WHERE id=?")->execute([$date,$ts,$te,$oid]);
    }
    header('Location: orders.php'); exit;
}

$statusMap = [
    'menunggu_konfirmasi'=>['Menunggu Konfirmasi','bm-badge-warning'],
    'dikonfirmasi'=>['Dikonfirmasi','bm-badge-info'],
    'ditolak'=>['Ditolak','bm-badge-danger'],
    'dp_menunggu'=>['DP Menunggu','bm-badge-warning'],
    'dp_diverifikasi'=>['DP Terverifikasi','bm-badge-success'],
    'pemeriksaan_stok'=>['Cek Stok','bm-badge-info'],
    'produksi'=>['Produksi','bm-badge-navy'],
    'qc'=>['QC','bm-badge-muted'],
    'jadwal_ambil'=>['Siap Diambil','bm-badge-success'],
    'selesai'=>['Selesai','bm-badge-success'],
    'revisi'=>['Revisi','bm-badge-warning'],
    'dibatalkan'=>['Dibatalkan','bm-badge-danger'],
];

require_once __DIR__ . '/../../includes/layout-admin.php';
?>

<div class="bm-page-header">
  <h1>Manajemen Pesanan</h1>
  <p>Kelola semua pesanan masuk, konfirmasi, dan jadwal pengambilan</p>
</div>

<!-- Filter Bar -->
<div class="bm-card mb-4">
  <div class="bm-card-body">
    <form method="GET" class="row g-2 align-items-end">
      <div class="col-md-5">
        <label class="bm-form-label">Cari Pesanan</label>
        <input type="text" name="q" class="bm-form-control" placeholder="Kode pesanan atau nama pelanggan..." value="<?= htmlspecialchars($search) ?>">
      </div>
      <div class="col-md-4">
        <label class="bm-form-label">Filter Status</label>
        <select name="filter" class="bm-form-control">
          <option value="all" <?= $filterStatus==='all'?'selected':'' ?>>Semua Status</option>
          <?php foreach ($statusMap as $k=>[$l,$c]): ?>
          <option value="<?= $k ?>" <?= $filterStatus===$k?'selected':'' ?>><?= $l ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3 d-flex gap-2">
        <button type="submit" class="btn-navy w-100"><i class="bi bi-search me-1"></i>Cari</button>
        <a href="orders.php" class="btn-ivory w-100 text-center">Reset</a>
      </div>
    </form>
  </div>
</div>

<!-- Orders Table -->
<div class="bm-card">
  <div class="bm-card-header">
    <span class="bm-card-title"><i class="bi bi-bag me-2"></i>Daftar Pesanan <span class="bm-badge bm-badge-navy ms-2"><?= count($orders) ?></span></span>
  </div>
  <div class="table-responsive">
    <table class="bm-table">
      <thead>
        <tr><th>Kode</th><th>Pelanggan</th><th>Produk</th><th>Total</th><th>DP</th><th>Tanggal</th><th>Status</th><th>Aksi</th></tr>
      </thead>
      <tbody>
      <?php if (empty($orders)): ?>
      <tr><td colspan="8" class="text-center py-4 text-muted">Tidak ada pesanan ditemukan</td></tr>
      <?php endif; ?>
      <?php foreach ($orders as $o):
        [$slbl,$scls] = $statusMap[$o['status']] ?? [ucfirst($o['status']),'bm-badge-muted'];
      ?>
      <tr>
        <td><a href="order-detail.php?id=<?= $o['id'] ?>" style="color:var(--navy);font-weight:700;text-decoration:none"><?= htmlspecialchars($o['order_code']) ?></a></td>
        <td>
          <div style="font-weight:500"><?= htmlspecialchars($o['customer_name']) ?></div>
          <div style="font-size:.75rem;color:var(--text-muted)"><?= htmlspecialchars($o['customer_phone']) ?></div>
        </td>
        <td><?= htmlspecialchars($o['product_name'] ?? 'Custom') ?> · <?= $o['qty'] ?> pcs</td>
        <td style="font-weight:600">Rp <?= number_format($o['total_price'],0,',','.') ?></td>
        <td>
          <?php $dpPaid = $o['dp_amount'] > 0; ?>
          <span class="bm-badge <?= $dpPaid ? 'bm-badge-success' : 'bm-badge-warning' ?>">
            <?= $dpPaid ? 'DP '.number_format($o['dp_amount'],0,',','.') : 'Belum DP' ?>
          </span>
        </td>
        <td style="font-size:.82rem"><?= date('d M Y', strtotime($o['created_at'])) ?></td>
        <td><span class="bm-badge <?= $scls ?>"><?= $slbl ?></span></td>
        <td>
          <div class="d-flex gap-1 flex-wrap">
            <a href="order-detail.php?id=<?= $o['id'] ?>" class="btn-navy" style="font-size:.72rem;padding:.28rem .6rem">Detail</a>
            <?php if ($o['status'] === 'menunggu_konfirmasi'): ?>
            <form method="POST" style="display:inline">
              <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
              <button name="action" value="confirm" class="btn-navy" style="font-size:.72rem;padding:.28rem .6rem;background:#10b981" data-confirm="Konfirmasi pesanan <?= htmlspecialchars($o['order_code']) ?>?">✓ Terima</button>
              <button name="action" value="reject"  class="btn-navy" style="font-size:.72rem;padding:.28rem .6rem;background:#ef4444" data-confirm="Tolak pesanan ini?">✗ Tolak</button>
            </form>
            <?php elseif ($o['status'] === 'qc'): ?>
            <button class="btn-navy" style="font-size:.72rem;padding:.28rem .6rem;background:#5b21b6" onclick="openPickupModal(<?= $o['id'] ?>, '<?= htmlspecialchars($o['order_code']) ?>')">📅 Jadwal</button>
            <?php endif; ?>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Pickup Modal -->
<div class="modal fade" id="pickupModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="border-radius:16px;overflow:hidden">
      <div class="modal-header" style="background:var(--navy);color:var(--ivory)">
        <h6 class="modal-title fw-bold"><i class="bi bi-calendar-check me-2"></i>Atur Jadwal Pengambilan</h6>
        <button class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST">
        <div class="modal-body p-4">
          <input type="hidden" name="action" value="set_pickup">
          <input type="hidden" name="order_id" id="pickup-order-id">
          <p class="mb-3" style="font-size:.88rem;color:var(--text-muted)">Pesanan: <strong id="pickup-order-code"></strong></p>
          <div class="mb-3">
            <label class="bm-form-label">Tanggal Pengambilan</label>
            <input type="date" name="pickup_date" class="bm-form-control" required min="<?= date('Y-m-d') ?>">
          </div>
          <div class="row g-2">
            <div class="col-6"><label class="bm-form-label">Jam Mulai</label><input type="time" name="pickup_time_start" class="bm-form-control" value="10:00" required></div>
            <div class="col-6"><label class="bm-form-label">Jam Selesai</label><input type="time" name="pickup_time_end" class="bm-form-control" value="16:00" required></div>
          </div>
        </div>
        <div class="modal-footer border-0 pt-0 px-4 pb-4">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="border-radius:8px">Batal</button>
          <button type="submit" class="btn-navy"><i class="bi bi-calendar-check me-1"></i>Simpan Jadwal</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function openPickupModal(id, code) {
  document.getElementById('pickup-order-id').value = id;
  document.getElementById('pickup-order-code').textContent = code;
  new bootstrap.Modal(document.getElementById('pickupModal')).show();
}
</script>
<?php require_once __DIR__ . '/../../includes/layout-admin-footer.php'; ?>
