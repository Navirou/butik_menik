<?php
require_once __DIR__ . '/../../includes/auth.php';
requireLogin([ROLE_OWNER]);

$user       = currentUser();
$role       = 'owner';
$pageTitle  = 'Verifikasi Pembayaran';
$activeMenu = 'payments';
$notifCount = 0;

$db = db();

// Handle verify/reject action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['payment_id'])) {
    $pid    = (int)$_POST['payment_id'];
    $action = $_POST['action'];
    $notes  = trim($_POST['notes'] ?? '');
    if ($action === 'verify') {
        $db->prepare("UPDATE payments SET status='diverifikasi', verified_by=?, verified_at=NOW(), notes=? WHERE id=?")->execute([$user['id'], $notes, $pid]);
        // Update order status
        $row = $db->prepare("SELECT order_id, type FROM payments WHERE id=?"); $row->execute([$pid]);
        $pay = $row->fetch();
        if ($pay) {
            $newStatus = $pay['type'] === 'dp' ? 'dp_diverifikasi' : 'selesai';
            $db->prepare("UPDATE orders SET status=?, dp_amount=(SELECT amount FROM payments WHERE id=?) WHERE id=?")->execute([$newStatus,$pid,$pay['order_id']]);
            // Notify customer
            $cid = $db->prepare("SELECT customer_id FROM orders WHERE id=?"); $cid->execute([$pay['order_id']]);
            $custId = $cid->fetchColumn();
            if ($custId) $db->prepare("INSERT INTO notifications(user_id,title,body,type,ref_id) VALUES(?,?,?,?,?)")
                ->execute([$custId,'Pembayaran Terverifikasi','Pembayaran '.$pay['type'].' kamu telah dikonfirmasi.','payment',$pay['order_id']]);
        }
    } elseif ($action === 'reject') {
        $db->prepare("UPDATE payments SET status='ditolak', verified_by=?, verified_at=NOW(), notes=? WHERE id=?")->execute([$user['id'], $notes, $pid]);
    }
    header('Location: payments.php'); exit;
}

$filter  = $_GET['filter'] ?? 'menunggu';
$where   = $filter === 'all' ? '' : 'AND p.status = ?';
$params  = $filter === 'all' ? [] : [$filter];

$stmt = $db->prepare(
    "SELECT p.*, o.order_code, u.name AS customer_name, vr.name AS verified_name
     FROM payments p
     JOIN orders o ON p.order_id=o.id
     JOIN users u ON o.customer_id=u.id
     LEFT JOIN users vr ON p.verified_by=vr.id
     WHERE 1=1 $where ORDER BY p.created_at DESC LIMIT 50"
);
$stmt->execute($params);
$payments = $stmt->fetchAll();

require_once __DIR__ . '/../../includes/layout-admin.php';
?>

<div class="bm-page-header">
  <h1>Verifikasi Pembayaran</h1>
  <p>Tinjau dan verifikasi bukti pembayaran pelanggan</p>
</div>

<!-- Filter Tabs -->
<div class="d-flex gap-2 mb-4 flex-wrap">
  <?php foreach (['menunggu'=>'⏳ Menunggu','diverifikasi'=>'✅ Terverifikasi','ditolak'=>'❌ Ditolak','all'=>'Semua'] as $k=>$l): ?>
  <a href="?filter=<?= $k ?>" class="bm-badge text-decoration-none" style="font-size:.82rem;padding:.5em 1em;<?= $filter===$k ? 'background:var(--navy);color:var(--ivory)' : 'background:#f3f4f6;color:var(--text-muted)' ?>">
    <?= $l ?>
  </a>
  <?php endforeach; ?>
</div>

<div class="row g-3">
<?php if (empty($payments)): ?>
<div class="col-12"><div class="bm-card"><div class="bm-card-body text-center py-5 text-muted"><i class="bi bi-receipt" style="font-size:2.5rem;display:block"></i><p class="mt-2">Tidak ada pembayaran</p></div></div></div>
<?php endif; ?>
<?php foreach ($payments as $pay): ?>
<div class="col-lg-6">
  <div class="bm-card">
    <div class="bm-card-header">
      <div>
        <span class="bm-card-title"><?= htmlspecialchars($pay['order_code']) ?></span>
        <div style="font-size:.78rem;color:var(--text-muted);margin-top:2px"><?= htmlspecialchars($pay['customer_name']) ?></div>
      </div>
      <span class="bm-badge <?= $pay['status']==='diverifikasi'?'bm-badge-success':($pay['status']==='ditolak'?'bm-badge-danger':'bm-badge-warning') ?>">
        <?= ucfirst($pay['status']) ?>
      </span>
    </div>
    <div class="bm-card-body">
      <div class="row g-2 mb-3">
        <div class="col-6"><small class="text-muted d-block">Tipe</small><strong><?= strtoupper($pay['type']) ?></strong></div>
        <div class="col-6"><small class="text-muted d-block">Jumlah</small><strong>Rp <?= number_format($pay['amount'],0,',','.') ?></strong></div>
        <div class="col-6"><small class="text-muted d-block">Metode</small><?= htmlspecialchars($pay['method'] ?? '-') ?></div>
        <div class="col-6"><small class="text-muted d-block">Tanggal</small><?= date('d M Y H:i', strtotime($pay['created_at'])) ?></div>
      </div>

      <?php if ($pay['proof_file']): ?>
      <div class="mb-3">
        <small class="text-muted d-block mb-1">Bukti Transfer</small>
        <a href="<?= APP_URL ?>/uploads/payments/<?= htmlspecialchars($pay['proof_file']) ?>" target="_blank"
           class="bm-upload-zone d-flex align-items-center gap-2 p-3" style="text-decoration:none;border-radius:10px">
          <i class="bi bi-file-earmark-image" style="font-size:1.5rem;color:var(--navy)"></i>
          <div>
            <div style="font-size:.85rem;font-weight:600;color:var(--navy)"><?= htmlspecialchars($pay['proof_file']) ?></div>
            <div style="font-size:.75rem;color:var(--text-muted)">Klik untuk lihat bukti</div>
          </div>
        </a>
      </div>
      <?php endif; ?>

      <?php if ($pay['notes']): ?>
      <p style="font-size:.82rem;color:var(--text-muted);background:#f8f9ff;border-radius:8px;padding:.6rem .8rem"><?= htmlspecialchars($pay['notes']) ?></p>
      <?php endif; ?>

      <?php if ($pay['status'] === 'menunggu'): ?>
      <form method="POST">
        <input type="hidden" name="payment_id" value="<?= $pay['id'] ?>">
        <div class="mb-2">
          <label class="bm-form-label">Catatan (opsional)</label>
          <input type="text" name="notes" class="bm-form-control" placeholder="Catatan verifikasi...">
        </div>
        <div class="d-flex gap-2">
          <button name="action" value="verify" class="btn-navy flex-fill" data-confirm="Verifikasi pembayaran ini?"><i class="bi bi-check-circle me-1"></i>Verifikasi</button>
          <button name="action" value="reject" class="btn-danger-outline flex-fill" data-confirm="Tolak pembayaran ini?"><i class="bi bi-x-circle me-1"></i>Tolak</button>
        </div>
      </form>
      <?php elseif ($pay['verified_name']): ?>
      <p class="mb-0" style="font-size:.78rem;color:var(--text-muted)">Diproses oleh: <strong><?= htmlspecialchars($pay['verified_name']) ?></strong> · <?= date('d M Y', strtotime($pay['verified_at'])) ?></p>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php endforeach; ?>
</div>

<?php require_once __DIR__ . '/../../includes/layout-admin-footer.php'; ?>
