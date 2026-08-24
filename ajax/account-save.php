<?php
/**
 * AJAX - Admin Account Save
 *
 * Updates the signed-in admin's display name, login email and password.
 * The current password is always required: these fields ARE the login
 * credentials, so someone who walks up to an unlocked browser must not
 * be able to take the account over silently.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') sendJsonResponse(false, 'Invalid request method.', [], 405);
if (!isLoggedIn()) sendJsonResponse(false, 'Unauthorized.', [], 403);
if (!validateCSRF()) sendJsonResponse(false, 'Invalid security token.', [], 403);

const MIN_PASSWORD_LENGTH = 10;

$adminId = getAdminId();
$name    = trim($_POST['account_name'] ?? '');
$email   = trim($_POST['account_email'] ?? '');

// Passwords are never trimmed - leading/trailing spaces are legitimate.
$currentPassword = (string) ($_POST['current_password'] ?? '');
$newPassword     = (string) ($_POST['new_password'] ?? '');
$confirmPassword = (string) ($_POST['confirm_password'] ?? '');

$errors = [];

if ($name === '') {
    $errors['account_name'] = 'Name is required.';
}

if ($email === '') {
    $errors['account_email'] = 'Email is required.';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors['account_email'] = 'Enter a valid email address.';
}

if ($currentPassword === '') {
    $errors['current_password'] = 'Enter your current password to save changes.';
}

$changingPassword = ($newPassword !== '' || $confirmPassword !== '');

if ($changingPassword) {
    if (mb_strlen($newPassword) < MIN_PASSWORD_LENGTH) {
        $errors['new_password'] = 'Use at least ' . MIN_PASSWORD_LENGTH . ' characters.';
    } elseif ($newPassword !== $confirmPassword) {
        $errors['confirm_password'] = 'The two passwords do not match.';
    } elseif ($newPassword === $currentPassword) {
        $errors['new_password'] = 'The new password must be different from the current one.';
    }
}

if ($errors) {
    sendJsonResponse(false, 'Please fix the errors below.', ['errors' => $errors]);
}

try {
    $db = getDB();

    $stmt = $db->prepare("SELECT id, name, email, password FROM admins WHERE id = ? LIMIT 1");
    $stmt->execute([$adminId]);
    $admin = $stmt->fetch();

    if (!$admin) {
        sendJsonResponse(false, 'Your account no longer exists. Please sign in again.', [], 403);
    }

    // Verify the current password before honouring anything.
    if (!password_verify($currentPassword, $admin['password'])) {
        sendJsonResponse(false, 'That is not your current password.', [
            'errors' => ['current_password' => 'Incorrect password.']
        ]);
    }

    // The email is the login identity, so it must stay unique.
    $stmt = $db->prepare("SELECT id FROM admins WHERE email = ? AND id != ? LIMIT 1");
    $stmt->execute([$email, $adminId]);
    if ($stmt->fetch()) {
        sendJsonResponse(false, 'That email is already used by another account.', [
            'errors' => ['account_email' => 'Already in use.']
        ]);
    }

    if ($changingPassword) {
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $db->prepare("UPDATE admins SET name = ?, email = ?, password = ? WHERE id = ?");
        $stmt->execute([$name, $email, $hash, $adminId]);
    } else {
        $stmt = $db->prepare("UPDATE admins SET name = ?, email = ? WHERE id = ?");
        $stmt->execute([$name, $email, $adminId]);
    }

    // Keep the visible session in step with what was saved.
    $_SESSION['admin_name']  = $name;
    $_SESSION['admin_email'] = $email;

    // A credential change should not be usable from a stolen session id.
    if ($changingPassword) {
        session_regenerate_id(true);
    }

    $message = $changingPassword
        ? 'Account updated and password changed.'
        : 'Account updated.';

    sendJsonResponse(true, $message, ['password_changed' => $changingPassword]);

} catch (PDOException $e) {
    sendJsonResponse(false, APP_DEBUG ? $e->getMessage() : 'Database error. Please try again.', [], 500);
}
