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

// ── Local Overrides ──────────────────────────────────────────
// config.local.php holds this installation's real credentials and
// canonical hostname. It is git-ignored, so secrets never enter version
// control. Everything below only fills in what it has not already
// defined - see config.local.php.example.
if (is_readable(__DIR__ . '/config.local.php')) {
    require_once __DIR__ . '/config.local.php';
}

// ── Error Reporting ──────────────────────────────────────────
// MUST stay false in production. When true, database errors and stack
// traces are printed to whoever triggered them - and this system holds
// donor names, phone numbers and blood groups.
// Set to true only while debugging on a local machine.
if (!defined('APP_DEBUG')) {
    define('APP_DEBUG', false);
}

// Errors are never shown to users, but they MUST be recorded. The
// previous setting was error_reporting(0), which stops errors being
// generated at all - so nothing reached a log either, and a broken page
// looked identical to an empty one. That turned a missing database
// column into a blank screen with no way to diagnose it.
error_reporting(E_ALL);
ini_set('display_errors', APP_DEBUG ? '1' : '0');
ini_set('log_errors', '1');

if (!defined('APP_ERROR_LOG')) {
    define('APP_ERROR_LOG', __DIR__ . '/storage/logs/php-error.log');
}

if (!is_dir(dirname(APP_ERROR_LOG))) {
    @mkdir(dirname(APP_ERROR_LOG), 0750, true);
}

ini_set('error_log', APP_ERROR_LOG);

// ── Database Configuration ───────────────────────────────────
// Defaults suit a local XAMPP machine only. Production credentials
// belong in config.local.php, under a least-privilege user - never root.
if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
if (!defined('DB_NAME')) define('DB_NAME', 'blood_donor_system');
if (!defined('DB_USER')) define('DB_USER', 'root');
if (!defined('DB_PASS')) define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// ── Application Settings ─────────────────────────────────────
define('APP_NAME', 'Blood Donor System');
define('APP_VERSION', '1.0.0');

// Base URL - update for your hosting
// Auto-detect base URL
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';

// The Host header is supplied by the client, so trusting it lets an
// attacker choose where redirects and asset URLs point:
//   curl -H "Host: evil.example.com" .../index.php
//   -> Location: http://evil.example.com/.../login.php
// APP_CANONICAL_HOST (set in config.local.php) pins it. Where it is not
// set - a local machine - fall back to the request host but strip
// anything that is not a valid host:port, so the header cannot inject.
if (defined('APP_CANONICAL_HOST') && APP_CANONICAL_HOST !== '') {
    $host = APP_CANONICAL_HOST;
} else {
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    if (!preg_match('/^[A-Za-z0-9.\-]+(:[0-9]{1,5})?$/', $host)) {
        $host = 'localhost';
    }
}
// Derive the subdirectory the app is served from, so the same config
// works at /blood-donor-system, /GITHUB/Blood-Donation-Camp-System, or
// a domain root. Falls back to '' (domain root) when DOCUMENT_ROOT is
// unavailable, e.g. under CLI.
$docRoot = rtrim(str_replace(DIRECTORY_SEPARATOR, '/', $_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
$appDir  = str_replace(DIRECTORY_SEPARATOR, '/', __DIR__);

// Windows reports drive letters inconsistently, so match case-insensitively
// but slice from the original to preserve the real path casing in the URL.
$basePath = ($docRoot !== '' && stripos($appDir, $docRoot) === 0)
    ? rtrim(substr($appDir, strlen($docRoot)), '/')
    : '';

define('BASE_URL', $protocol . '://' . $host . $basePath);

// ── Paths ────────────────────────────────────────────────────
define('ROOT_PATH', __DIR__);
define('INCLUDES_PATH', ROOT_PATH . '/includes');
// Uploads default to storage/, which .htaccess denies, rather than the
// old ROOT_PATH/uploads which was publicly reachable. On a production
// host, set APP_UPLOAD_PATH in config.local.php to a directory OUTSIDE
// the document root - that protects the files even if .htaccess is not
// honoured, which is the case on nginx.
if (!defined('APP_UPLOAD_PATH')) {
    define('APP_UPLOAD_PATH', ROOT_PATH . '/storage/uploads');
}
define('UPLOAD_PATH', APP_UPLOAD_PATH);
define('EXCEL_UPLOAD_PATH', UPLOAD_PATH . '/excel');

// ── Session Configuration ────────────────────────────────────
ini_set('session.cookie_httponly', '1');
ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_samesite', 'Lax');

// Only send the session cookie over HTTPS. Set conditionally: forcing it
// on plain HTTP would stop the cookie being sent at all and make login
// silently impossible on a local machine.
if ($protocol === 'https') {
    ini_set('session.cookie_secure', '1');
}

// How long a session may sit idle before it is treated as expired.
define('SESSION_IDLE_SECONDS', 2 * 60 * 60);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Enforce the idle timeout. login_time was already being recorded at
// login and then never read, so a session previously stayed valid until
// the browser closed. last_activity is refreshed on every request.
if (isset($_SESSION['admin_id'])) {
    $lastSeen = $_SESSION['last_activity'] ?? $_SESSION['login_time'] ?? time();

    if (time() - (int) $lastSeen > SESSION_IDLE_SECONDS) {
        $_SESSION = [];
        session_destroy();
        session_start();
        session_regenerate_id(true);
    } else {
        $_SESSION['last_activity'] = time();
    }
}

// ── CSRF Token ───────────────────────────────────────────────
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ── Timezone ─────────────────────────────────────────────────
date_default_timezone_set('Asia/Colombo');

// ── Eligible Donor Interval (months) ─────────────────────────
define('ELIGIBLE_MONTHS', 4);

// ── SMS Limits ───────────────────────────────────────────────
// Notify.lk rejects anything longer. Counted in characters, so a Sinhala
// message costs the same against this cap as an English one - though it
// will use more SMS segments on the carrier side.
define('NOTIFY_SMS_MAX_CHARS', 320);

// ── Upload Limits ────────────────────────────────────────────
// The donor import is the only upload path. PhpSpreadsheet 1.30.5
// carries three HIGH advisories, two of which are memory-exhaustion
// via crafted spreadsheets, so cap the input size explicitly rather
// than relying on php.ini's upload_max_filesize alone.
define('IMPORT_MAX_BYTES', 5 * 1024 * 1024);
