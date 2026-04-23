<?php
// includes/auth.php

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';

session_name(SESSION_NAME);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ── Helpers ──────────────────────────────────────────────────────────────────

function isLoggedIn(): bool {
    return isset($_SESSION['user_id'], $_SESSION['role_id']);
}

function currentUser(): ?array {
    return $_SESSION['user'] ?? null;
}

function currentRole(): int {
    return (int)($_SESSION['role_id'] ?? 0);
}

/**
 * Require login + optional role check.
 * Redirects to login page on failure.
 */
function requireLogin(array $allowedRoles = []): void {
    if (!isLoggedIn()) {
        header('Location: ' . APP_URL . '/login.php');
        exit;
    }
    if ($allowedRoles && !in_array(currentRole(), $allowedRoles, true)) {
        header('Location: ' . APP_URL . '/403.php');
        exit;
    }
}

function getDashboardUrl(int $roleId): string {
    $map = ROLE_REDIRECT;
    return APP_URL . ($map[$roleId] ?? '/login.php');
}

// ── Login / Logout ────────────────────────────────────────────────────────────

function attemptLogin(string $email, string $password): array {
    $stmt = db()->prepare('SELECT * FROM users WHERE email = ? AND is_active = 1 LIMIT 1');
    $stmt->execute([strtolower(trim($email))]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password'])) {
        return ['success' => false, 'message' => 'Email atau password salah.'];
    }

    // Regenerate session ID to prevent fixation
    session_regenerate_id(true);

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['role_id'] = $user['role_id'];
    $_SESSION['user']    = [
        'id'     => $user['id'],
        'name'   => $user['name'],
        'email'  => $user['email'],
        'avatar' => $user['avatar'],
        'role'   => $user['role_id'],
    ];

    return ['success' => true, 'role_id' => (int)$user['role_id']];
}

function logout(): void {
    $_SESSION = [];
    session_destroy();
    header('Location: ' . APP_URL . '/login.php');
    exit;
}

// ── Registration ──────────────────────────────────────────────────────────────

function registerCustomer(string $name, string $email, string $phone, string $password): array {
    $email = strtolower(trim($email));

    // Duplicate check
    $chk = db()->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $chk->execute([$email]);
    if ($chk->fetch()) {
        return ['success' => false, 'message' => 'Email sudah terdaftar.'];
    }

    $hash = password_hash($password, PASSWORD_BCRYPT);
    $ins  = db()->prepare(
        'INSERT INTO users (role_id, name, email, phone, password) VALUES (?, ?, ?, ?, ?)'
    );
    $ins->execute([ROLE_CUSTOMER, $name, $email, $phone, $hash]);

    return ['success' => true, 'message' => 'Registrasi berhasil. Silakan login.'];
}
