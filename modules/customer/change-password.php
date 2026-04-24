<?php
require_once __DIR__ . '/../../includes/auth.php';
requireLogin([ROLE_CUSTOMER]);

$user    = currentUser();
$db      = db();
$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current = $_POST['current_password'] ?? '';
    $new     = $_POST['new_password']     ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    // Fetch current hash
    $stmt = $db->prepare("SELECT password FROM users WHERE id=?");
    $stmt->execute([$user['id']]); $hash = $stmt->fetchColumn();

    if (!password_verify($current, $hash)) {
        $error = 'Password saat ini tidak sesuai.';
    } elseif (strlen($new) < 6) {
        $error = 'Password baru minimal 6 karakter.';
    } elseif ($new !== $confirm) {
        $error = 'Konfirmasi password tidak cocok.';
    } elseif ($new === $current) {
        $error = 'Password baru tidak boleh sama dengan password lama.';
    } else {
        $db->prepare("UPDATE users SET password=? WHERE id=?")
           ->execute([password_hash($new, PASSWORD_BCRYPT), $user['id']]);
        $success = 'Password berhasil diperbarui! Silakan gunakan password baru untuk login berikutnya.';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<meta name="theme-color" content="#003366">
<title>Ganti Password – <?= APP_NAME ?></title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="<?= APP_URL ?>/assets/css/global.css" rel="stylesheet">
<style>
body { background:var(--surface); padding-bottom:2rem; }
.top-bar { background:var(--navy); padding:.9rem 1.25rem; display:flex; align-items:center; gap:1rem; position:sticky; top:0; z-index:100; }
.top-bar .title { font-family:var(--ff-display); color:var(--ivory); font-size:1.05rem; flex:1; }
.page-wrap { max-width:500px; margin:0 auto; padding:1.25rem; }
.strength-bar { height:5px; border-radius:3px; background:#e5e7eb; overflow:hidden; margin-top:.4rem; }
.strength-fill { height:100%; border-radius:3px; transition:width .3s, background .3s; }
.pw-toggle { position:absolute; right:.75rem; top:50%; transform:translateY(-50%); background:none; border:none; color:var(--text-muted); cursor:pointer; font-size:1rem; }
.pw-wrapper { position:relative; }
</style>
</head>
<body>

<div class="top-bar">
  <a href="dashboard.php" style="color:rgba(255,255,224,.7);text-decoration:none;font-size:1.2rem"><i class="bi bi-arrow-left"></i></a>
  <span class="title">Ganti Password</span>
</div>

<div class="page-wrap">

  <!-- Security Icon Header -->
  <div class="text-center py-4">
    <div style="width:72px;height:72px;border-radius:50%;background:var(--ivory);display:flex;align-items:center;justify-content:center;margin:0 auto .75rem;box-shadow:var(--shadow-md)">
      <i class="bi bi-shield-lock-fill" style="font-size:2rem;color:var(--navy)"></i>
    </div>
    <h2 style="font-family:var(--ff-display);font-size:1.3rem;color:var(--navy)">Keamanan Akun</h2>
    <p style="font-size:.83rem;color:var(--text-muted)">Buat password yang kuat dan unik untuk menjaga keamanan akunmu.</p>
  </div>

  <?php if ($success): ?>
  <div class="bm-alert bm-alert-success">
    <i class="bi bi-check-circle-fill"></i>
    <div>
      <?= htmlspecialchars($success) ?>
      <div class="mt-2"><a href="dashboard.php" class="btn-navy" style="font-size:.82rem;padding:.35rem .8rem">Kembali ke Dashboard</a></div>
    </div>
  </div>
  <?php endif; ?>

  <?php if ($error): ?>
  <div class="bm-alert bm-alert-danger"><i class="bi bi-exclamation-circle-fill"></i><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <?php if (!$success): ?>
  <form method="POST">
    <div class="bm-card mb-4">
      <div class="bm-card-body">

        <!-- Current Password -->
        <div class="mb-3">
          <label class="bm-form-label">Password Saat Ini <span class="text-danger">*</span></label>
          <div class="pw-wrapper">
            <input type="password" name="current_password" id="pw-current" class="bm-form-control" required placeholder="Masukkan password saat ini" style="padding-right:2.5rem">
            <button type="button" class="pw-toggle" onclick="togglePw('pw-current', this)"><i class="bi bi-eye"></i></button>
          </div>
        </div>

        <div class="bm-divider"></div>

        <!-- New Password -->
        <div class="mb-3">
          <label class="bm-form-label">Password Baru <span class="text-danger">*</span></label>
          <div class="pw-wrapper">
            <input type="password" name="new_password" id="pw-new" class="bm-form-control" required placeholder="Min. 6 karakter" oninput="checkStrength(this.value)" style="padding-right:2.5rem">
            <button type="button" class="pw-toggle" onclick="togglePw('pw-new', this)"><i class="bi bi-eye"></i></button>
          </div>
          <!-- Strength indicator -->
          <div class="strength-bar mt-1"><div class="strength-fill" id="strength-fill" style="width:0%;background:#ef4444"></div></div>
          <div id="strength-label" style="font-size:.72rem;color:var(--text-muted);margin-top:.25rem"></div>
        </div>

        <!-- Confirm Password -->
        <div class="mb-0">
          <label class="bm-form-label">Konfirmasi Password Baru <span class="text-danger">*</span></label>
          <div class="pw-wrapper">
            <input type="password" name="confirm_password" id="pw-confirm" class="bm-form-control" required placeholder="Ulangi password baru" oninput="checkMatch()" style="padding-right:2.5rem">
            <button type="button" class="pw-toggle" onclick="togglePw('pw-confirm', this)"><i class="bi bi-eye"></i></button>
          </div>
          <div id="match-label" style="font-size:.72rem;margin-top:.25rem"></div>
        </div>
      </div>
    </div>

    <!-- Tips -->
    <div class="bm-card mb-4">
      <div class="bm-card-body py-3">
        <div style="font-size:.8rem;font-weight:700;color:var(--navy);margin-bottom:.5rem"><i class="bi bi-lightbulb me-1"></i>Tips Password Kuat</div>
        <?php foreach ([
          'Gunakan minimal 8 karakter',
          'Kombinasikan huruf besar, huruf kecil, dan angka',
          'Tambahkan karakter khusus (!@#$%^&*)',
          'Jangan gunakan tanggal lahir atau nama lengkap',
        ] as $tip): ?>
        <div style="font-size:.78rem;color:var(--text-muted);margin-bottom:.2rem"><i class="bi bi-check2 text-success me-1"></i><?= $tip ?></div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="d-grid">
      <button type="submit" class="btn-navy" style="padding:.85rem;border-radius:12px;font-size:1rem">
        <i class="bi bi-shield-check me-2"></i>Perbarui Password
      </button>
    </div>
  </form>
  <?php endif; ?>

  <p class="text-center mt-3" style="font-size:.82rem">
    <a href="dashboard.php" style="color:var(--navy);font-weight:600">← Kembali ke Dashboard</a>
  </p>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= APP_URL ?>/assets/js/global.js"></script>
<script>
function togglePw(id, btn) {
  const input = document.getElementById(id);
  const isText = input.type === 'text';
  input.type = isText ? 'password' : 'text';
  btn.innerHTML = `<i class="bi bi-eye${isText ? '' : '-slash'}"></i>`;
}

function checkStrength(pw) {
  let score = 0;
  if (pw.length >= 6)  score++;
  if (pw.length >= 10) score++;
  if (/[A-Z]/.test(pw)) score++;
  if (/[0-9]/.test(pw)) score++;
  if (/[^A-Za-z0-9]/.test(pw)) score++;

  const fill   = document.getElementById('strength-fill');
  const label  = document.getElementById('strength-label');
  const levels = [
    [0,  '0%',  '#e5e7eb', ''],
    [1,  '25%', '#ef4444', '🔴 Sangat Lemah'],
    [2,  '50%', '#f59e0b', '🟡 Sedang'],
    [3,  '70%', '#3b82f6', '🔵 Cukup Kuat'],
    [4,  '85%', '#10b981', '🟢 Kuat'],
    [5, '100%', '#059669', '🟢 Sangat Kuat'],
  ];
  const [,width,color,text] = levels[Math.min(score, 5)];
  fill.style.width      = width;
  fill.style.background = color;
  label.textContent     = text;
  label.style.color     = color;
}

function checkMatch() {
  const pw  = document.getElementById('pw-new').value;
  const cf  = document.getElementById('pw-confirm').value;
  const lbl = document.getElementById('match-label');
  if (!cf) { lbl.textContent = ''; return; }
  if (pw === cf) { lbl.textContent = '✅ Password cocok'; lbl.style.color = '#059669'; }
  else           { lbl.textContent = '❌ Password tidak cocok'; lbl.style.color = '#ef4444'; }
}
</script>
</body>
</html>
