<?php
/**
 * Authentication Helper
 * 
 * Session-based authentication with login check and protection.
 */

require_once __DIR__ . '/../config.php';

/**
 * Check if user is logged in. Redirect to login if not.
 */
function checkLogin(): void
{
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }
}

/**
 * Check if user is currently logged in.
 */
function isLoggedIn(): bool
{
    return isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id']);
}

/**
 * Get the currently logged-in admin's ID.
 */
function getAdminId(): ?int
{
    return $_SESSION['admin_id'] ?? null;
}

/**
 * Get the currently logged-in admin's name.
 */
function getAdminName(): string
{
    return $_SESSION['admin_name'] ?? 'Admin';
}

/**
 * Get the currently logged-in admin's email.
 */
function getAdminEmail(): string
{
    return $_SESSION['admin_email'] ?? '';
}

/**
 * Validate CSRF token from request.
 */
function validateCSRF(): bool
{
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    return hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

/**
 * Get CSRF token for forms.
 */
function csrfToken(): string
{
    return $_SESSION['csrf_token'] ?? '';
}

/**
 * Destroy session and log out.
 */
function doLogout(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
}
