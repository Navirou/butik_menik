<?php
require_once __DIR__ . '/../../includes/auth.php';
requireLogin([ROLE_STAFF]);

$user = currentUser(); $role = 'staff'; $pageTitle = 'Antrian Pesanan'; $activeMenu = 'orders'; $notifCount = 0;
$db = db();

$filter = $_GET['filter'] ?? 'active';
$search = trim($_GET['q'] ?? '');

$where  = "WHERE o.status IN ('dikonfirmasi','dp_diverifikasi','pemeriksaan_stok','produksi','qc','jadwal_ambil')";
$params = [];
if ($filter === 'all')    { $where = 'WHERE 1=1'; }
if ($filter === 'mine')   { $where .= ' AND o.staff_id=?'; $params[] = $user['id']; }
if ($search)              { $where .= ' AND (o.order_code LIKE ? OR u.name LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; }

$orders = $db->prepare(
    "SELECT o.id, o.order_code, o.status, o.qty, o.size, o.color, o.estimated_done, o.created_at,
            u.name AS customer_name, p.name AS product_name,
            s.name AS staff_name,
            (SELECT pl.progress FROM production_logs pl WHERE pl.order_id=o.id ORDER BY pl.created_at DESC LIMIT 1) AS progress
     FROM orders o
     JOIN users u ON o.customer_id=u.id
     LEFT JOIN products p ON o.product_id=p.id
     LEFT JOIN users s ON o.staff_id=s.id
     $where ORDER BY FIELD(o.status,'qc','produksi','pemeriksaan_stok','dp_diverifikasi','dikonfirmasi','jadwal_ambil'), o.estimated_done ASC LIMIT 60"
);
$orders->execute($params); $orders = $orders->fetchAll();

$statusMap = ['dikonfirmasi'=>['Dikonfirmasi','bm-badge-info'],'dp_diverifikasi'=>['DP OK','bm-badge-success'],'pemeriksaan_stok'=>['Cek Stok','bm-badge-info'],'produksi'=>['Produksi','bm-badge-navy'],'qc'=>['QC','bm-badge-warning'],'jadwal_ambil'=>['Siap Ambil','bm-badge-success']];

require_once __DIR__ . '/../../includes/layout-admin.php';
?>

<div class="bm-page-header"><h1>Antrian Pesanan</h1><p>Daftar pesanan yang perlu ditangani tim produksi</p></div>

<div class="bm-card mb-4">
  <div class="bm-card-body">
    <form method="GET" class="row g-2 align-items-end">
      <div class="col-md-5"><label class="bm-form-label">Cari</label><input type="text" name="q" class="bm-form-control" placeholder="Kode pesanan / nama pelanggan..." value="<?= htmlspecialchars($search) ?>"></div>
      <div class="col-md-4"><label class="bm-form-label">Tampilkan</label>
        <select name="filter" class="bm-form-control">
          <option value="active" <?= $filter==='active'?'selected':'' ?>>Pesanan Aktif</option>
          <option value="mine"   <?= $filter==='mine'  ?'selected':'' ?>>Ditugaskan ke Saya</option>
          <option value="all"    <?= $filter==='all'   ?'selected':'' ?>>Semua</option>
        </select>
      </div>
      <div class="col-md-3 d-flex gap-2">
        <button type="submit" class="btn-navy w-100"><i class="bi bi-search me-1"></i>Cari</button>
        <a href="orders.php" class="btn-ivory w-100 text-center">Reset</a>
      </div>
    </form>
  </div>
</div>

<div class="bm-card">
  <div class="bm-card-header"><span class="bm-card-title"><i class="bi bi-bag me-2"></i>Pesanan <span class="bm-badge bm-badge-navy ms-1"><?= count($orders) ?></span></span></div>
  <div class="table-responsive">
    <table class="bm-table">
      <thead><tr><th>Kode</th><th>Pelanggan</th><th>Produk</th><th>Ukuran/Qty</th><th>Progress</th><th>Est. Selesai</th><th>Status</th><th>Aksi</th></tr></thead>
      <tbody>
      <?php if (empty($orders)): ?><tr><td colspan="8" class="text-center py-4 text-muted">Tidak ada pesanan dalam antrian.</td></tr><?php endif; ?>
      <?php foreach ($orders as $o):
        $prog = (int)($o['progress'] ?? 0);
        [$slbl,$scls] = $statusMap[$o['status']] ?? [ucfirst($o['status']),'bm-badge-muted'];
        $urgent = $o['estimated_done'] && strtotime($o['estimated_done']) < strtotime('+2 days');
      ?>
      <tr>
        <td>
          <a href="<?= APP_URL ?>/modules/staff/production.php?order_id=<?= $o['id'] ?>" style="color:var(--navy);font-weight:700;text-decoration:none"><?= htmlspecialchars($o['order_code']) ?></a>
          <?php if ($urgent): ?><span class="bm-badge bm-badge-danger d-block mt-1" style="width:fit-content">⚡ Urgent</span><?php endif; ?>
        </td>
        <td><?= htmlspecialchars($o['customer_name']) ?></td>
        <td><?= htmlspecialchars($o['product_name']??'Custom') ?></td>
        <td><?= htmlspecialchars($o['size']??'-') ?> · <?= $o['qty'] ?> pcs</td>
        <td style="min-width:100px">
          <div class="d-flex align-items-center gap-2">
            <div class="bm-progress" style="width:60px"><div class="bm-progress-fill" style="width:<?= $prog ?>%"></div></div>
            <span style="font-size:.78rem;font-weight:700;color:var(--navy)"><?= $prog ?>%</span>
          </div>
        </td>
        <td style="font-size:.82rem"><?= $o['estimated_done']?date('d M Y',strtotime($o['estimated_done'])):'-' ?></td>
        <td><span class="bm-badge <?= $scls ?>"><?= $slbl ?></span></td>
        <td>
          <a href="<?= APP_URL ?>/modules/staff/production.php?order_id=<?= $o['id'] ?>" class="btn-navy" style="font-size:.75rem;padding:.3rem .65rem"><i class="bi bi-pencil me-1"></i>Update</a>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require_once __DIR__ . '/../../includes/layout-admin-footer.php'; ?>
