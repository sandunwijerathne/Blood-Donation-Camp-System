<?php
/**
 * AJAX - Settings Save and Provider Tests
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

function testWhatsAppMessage(string $phone, string $message): array
{
    $token = getSetting('whatsapp_api_token');
    $phoneNumberId = getSetting('whatsapp_phone_number_id');
    $version = getSetting('whatsapp_api_version', 'v23.0');

    if ($token === '' || $phoneNumberId === '') {
        return ['success' => false, 'message' => 'WhatsApp API credentials are not configured.'];
    }

    if (!function_exists('curl_init')) {
        return ['success' => false, 'message' => 'PHP cURL extension is not enabled.'];
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
        return ['success' => false, 'message' => 'WhatsApp test failed: ' . ($error ?: (string) $response)];
    }

    return ['success' => true, 'message' => 'WhatsApp test message sent.'];
}

function testSmsMessage(string $phone, string $message): array
{
    $gateway = strtolower(getSetting('sms_gateway', 'twilio'));
    $apiKey = getSetting('sms_api_key');
    $apiSecret = getSetting('sms_api_secret');
    $senderId = getSetting('sms_sender_id');

    if ($apiKey === '' || $apiSecret === '' || $senderId === '') {
        return ['success' => false, 'message' => 'SMS gateway credentials are not configured.'];
    }

    if ($gateway !== 'twilio') {
        return ['success' => false, 'message' => ucfirst($gateway) . ' test sending is not wired yet. Credentials were saved for provider setup.'];
    }

    if (!function_exists('curl_init')) {
        return ['success' => false, 'message' => 'PHP cURL extension is not enabled.'];
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
        return ['success' => false, 'message' => 'SMS test failed: ' . ($error ?: (string) $response)];
    }

    return ['success' => true, 'message' => 'SMS test message sent.'];
}

$action = trim($_POST['action'] ?? 'save');

if ($action === 'test_whatsapp' || $action === 'test_sms') {
    $phone = trim($_POST['test_phone'] ?? '');
    $message = trim($_POST['test_message'] ?? '');

    if ($phone === '' || $message === '') {
        sendJsonResponse(false, 'Test phone and message are required.');
    }

    $phone = formatPhoneForAPI($phone);
    $result = $action === 'test_whatsapp'
        ? testWhatsAppMessage($phone, $message)
        : testSmsMessage($phone, $message);

    sendJsonResponse($result['success'], $result['message']);
}

$allowed = [
    'app_name',
    'organization_name',
    'country_code',
    'whatsapp_api_token',
    'whatsapp_phone_number_id',
    'whatsapp_api_version',
    'sms_gateway',
    'sms_api_key',
    'sms_api_secret',
    'sms_sender_id'
];

$values = [];
foreach ($allowed as $key) {
    $values[$key] = trim($_POST[$key] ?? '');
}

$errors = [];
if ($values['app_name'] === '') {
    $errors[] = 'App name is required.';
}

if ($values['country_code'] === '' || !preg_match('/^\+[0-9]{1,4}$/', $values['country_code'])) {
    $errors[] = 'Country code must look like +94.';
}

if (!in_array($values['sms_gateway'], ['twilio', 'dialog', 'mobitel'], true)) {
    $values['sms_gateway'] = 'twilio';
}

if ($values['whatsapp_api_version'] === '') {
    $values['whatsapp_api_version'] = 'v23.0';
}

if ($errors) {
    sendJsonResponse(false, implode(' ', $errors));
}

try {
    foreach ($values as $key => $value) {
        saveSetting($key, $value);
    }

    sendJsonResponse(true, 'Settings saved successfully.');
} catch (Throwable $e) {
    sendJsonResponse(false, APP_DEBUG ? $e->getMessage() : 'Unable to save settings.', [], 500);
}
