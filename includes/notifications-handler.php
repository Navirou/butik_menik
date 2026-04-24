<?php
/**
 * includes/notifications-handler.php
 * Reusable notifications fetch + mark-read logic.
 * Include AFTER auth check.
 */

// Mark all as read on visit
if ($_GET['action'] ?? '' === 'mark_read') {
    $mid = (int)($_GET['id'] ?? 0);
    if ($mid) {
        db()->prepare('UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?')
           ->execute([$mid, currentUser()['id']]);
    } else {
        db()->prepare('UPDATE notifications SET is_read=1 WHERE user_id=?')
           ->execute([currentUser()['id']]);
    }
    header('Location: notifications.php?marked=1'); exit;
}

// Fetch
$notifFilter = $_GET['filter'] ?? 'all';
$notifWhere  = $notifFilter === 'unread' ? 'AND is_read=0' : '';
$notifStmt   = db()->prepare(
    "SELECT * FROM notifications WHERE user_id=? $notifWhere ORDER BY created_at DESC LIMIT 50"
);
$notifStmt->execute([currentUser()['id']]);
$notifications = $notifStmt->fetchAll();
$unreadCount   = db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0');
$unreadCount->execute([currentUser()['id']]);
$unreadCount = (int)$unreadCount->fetchColumn();

$notifIcons = [
    'order_update' => ['bi-bag-fill',       '#e8f0fe', '#003366'],
    'payment'      => ['bi-credit-card-fill','#d1fae5', '#065f46'],
    'pickup'       => ['bi-calendar-check-fill','#ede9fe','#5b21b6'],
    'revision'     => ['bi-exclamation-triangle-fill','#fef3c7','#92400e'],
    'stock'        => ['bi-boxes',           '#fee2e2', '#991b1b'],
];
