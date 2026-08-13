<?php
/**
 * Logout Handler
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';

doLogout();
header('Location: ' . BASE_URL . '/login.php');
exit;
