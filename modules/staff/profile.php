<?php
require_once __DIR__ . '/../../includes/auth.php';
requireLogin([ROLE_STAFF]);
require_once __DIR__ . '/../../includes/profile-handler.php';

$user = currentUser(); $role = 'staff'; $pageTitle = 'Profil Saya'; $activeMenu = 'profile'; $notifCount = 0;

// Work stats
$db = db();
$totalHandled = $db->prepare("SELECT COUNT(DISTINCT order_id) FROM production_logs WHERE updated_by=?"); $totalHandled->execute([$user['id']]); $totalHandled=$totalHandled->fetchColumn();
$thisMonth    = $db->prepare("SELECT COUNT(DISTINCT order_id) FROM production_logs WHERE updated_by=? AND MONTH(created_at)=MONTH(NOW())"); $thisMonth->execute([$user['id']]); $thisMonth=$thisMonth->fetchColumn();
$totalUpdates = $db->prepare("SELECT COUNT(*) FROM production_logs WHERE updated_by=?"); $totalUpdates->execute([$user['id']]); $totalUpdates=$totalUpdates->fetchColumn();

require_once __DIR__ . '/../../includes/layout-admin.php';
?>

<div class="bm-page-header"><h1>Profil Saya</h1><p>Kelola informasi dan keamanan akun</p></div>

<div class="row g-4">
  <div class="col-lg-4">
    <div class="bm-card text-center">
      <div class="bm-card-body py-4">
        <div style="width:80px;height:80px;border-radius:50%;background:var(--navy);color:var(--ivory);font-family:var(--ff-display);font-size:2rem;font-weight:700;display:flex;align-items:center;justify-content:center;margin:0 auto 1rem;box-shadow:var(--shadow-md)">
          <?= strtoupper(mb_substr($freshUser['name'],0,1)) ?>
        </div>
        <div style="font-family:var(--ff-display);font-size:1.3rem;color:var(--navy)"><?= htmlspecialchars($freshUser['name']) ?></div>
        <div style="font-size:.82rem;color:var(--text-muted);margin:.25rem 0 .75rem"><?= htmlspecialchars($freshUser['email']) ?></div>
        <span class="bm-badge bm-badge-info"><i class="bi bi-tools me-1"></i>Staff Produksi</span>
        <div class="bm-divider my-3"></div>
        <div class="row g-2 text-center">
          <?php foreach ([[$totalHandled,'Pesanan Ditangani'],[$thisMonth,'Bulan Ini'],[$totalUpdates,'Total Update']] as [$v,$l]): ?>
          <div class="col-4" style="padding:.5rem;background:var(--surface);border-radius:8px">
            <div style="font-weight:800;font-size:1.2rem;color:var(--navy)"><?= $v ?></div>
            <div style="font-size:.68rem;color:var(--text-muted)"><?= $l ?></div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
  <div class="col-lg-8">
    <div class="bm-card mb-4">
      <div class="bm-card-header"><span class="bm-card-title"><i class="bi bi-person-gear me-2"></i>Edit Profil</span></div>
      <div class="bm-card-body">
        <?php if ($profileSuccess): ?><div class="bm-alert bm-alert-success bm-alert-auto-dismiss"><i class="bi bi-check-circle-fill"></i><?= htmlspecialchars($profileSuccess) ?></div><?php endif; ?>
        <?php if ($profileError): ?><div class="bm-alert bm-alert-danger"><i class="bi bi-exclamation-circle-fill"></i><?= htmlspecialchars($profileError) ?></div><?php endif; ?>
        <form method="POST">
          <input type="hidden" name="action" value="update_profile">
          <div class="row g-3">
            <div class="col-sm-6"><label class="bm-form-label">Nama Lengkap</label><input type="text" name="name" class="bm-form-control" value="<?= htmlspecialchars($freshUser['name']) ?>" required></div>
            <div class="col-sm-6"><label class="bm-form-label">No. Telepon</label><input type="tel" name="phone" class="bm-form-control" value="<?= htmlspecialchars($freshUser['phone']??'') ?>"></div>
            <div class="col-12"><label class="bm-form-label">Email</label><input type="email" name="email" class="bm-form-control" value="<?= htmlspecialchars($freshUser['email']) ?>" required></div>
          </div>
          <div class="mt-3"><button type="submit" class="btn-navy"><i class="bi bi-check-circle me-1"></i>Simpan</button></div>
        </form>
      </div>
    </div>
    <div class="bm-card">
      <div class="bm-card-header"><span class="bm-card-title"><i class="bi bi-lock me-2"></i>Ganti Password</span></div>
      <div class="bm-card-body">
        <?php if ($passwordSuccess): ?><div class="bm-alert bm-alert-success bm-alert-auto-dismiss"><i class="bi bi-check-circle-fill"></i><?= htmlspecialchars($passwordSuccess) ?></div><?php endif; ?>
        <?php if ($passwordError): ?><div class="bm-alert bm-alert-danger"><i class="bi bi-exclamation-circle-fill"></i><?= htmlspecialchars($passwordError) ?></div><?php endif; ?>
        <form method="POST">
          <input type="hidden" name="action" value="change_password">
          <div class="row g-3">
            <div class="col-12"><label class="bm-form-label">Password Saat Ini</label><input type="password" name="current_password" class="bm-form-control" required placeholder="••••••••"></div>
            <div class="col-sm-6"><label class="bm-form-label">Password Baru</label><input type="password" name="new_password" class="bm-form-control" required placeholder="Min. 6 karakter"></div>
            <div class="col-sm-6"><label class="bm-form-label">Konfirmasi</label><input type="password" name="confirm_password" class="bm-form-control" required placeholder="••••••••"></div>
          </div>
          <div class="mt-3"><button type="submit" class="btn-navy"><i class="bi bi-shield-lock me-1"></i>Perbarui Password</button></div>
        </form>
      </div>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/../../includes/layout-admin-footer.php'; ?>
