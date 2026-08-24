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

function testWhatsAppMessage(string $phone, string $message, string $templateName = 'hello_world', string $language = 'en_US'): array
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

    // Use the "hello_world" template that Meta pre-approves on new accounts.
    // A plain text message only arrives if the tester messaged this number in the
    // last 24 hours, so a template proves the token, Phone Number ID and API
    // version are all correct by themselves. Once a business registers its own
    // number Meta rejects "hello_world" (error 131058); businesses must use their
    // own approved templates.
    $payload = [
        'messaging_product' => 'whatsapp',
        'to'                => ltrim($phone, '+'),
        'type'              => 'template',
        'template'          => [
            'name'     => $templateName !== '' ? $templateName : 'hello_world',
            'language' => ['code' => $language !== '' ? $language : 'en_US']
        ]
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
        $detail = $error ?: (string) $response;

        if (stripos($detail, '131058') !== false || stripos($detail, 'Public Test Numbers') !== false) {
            // The account has moved off Meta's shared test number.
            $detail = 'Your own number is registered, so the "hello_world" template no longer works'
                    . ' - Meta only allows it from their public test numbers. Create a template in'
                    . ' WhatsApp Manager, wait for approval, then pick it in the WhatsApp Test'
                    . ' Template box above. Your token and Phone Number ID are fine: this error'
                    . ' only happens after authentication succeeds.';
        } elseif (stripos($detail, 'not exist') !== false && stripos($detail, 'template') !== false) {
            $detail .= ' - no approved template with that name and language. Check WhatsApp Manager'
                     . ' -> Message Templates, and that the language code matches exactly.'
                     . ' Also confirm the Phone Number ID belongs to this WhatsApp Business Account.';
        } elseif (stripos($detail, 'payment') !== false || stripos($detail, '131042') !== false) {
            $detail .= ' - add a payment method to the WhatsApp Business Account. Business-initiated'
                     . ' messages cannot be sent without one.';
        } elseif ($httpCode === 401 || stripos($detail, 'access token') !== false) {
            $detail .= ' - the access token is invalid or has expired. Temporary tokens last 24 hours.';
        } elseif (stripos($detail, 'recipient') !== false) {
            $detail .= ' - add this number to the allowed recipient list on the WhatsApp > API Setup page first.';
        }

        return ['success' => false, 'message' => 'WhatsApp test failed: ' . $detail];
    }

    return ['success' => true, 'message' => 'WhatsApp test message sent (hello_world template).'];
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

    if ($phone === '') {
        sendJsonResponse(false, 'A test phone number is required.');
    }

    // A WhatsApp test sends a template, so the free-text box is not used.
    if ($action === 'test_sms' && $message === '') {
        sendJsonResponse(false, 'A test message is required for SMS.');
    }

    $phone = formatPhoneForAPI($phone);
    $testTemplate = trim($_POST['test_template'] ?? 'hello_world');
    $testLanguage = trim($_POST['test_language'] ?? 'en_US');

    $result = $action === 'test_whatsapp'
        ? testWhatsAppMessage($phone, $message, $testTemplate, $testLanguage)
        : testSmsMessage($phone, $message);

    sendJsonResponse($result['success'], $result['message']);
}

$allowed = [
    'app_name',
    'organization_name',
    'country_code',
    'whatsapp_api_token',
    'whatsapp_phone_number_id',
    'whatsapp_business_account_id',
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

// Secrets are never rendered back into the form, so a blank box means
// "keep what is stored" rather than "erase it". Without this, saving the
// General settings would silently wipe the WhatsApp token.
foreach (['whatsapp_api_token', 'sms_api_secret'] as $secretKey) {
    if ($values[$secretKey] === '') {
        $values[$secretKey] = getSetting($secretKey, '');
    }
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
