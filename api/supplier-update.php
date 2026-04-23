<?php
// api/supplier-update.php

require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json');

if (!isLoggedIn() || currentRole() !== ROLE_SUPPLIER) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit;
}

$data   = json_decode(file_get_contents('php://input'), true);
$id     = (int)($data['id']     ?? 0);
$status = $data['status'] ?? '';

$allowed = ['accepted', 'shipped', 'received', 'cancelled'];
if (!$id || !in_array($status, $allowed)) {
    echo json_encode(['success' => false, 'message' => 'Invalid input']); exit;
}

try {
    $upd = db()->prepare(
        'UPDATE material_requests SET status=?, updated_at=NOW() WHERE id=? AND supplier_id=?'
    );
    $upd->execute([$status, $id, currentUser()['id']]);
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
