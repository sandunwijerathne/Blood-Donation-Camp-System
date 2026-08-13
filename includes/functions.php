<?php
/**
 * Common Utility Functions
 */

require_once __DIR__ . '/db.php';

/**
 * Sanitize input string.
 */
function sanitize(string $input): string
{
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Send a JSON response and exit.
 */
function sendJsonResponse(bool $success, string $message = '', array $data = [], int $httpCode = 200): void
{
    http_response_code($httpCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data'    => $data
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Get a setting value from the settings table.
 */
function getSetting(string $key, string $default = ''): string
{
    static $cache = [];

    if (isset($cache[$key])) {
        return $cache[$key];
    }

    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $value = $stmt->fetchColumn();
        $cache[$key] = ($value !== false) ? $value : $default;
    } catch (PDOException $e) {
        $cache[$key] = $default;
    }

    return $cache[$key];
}

/**
 * Save a setting value.
 */
function saveSetting(string $key, string $value): bool
{
    try {
        $db = getDB();
        $stmt = $db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) 
                              ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        $stmt->execute([$key, $value]);
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Get total donor count.
 */
function getTotalDonors(): int
{
    $db = getDB();
    return (int) $db->query("SELECT COUNT(*) FROM donors")->fetchColumn();
}

/**
 * Get donor count by blood group.
 */
function getDonorCountByBloodGroup(string $bloodGroup): int
{
    $db = getDB();
    $stmt = $db->prepare("SELECT COUNT(*) FROM donors WHERE blood_group = ? AND status = 'Active'");
    $stmt->execute([$bloodGroup]);
    return (int) $stmt->fetchColumn();
}

/**
 * Get all blood group counts.
 */
function getAllBloodGroupCounts(): array
{
    $db = getDB();
    $stmt = $db->query("SELECT blood_group, COUNT(*) as count FROM donors WHERE status = 'Active' GROUP BY blood_group");
    $result = [];
    $groups = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
    foreach ($groups as $g) {
        $result[$g] = 0;
    }
    while ($row = $stmt->fetch()) {
        $result[$row['blood_group']] = (int) $row['count'];
    }
    return $result;
}

/**
 * Get eligible donors count (last donation >= ELIGIBLE_MONTHS ago or never donated).
 */
function getEligibleDonorsCount(): int
{
    $db = getDB();
    $months = defined('ELIGIBLE_MONTHS') ? ELIGIBLE_MONTHS : 4;
    $stmt = $db->prepare(
        "SELECT COUNT(*) FROM donors 
         WHERE status = 'Active' 
         AND (last_donation_date IS NULL OR last_donation_date <= DATE_SUB(NOW(), INTERVAL ? MONTH))"
    );
    $stmt->execute([$months]);
    return (int) $stmt->fetchColumn();
}

/**
 * Get messages sent today count.
 */
function getMessagesSentToday(): int
{
    $db = getDB();
    return (int) $db->query("SELECT COUNT(*) FROM message_logs WHERE DATE(sent_at) = CURDATE()")->fetchColumn();
}

/**
 * Get next upcoming blood camp.
 */
function getNextUpcomingCamp(): ?array
{
    $db = getDB();
    $stmt = $db->query("SELECT * FROM blood_camps WHERE camp_date >= CURDATE() AND status = 'Upcoming' ORDER BY camp_date ASC LIMIT 1");
    $camp = $stmt->fetch();
    return $camp ?: null;
}

/**
 * Format date for display.
 */
function formatDate(?string $date, string $format = 'd M Y'): string
{
    if (empty($date) || $date === '0000-00-00') {
        return '—';
    }
    return date($format, strtotime($date));
}

/**
 * Format time for display.
 */
function formatTime(?string $time, string $format = 'h:i A'): string
{
    if (empty($time)) {
        return '—';
    }
    return date($format, strtotime($time));
}

/**
 * Normalise a T.P. number to the canonical local storage format.
 *
 * The T.P. number is the unique identifier for a person, so every
 * entry point (donor form, Excel import, camp register) must store
 * it the same way or duplicates slip through. Handles the shapes
 * that appear in the paper register: "077 821 1176", "071-6340385",
 * "+94752698599", "94752698599".
 *
 * Returns '' when the input cannot be read as a usable number.
 */
function normalizeMobile(string $phone): string
{
    // Keep digits only - drop spaces, dashes, brackets, plus signs.
    $digits = preg_replace('/\D+/', '', $phone);

    if ($digits === '') {
        return '';
    }

    // +94xxxxxxxxx / 94xxxxxxxxx -> 0xxxxxxxxx
    if (str_starts_with($digits, '94') && strlen($digits) === 11) {
        $digits = '0' . substr($digits, 2);
    }

    // Bare 9-digit mobile (7xxxxxxxx) -> prepend the leading zero.
    if (strlen($digits) === 9 && str_starts_with($digits, '7')) {
        $digits = '0' . $digits;
    }

    // A Sri Lankan number is 10 digits starting with 0.
    if (strlen($digits) !== 10 || !str_starts_with($digits, '0')) {
        return '';
    }

    return $digits;
}

/**
 * Look up a donor by T.P. number (the unique identifier).
 */
function findDonorByMobile(string $mobile): ?array
{
    $mobile = normalizeMobile($mobile);

    if ($mobile === '') {
        return null;
    }

    $db = getDB();
    $stmt = $db->prepare(
        "SELECT id, donor_name, mobile, whatsapp, email, address, blood_group,
                gender, date_of_birth, last_donation_date, status
         FROM donors WHERE mobile = ? LIMIT 1"
    );
    $stmt->execute([$mobile]);
    $donor = $stmt->fetch();

    return $donor ?: null;
}

/**
 * Format phone number for WhatsApp API (add country code if missing).
 */
function formatPhoneForAPI(string $phone): string
{
    $phone = preg_replace('/[^0-9+]/', '', $phone);
    $countryCode = getSetting('country_code', '+94');
    
    // Remove leading zero and add country code
    if (str_starts_with($phone, '0')) {
        $phone = $countryCode . substr($phone, 1);
    }
    
    // Ensure + prefix
    if (!str_starts_with($phone, '+')) {
        $phone = '+' . $phone;
    }
    
    return $phone;
}

/**
 * Replace template placeholders with actual values.
 */
function replacePlaceholders(string $template, array $data): string
{
    foreach ($data as $key => $value) {
        $template = str_replace('{' . strtoupper($key) . '}', $value, $template);
    }
    return $template;
}

/**
 * Log a sent message.
 */
function logMessage(int $donorId, string $type, string $mobile, string $message, string $status, string $apiResponse = ''): int
{
    $db = getDB();
    $stmt = $db->prepare(
        "INSERT INTO message_logs (donor_id, message_type, mobile, message, status, api_response, sent_at) 
         VALUES (?, ?, ?, ?, ?, ?, NOW())"
    );
    $stmt->execute([$donorId, $type, $mobile, $message, $status, $apiResponse]);
    return (int) $db->lastInsertId();
}

/**
 * Get blood group color for UI.
 */
function getBloodGroupColor(string $group): string
{
    $colors = [
        'A+'  => '#e74c3c',
        'A-'  => '#c0392b',
        'B+'  => '#3498db',
        'B-'  => '#2980b9',
        'AB+' => '#9b59b6',
        'AB-' => '#8e44ad',
        'O+'  => '#2ecc71',
        'O-'  => '#27ae60'
    ];
    return $colors[$group] ?? '#95a5a6';
}

/**
 * Get blood group gradient for UI cards.
 */
function getBloodGroupGradient(string $group): string
{
    $gradients = [
        'A+'  => 'linear-gradient(135deg, #e74c3c, #ff6b6b)',
        'A-'  => 'linear-gradient(135deg, #c0392b, #e74c3c)',
        'B+'  => 'linear-gradient(135deg, #3498db, #74b9ff)',
        'B-'  => 'linear-gradient(135deg, #2980b9, #3498db)',
        'AB+' => 'linear-gradient(135deg, #9b59b6, #c39bd3)',
        'AB-' => 'linear-gradient(135deg, #8e44ad, #9b59b6)',
        'O+'  => 'linear-gradient(135deg, #2ecc71, #55efc4)',
        'O-'  => 'linear-gradient(135deg, #27ae60, #2ecc71)'
    ];
    return $gradients[$group] ?? 'linear-gradient(135deg, #95a5a6, #bdc3c7)';
}
