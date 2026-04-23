<?php
require_once __DIR__ . '/../../includes/auth.php';
requireLogin([ROLE_CUSTOMER]);

$user = currentUser();
$db   = db();

$success = $error = '';
$productId = (int)($_GET['product_id'] ?? 0);
$product   = null;
if ($productId) {
    $pStmt = $db->prepare("SELECT p.*, pc.name AS cat FROM products p LEFT JOIN product_categories pc ON p.category_id=pc.id WHERE p.id=? AND p.is_active=1");
    $pStmt->execute([$productId]); $product = $pStmt->fetch();
}
$products = $db->query("SELECT p.*, pc.name AS cat FROM products p LEFT JOIN product_categories pc ON p.category_id=pc.id WHERE p.is_active=1 ORDER BY p.id DESC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pid   = (int)$_POST['product_id'];
    $size  = trim($_POST['size'] ?? '');
    $fab   = trim($_POST['fabric_type'] ?? '');
    $color = trim($_POST['color'] ?? '');
    $desc  = trim($_POST['model_description'] ?? '');
    $qty   = max(1,(int)$_POST['qty']);
    $price = (float)$_POST['total_price'];

    if (!$pid || !$size || !$color || $price <= 0) {
        $error = 'Lengkapi semua kolom wajib.';
    } else {
        // Upload design
        $designFile = null;
        if (!empty($_FILES['design_file']['name'])) {
            $ext  = strtolower(pathinfo($_FILES['design_file']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg','jpeg','png','pdf'];
            if (in_array($ext, $allowed) && $_FILES['design_file']['size'] < 5*1024*1024) {
                $designFile = uniqid('dsn_').'.'.$ext;
                move_uploaded_file($_FILES['design_file']['tmp_name'], UPLOAD_PATH . 'designs/' . $designFile);
            } else { $error = 'File desain harus JPG/PNG/PDF maks 5MB.'; }
        }

        if (!$error) {
            $code   = 'BSN' . str_pad($user['id'],3,'0',STR_PAD_LEFT) . date('md') . rand(10,99);
            $dp     = round($price * 0.5);
            $est    = date('Y-m-d', strtotime('+14 days'));

            $ins = $db->prepare(
                "INSERT INTO orders (order_code,customer_id,product_id,size,fabric_type,color,model_description,design_file,qty,total_price,dp_amount,estimated_done,status)
                 VALUES(?,?,?,?,?,?,?,?,?,?,?,'$est','dp_menunggu')"
            );
            $ins->execute([$code,$user['id'],$pid,$size,$fab,$color,$desc,$designFile,$qty,$price,$dp]);
            $oid = $db->lastInsertId();

            // Notify owner
            $ownerStmt = $db->query("SELECT id FROM users WHERE role_id=".ROLE_OWNER." LIMIT 1");
            $ownerId = $ownerStmt->fetchColumn();
            if ($ownerId) $db->prepare("INSERT INTO notifications(user_id,title,body,type,ref_id) VALUES(?,?,?,?,?)")
                ->execute([$ownerId,'Pesanan Baru Masuk',"Pesanan $code dari {$user['name']} menunggu konfirmasi.",'order_update',$oid]);

            header("Location: payment.php?code=$code&new=1"); exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Buat Pesanan – <?= APP_NAME ?></title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="<?= APP_URL ?>/assets/css/global.css" rel="stylesheet">
<style>
body { background:var(--surface); padding-bottom:2rem; }
.top-bar { background:var(--navy); padding:.9rem 1.25rem; display:flex; align-items:center; gap:1rem; }
.top-bar .brand { font-family:var(--ff-display); color:var(--ivory); font-size:1.1rem; flex:1; }
.page-wrap { max-width:780px; margin:0 auto; padding:1.5rem; }
.step-header { background:var(--navy); border-radius:14px; padding:1.5rem; color:var(--ivory); margin-bottom:1.5rem; }
.product-option { border:2px solid var(--border); border-radius:12px; padding:1rem; cursor:pointer; transition:all .15s; }
.product-option:hover { border-color:var(--navy); background:var(--ivory); }
.product-option.selected { border-color:var(--navy); background:var(--ivory); }
.product-option input[type=radio] { display:none; }
</style>
</head>
<body>
<div class="top-bar">
  <a href="dashboard.php" style="color:rgba(255,255,224,.7);text-decoration:none;font-size:1.2rem"><i class="bi bi-arrow-left"></i></a>
  <span class="brand"><i class="bi bi-scissors me-2"></i>Buat Pesanan Baru</span>
</div>

<div class="page-wrap">
  <div class="step-header">
    <div style="font-family:var(--ff-display);font-size:1.3rem;margin-bottom:.25rem">Form Pemesanan</div>
    <p style="color:rgba(255,255,224,.7);font-size:.85rem;margin:0">Isi detail pesanan kamu. DP 50% diperlukan untuk memulai proses produksi.</p>
  </div>

  <?php if ($error): ?>
  <div class="bm-alert bm-alert-danger mb-3"><i class="bi bi-exclamation-circle-fill"></i><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="POST" enctype="multipart/form-data">
    <!-- Step 1: Pilih Produk -->
    <div class="bm-card mb-3">
      <div class="bm-card-header"><span class="bm-card-title"><i class="bi bi-grid me-2"></i>1. Pilih Produk</span></div>
      <div class="bm-card-body">
        <div class="row g-2">
        <?php foreach ($products as $p): $sel = $p['id'] == $productId; ?>
          <div class="col-sm-6">
            <label class="product-option d-flex gap-3 <?= $sel ? 'selected' : '' ?>" onclick="selectProduct(<?= $p['id'] ?>, <?= $p['base_price'] ?>)">
              <input type="radio" name="product_id" value="<?= $p['id'] ?>" <?= $sel?'checked':'' ?>>
              <div style="width:50px;height:50px;border-radius:10px;background:var(--ivory);display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:1.5rem">👗</div>
              <div>
                <div style="font-weight:700;font-size:.9rem;color:var(--navy)"><?= htmlspecialchars($p['name']) ?></div>
                <div style="font-size:.75rem;color:var(--text-muted)"><?= htmlspecialchars($p['cat'] ?? '') ?></div>
                <div style="font-size:.88rem;font-weight:700;color:var(--navy);margin-top:3px">Rp <?= number_format($p['base_price'],0,',','.') ?><?= $p['is_custom']?' / mulai dari':'' ?></div>
              </div>
            </label>
          </div>
        <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- Step 2: Detail Pesanan -->
    <div class="bm-card mb-3">
      <div class="bm-card-header"><span class="bm-card-title"><i class="bi bi-clipboard me-2"></i>2. Detail Pesanan</span></div>
      <div class="bm-card-body">
        <div class="row g-3">
          <div class="col-sm-4">
            <label class="bm-form-label">Ukuran <span class="text-danger">*</span></label>
            <select name="size" class="bm-form-control" required>
              <option value="">Pilih ukuran</option>
              <?php foreach (['XS','S','M','L','XL','XXL','3XL','Custom'] as $sz): ?>
              <option value="<?= $sz ?>"><?= $sz ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-sm-4">
            <label class="bm-form-label">Warna <span class="text-danger">*</span></label>
            <input type="text" name="color" class="bm-form-control" placeholder="Misal: Biru Navy" required value="<?= htmlspecialchars($_POST['color']??'') ?>">
          </div>
          <div class="col-sm-4">
            <label class="bm-form-label">Qty</label>
            <input type="number" name="qty" class="bm-form-control" min="1" value="<?= (int)($_POST['qty']??1) ?>" oninput="recalcTotal()">
          </div>
          <div class="col-12">
            <label class="bm-form-label">Jenis Bahan</label>
            <input type="text" name="fabric_type" class="bm-form-control" placeholder="Misal: Katun, Batik, Sutra..." value="<?= htmlspecialchars($_POST['fabric_type']??'') ?>">
          </div>
          <div class="col-12">
            <label class="bm-form-label">Deskripsi Model / Keinginan</label>
            <textarea name="model_description" class="bm-form-control" rows="3" placeholder="Jelaskan detail model yang diinginkan..."><?= htmlspecialchars($_POST['model_description']??'') ?></textarea>
          </div>
        </div>
      </div>
    </div>

    <!-- Step 3: Upload Desain -->
    <div class="bm-card mb-3">
      <div class="bm-card-header"><span class="bm-card-title"><i class="bi bi-image me-2"></i>3. Upload Desain (Opsional)</span></div>
      <div class="bm-card-body">
        <div class="bm-upload-zone">
          <i class="bi bi-cloud-upload"></i>
          <p>Tap untuk upload file desain</p>
          <small>Format: JPG, PNG, PDF · Maks 5MB</small>
          <div class="bm-upload-filename mt-2" style="font-size:.82rem;color:var(--navy);font-weight:600"></div>
          <input type="file" name="design_file" class="bm-file-input" accept=".jpg,.jpeg,.png,.pdf" style="display:none">
        </div>
      </div>
    </div>

    <!-- Step 4: Total & Harga -->
    <div class="bm-card mb-4">
      <div class="bm-card-header"><span class="bm-card-title"><i class="bi bi-receipt me-2"></i>4. Ringkasan Harga</span></div>
      <div class="bm-card-body">
        <input type="hidden" name="total_price" id="total-price-input" value="<?= $product ? $product['base_price'] : 0 ?>">
        <div class="d-flex justify-content-between mb-2" style="font-size:.9rem">
          <span>Harga Produk</span>
          <span id="display-base-price">Rp 0</span>
        </div>
        <div class="d-flex justify-content-between mb-2" style="font-size:.9rem">
          <span>Qty</span>
          <span id="display-qty">1</span>
        </div>
        <div class="bm-divider"></div>
        <div class="d-flex justify-content-between mb-2" style="font-weight:700;font-size:1rem;color:var(--navy)">
          <span>Total</span>
          <span id="display-total">Rp 0</span>
        </div>
        <div class="d-flex justify-content-between" style="font-size:.85rem;color:var(--text-muted)">
          <span>DP 50% (dibayar sekarang)</span>
          <span id="display-dp" style="color:var(--navy);font-weight:600">Rp 0</span>
        </div>
      </div>
    </div>

    <button type="submit" class="btn-navy w-100 py-3" style="font-size:1rem;border-radius:12px">
      <i class="bi bi-bag-check me-2"></i>Buat Pesanan & Lanjut Bayar DP
    </button>
  </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= APP_URL ?>/assets/js/global.js"></script>
<script>
let basePrice = <?= $product ? $product['base_price'] : 0 ?>;

const prices = {
  <?php foreach ($products as $p): ?>
  <?= $p['id'] ?>: <?= $p['base_price'] ?>,
  <?php endforeach; ?>
};

function selectProduct(id, price) {
  basePrice = price;
  document.querySelectorAll('.product-option').forEach(el => el.classList.remove('selected'));
  event.currentTarget.classList.add('selected');
  recalcTotal();
}

function recalcTotal() {
  const qty   = parseInt(document.querySelector('input[name=qty]').value) || 1;
  const total = basePrice * qty;
  const dp    = Math.round(total * 0.5);
  document.getElementById('display-base-price').textContent = 'Rp ' + basePrice.toLocaleString('id-ID');
  document.getElementById('display-qty').textContent = qty + ' pcs';
  document.getElementById('display-total').textContent = 'Rp ' + total.toLocaleString('id-ID');
  document.getElementById('display-dp').textContent = 'Rp ' + dp.toLocaleString('id-ID');
  document.getElementById('total-price-input').value = total;
}

recalcTotal();
</script>
</body>
</html>
