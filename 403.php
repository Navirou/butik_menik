<?php require_once __DIR__ . '/config/app.php'; ?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>403 – Akses Ditolak</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body { min-height:100vh; display:flex; align-items:center; justify-content:center; background:#f4f6fb; font-family:'DM Sans',sans-serif; }
.box { text-align:center; padding:3rem; }
.code { font-size:6rem; font-weight:900; color:#003366; line-height:1; }
h2 { color:#374151; }
</style>
</head>
<body>
<div class="box">
  <div class="code">403</div>
  <h2 class="mt-2">Akses Ditolak</h2>
  <p class="text-muted">Kamu tidak memiliki izin untuk mengakses halaman ini.</p>
  <a href="<?= APP_URL ?>/index.php" class="btn mt-3" style="background:#003366;color:#FFFFE0;border-radius:10px;font-weight:600">Kembali ke Beranda</a>
</div>
</body>
</html>
