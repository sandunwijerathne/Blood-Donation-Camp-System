<?php
/**
 * AJAX Login Handler
 * 
 * Validates credentials and starts a session.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(false, 'Invalid request method.', [], 405);
}

// Validate CSRF
if (!validateCSRF()) {
    sendJsonResponse(false, 'Invalid security token. Please refresh the page.', [], 403);
}

// Get input
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

// Validate input
if (empty($email) || empty($password)) {
    sendJsonResponse(false, 'Please enter both email and password.');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    sendJsonResponse(false, 'Please enter a valid email address.');
}

try {
    $db = getDB();

    // Throttle BEFORE touching the password. Unlimited attempts at full
    // speed, unlogged, was the previous behaviour - one script away from
    // guessing the only account guarding 488 people's health records.
    $wait = loginLockoutSeconds($email);
    if ($wait > 0) {
        // Deliberately does not say whether the account exists, so the
        // lockout cannot be used to enumerate accounts either.
        sendJsonResponse(false, sprintf(
            'Too many failed attempts. Try again in %s.',
            humaniseSeconds($wait)
        ), [], 429);
    }

    $stmt = $db->prepare("SELECT id, name, email, password FROM admins WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $admin = $stmt->fetch();

    if (!$admin || !password_verify($password, $admin['password'])) {
        recordLoginAttempt($email, false);

        // Same wording for "no such account" and "wrong password", so a
        // failed login still reveals nothing about who has an account.
        sendJsonResponse(false, 'Invalid email or password.');
    }

    recordLoginAttempt($email, true);

    // Regenerate session ID to prevent session fixation
    session_regenerate_id(true);

    // Set session variables
    $_SESSION['admin_id'] = $admin['id'];
    $_SESSION['admin_name'] = $admin['name'];
    $_SESSION['admin_email'] = $admin['email'];
    $_SESSION['login_time'] = time();

    sendJsonResponse(true, 'Login successful.');

} catch (PDOException $e) {
    if (APP_DEBUG) {
        sendJsonResponse(false, 'Database error: ' . $e->getMessage(), [], 500);
    }
    sendJsonResponse(false, 'An error occurred. Please try again.', [], 500);
}
