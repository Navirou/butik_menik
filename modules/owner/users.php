<?php
require_once __DIR__ . '/../../includes/auth.php';
requireLogin([ROLE_OWNER]);

$user = currentUser(); $role = 'owner'; $pageTitle = 'Manajemen User'; $activeMenu = 'users'; $notifCount = 0;
$db = db();

$success = $error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
        $name  = trim($_POST['name'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));
        $phone = trim($_POST['phone'] ?? '');
        $rid   = (int)$_POST['role_id'];
        $pass  = $_POST['password'] ?? '';
        if (!$name || !$email || !$pass || !$rid) { $error = 'Semua kolom wajib diisi.'; }
        else {
            $chk = $db->prepare("SELECT id FROM users WHERE email=?"); $chk->execute([$email]);
            if ($chk->fetch()) { $error = 'Email sudah terdaftar.'; }
            else {
                $db->prepare("INSERT INTO users(role_id,name,email,phone,password) VALUES(?,?,?,?,?)")
                   ->execute([$rid,$name,$email,$phone,password_hash($pass,PASSWORD_BCRYPT)]);
                $success = "Pengguna $name berhasil ditambahkan.";
            }
        }
    } elseif ($action === 'toggle') {
        $uid = (int)$_POST['user_id'];
        $db->prepare("UPDATE users SET is_active=1-is_active WHERE id=? AND id!=?")->execute([$uid,$user['id']]);
        header('Location: users.php'); exit;
    }
}

$users = $db->query(
    "SELECT u.*, r.label AS role_label FROM users u JOIN roles r ON u.role_id=r.id ORDER BY u.role_id, u.name"
)->fetchAll();
$roles = $db->query("SELECT * FROM roles ORDER BY id")->fetchAll();

require_once __DIR__ . '/../../includes/layout-admin.php';
?>

<div class="bm-page-header d-flex justify-content-between align-items-start gap-3">
  <div><h1>Manajemen User</h1><p>Kelola akun pengguna dan hak akses sistem</p></div>
  <button class="btn-navy" onclick="new bootstrap.Modal(document.getElementById('createModal')).show()"><i class="bi bi-person-plus me-1"></i>Tambah User</button>
</div>

<?php if ($success): ?><div class="bm-alert bm-alert-success bm-alert-auto-dismiss"><i class="bi bi-check-circle-fill"></i><?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="bm-alert bm-alert-danger"><i class="bi bi-exclamation-circle-fill"></i><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="bm-card">
  <div class="bm-card-header"><span class="bm-card-title"><i class="bi bi-people me-2"></i>Daftar Pengguna <span class="bm-badge bm-badge-navy ms-1"><?= count($users) ?></span></span></div>
  <div class="table-responsive">
    <table class="bm-table">
      <thead><tr><th>Nama</th><th>Email</th><th>Role</th><th>Telepon</th><th>Status</th><th>Bergabung</th><th>Aksi</th></tr></thead>
      <tbody>
      <?php foreach ($users as $u):
        $roleColors = [1=>'bm-badge-navy',2=>'bm-badge-info',3=>'bm-badge-gold',4=>'bm-badge-muted'];
        $rCls = $roleColors[$u['role_id']] ?? 'bm-badge-muted';
      ?>
      <tr>
        <td>
          <div class="d-flex align-items-center gap-2">
            <div style="width:32px;height:32px;border-radius:50%;background:var(--navy);color:var(--ivory);display:flex;align-items:center;justify-content:center;font-size:.8rem;font-weight:700;flex-shrink:0">
              <?= strtoupper(mb_substr($u['name'],0,1)) ?>
            </div>
            <span style="font-weight:600"><?= htmlspecialchars($u['name']) ?></span>
          </div>
        </td>
        <td style="font-size:.85rem"><?= htmlspecialchars($u['email']) ?></td>
        <td><span class="bm-badge <?= $rCls ?>"><?= htmlspecialchars($u['role_label']) ?></span></td>
        <td style="font-size:.85rem"><?= htmlspecialchars($u['phone'] ?? '-') ?></td>
        <td><span class="bm-badge <?= $u['is_active'] ? 'bm-badge-success' : 'bm-badge-danger' ?>"><?= $u['is_active'] ? 'Aktif' : 'Nonaktif' ?></span></td>
        <td style="font-size:.82rem"><?= date('d M Y', strtotime($u['created_at'])) ?></td>
        <td>
          <?php if ($u['id'] != $user['id']): ?>
          <form method="POST" style="display:inline">
            <input type="hidden" name="action" value="toggle">
            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
            <button type="submit" class="btn-<?= $u['is_active'] ? 'danger-outline' : 'ivory' ?>" style="font-size:.72rem;padding:.28rem .65rem"
                    data-confirm="<?= $u['is_active'] ? 'Nonaktifkan' : 'Aktifkan' ?> user ini?">
              <?= $u['is_active'] ? 'Nonaktifkan' : 'Aktifkan' ?>
            </button>
          </form>
          <?php else: ?>
          <span style="font-size:.75rem;color:var(--text-muted)">Akun Anda</span>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Create Modal -->
<div class="modal fade" id="createModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="border-radius:16px;overflow:hidden">
      <div class="modal-header" style="background:var(--navy);color:var(--ivory)">
        <h6 class="modal-title fw-bold"><i class="bi bi-person-plus me-2"></i>Tambah Pengguna Baru</h6>
        <button class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST">
        <input type="hidden" name="action" value="create">
        <div class="modal-body p-4">
          <div class="row g-3">
            <div class="col-12"><label class="bm-form-label">Nama Lengkap</label><input type="text" name="name" class="bm-form-control" required></div>
            <div class="col-12"><label class="bm-form-label">Email</label><input type="email" name="email" class="bm-form-control" required></div>
            <div class="col-6"><label class="bm-form-label">No. Telepon</label><input type="tel" name="phone" class="bm-form-control"></div>
            <div class="col-6"><label class="bm-form-label">Role</label>
              <select name="role_id" class="bm-form-control" required>
                <?php foreach ($roles as $r): ?>
                <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['label']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-12"><label class="bm-form-label">Password</label><input type="password" name="password" class="bm-form-control" placeholder="Min. 6 karakter" required></div>
          </div>
        </div>
        <div class="modal-footer border-0 pt-0 px-4 pb-4">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="border-radius:8px">Batal</button>
          <button type="submit" class="btn-navy"><i class="bi bi-person-check me-1"></i>Buat Akun</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/../../includes/layout-admin-footer.php'; ?>
