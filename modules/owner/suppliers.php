<?php
require_once __DIR__ . '/../../includes/auth.php';
requireLogin([ROLE_OWNER]);

$user = currentUser(); $role = 'owner'; $pageTitle = 'Manajemen Supplier'; $activeMenu = 'suppliers'; $notifCount = 0;
$db = db();

$success = $error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add_supplier') {
        $name  = trim($_POST['name']  ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));
        $phone = trim($_POST['phone'] ?? '');
        $pass  = $_POST['password']   ?? 'Supplier@123';
        if (!$name || !$email) { $error = 'Nama dan email wajib diisi.'; }
        else {
            $chk = $db->prepare('SELECT id FROM users WHERE email=?'); $chk->execute([$email]);
            if ($chk->fetch()) { $error = 'Email sudah terdaftar.'; }
            else {
                $db->prepare('INSERT INTO users(role_id,name,email,phone,password) VALUES(?,?,?,?,?)')->execute([ROLE_SUPPLIER,$name,$email,$phone,password_hash($pass,PASSWORD_BCRYPT)]);
                $success = "Supplier $name berhasil ditambahkan.";
            }
        }
    } elseif ($action === 'assign_material') {
        $matId = (int)$_POST['material_id'];
        $supId = !empty($_POST['supplier_id']) ? (int)$_POST['supplier_id'] : null;
        $db->prepare('UPDATE materials SET supplier_id=? WHERE id=?')->execute([$supId,$matId]);
        $success = 'Supplier bahan berhasil diperbarui.';
    }
}

$suppliers = $db->query(
    "SELECT u.*, COUNT(mr.id) AS total_req, SUM(mr.status='received') AS completed
     FROM users u
     LEFT JOIN material_requests mr ON mr.supplier_id=u.id
     WHERE u.role_id=".ROLE_SUPPLIER."
     GROUP BY u.id ORDER BY u.name"
)->fetchAll();

$materials = $db->query("SELECT m.*, u.name AS sup_name FROM materials m LEFT JOIN users u ON m.supplier_id=u.id ORDER BY m.name")->fetchAll();
$supList   = $db->query("SELECT id, name FROM users WHERE role_id=".ROLE_SUPPLIER)->fetchAll();

require_once __DIR__ . '/../../includes/layout-admin.php';
?>

<div class="bm-page-header d-flex justify-content-between align-items-start gap-3">
  <div><h1>Manajemen Supplier</h1><p>Kelola daftar supplier dan penugasan bahan baku</p></div>
  <button class="btn-navy" onclick="new bootstrap.Modal(document.getElementById('addModal')).show()"><i class="bi bi-plus-lg me-1"></i>Tambah Supplier</button>
</div>

<?php if ($success): ?><div class="bm-alert bm-alert-success bm-alert-auto-dismiss"><i class="bi bi-check-circle-fill"></i><?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="bm-alert bm-alert-danger"><i class="bi bi-exclamation-circle-fill"></i><?= htmlspecialchars($error) ?></div><?php endif; ?>

<!-- Supplier Cards -->
<div class="row g-3 mb-4">
<?php foreach ($suppliers as $s):
  $rating = $s['total_req'] > 0 ? round($s['completed']/$s['total_req']*100) : 0;
?>
<div class="col-md-6 col-xl-4">
  <div class="bm-card h-100">
    <div class="bm-card-body">
      <div class="d-flex gap-3 align-items-center mb-3">
        <div style="width:46px;height:46px;border-radius:12px;background:var(--navy);color:var(--ivory);display:flex;align-items:center;justify-content:center;font-family:var(--ff-display);font-size:1.2rem;font-weight:700;flex-shrink:0">
          <?= strtoupper(mb_substr($s['name'],0,1)) ?>
        </div>
        <div>
          <div style="font-weight:700;color:var(--navy);font-size:.97rem"><?= htmlspecialchars($s['name']) ?></div>
          <div style="font-size:.78rem;color:var(--text-muted)"><?= htmlspecialchars($s['email']) ?></div>
          <?php if ($s['phone']): ?><div style="font-size:.78rem;color:var(--text-muted)"><?= htmlspecialchars($s['phone']) ?></div><?php endif; ?>
        </div>
        <span class="bm-badge <?= $s['is_active']?'bm-badge-success':'bm-badge-danger' ?> ms-auto"><?= $s['is_active']?'Aktif':'Nonaktif' ?></span>
      </div>
      <div class="row g-2 mb-3 text-center">
        <div class="col-4" style="padding:.5rem;background:var(--surface);border-radius:8px">
          <div style="font-weight:800;font-size:1.1rem;color:var(--navy)"><?= $s['total_req'] ?></div>
          <div style="font-size:.7rem;color:var(--text-muted)">Total Req</div>
        </div>
        <div class="col-4" style="padding:.5rem;background:var(--surface);border-radius:8px">
          <div style="font-weight:800;font-size:1.1rem;color:#065f46"><?= $s['completed'] ?></div>
          <div style="font-size:.7rem;color:var(--text-muted)">Selesai</div>
        </div>
        <div class="col-4" style="padding:.5rem;background:var(--surface);border-radius:8px">
          <div style="font-weight:800;font-size:1.1rem;color:var(--gold)"><?= $rating ?>%</div>
          <div style="font-size:.7rem;color:var(--text-muted)">Rate</div>
        </div>
      </div>
      <!-- Completion rate bar -->
      <div class="bm-progress" style="height:5px">
        <div class="bm-progress-fill <?= $rating>=70?'success':'' ?>" style="width:<?= $rating ?>%;background:<?= $rating>=70?'var(--success)':($rating>=40?'var(--gold)':'var(--danger)') ?>"></div>
      </div>
      <!-- Assigned materials -->
      <?php
        $assigned = array_filter($materials, fn($m)=>$m['supplier_id']==$s['id']);
        if (!empty($assigned)):
      ?>
      <div class="mt-3">
        <div style="font-size:.72rem;color:var(--text-muted);font-weight:700;text-transform:uppercase;letter-spacing:.5px;margin-bottom:.4rem">Bahan Ditugaskan</div>
        <div class="d-flex flex-wrap gap-1">
          <?php foreach ($assigned as $m): ?>
          <span class="bm-badge bm-badge-navy"><?= htmlspecialchars($m['name']) ?></span>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>
      <div class="mt-3">
        <a href="<?= APP_URL ?>/modules/owner/stock.php" class="btn-ivory w-100 text-center" style="font-size:.82rem;padding:.45rem"><i class="bi bi-send me-1"></i>Buat Permintaan Bahan</a>
      </div>
    </div>
  </div>
</div>
<?php endforeach; ?>
</div>

<!-- Assign Material to Supplier -->
<div class="bm-card">
  <div class="bm-card-header"><span class="bm-card-title"><i class="bi bi-link-45deg me-2"></i>Penugasan Bahan ke Supplier</span></div>
  <div class="table-responsive">
    <table class="bm-table">
      <thead><tr><th>Bahan</th><th>Stok Saat Ini</th><th>Supplier Saat Ini</th><th>Ubah Supplier</th></tr></thead>
      <tbody>
      <?php foreach ($materials as $m): ?>
      <tr>
        <td style="font-weight:600"><?= htmlspecialchars($m['name']) ?> <span style="font-size:.75rem;color:var(--text-muted)">(<?= $m['unit'] ?>)</span></td>
        <td>
          <?php $isLow = $m['current_stock'] <= $m['min_stock']; ?>
          <span style="font-weight:700;color:<?= $isLow?'var(--danger)':'var(--navy)' ?>"><?= number_format($m['current_stock'],1) ?></span>
          <?php if ($isLow): ?><span class="bm-badge bm-badge-danger ms-1">Kritis</span><?php endif; ?>
        </td>
        <td><?= htmlspecialchars($m['sup_name']??'Belum ditugaskan') ?></td>
        <td>
          <form method="POST" class="d-flex gap-2 align-items-center">
            <input type="hidden" name="action" value="assign_material">
            <input type="hidden" name="material_id" value="<?= $m['id'] ?>">
            <select name="supplier_id" class="bm-form-control" style="width:auto;min-width:160px;font-size:.83rem">
              <option value="">– Pilih Supplier –</option>
              <?php foreach ($supList as $s): ?>
              <option value="<?= $s['id'] ?>" <?= $m['supplier_id']==$s['id']?'selected':'' ?>><?= htmlspecialchars($s['name']) ?></option>
              <?php endforeach; ?>
            </select>
            <button type="submit" class="btn-navy" style="font-size:.78rem;padding:.35rem .7rem;white-space:nowrap">Simpan</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Add Supplier Modal -->
<div class="modal fade" id="addModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="border-radius:16px;overflow:hidden">
      <div class="modal-header" style="background:var(--navy);color:var(--ivory)">
        <h6 class="modal-title fw-bold"><i class="bi bi-truck me-2"></i>Tambah Supplier Baru</h6>
        <button class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST">
        <input type="hidden" name="action" value="add_supplier">
        <div class="modal-body p-4">
          <div class="row g-3">
            <div class="col-12"><label class="bm-form-label">Nama Perusahaan / Supplier</label><input type="text" name="name" class="bm-form-control" required></div>
            <div class="col-12"><label class="bm-form-label">Email (untuk login)</label><input type="email" name="email" class="bm-form-control" required></div>
            <div class="col-sm-6"><label class="bm-form-label">No. Telepon</label><input type="tel" name="phone" class="bm-form-control"></div>
            <div class="col-sm-6"><label class="bm-form-label">Password Awal</label><input type="text" name="password" class="bm-form-control" value="Supplier@123"></div>
          </div>
        </div>
        <div class="modal-footer border-0 pt-0 px-4 pb-4">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="border-radius:8px">Batal</button>
          <button type="submit" class="btn-navy"><i class="bi bi-person-check me-1"></i>Tambahkan</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/../../includes/layout-admin-footer.php'; ?>
