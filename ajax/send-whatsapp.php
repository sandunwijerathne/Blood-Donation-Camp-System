<?php
/**
 * AJAX - Send WhatsApp Messages
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

function messagingDonorQuery(string $recipientType, string $bloodGroup, array $donorIds): array
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

    $sql = "SELECT id, donor_name, mobile, whatsapp, blood_group
            FROM donors
            WHERE " . implode(' AND ', $where) . "
            ORDER BY donor_name";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function sendWhatsAppText(string $phone, string $message): array
{
    $token = getSetting('whatsapp_api_token');
    $phoneNumberId = getSetting('whatsapp_phone_number_id');
    $version = getSetting('whatsapp_api_version', 'v23.0');

    if ($token === '' || $phoneNumberId === '') {
        return ['status' => 'Pending', 'response' => 'WhatsApp API credentials are not configured.'];
    }

    if (!function_exists('curl_init')) {
        return ['status' => 'Failed', 'response' => 'PHP cURL extension is not enabled.'];
    }

    $url = "https://graph.facebook.com/$version/$phoneNumberId/messages";
    $payload = [
        'messaging_product' => 'whatsapp',
        'to' => ltrim($phone, '+'),
        'type' => 'text',
        'text' => ['preview_url' => false, 'body' => $message]
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json'
        ],
        CURLOPT_POSTFIELDS => json_encode($payload),
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
    $donors = messagingDonorQuery($recipientType, $bloodGroup, $donorIds);
    if (!$donors) {
        sendJsonResponse(false, 'No matching active donors found.');
    }

    $sent = 0;
    $pending = 0;
    $failed = 0;

    foreach ($donors as $donor) {
        $mobile = $donor['whatsapp'] ?: $donor['mobile'];
        $phone = formatPhoneForAPI($mobile);
        $body = replacePlaceholders($message, [
            'name' => $donor['donor_name'],
            'blood_group' => $donor['blood_group'],
            'date' => $_POST['date'] ?? '',
            'location' => $_POST['location'] ?? '',
            'message' => $_POST['custom_message'] ?? ''
        ]);
        $result = sendWhatsAppText($phone, $body);
        logMessage((int) $donor['id'], 'WhatsApp', $phone, $body, $result['status'], $result['response']);

        if ($result['status'] === 'Sent') {
            $sent++;
        } elseif ($result['status'] === 'Pending') {
            $pending++;
        } else {
            $failed++;
        }
    }

    sendJsonResponse(true, "WhatsApp processing complete: $sent sent, $pending pending, $failed failed.", [
        'sent' => $sent,
        'pending' => $pending,
        'failed' => $failed
    ]);
} catch (PDOException $e) {
    sendJsonResponse(false, APP_DEBUG ? $e->getMessage() : 'Database error. Please try again.', [], 500);
}
