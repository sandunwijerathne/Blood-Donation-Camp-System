<?php
/**
 * Index - Entry Point
 * 
 * Redirects to dashboard if logged in, otherwise to login page.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    header('Location: ' . BASE_URL . '/admin/dashboard.php');
} else {
    header('Location: ' . BASE_URL . '/login.php');
}
exit;
