<?php
require_once __DIR__ . '/../../includes/auth.php';
requireLogin([ROLE_SUPPLIER]);
require_once __DIR__ . '/../../includes/profile-handler.php';

$user       = currentUser();
$role       = 'supplier';
$pageTitle  = 'Profil Supplier';
$activeMenu = 'profile';

$db = db();
$nc = db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0');
$nc->execute([$user['id']]); $notifCount = (int)$nc->fetchColumn();

// Supplier stats
$totalReq     = $db->prepare("SELECT COUNT(*) FROM material_requests WHERE supplier_id=?");
$totalReq->execute([$user['id']]); $totalReq = $totalReq->fetchColumn();
$completedReq = $db->prepare("SELECT COUNT(*) FROM material_requests WHERE supplier_id=? AND status='received'");
$completedReq->execute([$user['id']]); $completedReq = $completedReq->fetchColumn();
$pendingReq   = $db->prepare("SELECT COUNT(*) FROM material_requests WHERE supplier_id=? AND status='pending'");
$pendingReq->execute([$user['id']]); $pendingReq = $pendingReq->fetchColumn();

require_once __DIR__ . '/../../includes/layout-admin.php';
?>

<div class="bm-page-header"><h1>Profil Supplier</h1><p>Kelola informasi dan keamanan akun</p></div>

<div class="row g-4">
  <!-- Left: Profile Card -->
  <div class="col-lg-4">
    <div class="bm-card mb-3">
      <div style="background:linear-gradient(135deg,var(--navy),var(--navy-mid));padding:2rem;text-align:center">
        <div style="width:84px;height:84px;border-radius:50%;background:var(--gold);color:var(--navy);font-family:var(--ff-display);font-size:2rem;font-weight:700;display:flex;align-items:center;justify-content:center;margin:0 auto .85rem;border:3px solid rgba(255,255,224,.3)">
          <?= strtoupper(mb_substr($freshUser['name'], 0, 1)) ?>
        </div>
        <div style="font-family:var(--ff-display);font-size:1.15rem;color:var(--ivory)"><?= htmlspecialchars($freshUser['name']) ?></div>
        <div style="font-size:.8rem;color:rgba(255,255,224,.6);margin-top:.2rem"><?= htmlspecialchars($freshUser['email']) ?></div>
        <span class="bm-badge bm-badge-gold mt-2 d-inline-block"><i class="bi bi-truck me-1"></i>Supplier</span>
      </div>
      <div class="bm-card-body">
        <?php foreach ([
          ['bi-person',  'Nama',      $freshUser['name']],
          ['bi-envelope','Email',     $freshUser['email']],
          ['bi-phone',   'Telepon',   $freshUser['phone'] ?? '-'],
          ['bi-calendar','Bergabung', date('d M Y', strtotime($freshUser['created_at']))],
        ] as [$ico, $lbl, $val]): ?>
        <div class="d-flex align-items-center gap-3 py-2" style="border-bottom:1px solid var(--border)">
          <div style="width:34px;height:34px;border-radius:9px;background:var(--ivory);display:flex;align-items:center;justify-content:center;flex-shrink:0">
            <i class="bi <?= $ico ?>" style="color:var(--navy)"></i>
          </div>
          <div>
            <div style="font-size:.73rem;color:var(--text-muted)"><?= $lbl ?></div>
            <div style="font-size:.88rem;font-weight:500"><?= htmlspecialchars((string)$val) ?></div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Stats Card -->
    <div class="bm-card">
      <div class="bm-card-header"><span class="bm-card-title"><i class="bi bi-bar-chart me-2"></i>Statistik</span></div>
      <div class="bm-card-body">
        <div class="row g-2 text-center">
          <?php foreach ([[$totalReq,'Total Permintaan','#e8f0fe','#003366'],[$completedReq,'Selesai','#d1fae5','#065f46'],[$pendingReq,'Pending','#fef3c7','#92400e']] as [$v,$l,$bg,$c]): ?>
          <div class="col-4">
            <div style="padding:.75rem .4rem;background:<?= $bg ?>;border-radius:10px">
              <div style="font-weight:800;font-size:1.4rem;color:<?= $c ?>"><?= $v ?></div>
              <div style="font-size:.68rem;color:var(--text-muted);margin-top:2px"><?= $l ?></div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- Right: Forms -->
  <div class="col-lg-8">
    <!-- Edit Profile -->
    <div class="bm-card mb-3">
      <div class="bm-card-header"><span class="bm-card-title"><i class="bi bi-pencil-fill me-2"></i>Edit Informasi Profil</span></div>
      <div class="bm-card-body">
        <?php if ($profileSuccess): ?><div class="bm-alert bm-alert-success bm-alert-auto-dismiss"><i class="bi bi-check-circle-fill"></i><?= htmlspecialchars($profileSuccess) ?></div><?php endif; ?>
        <?php if ($profileError): ?><div class="bm-alert bm-alert-danger"><i class="bi bi-exclamation-circle-fill"></i><?= htmlspecialchars($profileError) ?></div><?php endif; ?>
        <form method="POST">
          <input type="hidden" name="action" value="update_profile">
          <div class="row g-3">
            <div class="col-sm-6">
              <label class="bm-form-label">Nama / Perusahaan</label>
              <input type="text" name="name" class="bm-form-control" value="<?= htmlspecialchars($freshUser['name']) ?>" required>
            </div>
            <div class="col-sm-6">
              <label class="bm-form-label">No. Telepon / WA</label>
              <input type="tel" name="phone" class="bm-form-control" value="<?= htmlspecialchars($freshUser['phone'] ?? '') ?>">
            </div>
            <div class="col-12">
              <label class="bm-form-label">Email</label>
              <input type="email" name="email" class="bm-form-control" value="<?= htmlspecialchars($freshUser['email']) ?>" required>
            </div>
          </div>
          <div class="mt-3 text-end">
            <button type="submit" class="btn-navy"><i class="bi bi-check-circle me-1"></i>Simpan Perubahan</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Change Password -->
    <div class="bm-card">
      <div class="bm-card-header"><span class="bm-card-title"><i class="bi bi-lock-fill me-2"></i>Ganti Password</span></div>
      <div class="bm-card-body">
        <?php if ($passwordSuccess): ?><div class="bm-alert bm-alert-success bm-alert-auto-dismiss"><i class="bi bi-check-circle-fill"></i><?= htmlspecialchars($passwordSuccess) ?></div><?php endif; ?>
        <?php if ($passwordError): ?><div class="bm-alert bm-alert-danger"><i class="bi bi-exclamation-circle-fill"></i><?= htmlspecialchars($passwordError) ?></div><?php endif; ?>
        <form method="POST">
          <input type="hidden" name="action" value="change_password">
          <div class="row g-3">
            <div class="col-12">
              <label class="bm-form-label">Password Saat Ini</label>
              <input type="password" name="current_password" class="bm-form-control" required placeholder="••••••••">
            </div>
            <div class="col-sm-6">
              <label class="bm-form-label">Password Baru</label>
              <input type="password" name="new_password" class="bm-form-control" required placeholder="Min. 6 karakter">
            </div>
            <div class="col-sm-6">
              <label class="bm-form-label">Konfirmasi Password Baru</label>
              <input type="password" name="confirm_password" class="bm-form-control" required placeholder="••••••••">
            </div>
          </div>
          <div class="mt-3 text-end">
            <button type="submit" class="btn-navy"><i class="bi bi-shield-check me-1"></i>Update Password</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../../includes/layout-admin-footer.php'; ?>
