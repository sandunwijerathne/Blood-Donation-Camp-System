<?php
/**
 * AJAX - Send SMS Messages
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(false, 'Invalid request method.', [], 405);
}

if (!isLoggedIn()) {
    sendJsonResponse(false, 'Unauthorized.', [], 403);
}

if (!validateCSRF()) {
    sendJsonResponse(false, 'Invalid security token.', [], 403);
}

function smsDonorQuery(string $recipientType, string $bloodGroup, array $donorIds): array
{
    $db = getDB();
    $where = ["status = 'Active'"];
    $params = [];

    if ($recipientType === 'blood_group') {
        $validGroups = ['A+','A-','B+','B-','AB+','AB-','O+','O-'];
        if (!in_array($bloodGroup, $validGroups, true)) {
            sendJsonResponse(false, 'Please select a valid blood group.');
        }
        $where[] = 'blood_group = ?';
        $params[] = $bloodGroup;
    } elseif ($recipientType === 'selected') {
        $donorIds = array_values(array_filter(array_map('intval', $donorIds), fn($id) => $id > 0));
        if (!$donorIds) {
            sendJsonResponse(false, 'Please select at least one donor.');
        }
        $placeholders = implode(',', array_fill(0, count($donorIds), '?'));
        $where[] = "id IN ($placeholders)";
        $params = array_merge($params, $donorIds);
    } elseif ($recipientType !== 'all') {
        sendJsonResponse(false, 'Invalid recipient type.');
    }

    $sql = "SELECT id, donor_name, mobile, blood_group
            FROM donors
            WHERE " . implode(' AND ', $where) . "
            ORDER BY donor_name";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function sendSmsText(string $phone, string $message): array
{
    $gateway = strtolower(getSetting('sms_gateway', 'twilio'));
    $apiKey = getSetting('sms_api_key');
    $apiSecret = getSetting('sms_api_secret');
    $senderId = getSetting('sms_sender_id');

    if ($apiKey === '' || $apiSecret === '' || $senderId === '') {
        return ['status' => 'Pending', 'response' => 'SMS gateway credentials are not configured.'];
    }

    if ($gateway !== 'twilio') {
        return ['status' => 'Pending', 'response' => ucfirst($gateway) . ' gateway credentials saved; provider-specific endpoint not configured yet.'];
    }

    if (!function_exists('curl_init')) {
        return ['status' => 'Failed', 'response' => 'PHP cURL extension is not enabled.'];
    }

    $url = "https://api.twilio.com/2010-04-01/Accounts/$apiKey/Messages.json";
    $payload = http_build_query([
        'From' => $senderId,
        'To' => $phone,
        'Body' => $message
    ]);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_USERPWD => $apiKey . ':' . $apiSecret,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_TIMEOUT => 20
    ]);
    $response = curl_exec($ch);
    $error = curl_error($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false || $httpCode >= 400) {
        return ['status' => 'Failed', 'response' => $error ?: (string) $response];
    }

    return ['status' => 'Sent', 'response' => (string) $response];
}

$recipientType = trim($_POST['recipient_type'] ?? 'all');
$bloodGroup = trim($_POST['blood_group'] ?? '');
$donorIds = $_POST['donor_ids'] ?? [];
if (!is_array($donorIds)) {
    $donorIds = explode(',', (string) $donorIds);
}
$message = trim($_POST['message'] ?? '');

if ($message === '') {
    sendJsonResponse(false, 'Message is required.');
}

try {
    $donors = smsDonorQuery($recipientType, $bloodGroup, $donorIds);
    if (!$donors) {
        sendJsonResponse(false, 'No matching active donors found.');
    }

    $sent = 0;
    $pending = 0;
    $failed = 0;

    foreach ($donors as $donor) {
        $phone = formatPhoneForAPI($donor['mobile']);
        $body = replacePlaceholders($message, [
            'name' => $donor['donor_name'],
            'blood_group' => $donor['blood_group'],
            'date' => $_POST['date'] ?? '',
            'location' => $_POST['location'] ?? '',
            'message' => $_POST['custom_message'] ?? ''
        ]);
        $result = sendSmsText($phone, $body);
        logMessage((int) $donor['id'], 'SMS', $phone, $body, $result['status'], $result['response']);

        if ($result['status'] === 'Sent') {
            $sent++;
        } elseif ($result['status'] === 'Pending') {
            $pending++;
        } else {
            $failed++;
        }
    }

    sendJsonResponse(true, "SMS processing complete: $sent sent, $pending pending, $failed failed.", [
        'sent' => $sent,
        'pending' => $pending,
        'failed' => $failed
    ]);
} catch (PDOException $e) {
    sendJsonResponse(false, APP_DEBUG ? $e->getMessage() : 'Database error. Please try again.', [], 500);
}
