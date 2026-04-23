<?php
require_once __DIR__ . '/includes/auth.php';
if (isLoggedIn()) {
    header('Location: ' . getDashboardUrl(currentRole()));
} else {
    header('Location: ' . APP_URL . '/login.php');
}
exit;
