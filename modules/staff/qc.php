<?php
require_once __DIR__ . '/../../includes/auth.php';
requireLogin([ROLE_STAFF]);

$user = currentUser(); $role = 'staff'; $pageTitle = 'Quality Control'; $activeMenu = 'qc'; $notifCount = 0;
$db = db();

$success = $error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $oid    = (int)$_POST['order_id'];
    $passed = (int)$_POST['passed'];
    $notes  = trim($_POST['notes'] ?? '');
    try {
        $db->beginTransaction();
        // Upsert QC
        $chk = $db->prepare("SELECT id FROM qc_results WHERE order_id=?"); $chk->execute([$oid]);
        if ($chk->fetchColumn()) {
            $db->prepare("UPDATE qc_results SET passed=?,notes=?,checked_by=?,checked_at=NOW() WHERE order_id=?")->execute([$passed,$notes,$user['id'],$oid]);
        } else {
            $db->prepare("INSERT INTO qc_results(order_id,checked_by,passed,notes) VALUES(?,?,?,?)")->execute([$oid,$user['id'],$passed,$notes]);
        }
        $newStatus = $passed ? 'jadwal_ambil' : 'produksi';
        $db->prepare("UPDATE orders SET status=? WHERE id=?")->execute([$newStatus,$oid]);
        $stageId = $passed ? 7 : 4;
        $db->prepare("INSERT INTO production_logs(order_id,stage_id,updated_by,progress,notes) VALUES(?,?,?,?,?)")->execute([$oid,$stageId,$user['id'],$passed?100:60,"QC ".($passed?'LULUS':'TIDAK LULUS').($notes?": $notes":'')]);
        // Notify customer
        $cid = $db->prepare("SELECT customer_id FROM orders WHERE id=?"); $cid->execute([$oid]); $custId = $cid->fetchColumn();
        if ($custId) {
            $msg = $passed ? 'Selamat! Pesanan kamu lulus Quality Control dan sedang menunggu jadwal pengambilan.' : 'Pesanan kamu perlu perbaikan. Tim kami sedang menindaklanjuti.';
            $db->prepare("INSERT INTO notifications(user_id,title,body,type,ref_id) VALUES(?,?,?,?,?)")->execute([$custId,'Hasil Quality Control',$msg,'order_update',$oid]);
        }
        $db->commit();
        $success = 'Hasil QC berhasil disimpan!';
    } catch (Exception $e) { $db->rollBack(); $error = $e->getMessage(); }
}

// Orders pending QC
$qcPending = $db->query(
    "SELECT o.id, o.order_code, o.qty, o.size, o.color, o.estimated_done,
            u.name AS customer_name, p.name AS product_name,
            qr.passed AS qc_passed, qr.checked_at
     FROM orders o
     JOIN users u ON o.customer_id=u.id
     LEFT JOIN products p ON o.product_id=p.id
     LEFT JOIN qc_results qr ON qr.order_id=o.id
     WHERE o.status='qc'
     ORDER BY o.estimated_done ASC"
)->fetchAll();

// Recent QC history
$qcHistory = $db->query(
    "SELECT qr.*, o.order_code, u.name AS checker, p.name AS product_name
     FROM qc_results qr
     JOIN orders o ON qr.order_id=o.id
     JOIN users u ON qr.checked_by=u.id
     LEFT JOIN products p ON o.product_id=p.id
     ORDER BY qr.checked_at DESC LIMIT 10"
)->fetchAll();

require_once __DIR__ . '/../../includes/layout-admin.php';
?>

<div class="bm-page-header"><h1>Quality Control</h1><p>Periksa kualitas pesanan sebelum diserahkan ke pelanggan</p></div>

<?php if ($success): ?><div class="bm-alert bm-alert-success bm-alert-auto-dismiss"><i class="bi bi-check-circle-fill"></i><?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="bm-alert bm-alert-danger"><i class="bi bi-exclamation-circle-fill"></i><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="row g-4">
  <div class="col-lg-7">
    <h6 class="fw-700 mb-3" style="color:var(--navy)">Pesanan Menunggu QC <span class="bm-badge bm-badge-warning ms-1"><?= count($qcPending) ?></span></h6>
    <?php if (empty($qcPending)): ?>
    <div class="bm-card"><div class="bm-card-body text-center py-5 text-muted"><i class="bi bi-clipboard-check" style="font-size:2.5rem;display:block"></i><p class="mt-2">Tidak ada pesanan menunggu QC.</p></div></div>
    <?php else: ?>
    <?php foreach ($qcPending as $o):
      $urgent = $o['estimated_done'] && strtotime($o['estimated_done']) < strtotime('+2 days');
    ?>
    <div class="bm-card mb-3">
      <div class="bm-card-body">
        <div class="d-flex justify-content-between align-items-start mb-3">
          <div>
            <div style="font-size:.75rem;color:var(--text-muted)"><?= htmlspecialchars($o['order_code']) ?> · <?= htmlspecialchars($o['customer_name']) ?></div>
            <div style="font-weight:700;font-size:1rem;color:var(--navy)"><?= htmlspecialchars($o['product_name']??'Custom') ?></div>
            <div style="font-size:.8rem;color:var(--text-muted);margin-top:2px"><?= $o['qty'] ?> pcs · <?= htmlspecialchars($o['size']??'') ?> · <?= htmlspecialchars($o['color']??'') ?></div>
          </div>
          <div class="text-end">
            <?php if ($urgent): ?><span class="bm-badge bm-badge-danger">⚡ Urgent</span><br><?php endif; ?>
            <?php if ($o['estimated_done']): ?><div style="font-size:.75rem;color:var(--text-muted);margin-top:.3rem">Est: <?= date('d M Y',strtotime($o['estimated_done'])) ?></div><?php endif; ?>
          </div>
        </div>

        <!-- QC Checklist visual -->
        <div class="row g-2 mb-3">
          <?php foreach (['Jahitan Rapi','Warna Sesuai','Ukuran Tepat','Finishing Bersih','Tidak Ada Cacat'] as $item): ?>
          <div class="col-6">
            <label class="d-flex align-items-center gap-2" style="cursor:pointer;font-size:.83rem;background:var(--surface);padding:.4rem .6rem;border-radius:7px">
              <input type="checkbox" style="accent-color:var(--navy)"> <?= $item ?>
            </label>
          </div>
          <?php endforeach; ?>
        </div>

        <form method="POST">
          <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
          <div class="mb-3">
            <label class="bm-form-label">Catatan QC</label>
            <textarea name="notes" class="bm-form-control" rows="2" placeholder="Catatan detail hasil pemeriksaan..."></textarea>
          </div>
          <div class="row g-2">
            <div class="col-6"><button type="submit" name="passed" value="1" class="btn-navy w-100" style="background:#059669" data-confirm="Tandai pesanan ini LULUS QC?"><i class="bi bi-check-circle me-1"></i>✅ Lulus QC</button></div>
            <div class="col-6"><button type="submit" name="passed" value="0" class="btn-navy w-100" style="background:#ef4444" data-confirm="Tandai pesanan TIDAK LULUS dan kembalikan ke produksi?"><i class="bi bi-x-circle me-1"></i>❌ Tidak Lulus</button></div>
          </div>
        </form>
      </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <!-- QC History -->
  <div class="col-lg-5">
    <h6 class="fw-700 mb-3" style="color:var(--navy)">Riwayat QC Terbaru</h6>
    <div class="bm-card">
      <?php if (empty($qcHistory)): ?>
      <div class="bm-card-body text-center py-4 text-muted" style="font-size:.85rem">Belum ada riwayat QC.</div>
      <?php else: ?>
      <?php foreach ($qcHistory as $qc): ?>
      <div class="d-flex gap-3 px-4 py-3" style="border-bottom:1px solid var(--border)">
        <div style="width:36px;height:36px;border-radius:50%;background:<?= $qc['passed']?'#d1fae5':'#fee2e2' ?>;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:1rem">
          <?= $qc['passed'] ? '✅' : '❌' ?>
        </div>
        <div>
          <div style="font-weight:700;font-size:.88rem;color:var(--navy)"><?= htmlspecialchars($qc['order_code']) ?></div>
          <div style="font-size:.78rem;color:var(--text-muted)"><?= htmlspecialchars($qc['product_name']??'Custom') ?></div>
          <?php if ($qc['notes']): ?><div style="font-size:.75rem;color:#9ca3af"><?= htmlspecialchars(mb_substr($qc['notes'],0,50)) ?></div><?php endif; ?>
          <div style="font-size:.72rem;color:#d1d5db;margin-top:2px"><?= date('d M Y, H:i', strtotime($qc['checked_at'])) ?> · <?= htmlspecialchars($qc['checker']) ?></div>
        </div>
      </div>
      <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/../../includes/layout-admin-footer.php'; ?>
