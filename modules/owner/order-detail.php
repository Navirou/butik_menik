<?php
require_once __DIR__ . '/../../includes/auth.php';
requireLogin([ROLE_OWNER]);

$user = currentUser(); $role = 'owner'; $activeMenu = 'orders'; $notifCount = 0;
$db = db();
$id = (int)($_GET['id'] ?? 0);

$order = $db->prepare(
    "SELECT o.*, u.name AS customer_name, u.email AS customer_email, u.phone AS customer_phone,
            p.name AS product_name, s.name AS staff_name
     FROM orders o JOIN users u ON o.customer_id=u.id
     LEFT JOIN products p ON o.product_id=p.id LEFT JOIN users s ON o.staff_id=s.id
     WHERE o.id=?"
);
$order->execute([$id]); $order = $order->fetch();
if (!$order) { header('Location: orders.php'); exit; }

$pageTitle = 'Detail Pesanan #'.$order['order_code'];

$logs = $db->prepare("SELECT pl.*, ps.label AS stage, u.name AS staff FROM production_logs pl JOIN production_stages ps ON pl.stage_id=ps.id JOIN users u ON pl.updated_by=u.id WHERE pl.order_id=? ORDER BY pl.created_at DESC");
$logs->execute([$id]); $logs = $logs->fetchAll();

$payments = $db->prepare("SELECT p.*, u.name AS verified_name FROM payments p LEFT JOIN users u ON p.verified_by=u.id WHERE p.order_id=? ORDER BY p.created_at ASC");
$payments->execute([$id]); $payments = $payments->fetchAll();

$revisions = $db->prepare("SELECT r.*, u.name AS handler FROM revisions r LEFT JOIN users u ON r.handled_by=u.id WHERE r.order_id=? ORDER BY r.created_at DESC");
$revisions->execute([$id]); $revisions = $revisions->fetchAll();

$qc = $db->prepare("SELECT qr.*, u.name AS checker FROM qc_results qr JOIN users u ON qr.checked_by=u.id WHERE qr.order_id=?");
$qc->execute([$id]); $qc = $qc->fetch();

$staffList = $db->query("SELECT id, name FROM users WHERE role_id=".ROLE_STAFF)->fetchAll();

$success = $error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'update_status') {
        $newStatus = $_POST['status'] ?? ''; $note = trim($_POST['owner_notes'] ?? '');
        $db->prepare("UPDATE orders SET status=?, owner_notes=? WHERE id=?")->execute([$newStatus,$note,$id]);
        $success = 'Status pesanan berhasil diperbarui.';
    } elseif ($action === 'assign_staff') {
        $sid = (int)$_POST['staff_id'];
        $db->prepare("UPDATE orders SET staff_id=? WHERE id=?")->execute([$sid,$id]);
        $success = 'Staff berhasil ditugaskan.';
    } elseif ($action === 'set_pickup') {
        $db->prepare("UPDATE orders SET pickup_date=?,pickup_time_start=?,pickup_time_end=?,status='jadwal_ambil' WHERE id=?")
           ->execute([$_POST['pickup_date'],$_POST['pickup_time_start'],$_POST['pickup_time_end'],$id]);
        // Notify customer
        $db->prepare("INSERT INTO notifications(user_id,title,body,type,ref_id) VALUES(?,?,?,?,?)")
           ->execute([$order['customer_id'],'Jadwal Pengambilan Sudah Tersedia','Pesanan '.$order['order_code'].' siap diambil! Cek jadwalnya di aplikasi.','pickup',$id]);
        $success = 'Jadwal pengambilan berhasil diatur dan notifikasi dikirim ke pelanggan.';
    } elseif ($action === 'handle_revision') {
        $rid = (int)$_POST['revision_id']; $rs = $_POST['revision_status']; $rn = trim($_POST['handler_note']??'');
        $db->prepare("UPDATE revisions SET status=?,handled_by=?,handler_note=? WHERE id=?")->execute([$rs,$user['id'],$rn,$rid]);
        if ($rs === 'resolved') $db->prepare("UPDATE orders SET status='produksi' WHERE id=?")->execute([$id]);
        $success = 'Revisi berhasil ditangani.';
    }
    header("Location: order-detail.php?id=$id&ok=1"); exit;
}
if (isset($_GET['ok'])) $success = 'Perubahan berhasil disimpan.';

$statusOptions = ['menunggu_konfirmasi'=>'Menunggu Konfirmasi','dikonfirmasi'=>'Dikonfirmasi','ditolak'=>'Ditolak','dp_menunggu'=>'DP Menunggu','dp_diverifikasi'=>'DP Terverifikasi','pemeriksaan_stok'=>'Cek Stok','produksi'=>'Produksi','qc'=>'QC','jadwal_ambil'=>'Siap Diambil','selesai'=>'Selesai','revisi'=>'Revisi','dibatalkan'=>'Dibatalkan'];
$statusBadge   = ['menunggu_konfirmasi'=>'bm-badge-warning','dikonfirmasi'=>'bm-badge-info','ditolak'=>'bm-badge-danger','dp_menunggu'=>'bm-badge-warning','dp_diverifikasi'=>'bm-badge-success','pemeriksaan_stok'=>'bm-badge-info','produksi'=>'bm-badge-navy','qc'=>'bm-badge-muted','jadwal_ambil'=>'bm-badge-success','selesai'=>'bm-badge-success','revisi'=>'bm-badge-warning','dibatalkan'=>'bm-badge-danger'];

require_once __DIR__ . '/../../includes/layout-admin.php';
?>

<?php if ($success): ?><div class="bm-alert bm-alert-success bm-alert-auto-dismiss"><i class="bi bi-check-circle-fill"></i><?= htmlspecialchars($success) ?></div><?php endif; ?>

<!-- Header -->
<div class="d-flex align-items-start justify-content-between gap-3 mb-4 flex-wrap">
  <div>
    <a href="orders.php" style="color:var(--text-muted);font-size:.82rem;text-decoration:none"><i class="bi bi-arrow-left me-1"></i>Kembali ke Pesanan</a>
    <h1 class="ff-display mt-1" style="color:var(--navy);font-size:1.5rem"><?= htmlspecialchars($order['order_code']) ?></h1>
    <p style="color:var(--text-muted);font-size:.85rem;margin:0"><?= date('d F Y, H:i', strtotime($order['created_at'])) ?></p>
  </div>
  <span class="bm-badge <?= $statusBadge[$order['status']]??'bm-badge-muted' ?>" style="font-size:.88rem;padding:.5em 1em"><?= $statusOptions[$order['status']]??$order['status'] ?></span>
</div>

<div class="row g-4">
  <div class="col-lg-8">
    <!-- Order Details -->
    <div class="bm-card mb-4">
      <div class="bm-card-header"><span class="bm-card-title"><i class="bi bi-bag me-2"></i>Detail Pesanan</span></div>
      <div class="bm-card-body">
        <div class="row g-3">
          <?php foreach ([
            ['Produk',$order['product_name']??'Custom'],['Ukuran',$order['size']??'-'],
            ['Warna',$order['color']??'-'],['Qty',$order['qty'].' pcs'],
            ['Jenis Bahan',$order['fabric_type']??'-'],
            ['Total Harga','Rp '.number_format($order['total_price'],0,',','.')],
            ['DP','Rp '.number_format($order['dp_amount'],0,',','.')],
            ['Sisa','Rp '.number_format(max(0,$order['total_price']-$order['dp_amount']),0,',','.')],
            ['Est. Selesai',$order['estimated_done']?date('d M Y',strtotime($order['estimated_done'])):'-'],
          ] as [$lbl,$val]): ?>
          <div class="col-sm-6">
            <div style="font-size:.75rem;color:var(--text-muted);font-weight:700;text-transform:uppercase;letter-spacing:.5px"><?= $lbl ?></div>
            <div style="font-weight:600;color:var(--text-main);margin-top:2px"><?= htmlspecialchars($val) ?></div>
          </div>
          <?php endforeach; ?>
          <?php if ($order['model_description']): ?>
          <div class="col-12">
            <div style="font-size:.75rem;color:var(--text-muted);font-weight:700;text-transform:uppercase;letter-spacing:.5px">Deskripsi Model</div>
            <div style="font-size:.88rem;color:var(--text-main);margin-top:2px"><?= nl2br(htmlspecialchars($order['model_description'])) ?></div>
          </div>
          <?php endif; ?>
          <?php if ($order['design_file']): ?>
          <div class="col-12">
            <div style="font-size:.75rem;color:var(--text-muted);font-weight:700;text-transform:uppercase;letter-spacing:.5px;margin-bottom:.5rem">File Desain</div>
            <?php
              $designPath = APP_URL . '/uploads/designs/' . $order['design_file'];
              $ext = strtolower(pathinfo($order['design_file'], PATHINFO_EXTENSION));
              $isImage = in_array($ext, ['jpg','jpeg','png','webp','gif']);
              $isPdf   = $ext === 'pdf';
            ?>
            <?php if ($isImage): ?>
            <!-- Inline image preview with lightbox -->
            <div style="position:relative;display:inline-block;max-width:100%">
              <img src="<?= $designPath ?>"
                   alt="Desain <?= htmlspecialchars($order['order_code']) ?>"
                   id="design-img"
                   style="max-width:100%;max-height:340px;border-radius:12px;border:2px solid var(--border);cursor:zoom-in;object-fit:contain;display:block;box-shadow:var(--shadow-md)"
                   onclick="openLightbox('<?= $designPath ?>')"
                   onerror="this.style.display='none';document.getElementById('design-fallback').style.display='flex'">
              <a href="<?= $designPath ?>" download
                 style="position:absolute;top:.5rem;right:.5rem;background:var(--navy);color:var(--ivory);border-radius:8px;padding:.3rem .6rem;font-size:.75rem;font-weight:600;text-decoration:none;display:flex;align-items:center;gap:.3rem;opacity:.9">
                <i class="bi bi-download"></i> Unduh
              </a>
            </div>
            <!-- Fallback if image fails -->
            <div id="design-fallback" style="display:none;align-items:center;gap:.75rem;padding:.85rem;background:var(--ivory);border-radius:10px;border:1px dashed var(--border)">
              <i class="bi bi-file-earmark-image" style="font-size:2rem;color:var(--navy)"></i>
              <div>
                <div style="font-size:.85rem;font-weight:600;color:var(--navy)"><?= htmlspecialchars($order['design_file']) ?></div>
                <a href="<?= $designPath ?>" target="_blank" style="font-size:.78rem;color:var(--text-muted)">Buka di tab baru</a>
              </div>
            </div>

            <?php elseif ($isPdf): ?>
            <!-- PDF inline preview -->
            <div style="border:2px solid var(--border);border-radius:12px;overflow:hidden;background:#fff;box-shadow:var(--shadow-sm)">
              <div style="background:var(--navy);padding:.6rem 1rem;display:flex;align-items:center;justify-content:space-between">
                <span style="color:var(--ivory);font-size:.82rem;font-weight:600"><i class="bi bi-file-earmark-pdf-fill me-2"></i><?= htmlspecialchars($order['design_file']) ?></span>
                <a href="<?= $designPath ?>" target="_blank" style="color:var(--gold-light);font-size:.78rem;text-decoration:none"><i class="bi bi-box-arrow-up-right me-1"></i>Buka penuh</a>
              </div>
              <iframe src="<?= $designPath ?>"
                      width="100%"
                      height="320"
                      style="border:none;display:block"
                      title="Desain PDF">
              </iframe>
              <div style="padding:.5rem 1rem;border-top:1px solid var(--border)">
                <a href="<?= $designPath ?>" download class="btn-navy" style="font-size:.78rem;padding:.3rem .7rem">
                  <i class="bi bi-download me-1"></i>Unduh PDF
                </a>
              </div>
            </div>

            <?php else: ?>
            <!-- Other file types -->
            <div style="display:flex;align-items:center;gap:.85rem;padding:.85rem;background:var(--ivory);border-radius:10px;border:1px solid var(--border)">
              <div style="width:44px;height:44px;border-radius:10px;background:var(--navy);display:flex;align-items:center;justify-content:center;flex-shrink:0">
                <i class="bi bi-file-earmark-fill" style="color:var(--ivory);font-size:1.2rem"></i>
              </div>
              <div style="flex:1;min-width:0">
                <div style="font-weight:600;font-size:.88rem;color:var(--navy);word-break:break-all"><?= htmlspecialchars($order['design_file']) ?></div>
                <div style="font-size:.75rem;color:var(--text-muted)">File desain pelanggan</div>
              </div>
              <a href="<?= $designPath ?>" download class="btn-navy" style="font-size:.78rem;padding:.35rem .75rem;flex-shrink:0">
                <i class="bi bi-download me-1"></i>Unduh
              </a>
            </div>
            <?php endif; ?>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Production Log -->
    <div class="bm-card mb-4">
      <div class="bm-card-header"><span class="bm-card-title"><i class="bi bi-tools me-2"></i>Log Produksi</span></div>
      <div class="bm-card-body">
        <?php if (empty($logs)): ?>
        <p class="text-muted text-center py-2" style="font-size:.85rem">Belum ada log produksi.</p>
        <?php else: ?>
        <ul class="bm-timeline">
          <?php foreach ($logs as $log): ?>
          <li class="bm-timeline-item">
            <div class="bm-timeline-dot done"><i class="bi bi-tools"></i></div>
            <div class="bm-timeline-content">
              <div class="bm-timeline-title"><?= htmlspecialchars($log['stage']) ?> <span style="font-weight:400;color:var(--text-muted)">– <?= $log['progress'] ?>%</span></div>
              <div class="bm-timeline-meta">oleh <?= htmlspecialchars($log['staff']) ?> · <?= date('d M Y, H:i', strtotime($log['created_at'])) ?></div>
              <?php if ($log['notes']): ?><div style="font-size:.78rem;color:#6b7280;margin-top:2px"><?= htmlspecialchars($log['notes']) ?></div><?php endif; ?>
            </div>
          </li>
          <?php endforeach; ?>
        </ul>
        <?php endif; ?>
      </div>
    </div>

    <!-- Payments -->
    <div class="bm-card mb-4">
      <div class="bm-card-header"><span class="bm-card-title"><i class="bi bi-receipt me-2"></i>Riwayat Pembayaran</span></div>
      <?php if (empty($payments)): ?>
      <div class="bm-card-body text-muted text-center py-3" style="font-size:.85rem">Belum ada pembayaran.</div>
      <?php else: ?>
      <div class="table-responsive">
        <table class="bm-table">
          <thead><tr><th>Tipe</th><th>Jumlah</th><th>Metode</th><th>Tanggal</th><th>Status</th><th>Verifikator</th></tr></thead>
          <tbody>
          <?php foreach ($payments as $pay): ?>
          <tr>
            <td><span class="bm-badge bm-badge-navy"><?= strtoupper($pay['type']) ?></span></td>
            <td style="font-weight:700">Rp <?= number_format($pay['amount'],0,',','.') ?></td>
            <td><?= htmlspecialchars($pay['method']??'-') ?></td>
            <td style="font-size:.82rem"><?= date('d M Y', strtotime($pay['created_at'])) ?></td>
            <td><span class="bm-badge <?= $pay['status']==='diverifikasi'?'bm-badge-success':($pay['status']==='ditolak'?'bm-badge-danger':'bm-badge-warning') ?>"><?= ucfirst($pay['status']) ?></span></td>
            <td style="font-size:.82rem"><?= htmlspecialchars($pay['verified_name']??'-') ?></td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>

    <!-- Revisions -->
    <?php if (!empty($revisions)): ?>
    <div class="bm-card">
      <div class="bm-card-header"><span class="bm-card-title"><i class="bi bi-exclamation-triangle me-2"></i>Keluhan / Revisi</span></div>
      <div class="bm-card-body">
        <?php foreach ($revisions as $r): ?>
        <div class="p-3 mb-3" style="background:var(--surface);border-radius:10px;border-left:4px solid var(--warning)">
          <div class="d-flex justify-content-between align-items-start">
            <div style="font-size:.85rem;font-weight:600"><?= htmlspecialchars($r['description']) ?></div>
            <span class="bm-badge <?= $r['status']==='resolved'?'bm-badge-success':($r['status']==='open'?'bm-badge-warning':'bm-badge-muted') ?>"><?= ucfirst($r['status']) ?></span>
          </div>
          <div style="font-size:.75rem;color:var(--text-muted);margin-top:.3rem"><?= date('d M Y, H:i', strtotime($r['created_at'])) ?></div>
          <?php if ($r['status'] === 'open'): ?>
          <form method="POST" class="mt-3 d-flex gap-2 align-items-end">
            <input type="hidden" name="action" value="handle_revision">
            <input type="hidden" name="revision_id" value="<?= $r['id'] ?>">
            <div style="flex:1"><input type="text" name="handler_note" class="bm-form-control" placeholder="Catatan penanganan..." style="font-size:.83rem"></div>
            <select name="revision_status" class="bm-form-control" style="width:auto;font-size:.83rem">
              <option value="in_progress">Sedang Diproses</option>
              <option value="resolved">Selesai</option>
              <option value="rejected">Ditolak</option>
            </select>
            <button type="submit" class="btn-navy" style="font-size:.78rem;white-space:nowrap">Simpan</button>
          </form>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <!-- Right Panel -->
  <div class="col-lg-4">
    <!-- Customer Info -->
    <div class="bm-card mb-3">
      <div class="bm-card-header"><span class="bm-card-title"><i class="bi bi-person me-2"></i>Info Pelanggan</span></div>
      <div class="bm-card-body">
        <?php foreach ([['bi-person','Nama',$order['customer_name']],['bi-envelope','Email',$order['customer_email']],['bi-phone','Telepon',$order['customer_phone']??'-']] as [$ico,$lbl,$val]): ?>
        <div class="d-flex gap-2 align-items-center mb-2" style="font-size:.85rem">
          <i class="bi <?= $ico ?>" style="color:var(--navy);width:16px"></i>
          <span style="color:var(--text-muted)"><?= $lbl ?>:</span>
          <span style="font-weight:600"><?= htmlspecialchars($val) ?></span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- QC Result -->
    <?php if ($qc): ?>
    <div class="bm-card mb-3">
      <div class="bm-card-header"><span class="bm-card-title"><i class="bi bi-clipboard-check me-2"></i>Hasil QC</span></div>
      <div class="bm-card-body">
        <div class="d-flex align-items-center gap-2 mb-2">
          <span class="bm-badge <?= $qc['passed']?'bm-badge-success':'bm-badge-danger' ?>" style="font-size:.88rem"><?= $qc['passed']?'✅ Lulus':'❌ Tidak Lulus' ?></span>
          <span style="font-size:.78rem;color:var(--text-muted)"><?= date('d M Y', strtotime($qc['checked_at'])) ?></span>
        </div>
        <div style="font-size:.82rem;color:var(--text-muted)">oleh <?= htmlspecialchars($qc['checker']) ?></div>
        <?php if ($qc['notes']): ?><p style="font-size:.82rem;margin-top:.5rem;color:var(--text-main)"><?= htmlspecialchars($qc['notes']) ?></p><?php endif; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- Actions -->
    <div class="bm-card mb-3">
      <div class="bm-card-header"><span class="bm-card-title"><i class="bi bi-gear me-2"></i>Aksi Owner</span></div>
      <div class="bm-card-body">
        <!-- Update status -->
        <form method="POST" class="mb-3">
          <input type="hidden" name="action" value="update_status">
          <label class="bm-form-label">Update Status</label>
          <select name="status" class="bm-form-control mb-2">
            <?php foreach ($statusOptions as $k=>$v): ?>
            <option value="<?= $k ?>" <?= $order['status']===$k?'selected':'' ?>><?= $v ?></option>
            <?php endforeach; ?>
          </select>
          <label class="bm-form-label">Catatan Owner</label>
          <textarea name="owner_notes" class="bm-form-control mb-2" rows="2"><?= htmlspecialchars($order['owner_notes']??'') ?></textarea>
          <button type="submit" class="btn-navy w-100" data-confirm="Perbarui status pesanan ini?"><i class="bi bi-check-circle me-1"></i>Perbarui Status</button>
        </form>
        <div class="bm-divider"></div>
        <!-- Assign Staff -->
        <form method="POST" class="mb-3">
          <input type="hidden" name="action" value="assign_staff">
          <label class="bm-form-label">Tugaskan Staff</label>
          <select name="staff_id" class="bm-form-control mb-2">
            <option value="">– Pilih Staff –</option>
            <?php foreach ($staffList as $s): ?>
            <option value="<?= $s['id'] ?>" <?= $order['staff_id']==$s['id']?'selected':'' ?>><?= htmlspecialchars($s['name']) ?></option>
            <?php endforeach; ?>
          </select>
          <button type="submit" class="btn-ivory w-100"><i class="bi bi-person-check me-1"></i>Tugaskan</button>
        </form>
        <div class="bm-divider"></div>
        <!-- Set Pickup -->
        <form method="POST">
          <input type="hidden" name="action" value="set_pickup">
          <label class="bm-form-label">Atur Jadwal Ambil</label>
          <input type="date" name="pickup_date" class="bm-form-control mb-2" value="<?= $order['pickup_date']??'' ?>" min="<?= date('Y-m-d') ?>">
          <div class="row g-1 mb-2">
            <div class="col-6"><input type="time" name="pickup_time_start" class="bm-form-control" value="<?= $order['pickup_time_start']?substr($order['pickup_time_start'],0,5):'10:00' ?>"></div>
            <div class="col-6"><input type="time" name="pickup_time_end" class="bm-form-control" value="<?= $order['pickup_time_end']?substr($order['pickup_time_end'],0,5):'16:00' ?>"></div>
          </div>
          <button type="submit" class="btn-navy w-100" style="background:#5b21b6"><i class="bi bi-calendar-check me-1"></i>Simpan Jadwal & Notifikasi</button>
        </form>
      </div>
    </div>
  </div>
</div>
<!-- Lightbox Modal for design image -->
<div id="lightbox" onclick="closeLightbox()"
     style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.88);z-index:9999;align-items:center;justify-content:center;padding:1rem;cursor:zoom-out">
  <div style="position:relative;max-width:90vw;max-height:90vh">
    <img id="lightbox-img" src="" alt="Desain" style="max-width:90vw;max-height:85vh;object-fit:contain;border-radius:8px;box-shadow:0 20px 60px rgba(0,0,0,.5)">
    <button onclick="closeLightbox()" style="position:absolute;top:-14px;right:-14px;width:32px;height:32px;border-radius:50%;background:#ef4444;color:#fff;border:none;font-size:1rem;cursor:pointer;display:flex;align-items:center;justify-content:center;box-shadow:0 2px 8px rgba(0,0,0,.3)">×</button>
    <a id="lightbox-dl" href="" download style="position:absolute;bottom:-42px;left:50%;transform:translateX(-50%);background:var(--navy);color:var(--ivory);border-radius:8px;padding:.4rem 1rem;font-size:.82rem;font-weight:600;text-decoration:none;white-space:nowrap"><i class="bi bi-download me-1"></i>Unduh Gambar</a>
  </div>
</div>
<script>
function openLightbox(src) {
  const lb = document.getElementById('lightbox');
  document.getElementById('lightbox-img').src = src;
  document.getElementById('lightbox-dl').href  = src;
  lb.style.display = 'flex';
  document.body.style.overflow = 'hidden';
}
function closeLightbox() {
  document.getElementById('lightbox').style.display = 'none';
  document.body.style.overflow = '';
}
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeLightbox(); });
</script>
<?php require_once __DIR__ . '/../../includes/layout-admin-footer.php'; ?>
