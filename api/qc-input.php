<?php
// api/qc-input.php
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json');

if (!isLoggedIn() || !in_array(currentRole(), [ROLE_STAFF, ROLE_OWNER])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit;
}

$data    = json_decode(file_get_contents('php://input'), true);
$orderId = (int)($data['order_id'] ?? 0);
$passed  = (int)($data['passed'] ?? 0);
$notes   = $data['notes'] ?? '';

if (!$orderId) { echo json_encode(['success'=>false,'message'=>'Invalid order']); exit; }

try {
    db()->beginTransaction();

    // Insert or update QC result
    $chk = db()->prepare("SELECT id FROM qc_results WHERE order_id=?");
    $chk->execute([$orderId]);
    if ($chk->fetchColumn()) {
        db()->prepare("UPDATE qc_results SET passed=?, notes=?, checked_by=?, checked_at=NOW() WHERE order_id=?")
           ->execute([$passed, $notes, currentUser()['id'], $orderId]);
    } else {
        db()->prepare("INSERT INTO qc_results (order_id, checked_by, passed, notes) VALUES(?,?,?,?)")
           ->execute([$orderId, currentUser()['id'], $passed, $notes]);
    }

    // Update order status
    $newStatus = $passed ? 'jadwal_ambil' : 'produksi'; // failed QC → back to production
    db()->prepare("UPDATE orders SET status=? WHERE id=?")->execute([$newStatus, $orderId]);

    // Log production stage
    $stageId = $passed ? 7 : 4; // done or back to sewing
    db()->prepare("INSERT INTO production_logs(order_id,stage_id,updated_by,progress,notes) VALUES(?,?,?,?,?)")
       ->execute([$orderId, $stageId, currentUser()['id'], $passed ? 100 : 60, $notes]);

    // Notify customer
    $cid = db()->prepare("SELECT customer_id FROM orders WHERE id=?");
    $cid->execute([$orderId]);
    $custId = $cid->fetchColumn();
    if ($custId) {
        $msg = $passed
            ? 'Pesanan kamu telah lulus QC dan siap untuk dijadwalkan pengambilan!'
            : 'Pesanan kamu perlu perbaikan berdasarkan hasil QC. Sedang diproses ulang.';
        db()->prepare("INSERT INTO notifications(user_id,title,body,type,ref_id) VALUES(?,?,?,?,?)")
           ->execute([$custId, 'Update Quality Control', $msg, 'order_update', $orderId]);
    }

    db()->commit();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    db()->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
