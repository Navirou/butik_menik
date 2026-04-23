<?php
require_once __DIR__ . '/includes/auth.php';
if (isLoggedIn()) { header('Location: ' . getDashboardUrl(currentRole())); exit; }

$success = $error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name']     ?? '');
    $email    = trim($_POST['email']    ?? '');
    $phone    = trim($_POST['phone']    ?? '');
    $password = $_POST['password']      ?? '';
    $confirm  = $_POST['confirm']       ?? '';

    if (!$name || !$email || !$password) {
        $error = 'Semua kolom wajib diisi.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Format email tidak valid.';
    } elseif (strlen($password) < 6) {
        $error = 'Password minimal 6 karakter.';
    } elseif ($password !== $confirm) {
        $error = 'Konfirmasi password tidak cocok.';
    } else {
        $res = registerCustomer($name, $email, $phone, $password);
        if ($res['success']) { $success = $res['message']; }
        else                  { $error   = $res['message']; }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Daftar – <?= APP_NAME ?></title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
  :root { --navy:#003366; --ivory:#FFFFE0; }
  body { min-height:100vh; background:linear-gradient(135deg,var(--navy) 0%,#001f40 100%);
         display:flex;align-items:center;justify-content:center; font-family:'DM Sans',sans-serif; }
  .card { border-radius:20px;overflow:hidden;max-width:460px;width:100%;border:none;
          box-shadow:0 30px 80px rgba(0,0,0,.35); }
  .card-header { background:var(--navy);padding:2rem;text-align:center; }
  .brand { font-family:'Playfair Display',serif;color:var(--ivory);font-size:1.5rem; }
  .tagline { color:rgba(255,255,224,.6);font-size:.75rem;letter-spacing:2px;text-transform:uppercase; }
  .card-body { padding:2rem; }
  .form-control { border-radius:10px;font-size:.9rem; }
  .form-control:focus { border-color:var(--navy);box-shadow:0 0 0 3px rgba(0,51,102,.12); }
  .btn-primary { background:var(--navy);border-color:var(--navy);border-radius:10px;font-weight:600; }
  .btn-primary:hover { background:#004080;border-color:#004080; }
</style>
</head>
<body>
<div class="card">
  <div class="card-header">
    <div class="brand"><i class="bi bi-scissors me-2"></i><?= APP_NAME ?></div>
    <div class="tagline">Buat Akun Pelanggan</div>
  </div>
  <div class="card-body">
    <?php if ($success): ?>
      <div class="alert alert-success"><?= htmlspecialchars($success) ?> <a href="login.php">Login sekarang</a></div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="alert alert-danger py-2"><i class="bi bi-exclamation-circle me-1"></i><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="POST" novalidate>
      <div class="mb-3"><label class="form-label fw-medium" style="font-size:.85rem">Nama Lengkap</label>
        <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($_POST['name']??'') ?>" required></div>
      <div class="mb-3"><label class="form-label fw-medium" style="font-size:.85rem">Email</label>
        <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($_POST['email']??'') ?>" required></div>
      <div class="mb-3"><label class="form-label fw-medium" style="font-size:.85rem">No. Telepon</label>
        <input type="tel" name="phone" class="form-control" value="<?= htmlspecialchars($_POST['phone']??'') ?>"></div>
      <div class="mb-3"><label class="form-label fw-medium" style="font-size:.85rem">Password</label>
        <input type="password" name="password" class="form-control" required></div>
      <div class="mb-4"><label class="form-label fw-medium" style="font-size:.85rem">Konfirmasi Password</label>
        <input type="password" name="confirm" class="form-control" required></div>
      <button type="submit" class="btn btn-primary w-100 py-2">Buat Akun</button>
    </form>
    <p class="text-center mt-3 mb-0" style="font-size:.85rem">Sudah punya akun? <a href="login.php" style="color:var(--navy);font-weight:500">Login di sini</a></p>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
