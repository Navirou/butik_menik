<?php
require_once __DIR__ . '/../../includes/auth.php';
requireLogin([ROLE_SUPPLIER]);

$user = currentUser(); $role = 'supplier'; $pageTitle = 'Supplier Portal'; $activeMenu = 'dashboard'; $notifCount = 0;
$db = db();

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $pid    = (int)$_POST['req_id'];
    $status = $_POST['action'];
    $note   = trim($_POST['note'] ?? '');
    $allowed = ['accepted','shipped','received','cancelled'];
    if (in_array($status, $allowed)) {
        $db->prepare("UPDATE material_requests SET status=?, supplier_note=?, updated_at=NOW() WHERE id=? AND supplier_id=?")
           ->execute([$status, $note, $pid, $user['id']]);
        // Update stock on received
        if ($status === 'received') {
            $req = $db->prepare("SELECT material_id, qty_requested FROM material_requests WHERE id=?");
            $req->execute([$pid]); $r = $req->fetch();
            if ($r) $db->prepare("UPDATE materials SET current_stock=current_stock+? WHERE id=?")->execute([$r['qty_requested'],$r['material_id']]);
        }
    }
    header('Location: dashboard.php'); exit;
}

$reqStmt = $db->prepare(
    "SELECT mr.*, m.name AS material_name, m.unit
     FROM material_requests mr JOIN materials m ON mr.material_id=m.id
     WHERE mr.supplier_id=? ORDER BY mr.created_at DESC"
);
$reqStmt->execute([$user['id']]);
$requests = $reqStmt->fetchAll();

$pending   = count(array_filter($requests, fn($r)=>$r['status']==='pending'));
$accepted  = count(array_filter($requests, fn($r)=>$r['status']==='accepted'));
$completed = count(array_filter($requests, fn($r)=>$r['status']==='received'));

require_once __DIR__ . '/../../includes/layout-admin.php';
?>

<div class="bm-page-header">
  <h1>Dashboard Supplier</h1>
  <p>Kelola permintaan bahan baku dari <?= APP_NAME ?></p>
</div>

<!-- Stats -->
<div class="row g-3 mb-4">
  <?php foreach ([
    ['bi-inbox','#fef3c7','#92400e',$pending,'Permintaan Pending'],
    ['bi-truck','#dbeafe','#1e40af',$accepted,'Diterima / Dikirim'],
    ['bi-check2-all','#d1fae5','#065f46',$completed,'Selesai'],
    ['bi-list-task','#e8f0fe','#003366',count($requests),'Total Permintaan'],
  ] as [$icon,$bg,$ic,$val,$lbl]): ?>
  <div class="col-6 col-md-3">
    <div class="bm-stat-card">
      <div class="bm-stat-icon" style="background:<?= $bg ?>"><i class="bi <?= $icon ?>" style="color:<?= $ic ?>"></i></div>
      <div class="bm-stat-val"><?= $val ?></div>
      <div class="bm-stat-lbl"><?= $lbl ?></div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Filter -->
<div class="d-flex gap-2 mb-4 flex-wrap">
  <?php foreach (['all'=>'Semua','pending'=>'⏳ Pending','accepted'=>'✅ Diterima','shipped'=>'🚚 Dikirim','received'=>'📦 Selesai'] as $k=>$l): ?>
  <button class="bm-badge text-decoration-none border-0" style="cursor:pointer;font-size:.82rem;padding:.5em 1em" onclick="filterReq(this,'<?= $k ?>')"><?= $l ?></button>
  <?php endforeach; ?>
</div>

<!-- Request Cards -->
<div class="row g-3" id="req-list">
<?php if (empty($requests)): ?>
<div class="col-12"><div class="bm-card"><div class="bm-card-body text-center py-5 text-muted"><i class="bi bi-inbox" style="font-size:2.5rem;display:block"></i><p>Belum ada permintaan.</p></div></div></div>
<?php endif; ?>
<?php foreach ($requests as $r):
  $badgeClass = match($r['status']) {
    'pending'   => 'bm-badge-warning',
    'accepted'  => 'bm-badge-info',
    'shipped'   => 'bm-badge-navy',
    'received'  => 'bm-badge-success',
    'cancelled' => 'bm-badge-danger',
    default     => 'bm-badge-muted'
  };
  $statusLabel = ['pending'=>'Pending','accepted'=>'Diterima','shipped'=>'Dikirim','received'=>'Selesai','cancelled'=>'Dibatalkan'][$r['status']] ?? $r['status'];
?>
<div class="col-lg-6 req-card" data-status="<?= $r['status'] ?>">
  <div class="bm-card <?= $r['priority']==='urgent' ? 'border-warning' : '' ?>">
    <div class="bm-card-header">
      <div>
        <div style="font-size:.75rem;color:var(--text-muted)"><?= htmlspecialchars($r['request_code']) ?> · <?= date('d M Y', strtotime($r['created_at'])) ?></div>
        <div style="font-weight:700;font-size:1.05rem;color:var(--navy)"><?= htmlspecialchars($r['material_name']) ?></div>
      </div>
      <div class="text-end">
        <span class="bm-badge <?= $r['priority']==='urgent' ? 'bm-badge-danger' : 'bm-badge-muted' ?>"><?= ucfirst($r['priority']) ?></span>
        <div class="mt-1"><span class="bm-badge <?= $badgeClass ?>"><?= $statusLabel ?></span></div>
      </div>
    </div>
    <div class="bm-card-body">
      <div class="row g-2 mb-3">
        <div class="col-6"><small class="text-muted d-block">Diminta</small><strong><?= number_format($r['qty_requested'],1) ?> <?= $r['unit'] ?></strong></div>
        <?php if ($r['qty_received']): ?>
        <div class="col-6"><small class="text-muted d-block">Dikirim</small><strong><?= number_format($r['qty_received'],1) ?> <?= $r['unit'] ?></strong></div>
        <?php endif; ?>
      </div>
      <?php if ($r['notes']): ?>
      <p style="font-size:.82rem;color:var(--text-muted);background:var(--surface);border-radius:8px;padding:.55rem .75rem;margin-bottom:.75rem"><i class="bi bi-chat-left-text me-1"></i><?= htmlspecialchars($r['notes']) ?></p>
      <?php endif; ?>
      <?php if (in_array($r['status'], ['pending','accepted'])): ?>
      <form method="POST" class="mt-2">
        <input type="hidden" name="req_id" value="<?= $r['id'] ?>">
        <div class="mb-2"><input type="text" name="note" class="bm-form-control" placeholder="Catatan (opsional)..." style="font-size:.83rem"></div>
        <div class="d-flex gap-2">
          <?php if ($r['status']==='pending'): ?>
          <button name="action" value="accepted" class="btn-navy flex-fill" data-confirm="Terima permintaan ini?"><i class="bi bi-check-circle me-1"></i>Terima</button>
          <button name="action" value="cancelled" class="btn-danger-outline" data-confirm="Batalkan permintaan ini?"><i class="bi bi-x-circle me-1"></i></button>
          <?php endif; ?>
          <?php if ($r['status']==='accepted'): ?>
          <button name="action" value="shipped" class="btn-navy flex-fill" data-confirm="Tandai sudah dikirim?"><i class="bi bi-truck me-1"></i>Tandai Dikirim</button>
          <?php endif; ?>
        </div>
      </form>
      <?php elseif ($r['status']==='shipped'): ?>
      <form method="POST">
        <input type="hidden" name="req_id" value="<?= $r['id'] ?>">
        <div class="mb-2">
          <label class="bm-form-label">Qty Aktual Diterima</label>
          <input type="number" name="qty_received" class="bm-form-control" step="0.1" min="0" value="<?= $r['qty_requested'] ?>">
        </div>
        <button name="action" value="received" class="btn-navy w-100" style="background:#059669" data-confirm="Konfirmasi selesai diterima?"><i class="bi bi-box-seam me-1"></i>Konfirmasi Diterima</button>
      </form>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php endforeach; ?>
</div>

<script>
function filterReq(btn, status) {
  document.querySelectorAll('.bm-badge[onclick]').forEach(b => {
    b.style.background='#f3f4f6'; b.style.color='var(--text-muted)';
  });
  btn.style.background='var(--navy)'; btn.style.color='var(--ivory)';
  document.querySelectorAll('.req-card').forEach(c => {
    c.style.display = (status==='all' || c.dataset.status===status) ? '' : 'none';
  });
}
// default style
document.querySelectorAll('.bm-badge[onclick]').forEach((b,i)=>{
  if(i===0){b.style.background='var(--navy)';b.style.color='var(--ivory)';}
  else{b.style.background='#f3f4f6';b.style.color='var(--text-muted)';}
});
</script>
<?php require_once __DIR__ . '/../../includes/layout-admin-footer.php'; ?>
