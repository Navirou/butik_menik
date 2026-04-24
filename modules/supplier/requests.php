<?php
require_once __DIR__ . '/../../includes/auth.php';
requireLogin([ROLE_SUPPLIER]);

$user       = currentUser();
$role       = 'supplier';
$pageTitle  = 'Permintaan Bahan';
$activeMenu = 'requests';
$db         = db();

$nc = $db->prepare('SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0');
$nc->execute([$user['id']]); $notifCount = (int)$nc->fetchColumn();

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['req_id'])) {
    $rid    = (int)$_POST['req_id'];
    $action = $_POST['action'];
    $note   = trim($_POST['supplier_note'] ?? '');
    $qtyRcv = (float)($_POST['qty_received'] ?? 0);

    $allowed = ['accepted', 'shipped', 'received', 'cancelled'];
    if (in_array($action, $allowed)) {
        if ($action === 'received' && $qtyRcv > 0) {
            $db->prepare("UPDATE material_requests SET status=?, supplier_note=?, qty_received=?, updated_at=NOW() WHERE id=? AND supplier_id=?")
               ->execute([$action, $note, $qtyRcv, $rid, $user['id']]);
            // Auto-update stock
            $r = $db->prepare("SELECT material_id FROM material_requests WHERE id=?");
            $r->execute([$rid]); $matId = $r->fetchColumn();
            if ($matId) {
                $db->prepare("UPDATE materials SET current_stock=current_stock+? WHERE id=?")->execute([$qtyRcv, $matId]);
                // Log stock ledger
                $db->prepare("INSERT INTO stock_ledger(material_id,type,qty,reference,recorded_by) VALUES(?,'in',?,?,?)")
                   ->execute([$matId, $qtyRcv, "REQ-$rid", $user['id']]);
            }
        } else {
            $db->prepare("UPDATE material_requests SET status=?, supplier_note=?, updated_at=NOW() WHERE id=? AND supplier_id=?")
               ->execute([$action, $note, $rid, $user['id']]);
        }
        // Notify owner
        $ownerId = $db->query("SELECT id FROM users WHERE role_id=".ROLE_OWNER." LIMIT 1")->fetchColumn();
        if ($ownerId) {
            $reqCode = $db->prepare("SELECT request_code FROM material_requests WHERE id=?");
            $reqCode->execute([$rid]); $rc = $reqCode->fetchColumn();
            $statusLabels = ['accepted'=>'diterima','shipped'=>'dikirim','received'=>'diterima di gudang','cancelled'=>'dibatalkan'];
            $db->prepare("INSERT INTO notifications(user_id,title,body,type,ref_id) VALUES(?,?,?,?,?)")
               ->execute([$ownerId, 'Update Permintaan Bahan', "Permintaan $rc telah {$statusLabels[$action]} oleh supplier.", 'stock', $rid]);
        }
        header('Location: requests.php?updated=1'); exit;
    }
}

// Fetch requests with filtering
$filterStatus = $_GET['filter'] ?? 'all';
$search       = trim($_GET['q'] ?? '');
$where        = 'WHERE mr.supplier_id=?';
$params       = [$user['id']];
if ($filterStatus !== 'all') { $where .= ' AND mr.status=?'; $params[] = $filterStatus; }
if ($search) { $where .= ' AND (m.name LIKE ? OR mr.request_code LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; }

$stmt = $db->prepare(
    "SELECT mr.*, m.name AS material_name, m.unit, u.name AS requested_by_name
     FROM material_requests mr
     JOIN materials m ON mr.material_id=m.id
     JOIN users u ON mr.requested_by=u.id
     $where ORDER BY FIELD(mr.status,'pending','accepted','shipped','received','cancelled'), mr.created_at DESC"
);
$stmt->execute($params);
$requests = $stmt->fetchAll();

// Count by status for tabs
$counts = [];
foreach (['pending','accepted','shipped','received','cancelled'] as $s) {
    $cs = $db->prepare("SELECT COUNT(*) FROM material_requests WHERE supplier_id=? AND status=?");
    $cs->execute([$user['id'], $s]); $counts[$s] = $cs->fetchColumn();
}
$counts['all'] = array_sum($counts);

$statusCfg = [
    'pending'   => ['Pending',   'bm-badge-warning', 'bi-hourglass'],
    'accepted'  => ['Diterima',  'bm-badge-info',    'bi-check-circle'],
    'shipped'   => ['Dikirim',   'bm-badge-navy',    'bi-truck'],
    'received'  => ['Selesai',   'bm-badge-success', 'bi-box-seam'],
    'cancelled' => ['Dibatalkan','bm-badge-danger',  'bi-x-circle'],
];

require_once __DIR__ . '/../../includes/layout-admin.php';
?>

<div class="bm-page-header d-flex justify-content-between align-items-start gap-3">
  <div><h1>Permintaan Bahan Baku</h1><p>Kelola semua permintaan bahan dari <?= APP_NAME ?></p></div>
</div>

<?php if (isset($_GET['updated'])): ?>
<div class="bm-alert bm-alert-success bm-alert-auto-dismiss"><i class="bi bi-check-circle-fill"></i>Status permintaan berhasil diperbarui.</div>
<?php endif; ?>

<!-- Search + Filter Bar -->
<div class="bm-card mb-4">
  <div class="bm-card-body">
    <form method="GET" class="row g-2 align-items-end">
      <div class="col-md-6">
        <label class="bm-form-label">Cari Permintaan</label>
        <input type="text" name="q" class="bm-form-control" placeholder="Nama bahan atau kode permintaan..." value="<?= htmlspecialchars($search) ?>">
      </div>
      <div class="col-md-3">
        <label class="bm-form-label">Status</label>
        <select name="filter" class="bm-form-control">
          <option value="all">Semua (<?= $counts['all'] ?>)</option>
          <?php foreach ($statusCfg as $k => [$l]): ?>
          <option value="<?= $k ?>" <?= $filterStatus === $k ? 'selected' : '' ?>><?= $l ?> (<?= $counts[$k] ?? 0 ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3 d-flex gap-2">
        <button type="submit" class="btn-navy w-100"><i class="bi bi-search me-1"></i>Cari</button>
        <a href="requests.php" class="btn-ivory w-100 text-center">Reset</a>
      </div>
    </form>
  </div>
</div>

<!-- Status Summary Tabs -->
<div class="row g-2 mb-4">
  <?php foreach (['all'=>['Semua','bi-list-task','#e8f0fe','#003366']] + array_map(fn($v,$k)=>array_merge($v,['','','']),$statusCfg,array_keys($statusCfg)) as $k=>$cfg):
    [$lbl] = $cfg;
    $isActive = $filterStatus === $k;
  ?>
  <div class="col">
    <a href="?filter=<?= $k ?>" class="d-block text-center text-decoration-none p-2" style="border-radius:10px;background:<?= $isActive ? 'var(--navy)' : '#fff' ?>;border:1px solid <?= $isActive ? 'var(--navy)' : 'var(--border)' ?>;transition:all .15s">
      <div style="font-size:1.3rem;font-weight:800;color:<?= $isActive ? 'var(--ivory)' : 'var(--navy)' ?>"><?= $counts[$k] ?? 0 ?></div>
      <div style="font-size:.68rem;color:<?= $isActive ? 'rgba(255,255,224,.75)' : 'var(--text-muted)' ?>;white-space:nowrap"><?= $lbl ?></div>
    </a>
  </div>
  <?php endforeach; ?>
</div>

<!-- Requests List -->
<?php if (empty($requests)): ?>
<div class="bm-card"><div class="bm-card-body text-center py-5 text-muted">
  <i class="bi bi-inbox" style="font-size:2.5rem;display:block;margin-bottom:.5rem"></i>
  <p>Tidak ada permintaan<?= $filterStatus !== 'all' ? ' dengan status ini' : '' ?>.</p>
</div></div>
<?php else: ?>
<div class="bm-card">
  <div class="bm-card-header"><span class="bm-card-title"><i class="bi bi-list-task me-2"></i>Daftar Permintaan <span class="bm-badge bm-badge-navy ms-1"><?= count($requests) ?></span></span></div>
  <?php foreach ($requests as $r):
    [$slbl, $scls, $sico] = $statusCfg[$r['status']] ?? ['Unknown','bm-badge-muted','bi-question'];
    $isUrgent = $r['priority'] === 'urgent';
  ?>
  <div class="px-4 py-4" style="border-bottom:1px solid var(--border);<?= $isUrgent ? 'border-left:4px solid var(--danger);padding-left:1.1rem' : '' ?>">
    <div class="d-flex flex-wrap gap-3 align-items-start justify-content-between mb-3">
      <div>
        <div style="font-size:.75rem;color:var(--text-muted);font-weight:500">
          <?= htmlspecialchars($r['request_code']) ?> · <?= date('d M Y, H:i', strtotime($r['created_at'])) ?>
        </div>
        <div style="font-weight:700;font-size:1.05rem;color:var(--navy)"><?= htmlspecialchars($r['material_name']) ?></div>
        <div style="font-size:.83rem;color:var(--text-muted)">Diminta oleh: <?= htmlspecialchars($r['requested_by_name']) ?></div>
      </div>
      <div class="d-flex gap-2 align-items-start">
        <?php if ($isUrgent): ?><span class="bm-badge bm-badge-danger"><i class="bi bi-lightning-fill me-1"></i>URGENT</span><?php endif; ?>
        <span class="bm-badge <?= $scls ?>"><i class="bi <?= $sico ?> me-1"></i><?= $slbl ?></span>
      </div>
    </div>

    <div class="row g-3 mb-3">
      <div class="col-sm-3">
        <div style="background:var(--surface);border-radius:10px;padding:.7rem;text-align:center">
          <div style="font-size:1.4rem;font-weight:800;color:var(--navy)"><?= number_format($r['qty_requested'], 1) ?></div>
          <div style="font-size:.72rem;color:var(--text-muted)"><?= $r['unit'] ?> diminta</div>
        </div>
      </div>
      <?php if ($r['qty_received']): ?>
      <div class="col-sm-3">
        <div style="background:#d1fae5;border-radius:10px;padding:.7rem;text-align:center">
          <div style="font-size:1.4rem;font-weight:800;color:#065f46"><?= number_format($r['qty_received'], 1) ?></div>
          <div style="font-size:.72rem;color:#065f46"><?= $r['unit'] ?> diterima</div>
        </div>
      </div>
      <?php endif; ?>
      <?php if ($r['notes']): ?>
      <div class="col-sm-<?= $r['qty_received'] ? '6' : '9' ?>">
        <div style="background:var(--ivory);border-radius:10px;padding:.7rem;height:100%">
          <div style="font-size:.72rem;color:var(--text-muted);margin-bottom:.2rem">Catatan dari butik:</div>
          <div style="font-size:.85rem;color:var(--text-main)"><?= htmlspecialchars($r['notes']) ?></div>
        </div>
      </div>
      <?php endif; ?>
    </div>

    <?php if ($r['supplier_note']): ?>
    <div class="mb-3" style="background:#f0f9ff;border-radius:8px;padding:.65rem .85rem;font-size:.83rem">
      <span style="font-weight:600;color:#0369a1">Catatan Anda: </span><?= htmlspecialchars($r['supplier_note']) ?>
    </div>
    <?php endif; ?>

    <!-- Action Buttons -->
    <?php if (in_array($r['status'], ['pending', 'accepted', 'shipped'])): ?>
    <form method="POST">
      <input type="hidden" name="req_id" value="<?= $r['id'] ?>">
      <div class="row g-2">
        <div class="col-md-6">
          <label class="bm-form-label">Catatan Balasan (opsional)</label>
          <input type="text" name="supplier_note" class="bm-form-control" placeholder="Pesan untuk tim butik...">
        </div>
        <?php if ($r['status'] === 'shipped'): ?>
        <div class="col-md-3">
          <label class="bm-form-label">Qty Aktual Diterima (<?= $r['unit'] ?>)</label>
          <input type="number" name="qty_received" class="bm-form-control" step="0.1" min="0.1" value="<?= $r['qty_requested'] ?>" required>
        </div>
        <?php endif; ?>
        <div class="col-md-<?= $r['status'] === 'shipped' ? '3' : '6' ?> d-flex align-items-end gap-2">
          <?php if ($r['status'] === 'pending'): ?>
          <button name="action" value="accepted" class="btn-navy flex-fill" data-confirm="Terima permintaan ini?">
            <i class="bi bi-check-circle me-1"></i>Terima Permintaan
          </button>
          <button name="action" value="cancelled" class="btn-danger-outline" data-confirm="Batalkan permintaan ini?" title="Batalkan">
            <i class="bi bi-x-lg"></i>
          </button>
          <?php elseif ($r['status'] === 'accepted'): ?>
          <button name="action" value="shipped" class="btn-navy flex-fill" style="background:#2563eb" data-confirm="Tandai bahan sudah dikirim?">
            <i class="bi bi-truck me-1"></i>Tandai Dikirim
          </button>
          <?php elseif ($r['status'] === 'shipped'): ?>
          <button name="action" value="received" type="submit" class="btn-navy flex-fill" style="background:#059669" data-confirm="Konfirmasi bahan telah sampai di butik?">
            <i class="bi bi-box-seam me-1"></i>Konfirmasi Diterima
          </button>
          <?php endif; ?>
        </div>
      </div>
    </form>
    <?php else: ?>
    <div style="font-size:.8rem;color:var(--text-muted)">
      <i class="bi bi-clock-history me-1"></i>Terakhir diperbarui: <?= date('d M Y, H:i', strtotime($r['updated_at'])) ?>
    </div>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/layout-admin-footer.php'; ?>
