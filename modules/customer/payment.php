<?php
require_once __DIR__ . '/../../includes/auth.php';
requireLogin([ROLE_CUSTOMER]);

$user = currentUser();
$db   = db();
$code = trim($_GET['code'] ?? '');
$isNew = isset($_GET['new']);

$order = null;
if ($code) {
    $stmt = $db->prepare(
        "SELECT o.*, p.name AS product_name FROM orders o
         LEFT JOIN products p ON o.product_id=p.id
         WHERE o.order_code=? AND o.customer_id=?"
    );
    $stmt->execute([$code, $user['id']]);
    $order = $stmt->fetch();
}

if (!$order) { header('Location: dashboard.php'); exit; }

$success = $error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $method = trim($_POST['method'] ?? '');
    $notes  = trim($_POST['notes'] ?? '');
    $type   = in_array($order['status'],['dp_menunggu','dikonfirmasi']) ? 'dp' : 'pelunasan';

    if (!$method) { $error = 'Pilih metode pembayaran.'; }
    elseif (empty($_FILES['proof']['name'])) { $error = 'Upload bukti transfer.'; }
    else {
        $ext = strtolower(pathinfo($_FILES['proof']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext,['jpg','jpeg','png','pdf'])) { $error = 'Format file tidak valid.'; }
        elseif ($_FILES['proof']['size'] > 5*1024*1024) { $error = 'Ukuran file maks 5MB.'; }
        else {
            $filename = uniqid('pay_').'.'.$ext;
            move_uploaded_file($_FILES['proof']['tmp_name'], UPLOAD_PATH.'payments/'.$filename);
            $amount = $type === 'dp' ? $order['dp_amount'] : ($order['total_price'] - $order['dp_amount']);
            $ins = $db->prepare("INSERT INTO payments(order_id,type,amount,method,proof_file,notes) VALUES(?,?,?,?,?,?)");
            $ins->execute([$order['id'],$type,$amount,$method,$filename,$notes]);
            // Notify owner
            $ownerId = $db->query("SELECT id FROM users WHERE role_id=".ROLE_OWNER." LIMIT 1")->fetchColumn();
            if ($ownerId) $db->prepare("INSERT INTO notifications(user_id,title,body,type,ref_id) VALUES(?,?,?,?,?)")
                ->execute([$ownerId,'Bukti Pembayaran Baru',"Bukti ".strtoupper($type)." dari {$user['name']} untuk pesanan $code menunggu verifikasi.",'payment',$order['id']]);
            $success = 'Bukti pembayaran berhasil dikirim! Menunggu verifikasi dari admin.';
        }
    }
}

$dpAmount    = $order['dp_amount'];
$totalAmount = $order['total_price'];
$remaining   = $totalAmount - $dpAmount;
$isPelunasan = !in_array($order['status'],['dp_menunggu','dikonfirmasi']);
$payAmount   = $isPelunasan ? $remaining : $dpAmount;
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Pembayaran <?= $isPelunasan?'Pelunasan':'DP' ?> – <?= APP_NAME ?></title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="<?= APP_URL ?>/assets/css/global.css" rel="stylesheet">
<style>
body { background:var(--surface); padding-bottom:2rem; }
.top-bar { background:var(--navy); padding:.9rem 1.25rem; display:flex; align-items:center; gap:1rem; }
.brand { font-family:var(--ff-display); color:var(--ivory); font-size:1.1rem; flex:1; }
.page-wrap { max-width:540px; margin:0 auto; padding:1.25rem; }
.amount-hero { background:linear-gradient(135deg,var(--navy),var(--navy-mid)); border-radius:16px; padding:1.75rem; color:var(--ivory); text-align:center; margin-bottom:1.25rem; }
.amount-hero .lbl { color:rgba(255,255,224,.65); font-size:.82rem; text-transform:uppercase; letter-spacing:1px; }
.amount-hero .val { font-family:var(--ff-display); font-size:2.25rem; font-weight:700; margin:.2rem 0 .1rem; }
.bank-card { background:var(--white); border:1px solid var(--border); border-radius:12px; padding:1rem 1.1rem; display:flex; align-items:center; gap:.85rem; margin-bottom:1rem; }
.bank-icon { width:42px; height:42px; border-radius:10px; background:#e8f0fe; display:flex; align-items:center; justify-content:center; font-size:1.2rem; color:var(--navy); flex-shrink:0; }
.bank-info .name { font-weight:700; font-size:.95rem; color:var(--navy); }
.bank-info .acc  { font-size:.82rem; color:var(--text-muted); }
.copy-btn { margin-left:auto; background:var(--ivory); border:1px solid var(--border); border-radius:8px; padding:.35rem .65rem; font-size:.78rem; font-weight:600; color:var(--navy); cursor:pointer; }
.copy-btn:hover { background:var(--ivory-dim); }
.method-select option { padding:.5rem; }
.secure-note { text-align:center; color:var(--text-muted); font-size:.75rem; margin-top:.75rem; }
</style>
</head>
<body>
<div class="top-bar">
  <a href="dashboard.php" style="color:rgba(255,255,224,.7);text-decoration:none;font-size:1.2rem"><i class="bi bi-arrow-left"></i></a>
  <span class="brand">Pembayaran <?= $isPelunasan ? 'Pelunasan' : 'DP' ?></span>
</div>

<div class="page-wrap">
  <?php if ($isNew): ?>
  <div class="bm-alert bm-alert-success mb-3 bm-alert-auto-dismiss">
    <i class="bi bi-check-circle-fill"></i>Pesanan berhasil dibuat! Silakan lanjut bayar DP untuk memulai proses.
  </div>
  <?php endif; ?>
  <?php if ($success): ?>
  <div class="bm-alert bm-alert-success mb-3">
    <i class="bi bi-check-circle-fill"></i><?= htmlspecialchars($success) ?>
    <div class="mt-2"><a href="dashboard.php" class="btn-navy" style="font-size:.82rem;padding:.35rem .75rem">Kembali ke Dashboard</a></div>
  </div>
  <?php endif; ?>
  <?php if ($error): ?>
  <div class="bm-alert bm-alert-danger mb-3"><i class="bi bi-exclamation-circle-fill"></i><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <?php if (!$success): ?>
  <!-- Amount Hero -->
  <div class="amount-hero">
    <div class="lbl">Total <?= $isPelunasan ? 'Pelunasan' : 'DP (50%)' ?> yang harus dibayar</div>
    <div class="val">Rp <?= number_format($payAmount, 0, ',', '.') ?></div>
    <div style="font-size:.78rem;color:rgba(255,255,224,.55)">Pesanan: <?= htmlspecialchars($code) ?> · <?= htmlspecialchars($order['product_name'] ?? 'Custom') ?></div>
  </div>

  <!-- Bank Info -->
  <div class="bank-card">
    <div class="bank-icon"><i class="bi bi-building-fill"></i></div>
    <div class="bank-info">
      <div class="name">Bank BCA</div>
      <div class="acc" id="rekening">No. Rekening: <strong>1234567890</strong></div>
      <div class="acc">a.n. Butik Menik Modeste</div>
    </div>
    <button class="copy-btn" onclick="copyRek()"><i class="bi bi-copy me-1"></i>Salin</button>
  </div>

  <form method="POST" enctype="multipart/form-data">
    <div class="bm-card mb-3">
      <div class="bm-card-body">
        <!-- Payment Method -->
        <div class="mb-3">
          <label class="bm-form-label">Metode Pembayaran</label>
          <select name="method" class="bm-form-control" required>
            <option value="">Pilih metode pembayaran</option>
            <option value="Bank BCA">Bank BCA</option>
            <option value="Bank BRI">Bank BRI</option>
            <option value="Bank Mandiri">Bank Mandiri</option>
            <option value="GoPay">GoPay</option>
            <option value="OVO">OVO</option>
            <option value="Dana">Dana</option>
            <option value="ShopeePay">ShopeePay</option>
          </select>
        </div>

        <!-- Upload Proof -->
        <div class="mb-3">
          <label class="bm-form-label">Bukti Transfer <span class="text-danger">*</span></label>
          <div class="bm-upload-zone">
            <i class="bi bi-cloud-upload"></i>
            <p>Tap untuk upload bukti transfer</p>
            <small>Format: JPG, PNG, PDF · Maks 5MB</small>
            <div class="bm-upload-filename mt-2" style="font-size:.82rem;color:var(--navy);font-weight:600"></div>
            <input type="file" name="proof" class="bm-file-input" accept=".jpg,.jpeg,.png,.pdf" required style="display:none">
          </div>
        </div>

        <!-- Notes -->
        <div class="mb-0">
          <label class="bm-form-label">Catatan <span style="color:var(--text-muted);font-weight:400">(Opsional)</span></label>
          <textarea name="notes" class="bm-form-control" rows="2" placeholder="Tambahkan catatan jika diperlukan..."></textarea>
        </div>
      </div>
    </div>

    <button type="submit" class="btn-navy w-100 py-3" style="font-size:1rem;border-radius:12px">
      <i class="bi bi-shield-check me-2"></i>Konfirmasi Pembayaran
    </button>
    <p class="secure-note"><i class="bi bi-lock-fill me-1"></i>Pembayaran Anda aman dan terenkripsi</p>
  </form>
  <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= APP_URL ?>/assets/js/global.js"></script>
<script>
function copyRek() {
  navigator.clipboard.writeText('1234567890').then(()=>bmToast('Nomor rekening disalin!'));
}
</script>
</body>
</html>
