<?php
require_once __DIR__ . '/../../includes/auth.php';
requireLogin([ROLE_CUSTOMER]);

$user = currentUser();
$db   = db();

// Reload fresh data
$fresh = $db->prepare("SELECT * FROM users WHERE id=?");
$fresh->execute([$user['id']]); $fresh = $fresh->fetch();

$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name  = trim($_POST['name']  ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));

    if (!$name || !$email) {
        $error = 'Nama dan email wajib diisi.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Format email tidak valid.';
    } else {
        // Check email duplicate
        $chk = $db->prepare("SELECT id FROM users WHERE email=? AND id!=?");
        $chk->execute([$email, $user['id']]);
        if ($chk->fetch()) {
            $error = 'Email sudah digunakan oleh akun lain.';
        } else {
            // Handle avatar upload
            $avatarFile = $fresh['avatar'];
            if (!empty($_FILES['avatar']['name'])) {
                $ext = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
                if (!in_array($ext, ['jpg','jpeg','png','webp'])) {
                    $error = 'Format foto harus JPG, PNG, atau WEBP.';
                } elseif ($_FILES['avatar']['size'] > 2 * 1024 * 1024) {
                    $error = 'Ukuran foto maks 2MB.';
                } else {
                    // Ensure avatars dir exists
                    @mkdir(UPLOAD_PATH . 'avatars/', 0755, true);
                    $avatarFile = 'avatar_' . $user['id'] . '.' . $ext;
                    move_uploaded_file($_FILES['avatar']['tmp_name'], UPLOAD_PATH . 'avatars/' . $avatarFile);
                }
            }

            if (!$error) {
                $db->prepare("UPDATE users SET name=?, email=?, phone=?, avatar=? WHERE id=?")
                   ->execute([$name, $email, $phone, $avatarFile, $user['id']]);
                // Refresh session
                $_SESSION['user']['name']   = $name;
                $_SESSION['user']['email']  = $email;
                $_SESSION['user']['avatar'] = $avatarFile;
                $user = currentUser();

                // Reload
                $fresh = $db->prepare("SELECT * FROM users WHERE id=?");
                $fresh->execute([$user['id']]); $fresh = $fresh->fetch();
                $success = 'Profil berhasil diperbarui!';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<meta name="theme-color" content="#003366">
<title>Edit Profil – <?= APP_NAME ?></title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="<?= APP_URL ?>/assets/css/global.css" rel="stylesheet">
<style>
body { background:var(--surface); padding-bottom:2rem; }
.top-bar { background:var(--navy); padding:.9rem 1.25rem; display:flex; align-items:center; gap:1rem; position:sticky; top:0; z-index:100; }
.top-bar .title { font-family:var(--ff-display); color:var(--ivory); font-size:1.05rem; flex:1; }
.page-wrap { max-width:600px; margin:0 auto; padding:1.25rem; }
.avatar-zone { position:relative; width:90px; height:90px; margin:0 auto; cursor:pointer; }
.avatar-circle {
  width:90px; height:90px; border-radius:50%;
  background:var(--gold); color:var(--navy);
  font-family:var(--ff-display); font-size:2.2rem; font-weight:700;
  display:flex; align-items:center; justify-content:center;
  border:3px solid rgba(255,255,224,.3); overflow:hidden;
}
.avatar-circle img { width:100%; height:100%; object-fit:cover; }
.avatar-edit-btn {
  position:absolute; bottom:0; right:0;
  width:28px; height:28px; border-radius:50%;
  background:var(--navy); color:var(--ivory);
  display:flex; align-items:center; justify-content:center;
  font-size:.85rem; border:2px solid var(--surface);
}
</style>
</head>
<body>

<div class="top-bar">
  <a href="dashboard.php" style="color:rgba(255,255,224,.7);text-decoration:none;font-size:1.2rem"><i class="bi bi-arrow-left"></i></a>
  <span class="title">Edit Profil</span>
</div>

<div class="page-wrap">
  <?php if ($success): ?>
  <div class="bm-alert bm-alert-success bm-alert-auto-dismiss"><i class="bi bi-check-circle-fill"></i><?= htmlspecialchars($success) ?></div>
  <?php endif; ?>
  <?php if ($error): ?>
  <div class="bm-alert bm-alert-danger"><i class="bi bi-exclamation-circle-fill"></i><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="POST" enctype="multipart/form-data">
    <!-- Avatar Section -->
    <div class="bm-card mb-3">
      <div style="background:linear-gradient(135deg,var(--navy),var(--navy-mid));padding:1.75rem;text-align:center">
        <label for="avatarInput" class="avatar-zone d-block mx-auto" style="cursor:pointer">
          <div class="avatar-circle mx-auto">
            <?php if ($fresh['avatar']): ?>
            <img src="<?= APP_URL ?>/uploads/avatars/<?= htmlspecialchars($fresh['avatar']) ?>" alt="" id="avatarPreview">
            <?php else: ?>
            <span id="avatarInitial"><?= strtoupper(mb_substr($fresh['name'],0,1)) ?></span>
            <?php endif; ?>
          </div>
          <div class="avatar-edit-btn"><i class="bi bi-camera-fill"></i></div>
        </label>
        <input type="file" id="avatarInput" name="avatar" accept=".jpg,.jpeg,.png,.webp" style="display:none" onchange="previewAvatar(this)">
        <div style="font-family:var(--ff-display);color:var(--ivory);font-size:1.1rem;margin-top:.75rem"><?= htmlspecialchars($fresh['name']) ?></div>
        <div style="color:rgba(255,255,224,.6);font-size:.78rem">Ketuk foto untuk mengubah</div>
      </div>
    </div>

    <!-- Form Fields -->
    <div class="bm-card mb-3">
      <div class="bm-card-header"><span class="bm-card-title"><i class="bi bi-person-fill me-2"></i>Informasi Pribadi</span></div>
      <div class="bm-card-body">
        <div class="mb-3">
          <label class="bm-form-label">Nama Lengkap <span class="text-danger">*</span></label>
          <input type="text" name="name" class="bm-form-control" value="<?= htmlspecialchars($fresh['name']) ?>" required>
        </div>
        <div class="mb-3">
          <label class="bm-form-label">Email <span class="text-danger">*</span></label>
          <input type="email" name="email" class="bm-form-control" value="<?= htmlspecialchars($fresh['email']) ?>" required>
        </div>
        <div class="mb-0">
          <label class="bm-form-label">No. Telepon / WhatsApp</label>
          <input type="tel" name="phone" class="bm-form-control" value="<?= htmlspecialchars($fresh['phone'] ?? '') ?>" placeholder="08xxxxxxxxxx">
        </div>
      </div>
    </div>

    <!-- Info Read-Only -->
    <div class="bm-card mb-4">
      <div class="bm-card-header"><span class="bm-card-title"><i class="bi bi-info-circle me-2"></i>Informasi Akun</span></div>
      <div class="bm-card-body">
        <?php foreach ([
          ['Role',       'Pelanggan'],
          ['Bergabung',  date('d F Y', strtotime($fresh['created_at']))],
          ['Status',     $fresh['is_active'] ? '✅ Aktif' : '❌ Nonaktif'],
        ] as [$lbl,$val]): ?>
        <div class="d-flex justify-content-between py-2" style="border-bottom:1px solid var(--border);font-size:.88rem">
          <span style="color:var(--text-muted)"><?= $lbl ?></span>
          <strong><?= htmlspecialchars($val) ?></strong>
        </div>
        <?php endforeach; ?>
        <div class="pt-2" style="font-size:.8rem;color:var(--text-muted)">
          <i class="bi bi-lock me-1"></i>Untuk mengganti password, gunakan menu <a href="change-password.php" style="color:var(--navy);font-weight:600">Ganti Password</a>.
        </div>
      </div>
    </div>

    <div class="d-grid gap-2">
      <button type="submit" class="btn-navy" style="padding:.85rem;border-radius:12px;font-size:1rem">
        <i class="bi bi-check-circle me-2"></i>Simpan Perubahan
      </button>
      <a href="dashboard.php" class="btn-ivory text-center" style="border-radius:12px;padding:.75rem">Batal</a>
    </div>
  </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= APP_URL ?>/assets/js/global.js"></script>
<script>
function previewAvatar(input) {
  if (!input.files[0]) return;
  const reader = new FileReader();
  reader.onload = e => {
    const circle = input.closest('label').querySelector('.avatar-circle');
    circle.innerHTML = `<img src="${e.target.result}" style="width:100%;height:100%;object-fit:cover;border-radius:50%">`;
  };
  reader.readAsDataURL(input.files[0]);
}
</script>
</body>
</html>
