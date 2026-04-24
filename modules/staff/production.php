<?php
require_once __DIR__ . '/../../includes/auth.php';
requireLogin([ROLE_STAFF]);

$user = currentUser(); $role = 'staff'; $pageTitle = 'Update Produksi'; $activeMenu = 'production'; $notifCount = 0;
$db = db();

$orderId = (int)($_GET['order_id'] ?? 0);
$order   = null;
if ($orderId) {
    $stmt = $db->prepare("SELECT o.*, u.name AS customer_name, p.name AS product_name FROM orders o JOIN users u ON o.customer_id=u.id LEFT JOIN products p ON o.product_id=p.id WHERE o.id=?");
    $stmt->execute([$orderId]); $order = $stmt->fetch();
}

$success = $error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_id'])) {
    $oid     = (int)$_POST['order_id'];
    $stageId = (int)$_POST['stage_id'];
    $prog    = min(100, max(0, (int)$_POST['progress']));
    $notes   = trim($_POST['notes'] ?? '');

    $stageToStatus = [2=>'pemeriksaan_stok',3=>'produksi',4=>'produksi',5=>'produksi',6=>'qc',7=>'jadwal_ambil'];
    try {
        $db->beginTransaction();
        $db->prepare("INSERT INTO production_logs(order_id,stage_id,updated_by,progress,notes) VALUES(?,?,?,?,?)")->execute([$oid,$stageId,$user['id'],$prog,$notes]);
        if (isset($stageToStatus[$stageId])) $db->prepare("UPDATE orders SET status=? WHERE id=?")->execute([$stageToStatus[$stageId],$oid]);
        // Notify customer
        $cid = $db->prepare("SELECT customer_id FROM orders WHERE id=?"); $cid->execute([$oid]);
        $custId = $cid->fetchColumn();
        $stageLabel = ['','DP Diverifikasi','Pemeriksaan Stok','Pemotongan','Penjahitan','Finishing','QC','Selesai'][$stageId]??'Diperbarui';
        if ($custId) $db->prepare("INSERT INTO notifications(user_id,title,body,type,ref_id) VALUES(?,?,?,?,?)")->execute([$custId,'Update Produksi Pesanan',"Tahap: $stageLabel ($prog% selesai).",'order_update',$oid]);
        $db->commit();
        $success = 'Progres produksi berhasil disimpan!';
        // Reload order
        $stmt = $db->prepare("SELECT o.*, u.name AS customer_name, p.name AS product_name FROM orders o JOIN users u ON o.customer_id=u.id LEFT JOIN products p ON o.product_id=p.id WHERE o.id=?");
        $stmt->execute([$oid]); $order = $stmt->fetch();
    } catch (Exception $e) { $db->rollBack(); $error = $e->getMessage(); }
}

$stages = $db->query("SELECT * FROM production_stages ORDER BY seq")->fetchAll();
$allOrders = $db->query("SELECT o.id, o.order_code, p.name AS product_name FROM orders o LEFT JOIN products p ON o.product_id=p.id WHERE o.status IN ('pemeriksaan_stok','produksi','qc') ORDER BY o.created_at ASC")->fetchAll();

// Logs for selected order
$logs = [];
if ($order) {
    $ls = $db->prepare("SELECT pl.*, ps.label AS stage, ps.seq FROM production_logs pl JOIN production_stages ps ON pl.stage_id=ps.id WHERE pl.order_id=? ORDER BY pl.created_at DESC LIMIT 15");
    $ls->execute([$order['id']]); $logs = $ls->fetchAll();
}

require_once __DIR__ . '/../../includes/layout-admin.php';
?>

<div class="bm-page-header"><h1>Update Produksi</h1><p>Catat tahapan dan progres pengerjaan pesanan</p></div>

<div class="row g-4">
  <div class="col-lg-5">
    <!-- Order Selector -->
    <div class="bm-card mb-4">
      <div class="bm-card-header"><span class="bm-card-title"><i class="bi bi-bag me-2"></i>Pilih Pesanan</span></div>
      <div class="bm-card-body p-0">
        <?php if (empty($allOrders)): ?>
        <div class="text-center py-4 text-muted" style="font-size:.85rem">Tidak ada pesanan aktif.</div>
        <?php endif; ?>
        <?php foreach ($allOrders as $o): $isSelected = $order && $order['id']==$o['id']; ?>
        <a href="?order_id=<?= $o['id'] ?>" class="d-flex align-items-center gap-3 px-4 py-3 text-decoration-none" style="border-bottom:1px solid var(--border);background:<?= $isSelected?'var(--ivory)':'' ?>;transition:background .12s" onmouseover="if(!<?= $isSelected?'true':'false' ?>)this.style.background='#f8f9ff'" onmouseout="if(!<?= $isSelected?'true':'false' ?>)this.style.background=''">
          <div style="width:36px;height:36px;border-radius:9px;background:<?= $isSelected?'var(--navy)':'var(--surface)' ?>;display:flex;align-items:center;justify-content:center;flex-shrink:0">
            <i class="bi bi-bag" style="color:<?= $isSelected?'var(--ivory)':'var(--text-muted)' ?>"></i>
          </div>
          <div>
            <div style="font-weight:700;font-size:.88rem;color:<?= $isSelected?'var(--navy)':'var(--text-main)' ?>"><?= htmlspecialchars($o['order_code']) ?></div>
            <div style="font-size:.75rem;color:var(--text-muted)"><?= htmlspecialchars($o['product_name']??'Custom') ?></div>
          </div>
          <?php if ($isSelected): ?><i class="bi bi-chevron-right ms-auto" style="color:var(--navy)"></i><?php endif; ?>
        </a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <div class="col-lg-7">
    <?php if ($success): ?><div class="bm-alert bm-alert-success bm-alert-auto-dismiss"><i class="bi bi-check-circle-fill"></i><?= htmlspecialchars($success) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="bm-alert bm-alert-danger"><i class="bi bi-exclamation-circle-fill"></i><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <?php if ($order): ?>
    <!-- Order Info -->
    <div class="bm-card mb-4">
      <div class="bm-card-header">
        <div>
          <span class="bm-card-title"><?= htmlspecialchars($order['order_code']) ?></span>
          <div style="font-size:.78rem;color:var(--text-muted);margin-top:2px"><?= htmlspecialchars($order['customer_name']) ?> · <?= htmlspecialchars($order['product_name']??'Custom') ?></div>
        </div>
        <span class="bm-badge bm-badge-navy"><?= ucfirst(str_replace('_',' ',$order['status'])) ?></span>
      </div>
      <div class="bm-card-body">
        <div class="row g-2" style="font-size:.84rem">
          <div class="col-4"><span style="color:var(--text-muted)">Ukuran</span><div style="font-weight:600"><?= htmlspecialchars($order['size']??'-') ?></div></div>
          <div class="col-4"><span style="color:var(--text-muted)">Warna</span><div style="font-weight:600"><?= htmlspecialchars($order['color']??'-') ?></div></div>
          <div class="col-4"><span style="color:var(--text-muted)">Qty</span><div style="font-weight:600"><?= $order['qty'] ?> pcs</div></div>
          <?php if ($order['estimated_done']): ?>
          <div class="col-12"><span style="color:var(--text-muted)">Estimasi Selesai:</span> <strong><?= date('d M Y', strtotime($order['estimated_done'])) ?></strong>
            <?php if (strtotime($order['estimated_done'])<strtotime('+2 days')): ?><span class="bm-badge bm-badge-danger ms-1">⚡ Urgent</span><?php endif; ?>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Update Form -->
    <div class="bm-card mb-4">
      <div class="bm-card-header"><span class="bm-card-title"><i class="bi bi-pencil me-2"></i>Catat Update Produksi</span></div>
      <div class="bm-card-body">
        <form method="POST">
          <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
          <div class="mb-3">
            <label class="bm-form-label">Tahap Produksi</label>
            <select name="stage_id" class="bm-form-control" required>
              <?php foreach ($stages as $s): ?>
              <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['label']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="bm-form-label">Persentase Selesai: <span id="prog-val" style="color:var(--navy);font-weight:700">0</span>%</label>
            <input type="range" name="progress" id="prog-range" class="form-range" min="0" max="100" value="0" oninput="document.getElementById('prog-val').textContent=this.value" style="accent-color:var(--navy)">
          </div>
          <div class="mb-3">
            <label class="bm-form-label">Catatan</label>
            <textarea name="notes" class="bm-form-control" rows="3" placeholder="Deskripsikan progres pengerjaan saat ini..."></textarea>
          </div>
          <button type="submit" class="btn-navy w-100"><i class="bi bi-check-circle me-1"></i>Simpan Update</button>
        </form>
      </div>
    </div>

    <!-- Log History -->
    <?php if (!empty($logs)): ?>
    <div class="bm-card">
      <div class="bm-card-header"><span class="bm-card-title"><i class="bi bi-clock-history me-2"></i>Riwayat Update</span></div>
      <div class="bm-card-body">
        <ul class="bm-timeline">
          <?php foreach ($logs as $log): ?>
          <li class="bm-timeline-item">
            <div class="bm-timeline-dot done"><i class="bi bi-check"></i></div>
            <div class="bm-timeline-content">
              <div class="bm-timeline-title"><?= htmlspecialchars($log['stage']) ?> – <span style="font-weight:700;color:var(--navy)"><?= $log['progress'] ?>%</span></div>
              <?php if ($log['notes']): ?><div style="font-size:.8rem;color:var(--text-muted);margin-top:1px"><?= htmlspecialchars($log['notes']) ?></div><?php endif; ?>
              <div class="bm-timeline-meta"><?= date('d M Y, H:i', strtotime($log['created_at'])) ?></div>
            </div>
          </li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>
    <?php endif; ?>

    <?php else: ?>
    <div class="bm-card"><div class="bm-card-body text-center py-5 text-muted">
      <i class="bi bi-arrow-left-circle" style="font-size:2.5rem;display:block;margin-bottom:.75rem"></i>
      <p>Pilih pesanan dari daftar di sebelah kiri untuk memulai update produksi.</p>
    </div></div>
    <?php endif; ?>
  </div>
</div>
<?php require_once __DIR__ . '/../../includes/layout-admin-footer.php'; ?>
