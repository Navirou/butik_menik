<?php
require_once __DIR__ . '/../../includes/auth.php';
requireLogin([ROLE_STAFF]);

$user       = currentUser();
$role       = 'staff';
$pageTitle  = 'Cek Stok Bahan';
$activeMenu = 'stock';
$db         = db();

$nc = $db->prepare('SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0');
$nc->execute([$user['id']]); $notifCount = (int)$nc->fetchColumn();

// Handle stock adjustment (staff can record usage/out)
$success = $error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'record_usage') {
    $matId = (int)$_POST['material_id'];
    $qty   = abs((float)$_POST['qty']);
    $notes = trim($_POST['notes'] ?? '');
    $ref   = trim($_POST['reference'] ?? '');

    if (!$matId || $qty <= 0) {
        $error = 'Pilih bahan dan masukkan jumlah yang valid.';
    } else {
        // Check if enough stock
        $avail = $db->prepare("SELECT current_stock FROM materials WHERE id=?");
        $avail->execute([$matId]); $avail = (float)$avail->fetchColumn();
        if ($qty > $avail) {
            $error = "Stok tidak mencukupi. Stok tersedia: $avail";
        } else {
            $db->prepare("UPDATE materials SET current_stock=current_stock-? WHERE id=?")->execute([$qty, $matId]);
            $db->prepare("INSERT INTO stock_ledger(material_id,type,qty,reference,notes,recorded_by) VALUES(?,'out',?,?,?,?)")
               ->execute([$matId, $qty, $ref, $notes, $user['id']]);
            $success = 'Penggunaan bahan berhasil dicatat.';
        }
    }
}

// Fetch materials
$search = trim($_GET['q'] ?? '');
$where  = $search ? 'WHERE m.name LIKE ?' : '';
$params = $search ? ["%$search%"] : [];

$stmt = $db->prepare(
    "SELECT m.*, u.name AS supplier_name,
            (SELECT SUM(qty) FROM stock_ledger WHERE material_id=m.id AND type='out' AND MONTH(created_at)=MONTH(NOW())) AS used_this_month
     FROM materials m LEFT JOIN users u ON m.supplier_id=u.id
     $where ORDER BY m.current_stock/NULLIF(m.min_stock,0) ASC"
);
$stmt->execute($params);
$materials = $stmt->fetchAll();

// Recent stock ledger
$ledger = $db->query(
    "SELECT sl.*, m.name AS material_name, m.unit, u.name AS recorded_by_name
     FROM stock_ledger sl
     JOIN materials m ON sl.material_id=m.id
     JOIN users u ON sl.recorded_by=u.id
     ORDER BY sl.created_at DESC LIMIT 20"
)->fetchAll();

require_once __DIR__ . '/../../includes/layout-admin.php';
?>

<div class="bm-page-header d-flex justify-content-between align-items-start gap-3">
  <div><h1>Cek Stok Bahan</h1><p>Monitor ketersediaan bahan dan catat penggunaan</p></div>
  <button class="btn-navy" onclick="new bootstrap.Modal(document.getElementById('usageModal')).show()">
    <i class="bi bi-dash-circle me-1"></i>Catat Penggunaan
  </button>
</div>

<?php if ($success): ?><div class="bm-alert bm-alert-success bm-alert-auto-dismiss"><i class="bi bi-check-circle-fill"></i><?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="bm-alert bm-alert-danger"><i class="bi bi-exclamation-circle-fill"></i><?= htmlspecialchars($error) ?></div><?php endif; ?>

<!-- Search -->
<form method="GET" class="mb-4">
  <div class="d-flex gap-2">
    <input type="text" name="q" class="bm-form-control" placeholder="Cari nama bahan..." value="<?= htmlspecialchars($search) ?>" style="max-width:320px">
    <button type="submit" class="btn-navy"><i class="bi bi-search"></i></button>
    <?php if ($search): ?><a href="stock.php" class="btn-ivory">Reset</a><?php endif; ?>
  </div>
</form>

<!-- Stock Cards -->
<div class="row g-3 mb-4">
<?php foreach ($materials as $m):
  $pct     = $m['min_stock'] > 0 ? min(100, round($m['current_stock'] / $m['min_stock'] * 50)) : 100;
  $isLow   = $m['current_stock'] <= $m['min_stock'];
  $isCrit  = $m['current_stock'] <= ($m['min_stock'] * 0.5);
  $barClr  = $isCrit ? '#ef4444' : ($isLow ? '#f59e0b' : '#10b981');
?>
<div class="col-sm-6 col-xl-4">
  <div class="bm-card <?= $isCrit ? 'border-danger' : ($isLow ? 'border-warning' : '') ?>">
    <div class="bm-card-body">
      <div class="d-flex align-items-start justify-content-between mb-1">
        <div>
          <div style="font-weight:700;font-size:.98rem;color:var(--navy)"><?= htmlspecialchars($m['name']) ?></div>
          <div style="font-size:.75rem;color:var(--text-muted)"><?= htmlspecialchars($m['supplier_name'] ?? 'Tanpa supplier') ?></div>
        </div>
        <?php if ($isCrit): ?>
        <span class="bm-badge bm-badge-danger"><i class="bi bi-exclamation-circle me-1"></i>Kritis</span>
        <?php elseif ($isLow): ?>
        <span class="bm-badge bm-badge-warning"><i class="bi bi-exclamation-triangle me-1"></i>Rendah</span>
        <?php else: ?>
        <span class="bm-badge bm-badge-success"><i class="bi bi-check-circle me-1"></i>Aman</span>
        <?php endif; ?>
      </div>

      <div class="d-flex align-items-baseline gap-2 my-2">
        <span style="font-size:2rem;font-weight:800;color:<?= $isCrit ? '#ef4444' : 'var(--navy)' ?>"><?= number_format($m['current_stock'],1) ?></span>
        <span style="font-size:.85rem;color:var(--text-muted)"><?= $m['unit'] ?></span>
        <span style="font-size:.75rem;color:var(--text-muted);margin-left:auto">Min: <?= number_format($m['min_stock'],0) ?></span>
      </div>

      <div class="bm-progress mb-2">
        <div class="bm-progress-fill" style="width:<?= $pct ?>%;background:<?= $barClr ?>"></div>
      </div>

      <?php if ($m['used_this_month']): ?>
      <div style="font-size:.75rem;color:var(--text-muted)">
        <i class="bi bi-arrow-down-circle me-1"></i>Terpakai bulan ini: <strong><?= number_format($m['used_this_month'],1) ?> <?= $m['unit'] ?></strong>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php endforeach; ?>
</div>

<!-- Recent Ledger -->
<div class="bm-card">
  <div class="bm-card-header"><span class="bm-card-title"><i class="bi bi-clock-history me-2"></i>Riwayat Pergerakan Stok</span></div>
  <?php if (empty($ledger)): ?>
  <div class="bm-card-body text-center py-4 text-muted"><p>Belum ada catatan pergerakan stok.</p></div>
  <?php else: ?>
  <div class="table-responsive">
    <table class="bm-table">
      <thead><tr><th>Tanggal</th><th>Bahan</th><th>Tipe</th><th>Jumlah</th><th>Referensi</th><th>Catatan</th><th>Dicatat Oleh</th></tr></thead>
      <tbody>
      <?php foreach ($ledger as $l): ?>
      <tr>
        <td style="font-size:.82rem;white-space:nowrap"><?= date('d M Y, H:i', strtotime($l['created_at'])) ?></td>
        <td style="font-weight:500"><?= htmlspecialchars($l['material_name']) ?></td>
        <td>
          <span class="bm-badge <?= $l['type']==='in' ? 'bm-badge-success' : ($l['type']==='out' ? 'bm-badge-danger' : 'bm-badge-info') ?>">
            <?= $l['type']==='in' ? '▲ Masuk' : ($l['type']==='out' ? '▼ Keluar' : '⇄ Adj') ?>
          </span>
        </td>
        <td style="font-weight:700;color:<?= $l['type']==='in' ? '#059669' : '#ef4444' ?>">
          <?= $l['type']==='in' ? '+' : '-' ?><?= number_format($l['qty'],1) ?> <?= $l['unit'] ?>
        </td>
        <td style="font-size:.82rem"><?= htmlspecialchars($l['reference'] ?? '-') ?></td>
        <td style="font-size:.82rem;color:var(--text-muted)"><?= htmlspecialchars(mb_substr($l['notes'] ?? '-', 0, 40)) ?></td>
        <td style="font-size:.82rem"><?= htmlspecialchars($l['recorded_by_name']) ?></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<!-- Usage Modal -->
<div class="modal fade" id="usageModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="border-radius:16px;overflow:hidden">
      <div class="modal-header" style="background:var(--navy);color:var(--ivory)">
        <h6 class="modal-title fw-bold"><i class="bi bi-dash-circle me-2"></i>Catat Penggunaan Bahan</h6>
        <button class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST">
        <input type="hidden" name="action" value="record_usage">
        <div class="modal-body p-4">
          <div class="mb-3">
            <label class="bm-form-label">Bahan yang Digunakan</label>
            <select name="material_id" class="bm-form-control" required>
              <option value="">Pilih bahan...</option>
              <?php foreach ($materials as $m): ?>
              <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['name']) ?> (Stok: <?= number_format($m['current_stock'],1) ?> <?= $m['unit'] ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="row g-2 mb-3">
            <div class="col-6">
              <label class="bm-form-label">Jumlah Digunakan</label>
              <input type="number" name="qty" class="bm-form-control" step="0.1" min="0.01" required placeholder="0">
            </div>
            <div class="col-6">
              <label class="bm-form-label">Referensi Pesanan</label>
              <input type="text" name="reference" class="bm-form-control" placeholder="Kode pesanan...">
            </div>
          </div>
          <div>
            <label class="bm-form-label">Catatan</label>
            <textarea name="notes" class="bm-form-control" rows="2" placeholder="Keterangan penggunaan..."></textarea>
          </div>
        </div>
        <div class="modal-footer border-0 pt-0 px-4 pb-4">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="border-radius:8px">Batal</button>
          <button type="submit" class="btn-navy"><i class="bi bi-check-circle me-1"></i>Catat</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../../includes/layout-admin-footer.php'; ?>
