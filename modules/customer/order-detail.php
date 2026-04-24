<?php
require_once __DIR__ . '/../../includes/auth.php';
requireLogin([ROLE_CUSTOMER]);

$user = currentUser();
$db   = db();
$code = trim($_GET['code'] ?? '');

$stmt = $db->prepare(
    "SELECT o.*, p.name AS product_name, p.base_price, pc.name AS cat_name
     FROM orders o
     LEFT JOIN products p ON o.product_id=p.id
     LEFT JOIN product_categories pc ON p.category_id=pc.id
     WHERE o.order_code=? AND o.customer_id=?"
);
$stmt->execute([$code, $user['id']]);
$order = $stmt->fetch();
if (!$order) { header('Location: dashboard.php'); exit; }

// Production logs
$logs = $db->prepare(
    "SELECT pl.*, ps.label AS stage_label, ps.seq, u.name AS staff_name
     FROM production_logs pl
     JOIN production_stages ps ON pl.stage_id=ps.id
     JOIN users u ON pl.updated_by=u.id
     WHERE pl.order_id=? ORDER BY pl.created_at DESC LIMIT 10"
);
$logs->execute([$order['id']]);
$prodLogs = $logs->fetchAll();

// Payments
$payStmt = $db->prepare("SELECT * FROM payments WHERE order_id=? ORDER BY created_at ASC");
$payStmt->execute([$order['id']]);
$payments = $payStmt->fetchAll();

// Revisions
$revStmt = $db->prepare("SELECT * FROM revisions WHERE order_id=? ORDER BY created_at DESC");
$revStmt->execute([$order['id']]);
$revisions = $revStmt->fetchAll();

// Handle revision submit
$revSuccess = $revError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'revision') {
    $desc = trim($_POST['description'] ?? '');
    if (!$desc) { $revError = 'Deskripsi keluhan wajib diisi.'; }
    else {
        $db->prepare("INSERT INTO revisions(order_id,requested_by,description) VALUES(?,?,?)")->execute([$order['id'],$user['id'],$desc]);
        $db->prepare("UPDATE orders SET status='revisi' WHERE id=?")->execute([$order['id']]);
        header("Location: order-detail.php?code=$code&revised=1"); exit;
    }
}
if (isset($_GET['revised'])) $revSuccess = 'Keluhan berhasil dikirim!';

$stages = [
    ['key'=>'dp_diverifikasi', 'label'=>'DP Diverifikasi', 'icon'=>'bi-credit-card-fill'],
    ['key'=>'pemeriksaan_stok','label'=>'Pemeriksaan Stok', 'icon'=>'bi-boxes'],
    ['key'=>'produksi',        'label'=>'Produksi',         'icon'=>'bi-tools'],
    ['key'=>'qc',              'label'=>'Quality Control',  'icon'=>'bi-clipboard-check-fill'],
    ['key'=>'jadwal_ambil',    'label'=>'Jadwal Ambil',     'icon'=>'bi-calendar-check-fill'],
];
$stageOrder = ['menunggu_konfirmasi','dikonfirmasi','dp_menunggu','dp_diverifikasi','pemeriksaan_stok','produksi','qc','jadwal_ambil','selesai'];
$currentIdx = array_search($order['status'], $stageOrder);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Detail Pesanan <?= htmlspecialchars($code) ?> – <?= APP_NAME ?></title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="<?= APP_URL ?>/assets/css/global.css" rel="stylesheet">
<style>
body { background:var(--surface); padding-bottom:2rem; }
.top-bar { background:var(--navy); padding:.9rem 1.25rem; display:flex; align-items:center; gap:1rem; }
.brand { font-family:var(--ff-display); color:var(--ivory); font-size:1rem; flex:1; }
.page-wrap { max-width:620px; margin:0 auto; padding:1.25rem; }
</style>
</head>
<body>
<div class="top-bar">
  <a href="dashboard.php" style="color:rgba(255,255,224,.7);text-decoration:none;font-size:1.2rem"><i class="bi bi-arrow-left"></i></a>
  <span class="brand">Status Pesanan #<?= htmlspecialchars(substr($code,-6)) ?></span>
  <a href="payment.php?code=<?= urlencode($code) ?>" style="color:rgba(255,255,224,.7);text-decoration:none;font-size:1.1rem" title="Bayar"><i class="bi bi-wallet2"></i></a>
</div>

<div class="page-wrap">
  <?php if ($revSuccess): ?><div class="bm-alert bm-alert-success bm-alert-auto-dismiss"><i class="bi bi-check-circle-fill"></i><?= $revSuccess ?></div><?php endif; ?>
  <?php if ($revError): ?><div class="bm-alert bm-alert-danger"><i class="bi bi-exclamation-circle-fill"></i><?= htmlspecialchars($revError) ?></div><?php endif; ?>

  <!-- Pickup Notification (if ready) -->
  <?php if ($order['status'] === 'jadwal_ambil' && $order['pickup_date']): ?>
  <div class="bm-card mb-3" style="border:2px solid #10b981">
    <div class="bm-card-body text-center py-3">
      <div style="font-size:2rem;margin-bottom:.5rem">📅</div>
      <div style="font-family:var(--ff-display);font-size:1.2rem;color:var(--navy);margin-bottom:.5rem">Jadwal Pengambilan</div>
      <div class="d-flex flex-column gap-1 text-start" style="max-width:260px;margin:0 auto">
        <div class="d-flex gap-2 align-items-center" style="font-size:.88rem"><i class="bi bi-bag text-secondary"></i><div><small class="text-muted d-block">ID Pesanan</small><strong><?= htmlspecialchars($code) ?></strong></div></div>
        <div class="d-flex gap-2 align-items-center mt-1" style="font-size:.88rem"><i class="bi bi-calendar3 text-secondary"></i><div><small class="text-muted d-block">Tanggal</small><strong><?= date('l, d F Y', strtotime($order['pickup_date'])) ?></strong></div></div>
        <?php if ($order['pickup_time_start']): ?>
        <div class="d-flex gap-2 align-items-center mt-1" style="font-size:.88rem"><i class="bi bi-clock text-secondary"></i><div><small class="text-muted d-block">Waktu</small><strong><?= substr($order['pickup_time_start'],0,5) ?> – <?= substr($order['pickup_time_end'],0,5) ?> WIB</strong></div></div>
        <?php endif; ?>
        <div class="d-flex gap-2 align-items-center mt-1" style="font-size:.88rem"><i class="bi bi-geo-alt-fill text-secondary"></i><div><small class="text-muted d-block">Lokasi Pengambilan</small><strong><?= APP_NAME ?></strong><div style="font-size:.76rem;color:var(--text-muted)">Jl. Sudirman No. 123, Jakarta Pusat</div></div></div>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- Production Timeline -->
  <div class="bm-card mb-3">
    <div class="bm-card-header"><span class="bm-card-title"><i class="bi bi-diagram-3 me-2"></i>Status Produksi</span></div>
    <div class="bm-card-body">
      <ul class="bm-timeline">
        <?php foreach ($stages as $stage):
          $stageIdx  = array_search($stage['key'], $stageOrder);
          $isDone    = $currentIdx !== false && $stageIdx !== false && $currentIdx > $stageIdx;
          $isActive  = $order['status'] === $stage['key'];
          $isPending = !$isDone && !$isActive;

          // Find latest log for this stage
          $stageLog = null;
          foreach ($prodLogs as $log) {
            if (str_contains(strtolower($log['stage_label']), strtolower($stage['label']))) { $stageLog = $log; break; }
          }
        ?>
        <li class="bm-timeline-item">
          <div class="bm-timeline-dot <?= $isDone ? 'done' : ($isActive ? 'active' : '') ?>">
            <?php if ($isDone): ?><i class="bi bi-check"></i>
            <?php elseif ($isActive): ?><i class="bi <?= $stage['icon'] ?>"></i>
            <?php else: ?><i class="bi bi-circle" style="font-size:.6rem"></i>
            <?php endif; ?>
          </div>
          <div class="bm-timeline-content">
            <div class="bm-timeline-title <?= $isPending ? 'muted' : '' ?>"><?= $stage['label'] ?>
              <?php if ($isActive): ?><span class="bm-badge bm-badge-navy ms-2" style="font-size:.65rem">Sekarang</span><?php endif; ?>
            </div>
            <?php if ($stageLog): ?>
            <div class="bm-timeline-meta">
              <?= date('d M Y, H:i', strtotime($stageLog['created_at'])) ?>
              <?php if ($stageLog['progress'] > 0): ?> · <?= $stageLog['progress'] ?>%<?php endif; ?>
            </div>
            <?php if ($isActive && $stageLog['progress'] > 0): ?>
            <div class="bm-progress mt-1" style="width:160px"><div class="bm-progress-fill" style="width:<?= $stageLog['progress'] ?>%"></div></div>
            <?php endif; ?>
            <?php elseif ($isPending): ?>
            <div class="bm-timeline-meta">Menunggu</div>
            <?php endif; ?>
          </div>
        </li>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>

  <!-- Order Details -->
  <div class="bm-card mb-3">
    <div class="bm-card-header"><span class="bm-card-title"><i class="bi bi-bag me-2"></i>Detail Pesanan</span></div>
    <div class="bm-card-body">
      <div class="d-flex gap-3 align-items-center mb-3 p-3" style="background:var(--ivory);border-radius:10px">
        <div style="width:50px;height:50px;border-radius:10px;background:var(--navy);display:flex;align-items:center;justify-content:center;font-size:1.5rem">👕</div>
        <div>
          <div style="font-weight:700;font-size:.95rem;color:var(--navy)"><?= htmlspecialchars($order['product_name'] ?? 'Pesanan Custom') ?></div>
          <?php if ($order['size']): ?><div style="font-size:.8rem;color:var(--text-muted)">Ukuran: <?= htmlspecialchars($order['size']) ?></div><?php endif; ?>
          <?php if ($order['color']): ?><div style="font-size:.8rem;color:var(--text-muted)">Warna: <?= htmlspecialchars($order['color']) ?></div><?php endif; ?>
          <div style="font-size:.8rem;color:var(--text-muted)">Qty: <?= $order['qty'] ?> pcs</div>
        </div>
        <div class="ms-auto text-end"><div style="font-weight:700;color:var(--navy)">Rp <?= number_format($order['total_price'],0,',','.') ?></div></div>
      </div>
      <?php $rows = [
        ['ID Pesanan','order_code'],['Tanggal Pesan','created_at','date'],['Estimasi Selesai','estimated_done','date'],
        ['Total Bayar','total_price','rupiah'],['DP Dibayar','dp_amount','rupiah'],
      ]; foreach ($rows as [$lbl,$key,$fmt='raw']): $val=$order[$key]; ?>
      <div class="d-flex justify-content-between py-2" style="border-bottom:1px solid var(--border);font-size:.88rem">
        <span style="color:var(--text-muted)"><?= $lbl ?></span>
        <strong style="color:var(--navy)"><?php
          if ($fmt==='date') echo $val ? date('d M Y', strtotime($val)) : '-';
          elseif ($fmt==='rupiah') echo 'Rp '.number_format((float)$val,0,',','.');
          else echo htmlspecialchars((string)$val);
        ?></strong>
      </div>
      <?php endforeach; ?>
      <div class="d-flex justify-content-between pt-2" style="font-size:.88rem">
        <span style="color:var(--text-muted)">Sisa Pembayaran</span>
        <strong style="color:<?= $order['total_price']-$order['dp_amount']>0 ? 'var(--danger)' : 'var(--success)' ?>">
          Rp <?= number_format(max(0,$order['total_price']-$order['dp_amount']),0,',','.') ?>
        </strong>
      </div>
    </div>
  </div>

  <!-- Payment History -->
  <?php if (!empty($payments)): ?>
  <div class="bm-card mb-3">
    <div class="bm-card-header"><span class="bm-card-title"><i class="bi bi-receipt me-2"></i>Riwayat Pembayaran</span></div>
    <div class="bm-card-body p-0">
      <?php foreach ($payments as $pay): ?>
      <div class="d-flex align-items-center gap-3 px-4 py-3" style="border-bottom:1px solid var(--border)">
        <div style="width:38px;height:38px;border-radius:9px;background:<?= $pay['status']==='diverifikasi'?'#d1fae5':'#fef3c7' ?>;display:flex;align-items:center;justify-content:center;flex-shrink:0">
          <i class="bi <?= $pay['status']==='diverifikasi'?'bi-check-circle-fill':'bi-clock-fill' ?>" style="color:<?= $pay['status']==='diverifikasi'?'#065f46':'#92400e' ?>"></i>
        </div>
        <div>
          <div style="font-weight:600;font-size:.9rem"><?= strtoupper($pay['type']) ?> – Rp <?= number_format($pay['amount'],0,',','.') ?></div>
          <div style="font-size:.75rem;color:var(--text-muted)"><?= $pay['method'] ?> · <?= date('d M Y', strtotime($pay['created_at'])) ?></div>
        </div>
        <span class="ms-auto bm-badge <?= $pay['status']==='diverifikasi'?'bm-badge-success':($pay['status']==='ditolak'?'bm-badge-danger':'bm-badge-warning') ?>"><?= ucfirst($pay['status']) ?></span>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- Action Buttons -->
  <div class="d-grid gap-2">
    <?php if (in_array($order['status'],['dp_menunggu','dikonfirmasi'])): ?>
    <a href="payment.php?code=<?= urlencode($code) ?>" class="btn-navy text-center" style="padding:.75rem;border-radius:12px;font-size:.95rem">
      <i class="bi bi-credit-card me-2"></i>Bayar DP Sekarang
    </a>
    <?php elseif ($order['status']==='jadwal_ambil'): ?>
    <a href="payment.php?code=<?= urlencode($code) ?>" class="btn-navy text-center" style="padding:.75rem;border-radius:12px;background:#059669;font-size:.95rem">
      <i class="bi bi-wallet2 me-2"></i>Lunasi Pembayaran
    </a>
    <?php endif; ?>

    <?php if (in_array($order['status'],['selesai','qc','jadwal_ambil']) && !in_array($order['status'],['revisi'])): ?>
    <button class="btn-ivory" style="border-radius:12px;padding:.7rem" onclick="new bootstrap.Modal(document.getElementById('revisionModal')).show()">
      <i class="bi bi-exclamation-triangle me-2"></i>Ajukan Keluhan / Revisi
    </button>
    <?php endif; ?>
  </div>
</div>

<!-- Revision Modal -->
<div class="modal fade" id="revisionModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="border-radius:16px;overflow:hidden">
      <div class="modal-header" style="background:var(--navy);color:var(--ivory)">
        <h6 class="modal-title fw-bold"><i class="bi bi-exclamation-triangle me-2"></i>Ajukan Keluhan / Revisi</h6>
        <button class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST">
        <input type="hidden" name="action" value="revision">
        <div class="modal-body p-4">
          <p style="font-size:.85rem;color:var(--text-muted)">Jelaskan keluhan atau revisi yang diinginkan. Tim kami akan segera menindaklanjuti.</p>
          <textarea name="description" class="bm-form-control" rows="4" placeholder="Contoh: Jahitan di bagian lengan kurang rapi..." required></textarea>
        </div>
        <div class="modal-footer border-0 pt-0 px-4 pb-4">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="border-radius:8px">Batal</button>
          <button type="submit" class="btn-navy"><i class="bi bi-send me-1"></i>Kirim Keluhan</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= APP_URL ?>/assets/js/global.js"></script>
</body>
</html>
