<?php
/**
 * AJAX - Settings Save and Provider Tests
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/messaging.php';

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
    // Use the "hello_world" template that Meta pre-approves on new accounts.
    // A plain text message only arrives if the tester messaged this number in the
    // last 24 hours, so a template proves the token, Phone Number ID and API
    // version are all correct by themselves. Once a business registers its own
    // number Meta rejects "hello_world" (error 131058); businesses must use their
    // own approved templates.
    $result = whatsAppSend([
        'messaging_product' => 'whatsapp',
        'to'                => ltrim($phone, '+'),
        'type'              => 'template',
        'template'          => [
            'name'     => $templateName !== '' ? $templateName : 'hello_world',
            'language' => ['code' => $language !== '' ? $language : 'en_US']
        ]
    ]);

    if ($result['ok']) {
        return ['success' => true, 'message' => 'WhatsApp test message sent (' . $templateName . ' template).'];
    }

    return ['success' => false, 'message' => 'WhatsApp test failed: ' . whatsAppSetupHint($result)];
}

function testSmsMessage(string $phone, string $message): array
{
    // Same sender as a real send, so a passing test means real sends work -
    // which was not guaranteed while the two kept separate copies of the
    // gateway code.
    $result = smsSend($phone, $message);

    if ($result['ok']) {
        return ['success' => true, 'message' => 'SMS test message sent via ' . ucfirst($result['gateway']) . '.'];
    }

    return ['success' => false, 'message' => 'SMS test failed: ' . smsSetupHint($result)];
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
    'currency_symbol',
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

if (!in_array($values['sms_gateway'], ['notify', 'twilio', 'dialog', 'mobitel'], true)) {
    $values['sms_gateway'] = 'twilio';
}

if ($values['whatsapp_api_version'] === '') {
    $values['whatsapp_api_version'] = 'v23.0';
}

// A blank currency box falls back to the default rather than leaving
// the budget screens showing bare numbers with no unit.
if ($values['currency_symbol'] === '') {
    $values['currency_symbol'] = 'Rs.';
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
