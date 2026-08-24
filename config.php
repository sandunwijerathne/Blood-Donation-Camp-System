<?php
/**
 * Blood Donor Management System - Configuration
 *
 * Update these settings to match your hosting environment.
 */

// ── Re-entry Guard ───────────────────────────────────────────
// Composer's "autoload.files" pulls this file in with a plain
// require, which bypasses the require_once used elsewhere. Without
// this guard the file runs twice and emits "Constant already
// defined" warnings - and those warnings corrupt binary downloads
// such as the Excel exports.
if (defined('APP_CONFIG_LOADED')) {
    return;
}
define('APP_CONFIG_LOADED', true);

// ── Error Reporting ──────────────────────────────────────────
// MUST stay false in production. When true, database errors and stack
// traces are printed to whoever triggered them - and this system holds
// donor names, phone numbers and blood groups.
// Set to true only while debugging on a local machine.
define('APP_DEBUG', false);

if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}

// ── Database Configuration ───────────────────────────────────
define('DB_HOST', 'localhost');
define('DB_NAME', 'blood_donor_system');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// ── Application Settings ─────────────────────────────────────
define('APP_NAME', 'Blood Donor System');
define('APP_VERSION', '1.0.0');

// Base URL - update for your hosting
// Auto-detect base URL
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
define('BASE_URL', $protocol . '://' . $host . '/blood-donor-system');

// ── Paths ────────────────────────────────────────────────────
define('ROOT_PATH', __DIR__);
define('INCLUDES_PATH', ROOT_PATH . '/includes');
define('UPLOAD_PATH', ROOT_PATH . '/uploads');
define('EXCEL_UPLOAD_PATH', UPLOAD_PATH . '/excel');

// ── Session Configuration ────────────────────────────────────
ini_set('session.cookie_httponly', '1');
ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_samesite', 'Lax');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ── CSRF Token ───────────────────────────────────────────────
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ── Timezone ─────────────────────────────────────────────────
date_default_timezone_set('Asia/Colombo');

// ── Eligible Donor Interval (months) ─────────────────────────
define('ELIGIBLE_MONTHS', 4);
