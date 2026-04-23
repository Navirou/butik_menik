<?php
require_once __DIR__ . '/../../includes/auth.php';
requireLogin([ROLE_OWNER]);

$user = currentUser(); $role = 'owner'; $pageTitle = 'Manajemen Stok Bahan'; $activeMenu = 'stock'; $notifCount = 0;
$db = db();

// Handle new request to supplier
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'request') {
    $matId  = (int)$_POST['material_id'];
    $supId  = (int)$_POST['supplier_id'];
    $qty    = (float)$_POST['qty_requested'];
    $prio   = in_array($_POST['priority'],['regular','urgent']) ? $_POST['priority'] : 'regular';
    $notes  = trim($_POST['notes'] ?? '');
    $code   = 'REQ-' . date('Y') . '-' . str_pad($db->query("SELECT COUNT(*)+1 FROM material_requests")->fetchColumn(), 3, '0', STR_PAD_LEFT);
    $db->prepare("INSERT INTO material_requests (request_code,material_id,requested_by,supplier_id,qty_requested,priority,notes) VALUES(?,?,?,?,?,?,?)")
       ->execute([$code,$matId,$user['id'],$supId,$qty,$prio,$notes]);
    header('Location: stock.php?success=1'); exit;
}

$materials = $db->query(
    "SELECT m.*, u.name AS supplier_name, u.id AS supplier_id FROM materials m LEFT JOIN users u ON m.supplier_id=u.id ORDER BY m.name"
)->fetchAll();
$suppliers = $db->query("SELECT id, name FROM users WHERE role_id=".ROLE_SUPPLIER)->fetchAll();

require_once __DIR__ . '/../../includes/layout-admin.php';
?>

<div class="bm-page-header d-flex align-items-start justify-content-between gap-3">
  <div>
    <h1>Stok Bahan Baku</h1>
    <p>Monitor ketersediaan bahan dan buat permintaan ke supplier</p>
  </div>
  <button class="btn-navy" onclick="new bootstrap.Modal(document.getElementById('reqModal')).show()"><i class="bi bi-plus-lg me-1"></i>Permintaan Bahan</button>
</div>

<?php if (isset($_GET['success'])): ?>
<div class="bm-alert bm-alert-success bm-alert-auto-dismiss"><i class="bi bi-check-circle-fill"></i>Permintaan bahan berhasil dikirim ke supplier.</div>
<?php endif; ?>

<!-- Stock Grid -->
<div class="row g-3">
<?php foreach ($materials as $m):
  $pct     = $m['min_stock'] > 0 ? min(100, round($m['current_stock'] / $m['min_stock'] * 50)) : 100;
  $isLow   = $m['current_stock'] <= $m['min_stock'];
  $barColor= $isLow ? 'var(--danger)' : ($pct < 80 ? 'var(--warning)' : 'var(--success)');
?>
<div class="col-sm-6 col-xl-4">
  <div class="bm-card <?= $isLow ? 'border-danger' : '' ?>">
    <div class="bm-card-body">
      <div class="d-flex align-items-start justify-content-between mb-2">
        <div>
          <div style="font-weight:700;font-size:1rem;color:var(--navy)"><?= htmlspecialchars($m['name']) ?></div>
          <div style="font-size:.78rem;color:var(--text-muted)"><?= htmlspecialchars($m['supplier_name'] ?? 'Tanpa supplier') ?></div>
        </div>
        <?php if ($isLow): ?>
        <span class="bm-badge bm-badge-danger"><i class="bi bi-exclamation-circle me-1"></i>Stok Kritis</span>
        <?php endif; ?>
      </div>
      <div class="d-flex align-items-baseline gap-2 mb-2">
        <span style="font-size:1.75rem;font-weight:800;color:<?= $isLow ? 'var(--danger)' : 'var(--navy)' ?>"><?= number_format($m['current_stock'],1) ?></span>
        <span style="font-size:.85rem;color:var(--text-muted)"><?= $m['unit'] ?></span>
        <span style="font-size:.78rem;color:var(--text-muted);margin-left:auto">Min: <?= number_format($m['min_stock'],0) ?> <?= $m['unit'] ?></span>
      </div>
      <div class="bm-progress mb-3">
        <div class="bm-progress-fill" style="width:<?= $pct ?>%;background:<?= $barColor ?>"></div>
      </div>
      <button class="btn-ivory w-100" style="font-size:.82rem" onclick="prefillRequest(<?= $m['id'] ?>, '<?= htmlspecialchars($m['name']) ?>', <?= $m['supplier_id'] ?>)">
        <i class="bi bi-truck me-1"></i>Minta ke Supplier
      </button>
    </div>
  </div>
</div>
<?php endforeach; ?>
</div>

<!-- Request Modal -->
<div class="modal fade" id="reqModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="border-radius:16px;overflow:hidden">
      <div class="modal-header" style="background:var(--navy);color:var(--ivory)">
        <h6 class="modal-title fw-bold"><i class="bi bi-truck me-2"></i>Permintaan Bahan Baku</h6>
        <button class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST">
        <input type="hidden" name="action" value="request">
        <div class="modal-body p-4">
          <div class="mb-3">
            <label class="bm-form-label">Bahan</label>
            <select name="material_id" id="req-material" class="bm-form-control" required>
              <option value="">Pilih bahan...</option>
              <?php foreach ($materials as $m): ?>
              <option value="<?= $m['id'] ?>" data-supplier="<?= $m['supplier_id'] ?>"><?= htmlspecialchars($m['name']) ?> (Stok: <?= $m['current_stock'] ?> <?= $m['unit'] ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="bm-form-label">Supplier</label>
            <select name="supplier_id" id="req-supplier" class="bm-form-control" required>
              <option value="">Pilih supplier...</option>
              <?php foreach ($suppliers as $s): ?>
              <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="row g-2 mb-3">
            <div class="col-7">
              <label class="bm-form-label">Jumlah</label>
              <input type="number" name="qty_requested" class="bm-form-control" step="0.1" min="0.1" required placeholder="0">
            </div>
            <div class="col-5">
              <label class="bm-form-label">Prioritas</label>
              <select name="priority" class="bm-form-control">
                <option value="regular">Regular</option>
                <option value="urgent">Urgent</option>
              </select>
            </div>
          </div>
          <div class="mb-2">
            <label class="bm-form-label">Catatan</label>
            <textarea name="notes" class="bm-form-control" rows="2" placeholder="Keterangan tambahan..."></textarea>
          </div>
        </div>
        <div class="modal-footer border-0 pt-0 px-4 pb-4">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="border-radius:8px">Batal</button>
          <button type="submit" class="btn-navy"><i class="bi bi-send me-1"></i>Kirim Permintaan</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function prefillRequest(matId, matName, supplierId) {
  document.getElementById('req-material').value = matId;
  if (supplierId) document.getElementById('req-supplier').value = supplierId;
  new bootstrap.Modal(document.getElementById('reqModal')).show();
}
</script>
<?php require_once __DIR__ . '/../../includes/layout-admin-footer.php'; ?>
