<?php
// api/notifications.php – returns unread notifications as JSON

require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json');

if (!isLoggedIn()) { echo json_encode([]); exit; }

$stmt = db()->prepare(
    'SELECT id, title, body, type, is_read, DATE_FORMAT(created_at,"%d %b %Y %H:%i") AS created_at
     FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 20'
);
$stmt->execute([currentUser()['id']]);
echo json_encode($stmt->fetchAll());
