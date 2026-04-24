<?php
require_once __DIR__ . '/../../includes/auth.php';
requireLogin([ROLE_SUPPLIER]);

$user       = currentUser();
$role       = 'supplier';
$pageTitle  = 'Faktur Saya';
$activeMenu = 'invoices';
$db         = db();

$nc = $db->prepare('SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0');
$nc->execute([$user['id']]); $notifCount = (int)$nc->fetchColumn();

// Handle invoice upload
$success = $error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'upload_invoice') {
    $rid = (int)$_POST['req_id'];

    // Verify request belongs to this supplier
    $chk = $db->prepare("SELECT id FROM material_requests WHERE id=? AND supplier_id=?");
    $chk->execute([$rid, $user['id']]);
    if (!$chk->fetch()) {
        $error = 'Permintaan tidak ditemukan.';
    } elseif (empty($_FILES['invoice_file']['name'])) {
        $error = 'Pilih file faktur terlebih dahulu.';
    } else {
        $ext = strtolower(pathinfo($_FILES['invoice_file']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg','jpeg','png','pdf'])) {
            $error = 'Format file harus JPG, PNG, atau PDF.';
        } elseif ($_FILES['invoice_file']['size'] > 5 * 1024 * 1024) {
            $error = 'Ukuran file maks 5MB.';
        } else {
            $filename = 'inv_' . $user['id'] . '_' . $rid . '_' . time() . '.' . $ext;
            move_uploaded_file($_FILES['invoice_file']['tmp_name'], UPLOAD_PATH . 'invoices/' . $filename);
            $db->prepare("UPDATE material_requests SET invoice_file=? WHERE id=?")->execute([$filename, $rid]);
            $success = 'Faktur berhasil diupload.';
        }
    }
}

// Fetch all requests with invoices info
$requests = $db->prepare(
    "SELECT mr.*, m.name AS material_name, m.unit
     FROM material_requests mr
     JOIN materials m ON mr.material_id=m.id
     WHERE mr.supplier_id=?
     ORDER BY mr.updated_at DESC"
);
$requests->execute([$user['id']]);
$requests = $requests->fetchAll();

$withInvoice    = count(array_filter($requests, fn($r) => !empty($r['invoice_file'])));
$withoutInvoice = count(array_filter($requests, fn($r) => empty($r['invoice_file']) && $r['status'] !== 'cancelled'));

require_once __DIR__ . '/../../includes/layout-admin.php';
?>

<div class="bm-page-header"><h1>Faktur Saya</h1><p>Kelola dan upload faktur untuk setiap pengiriman bahan</p></div>

<?php if ($success): ?><div class="bm-alert bm-alert-success bm-alert-auto-dismiss"><i class="bi bi-check-circle-fill"></i><?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="bm-alert bm-alert-danger"><i class="bi bi-exclamation-circle-fill"></i><?= htmlspecialchars($error) ?></div><?php endif; ?>

<!-- Summary -->
<div class="row g-3 mb-4">
  <?php foreach ([
    ['bi-receipt','#e8f0fe','#003366', count($requests),  'Total Transaksi'],
    ['bi-file-check-fill','#d1fae5','#065f46', $withInvoice,    'Sudah Ada Faktur'],
    ['bi-file-earmark-x','#fee2e2','#991b1b', $withoutInvoice, 'Belum Ada Faktur'],
  ] as [$ico,$bg,$ic,$val,$lbl]): ?>
  <div class="col-sm-4">
    <div class="bm-stat-card">
      <div class="bm-stat-icon" style="background:<?= $bg ?>"><i class="bi <?= $ico ?>" style="color:<?= $ic ?>"></i></div>
      <div class="bm-stat-val"><?= $val ?></div>
      <div class="bm-stat-lbl"><?= $lbl ?></div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Invoice List -->
<?php if (empty($requests)): ?>
<div class="bm-card"><div class="bm-card-body text-center py-5 text-muted">
  <i class="bi bi-receipt" style="font-size:2.5rem;display:block;margin-bottom:.5rem"></i>
  <p>Belum ada transaksi.</p>
</div></div>
<?php else: ?>
<div class="row g-3">
<?php foreach ($requests as $r):
  if ($r['status'] === 'cancelled') continue;
  $hasInvoice = !empty($r['invoice_file']);
?>
<div class="col-lg-6">
  <div class="bm-card <?= !$hasInvoice && in_array($r['status'],['shipped','received']) ? 'border-warning' : '' ?>">
    <div class="bm-card-header">
      <div>
        <div style="font-size:.75rem;color:var(--text-muted)"><?= htmlspecialchars($r['request_code']) ?> · <?= date('d M Y', strtotime($r['created_at'])) ?></div>
        <div style="font-weight:700;font-size:1rem;color:var(--navy)"><?= htmlspecialchars($r['material_name']) ?></div>
        <div style="font-size:.8rem;color:var(--text-muted)"><?= number_format($r['qty_requested'],1) ?> <?= $r['unit'] ?><?= $r['qty_received'] ? ' · Diterima: '.number_format($r['qty_received'],1) : '' ?></div>
      </div>
      <span class="bm-badge <?= $hasInvoice ? 'bm-badge-success' : 'bm-badge-warning' ?>">
        <i class="bi <?= $hasInvoice ? 'bi-file-check-fill' : 'bi-file-earmark-x' ?> me-1"></i>
        <?= $hasInvoice ? 'Ada Faktur' : 'Belum Ada' ?>
      </span>
    </div>
    <div class="bm-card-body">
      <?php if ($hasInvoice): ?>
      <!-- Show existing invoice -->
      <div class="d-flex align-items-center gap-3 mb-3" style="background:var(--ivory);border-radius:10px;padding:.85rem">
        <div style="width:42px;height:42px;border-radius:10px;background:var(--navy);display:flex;align-items:center;justify-content:center;flex-shrink:0">
          <i class="bi bi-file-earmark-pdf-fill" style="color:var(--ivory);font-size:1.2rem"></i>
        </div>
        <div style="flex:1;min-width:0">
          <div style="font-weight:600;font-size:.88rem;color:var(--navy);word-break:break-all"><?= htmlspecialchars($r['invoice_file']) ?></div>
          <div style="font-size:.75rem;color:var(--text-muted)">Diupload</div>
        </div>
        <a href="<?= APP_URL ?>/uploads/invoices/<?= urlencode($r['invoice_file']) ?>" target="_blank"
           class="btn-ivory" style="font-size:.8rem;padding:.35rem .75rem;flex-shrink:0">
          <i class="bi bi-eye me-1"></i>Lihat
        </a>
      </div>
      <button class="btn-ivory w-100" style="font-size:.82rem" onclick="openUpload(<?= $r['id'] ?>, '<?= htmlspecialchars($r['request_code']) ?>')">
        <i class="bi bi-arrow-repeat me-1"></i>Ganti Faktur
      </button>
      <?php else: ?>
      <?php if (in_array($r['status'], ['shipped','received'])): ?>
      <div class="bm-alert bm-alert-warning" style="margin-bottom:.75rem">
        <i class="bi bi-exclamation-triangle-fill"></i>Faktur belum diupload untuk pengiriman ini.
      </div>
      <?php endif; ?>
      <button class="btn-navy w-100" onclick="openUpload(<?= $r['id'] ?>, '<?= htmlspecialchars($r['request_code']) ?>')">
        <i class="bi bi-cloud-upload me-1"></i>Upload Faktur
      </button>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Upload Modal -->
<div class="modal fade" id="uploadModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="border-radius:16px;overflow:hidden">
      <div class="modal-header" style="background:var(--navy);color:var(--ivory)">
        <h6 class="modal-title fw-bold"><i class="bi bi-file-earmark-arrow-up me-2"></i>Upload Faktur – <span id="modal-req-code"></span></h6>
        <button class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="action" value="upload_invoice">
        <input type="hidden" name="req_id" id="modal-req-id">
        <div class="modal-body p-4">
          <div class="bm-upload-zone">
            <i class="bi bi-cloud-upload"></i>
            <p>Klik atau seret file faktur ke sini</p>
            <small>Format: JPG, PNG, PDF · Maks 5MB</small>
            <div class="bm-upload-filename mt-2" style="font-size:.82rem;color:var(--navy);font-weight:600"></div>
            <input type="file" name="invoice_file" class="bm-file-input" accept=".jpg,.jpeg,.png,.pdf" required style="display:none">
          </div>
        </div>
        <div class="modal-footer border-0 pt-0 px-4 pb-4">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="border-radius:8px">Batal</button>
          <button type="submit" class="btn-navy"><i class="bi bi-cloud-upload me-1"></i>Upload Sekarang</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function openUpload(reqId, reqCode) {
  document.getElementById('modal-req-id').value   = reqId;
  document.getElementById('modal-req-code').textContent = reqCode;
  new bootstrap.Modal(document.getElementById('uploadModal')).show();
}
</script>

<?php require_once __DIR__ . '/../../includes/layout-admin-footer.php'; ?>
