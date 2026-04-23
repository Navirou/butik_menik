<?php
require_once __DIR__ . '/../../includes/auth.php';
requireLogin([ROLE_STAFF]);

$user = currentUser(); $role = 'staff'; $pageTitle = 'Dashboard Produksi'; $activeMenu = 'dashboard'; $notifCount = 0;
$db = db();

$totalQueue   = $db->query("SELECT COUNT(*) FROM orders WHERE status IN ('pemeriksaan_stok','produksi','qc')")->fetchColumn();
$inProduction = $db->query("SELECT COUNT(*) FROM orders WHERE status='produksi'")->fetchColumn();
$inQC         = $db->query("SELECT COUNT(*) FROM orders WHERE status='qc'")->fetchColumn();
$doneToday    = $db->query("SELECT COUNT(*) FROM orders WHERE status='jadwal_ambil' AND DATE(updated_at)=CURDATE()")->fetchColumn();

$orders = $db->query(
    "SELECT o.id, o.order_code, o.status, o.qty, o.size, o.color, o.estimated_done,
            u.name AS customer_name, p.name AS product_name,
            (SELECT pl.progress FROM production_logs pl WHERE pl.order_id=o.id ORDER BY pl.created_at DESC LIMIT 1) AS latest_progress,
            (SELECT ps.label FROM production_logs pl2 JOIN production_stages ps ON pl2.stage_id=ps.id WHERE pl2.order_id=o.id ORDER BY pl2.created_at DESC LIMIT 1) AS latest_stage
     FROM orders o
     JOIN users u ON o.customer_id=u.id
     LEFT JOIN products p ON o.product_id=p.id
     WHERE o.status IN ('pemeriksaan_stok','produksi','qc')
     ORDER BY FIELD(o.status,'qc','produksi','pemeriksaan_stok'), o.created_at ASC"
)->fetchAll();

$stages = $db->query("SELECT * FROM production_stages ORDER BY seq")->fetchAll();

require_once __DIR__ . '/../../includes/layout-admin.php';
?>

<div class="bm-page-header"><h1>Antrian Produksi</h1><p>Update progres dan kelola tahapan produksi pesanan aktif</p></div>

<!-- Stats -->
<div class="row g-3 mb-4">
  <?php foreach ([
    ['bi-list-task','#e8f0fe','#003366',$totalQueue,'Total Antrian'],
    ['bi-tools','#fef9e7','#C9A84C',$inProduction,'Sedang Produksi'],
    ['bi-clipboard-check','#dbeafe','#1e40af',$inQC,'Dalam QC'],
    ['bi-check2-all','#d1fae5','#065f46',$doneToday,'Selesai Hari Ini'],
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

<!-- Orders -->
<?php if (empty($orders)): ?>
<div class="bm-card"><div class="bm-card-body text-center py-5 text-muted"><i class="bi bi-check2-all" style="font-size:3rem;display:block"></i><p class="mt-2">Tidak ada pesanan dalam antrian produksi.</p></div></div>
<?php else: ?>
<div class="row g-3">
<?php foreach ($orders as $o):
  $prog  = (int)($o['latest_progress'] ?? 0);
  $isQC  = $o['status'] === 'qc';
  $urgent = $o['estimated_done'] && strtotime($o['estimated_done']) < strtotime('+2 days');
?>
<div class="col-lg-6">
  <div class="bm-card <?= $urgent ? 'border-warning' : '' ?>">
    <div class="bm-card-header">
      <div>
        <div style="font-size:.75rem;color:var(--text-muted);font-weight:500"><?= htmlspecialchars($o['order_code']) ?> · <?= htmlspecialchars($o['customer_name']) ?></div>
        <div style="font-weight:700;font-size:1rem;color:var(--navy)"><?= htmlspecialchars($o['product_name'] ?? 'Pesanan Custom') ?></div>
      </div>
      <div class="text-end">
        <?php if ($isQC): ?>
        <span class="bm-badge bm-badge-info">⚗️ QC</span>
        <?php elseif ($urgent): ?>
        <span class="bm-badge bm-badge-warning">⚡ Urgent</span>
        <?php endif; ?>
      </div>
    </div>
    <div class="bm-card-body">
      <div class="d-flex gap-3 mb-3 flex-wrap" style="font-size:.82rem;color:var(--text-muted)">
        <span><i class="bi bi-rulers me-1"></i><?= htmlspecialchars($o['size'] ?? '-') ?></span>
        <span><i class="bi bi-palette me-1"></i><?= htmlspecialchars($o['color'] ?? '-') ?></span>
        <span><i class="bi bi-bag me-1"></i><?= $o['qty'] ?> pcs</span>
        <?php if ($o['estimated_done']): ?>
        <span><i class="bi bi-calendar me-1"></i>Est: <?= date('d M', strtotime($o['estimated_done'])) ?></span>
        <?php endif; ?>
      </div>

      <?php if ($o['latest_stage']): ?>
      <div class="mb-2" style="font-size:.82rem;font-weight:600;color:var(--navy)"><i class="bi bi-tools me-1"></i><?= htmlspecialchars($o['latest_stage']) ?></div>
      <?php endif; ?>

      <div class="d-flex align-items-center gap-2 mb-3">
        <div class="bm-progress flex-grow-1"><div class="bm-progress-fill <?= $prog >= 100 ? 'success' : '' ?>" style="width:<?= $prog ?>%"></div></div>
        <span style="font-size:.82rem;font-weight:700;color:var(--navy);min-width:36px"><?= $prog ?>%</span>
      </div>

      <div class="d-flex gap-2 flex-wrap">
        <button class="btn-navy" onclick="openUpdateModal(<?= $o['id'] ?>, '<?= htmlspecialchars($o['order_code']) ?>', <?= $prog ?>)">
          <i class="bi bi-pencil me-1"></i>Update Progres
        </button>
        <a href="order-detail.php?id=<?= $o['id'] ?>" class="btn-ivory" style="font-size:.85rem">Detail</a>
        <?php if ($isQC): ?>
        <button class="btn-navy" style="background:#059669" onclick="openQCModal(<?= $o['id'] ?>, '<?= htmlspecialchars($o['order_code']) ?>')">
          <i class="bi bi-clipboard-check me-1"></i>Input QC
        </button>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Update Modal -->
<div class="modal fade" id="updateModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="border-radius:16px;overflow:hidden">
      <div class="modal-header" style="background:var(--navy);color:var(--ivory)">
        <h6 class="modal-title fw-bold"><i class="bi bi-tools me-2"></i>Update Progres – <span id="upd-code"></span></h6>
        <button class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form id="updateForm">
        <div class="modal-body p-4">
          <input type="hidden" id="upd-order-id">
          <div class="mb-3">
            <label class="bm-form-label">Tahap Produksi</label>
            <select id="upd-stage" class="bm-form-control">
              <?php foreach ($stages as $s): ?>
              <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['label']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="bm-form-label">Persentase Selesai: <span id="prog-display" style="color:var(--navy);font-weight:700">0</span>%</label>
            <input type="range" id="upd-progress" class="form-range" min="0" max="100" value="0" oninput="document.getElementById('prog-display').textContent=this.value" style="accent-color:var(--navy)">
          </div>
          <div class="mb-0">
            <label class="bm-form-label">Catatan</label>
            <textarea id="upd-notes" class="bm-form-control" rows="2" placeholder="Catatan update produksi..."></textarea>
          </div>
        </div>
        <div class="modal-footer border-0 pt-0 px-4 pb-4">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="border-radius:8px">Batal</button>
          <button type="submit" class="btn-navy"><i class="bi bi-check-circle me-1"></i>Simpan</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- QC Modal -->
<div class="modal fade" id="qcModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="border-radius:16px;overflow:hidden">
      <div class="modal-header" style="background:#059669;color:#fff">
        <h6 class="modal-title fw-bold"><i class="bi bi-clipboard-check me-2"></i>Input Quality Control – <span id="qc-code"></span></h6>
        <button class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form id="qcForm">
        <div class="modal-body p-4">
          <input type="hidden" id="qc-order-id">
          <div class="mb-3">
            <label class="bm-form-label">Hasil QC</label>
            <div class="d-flex gap-3">
              <label class="d-flex align-items-center gap-2" style="cursor:pointer;font-weight:600">
                <input type="radio" name="qc-passed" value="1" style="accent-color:#059669" checked> ✅ Lulus QC
              </label>
              <label class="d-flex align-items-center gap-2" style="cursor:pointer;font-weight:600">
                <input type="radio" name="qc-passed" value="0" style="accent-color:#ef4444"> ❌ Tidak Lulus
              </label>
            </div>
          </div>
          <div class="mb-0">
            <label class="bm-form-label">Catatan QC</label>
            <textarea id="qc-notes" class="bm-form-control" rows="3" placeholder="Catatan hasil pemeriksaan kualitas..."></textarea>
          </div>
        </div>
        <div class="modal-footer border-0 pt-0 px-4 pb-4">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="border-radius:8px">Batal</button>
          <button type="submit" class="btn-navy" style="background:#059669"><i class="bi bi-clipboard-check me-1"></i>Simpan QC</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function openUpdateModal(id, code, prog) {
  document.getElementById('upd-order-id').value = id;
  document.getElementById('upd-code').textContent = code;
  document.getElementById('upd-progress').value = prog;
  document.getElementById('prog-display').textContent = prog;
  new bootstrap.Modal(document.getElementById('updateModal')).show();
}
function openQCModal(id, code) {
  document.getElementById('qc-order-id').value = id;
  document.getElementById('qc-code').textContent = code;
  new bootstrap.Modal(document.getElementById('qcModal')).show();
}

document.getElementById('updateForm').addEventListener('submit', async e => {
  e.preventDefault();
  const d = await bmPost('../../api/production-update.php', {
    order_id: document.getElementById('upd-order-id').value,
    stage_id: document.getElementById('upd-stage').value,
    progress: document.getElementById('upd-progress').value,
    notes:    document.getElementById('upd-notes').value,
  });
  if (d.success) { bmToast('Progres berhasil diupdate!'); setTimeout(()=>location.reload(),1000); }
  else bmToast(d.message, 'danger');
});

document.getElementById('qcForm').addEventListener('submit', async e => {
  e.preventDefault();
  const passed = document.querySelector('input[name="qc-passed"]:checked').value;
  const d = await bmPost('../../api/qc-input.php', {
    order_id: document.getElementById('qc-order-id').value,
    passed,
    notes: document.getElementById('qc-notes').value,
  });
  if (d.success) { bmToast('Hasil QC disimpan!'); setTimeout(()=>location.reload(),1000); }
  else bmToast(d.message, 'danger');
});
</script>
<?php require_once __DIR__ . '/../../includes/layout-admin-footer.php'; ?>
