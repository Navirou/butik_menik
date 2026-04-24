<?php
require_once __DIR__ . '/../../includes/auth.php';
requireLogin([ROLE_OWNER]);

$user = currentUser(); $role = 'owner'; $pageTitle = 'Monitor Produksi'; $activeMenu = 'production'; $notifCount = 0;
$db = db();

// Summary
$inProd  = $db->query("SELECT COUNT(*) FROM orders WHERE status='produksi'")->fetchColumn();
$inQC    = $db->query("SELECT COUNT(*) FROM orders WHERE status='qc'")->fetchColumn();
$inStock = $db->query("SELECT COUNT(*) FROM orders WHERE status='pemeriksaan_stok'")->fetchColumn();
$ready   = $db->query("SELECT COUNT(*) FROM orders WHERE status='jadwal_ambil'")->fetchColumn();

// All active production orders with latest progress
$orders = $db->query(
    "SELECT o.id, o.order_code, o.status, o.qty, o.size, o.color, o.estimated_done,
            u.name AS customer_name,
            p.name AS product_name,
            s.name AS staff_name,
            (SELECT pl.progress   FROM production_logs pl WHERE pl.order_id=o.id ORDER BY pl.created_at DESC LIMIT 1) AS progress,
            (SELECT ps.label      FROM production_logs pl2 JOIN production_stages ps ON pl2.stage_id=ps.id WHERE pl2.order_id=o.id ORDER BY pl2.created_at DESC LIMIT 1) AS stage_label,
            (SELECT pl3.created_at FROM production_logs pl3 WHERE pl3.order_id=o.id ORDER BY pl3.created_at DESC LIMIT 1) AS last_update
     FROM orders o
     JOIN users u ON o.customer_id=u.id
     LEFT JOIN products p ON o.product_id=p.id
     LEFT JOIN users s ON o.staff_id=s.id
     WHERE o.status IN ('pemeriksaan_stok','produksi','qc','jadwal_ambil')
     ORDER BY FIELD(o.status,'qc','produksi','pemeriksaan_stok','jadwal_ambil'), o.estimated_done ASC"
)->fetchAll();

// Production logs for timeline
$logs = $db->query(
    "SELECT pl.order_id, pl.progress, ps.label AS stage, u.name AS staff, pl.notes, pl.created_at
     FROM production_logs pl
     JOIN production_stages ps ON pl.stage_id=ps.id
     JOIN users u ON pl.updated_by=u.id
     ORDER BY pl.created_at DESC LIMIT 20"
)->fetchAll();

$statusMap = [
    'pemeriksaan_stok'=>['Cek Stok','bm-badge-info','bi-boxes'],
    'produksi'=>['Produksi','bm-badge-navy','bi-tools'],
    'qc'=>['QC','bm-badge-warning','bi-clipboard-check-fill'],
    'jadwal_ambil'=>['Siap Ambil','bm-badge-success','bi-calendar-check-fill'],
];

require_once __DIR__ . '/../../includes/layout-admin.php';
?>

<div class="bm-page-header">
  <h1>Monitor Produksi</h1>
  <p>Pantau semua pesanan yang sedang dalam proses produksi secara real-time</p>
</div>

<!-- Summary -->
<div class="row g-3 mb-4">
  <?php foreach ([
    ['bi-boxes','#dbeafe','#1e40af',$inStock,'Cek Stok'],
    ['bi-tools','#e8f0fe','#003366',$inProd,'Produksi'],
    ['bi-clipboard-check-fill','#fef3c7','#92400e',$inQC,'Quality Control'],
    ['bi-calendar-check-fill','#d1fae5','#065f46',$ready,'Siap Diambil'],
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

<div class="row g-4">
  <!-- Order Cards -->
  <div class="col-lg-8">
    <div class="d-flex align-items-center justify-content-between mb-3">
      <h6 style="font-weight:700;color:var(--navy);margin:0">Pesanan Aktif (<?= count($orders) ?>)</h6>
      <!-- Filter pills -->
      <div class="d-flex gap-1 flex-wrap">
        <?php foreach (['all'=>'Semua','pemeriksaan_stok'=>'Cek Stok','produksi'=>'Produksi','qc'=>'QC','jadwal_ambil'=>'Siap'] as $k=>$l): ?>
        <button class="bm-badge border-0" style="cursor:pointer;font-size:.72rem;padding:.3em .7em;<?= $k==='all'?'background:var(--navy);color:var(--ivory)':'background:#f3f4f6;color:var(--text-muted)' ?>" onclick="filterProd(this,'<?= $k ?>')"><?= $l ?></button>
        <?php endforeach; ?>
      </div>
    </div>

    <?php if (empty($orders)): ?>
    <div class="bm-card"><div class="bm-card-body text-center py-5 text-muted"><i class="bi bi-check2-all" style="font-size:2.5rem;display:block"></i><p>Tidak ada pesanan aktif.</p></div></div>
    <?php endif; ?>

    <div id="prod-list">
    <?php foreach ($orders as $o):
      $prog = (int)($o['progress'] ?? 0);
      [$slbl,$scls,$sico] = $statusMap[$o['status']] ?? ['Proses','bm-badge-muted','bi-tools'];
      $isUrgent = $o['estimated_done'] && strtotime($o['estimated_done']) < strtotime('+3 days');
    ?>
    <div class="bm-card mb-3 prod-card" data-status="<?= $o['status'] ?>">
      <div class="bm-card-body">
        <div class="d-flex align-items-start justify-content-between gap-2 mb-2">
          <div>
            <div style="font-size:.75rem;color:var(--text-muted)"><?= htmlspecialchars($o['order_code']) ?> · <?= htmlspecialchars($o['customer_name']) ?></div>
            <div style="font-weight:700;font-size:.97rem;color:var(--navy)"><?= htmlspecialchars($o['product_name']??'Custom') ?></div>
            <div style="font-size:.8rem;color:var(--text-muted);margin-top:2px">
              <?= $o['qty'] ?> pcs · <?= htmlspecialchars($o['size']??'') ?> · <?= htmlspecialchars($o['color']??'') ?>
            </div>
          </div>
          <div class="text-end" style="flex-shrink:0">
            <span class="bm-badge <?= $scls ?>"><i class="bi <?= $sico ?> me-1"></i><?= $slbl ?></span>
            <?php if ($isUrgent): ?><div class="mt-1"><span class="bm-badge bm-badge-danger">⚡ Urgent</span></div><?php endif; ?>
          </div>
        </div>

        <?php if ($o['stage_label']): ?>
        <div style="font-size:.78rem;color:var(--text-muted);margin-bottom:.4rem"><i class="bi bi-tools me-1"></i><?= htmlspecialchars($o['stage_label']) ?></div>
        <?php endif; ?>

        <div class="d-flex align-items-center gap-2 mb-2">
          <div class="bm-progress flex-grow-1"><div class="bm-progress-fill <?= $prog>=100?'success':'' ?>" style="width:<?= $prog ?>%"></div></div>
          <span style="font-size:.8rem;font-weight:700;color:var(--navy);min-width:34px"><?= $prog ?>%</span>
        </div>

        <div class="d-flex align-items-center justify-content-between" style="font-size:.75rem;color:var(--text-muted)">
          <span><?= $o['last_update'] ? 'Update: '.date('d M, H:i', strtotime($o['last_update'])) : 'Belum ada update' ?></span>
          <?php if ($o['estimated_done']): ?><span>Est: <?= date('d M Y', strtotime($o['estimated_done'])) ?></span><?php endif; ?>
          <a href="order-detail.php?id=<?= $o['id'] ?>" class="btn-navy" style="font-size:.72rem;padding:.25rem .6rem">Detail</a>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
    </div>
  </div>

  <!-- Activity Log -->
  <div class="col-lg-4">
    <div class="bm-card h-100">
      <div class="bm-card-header"><span class="bm-card-title"><i class="bi bi-clock-history me-2"></i>Log Aktivitas Terbaru</span></div>
      <div class="bm-card-body p-0">
        <?php if (empty($logs)): ?>
        <div class="text-center py-4 text-muted" style="font-size:.85rem">Belum ada aktivitas.</div>
        <?php endif; ?>
        <?php foreach ($logs as $log): ?>
        <div class="px-4 py-3" style="border-bottom:1px solid var(--border)">
          <div style="font-weight:600;font-size:.85rem;color:var(--navy)"><?= htmlspecialchars($log['stage']) ?></div>
          <div style="font-size:.78rem;color:var(--text-muted)">oleh <?= htmlspecialchars($log['staff']) ?> · <?= $log['progress'] ?>%</div>
          <?php if ($log['notes']): ?><div style="font-size:.75rem;color:#9ca3af;margin-top:2px"><?= htmlspecialchars(mb_substr($log['notes'],0,60)) ?><?= strlen($log['notes'])>60?'...':'' ?></div><?php endif; ?>
          <div style="font-size:.72rem;color:#d1d5db;margin-top:2px"><?= date('d M Y, H:i', strtotime($log['created_at'])) ?></div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<script>
function filterProd(btn, status) {
  document.querySelectorAll('[onclick^="filterProd"]').forEach(b => {
    b.style.background='#f3f4f6'; b.style.color='var(--text-muted)';
  });
  btn.style.background='var(--navy)'; btn.style.color='var(--ivory)';
  document.querySelectorAll('.prod-card').forEach(c => {
    c.style.display = (status==='all'||c.dataset.status===status) ? '' : 'none';
  });
}
</script>
<?php require_once __DIR__ . '/../../includes/layout-admin-footer.php'; ?>
