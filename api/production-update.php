<?php
// api/production-update.php

require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json');

if (!isLoggedIn() || !in_array(currentRole(), [ROLE_STAFF, ROLE_OWNER])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$orderId  = (int)($data['order_id'] ?? 0);
$stageId  = (int)($data['stage_id'] ?? 0);
$progress = min(100, max(0, (int)($data['progress'] ?? 0)));
$notes    = $data['notes'] ?? null;

if (!$orderId || !$stageId) {
    echo json_encode(['success' => false, 'message' => 'Parameter tidak lengkap']); exit;
}

try {
    db()->beginTransaction();
    // Insert log
    $ins = db()->prepare(
        'INSERT INTO production_logs (order_id, stage_id, updated_by, progress, notes) VALUES (?,?,?,?,?)'
    );
    $ins->execute([$orderId, $stageId, currentUser()['id'], $progress, $notes]);

    // Update order status based on stage
    $stageToStatus = [
        2 => 'pemeriksaan_stok',
        3 => 'produksi', 4 => 'produksi', 5 => 'produksi',
        6 => 'qc',
        7 => 'jadwal_ambil',
    ];
    if (isset($stageToStatus[$stageId])) {
        $upd = db()->prepare('UPDATE orders SET status=? WHERE id=?');
        $upd->execute([$stageToStatus[$stageId], $orderId]);
    }

    // Notify customer
    $custStmt = db()->prepare('SELECT customer_id FROM orders WHERE id=?');
    $custStmt->execute([$orderId]);
    $customerId = $custStmt->fetchColumn();
    if ($customerId) {
        $stageLabel = ['','DP Diverifikasi','Pemeriksaan Stok','Pemotongan','Penjahitan','Finishing','QC','Selesai'][$stageId] ?? 'Diperbarui';
        $notif = db()->prepare(
            'INSERT INTO notifications (user_id, title, body, type, ref_id) VALUES (?,?,?,?,?)'
        );
        $notif->execute([$customerId, "Produksi Diperbarui", "Pesanan Anda sekarang dalam tahap: $stageLabel ($progress%)", 'order_update', $orderId]);
    }

    db()->commit();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    db()->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
