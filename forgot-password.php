<?php
require_once __DIR__ . '/includes/auth.php';
if (isLoggedIn()) { header('Location: '.getDashboardUrl(currentRole())); exit; }

$success = $error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim($_POST['email'] ?? ''));
    $stmt  = db()->prepare("SELECT id FROM users WHERE email=? AND is_active=1");
    $stmt->execute([$email]);
    $uid = $stmt->fetchColumn();
    // Always show success to prevent enumeration
    if ($uid) {
        $token = bin2hex(random_bytes(32));
        db()->prepare("INSERT INTO password_resets(user_id,token,expires_at) VALUES(?,?,DATE_ADD(NOW(),INTERVAL 1 HOUR))")
           ->execute([$uid,$token]);
        // In production: send email with reset link APP_URL/reset-password.php?token=$token
    }
    $success = 'Jika email terdaftar, link reset password telah dikirim. Periksa inbox kamu.';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Lupa Password – <?= APP_NAME ?></title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
:root{--navy:#003366;--ivory:#FFFFE0;}
body{min-height:100vh;background:linear-gradient(135deg,var(--navy) 0%,#001f40 100%);display:flex;align-items:center;justify-content:center;font-family:'DM Sans',sans-serif;}
.card{border-radius:20px;overflow:hidden;max-width:420px;width:100%;border:none;box-shadow:0 30px 80px rgba(0,0,0,.35);}
.card-header{background:var(--navy);padding:2rem;text-align:center;}
.brand{font-family:'Playfair Display',serif;color:var(--ivory);font-size:1.4rem;}
.card-body{padding:2rem;}
.form-control{border-radius:10px;font-size:.9rem;}
.form-control:focus{border-color:var(--navy);box-shadow:0 0 0 3px rgba(0,51,102,.12);}
.btn-primary{background:var(--navy);border-color:var(--navy);border-radius:10px;font-weight:600;}
</style>
</head>
<body>
<div class="card">
  <div class="card-header">
    <div class="brand"><i class="bi bi-key me-2"></i>Lupa Password</div>
    <div style="color:rgba(255,255,224,.55);font-size:.78rem;margin-top:.25rem">Masukkan email untuk reset password</div>
  </div>
  <div class="card-body">
    <?php if ($success): ?><div class="alert alert-success" style="border-radius:10px;font-size:.85rem"><?= htmlspecialchars($success) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger" style="border-radius:10px;font-size:.85rem"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <form method="POST">
      <div class="mb-4">
        <label class="form-label fw-600" style="font-size:.85rem">Alamat Email</label>
        <input type="email" name="email" class="form-control" placeholder="nama@email.com" required>
      </div>
      <button type="submit" class="btn btn-primary w-100 py-2">Kirim Link Reset</button>
    </form>
    <p class="text-center mt-3 mb-0" style="font-size:.85rem"><a href="login.php" style="color:var(--navy);font-weight:600">← Kembali ke Login</a></p>
  </div>
</div>
</body>
</html>
