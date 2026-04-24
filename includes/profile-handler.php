<?php
/**
 * includes/profile-handler.php
 * Reusable profile update + password change logic.
 * Include AFTER auth check. Sets $profileSuccess / $profileError / $passwordSuccess / $passwordError.
 */

$profileSuccess = $profileError = $passwordSuccess = $passwordError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── Update profile info ──────────────────────────────────
    if ($action === 'update_profile') {
        $name  = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));

        if (!$name || !$email) {
            $profileError = 'Nama dan email wajib diisi.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $profileError = 'Format email tidak valid.';
        } else {
            // Check duplicate email (excluding self)
            $chk = db()->prepare('SELECT id FROM users WHERE email=? AND id!=?');
            $chk->execute([$email, currentUser()['id']]);
            if ($chk->fetch()) {
                $profileError = 'Email sudah digunakan oleh akun lain.';
            } else {
                db()->prepare('UPDATE users SET name=?, email=?, phone=? WHERE id=?')
                   ->execute([$name, $email, $phone, currentUser()['id']]);
                // Update session
                $_SESSION['user']['name']  = $name;
                $_SESSION['user']['email'] = $email;
                $profileSuccess = 'Profil berhasil diperbarui.';
            }
        }
    }

    // ── Change password ──────────────────────────────────────
    if ($action === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        $stmt = db()->prepare('SELECT password FROM users WHERE id=?');
        $stmt->execute([currentUser()['id']]);
        $hash = $stmt->fetchColumn();

        if (!password_verify($current, $hash)) {
            $passwordError = 'Password saat ini tidak sesuai.';
        } elseif (strlen($new) < 6) {
            $passwordError = 'Password baru minimal 6 karakter.';
        } elseif ($new !== $confirm) {
            $passwordError = 'Konfirmasi password tidak cocok.';
        } else {
            db()->prepare('UPDATE users SET password=? WHERE id=?')
               ->execute([password_hash($new, PASSWORD_BCRYPT), currentUser()['id']]);
            $passwordSuccess = 'Password berhasil diperbarui.';
        }
    }
}

// Reload fresh user data
$freshUser = db()->prepare('SELECT * FROM users WHERE id=?');
$freshUser->execute([currentUser()['id']]);
$freshUser = $freshUser->fetch();
