<?php
// login.php  –  Unified login + role-based redirect

require_once __DIR__ . '/includes/auth.php';

// Already logged in → redirect
if (isLoggedIn()) {
    header('Location: ' . getDashboardUrl(currentRole()));
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = $_POST['email']    ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Email dan password wajib diisi.';
    } else {
        $result = attemptLogin($email, $password);
        if ($result['success']) {
            header('Location: ' . getDashboardUrl($result['role_id']));
            exit;
        }
        $error = $result['message'];
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login – <?= APP_NAME ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
  :root {
    --navy:  #003366;
    --ivory: #FFFFE0;
    --gold:  #C9A84C;
  }
  body {
    min-height: 100vh;
    background: linear-gradient(135deg, var(--navy) 0%, #001f40 60%, #002850 100%);
    display: flex; align-items: center; justify-content: center;
    font-family: 'DM Sans', sans-serif;
  }
  .login-card {
    background: #fff;
    border-radius: 20px;
    overflow: hidden;
    width: 100%;
    max-width: 440px;
    box-shadow: 0 30px 80px rgba(0,0,0,.35);
  }
  .login-header {
    background: var(--navy);
    padding: 2.5rem 2rem 2rem;
    text-align: center;
  }
  .login-header .brand {
    font-family: 'Playfair Display', serif;
    color: var(--ivory);
    font-size: 1.6rem;
    letter-spacing: .5px;
  }
  .login-header .tagline {
    color: rgba(255,255,224,.65);
    font-size: .8rem;
    margin-top: .25rem;
    letter-spacing: 2px;
    text-transform: uppercase;
  }
  .login-body { padding: 2rem; }
  .form-label { font-size: .85rem; font-weight: 500; color: #444; }
  .form-control {
    border-radius: 10px; border-color: #ddd; padding: .7rem 1rem;
    font-size: .9rem;
  }
  .form-control:focus { border-color: var(--navy); box-shadow: 0 0 0 3px rgba(0,51,102,.12); }
  .btn-login {
    background: var(--navy); color: var(--ivory);
    border: none; border-radius: 10px;
    padding: .8rem; font-weight: 600; letter-spacing: .5px;
    width: 100%; font-size: 1rem;
    transition: background .2s, transform .1s;
  }
  .btn-login:hover { background: #004080; color: var(--ivory); transform: translateY(-1px); }
  .divider { color: #aaa; font-size: .8rem; }
  .register-link { color: var(--navy); font-weight: 500; text-decoration: none; }
  .register-link:hover { text-decoration: underline; }
  .alert-danger { border-radius: 10px; font-size: .85rem; }
</style>
</head>
<body>
<div class="login-card">
  <div class="login-header">
    <div class="brand"><i class="bi bi-scissors me-2"></i><?= APP_NAME ?></div>
    <div class="tagline">Sistem Informasi Butik</div>
  </div>
  <div class="login-body">
    <?php if ($error): ?>
    <div class="alert alert-danger py-2"><i class="bi bi-exclamation-circle me-1"></i><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="POST" novalidate>
      <div class="mb-3">
        <label class="form-label">Alamat Email</label>
        <input type="email" name="email" class="form-control" placeholder="nama@email.com"
               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required autofocus>
      </div>
      <div class="mb-4">
        <label class="form-label">Password</label>
        <input type="password" name="password" class="form-control" placeholder="••••••••" required>
      </div>
      <button type="submit" class="btn-login">Masuk ke Sistem</button>
    </form>
    <div class="text-center mt-3 divider">Belum punya akun?
      <a href="register.php" class="register-link">Daftar di sini</a>
    </div>
    <div class="text-center mt-1 divider">
      <a href="forgot-password.php" class="register-link" style="font-size:.82rem;">Lupa password?</a>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
