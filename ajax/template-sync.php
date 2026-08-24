<?php
/**
 * AJAX - List the message templates approved on the WhatsApp Business
 * Account, so the Templates page can show what actually exists at Meta
 * rather than guessing at names.
 *
 * Read-only: it fetches, it never creates or edits anything at Meta.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') sendJsonResponse(false, 'Invalid request method.', [], 405);
if (!isLoggedIn()) sendJsonResponse(false, 'Unauthorized.', [], 403);
if (!validateCSRF()) sendJsonResponse(false, 'Invalid security token.', [], 403);

$token   = getSetting('whatsapp_api_token');
$wabaId  = getSetting('whatsapp_business_account_id');
$version = getSetting('whatsapp_api_version', 'v23.0');

if ($token === '') {
    sendJsonResponse(false, 'Add your WhatsApp API token in Settings first.');
}

if ($wabaId === '') {
    sendJsonResponse(false, 'Add your WhatsApp Business Account ID in Settings first. You can find it in the WhatsApp setup screen, above your phone number.');
}

if (!function_exists('curl_init')) {
    sendJsonResponse(false, 'PHP cURL extension is not enabled.');
}

$url = "https://graph.facebook.com/$version/$wabaId/message_templates?limit=100"
     . "&fields=name,status,language,category,components";

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $token],
    CURLOPT_TIMEOUT        => 20
]);
$response = curl_exec($ch);
$error    = curl_error($ch);
$httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($response === false || $httpCode >= 400) {
    $detail = $error ?: (string) $response;

    if (stripos($detail, 'does not exist') !== false || $httpCode === 404) {
        $detail = 'That WhatsApp Business Account ID was not found, or this token cannot read it.';
    } elseif ($httpCode === 401 || stripos($detail, 'access token') !== false) {
        $detail = 'The access token is invalid or expired.';
    }

    sendJsonResponse(false, 'Could not read templates from Meta: ' . $detail, [], 500);
}

$decoded = json_decode((string) $response, true);
$rows    = $decoded['data'] ?? [];

$templates = [];
foreach ($rows as $row) {
    // Pull the body text out so the admin can see what will be sent, and
    // count the {{n}} variables the template expects.
    $body = '';
    foreach (($row['components'] ?? []) as $component) {
        if (($component['type'] ?? '') === 'BODY') {
            $body = $component['text'] ?? '';
            break;
        }
    }

    preg_match_all('/\{\{(\d+)\}\}/', $body, $matches);
    $variableCount = $matches[1] ? max(array_map('intval', $matches[1])) : 0;

    $templates[] = [
        'name'           => $row['name'] ?? '',
        'language'       => $row['language'] ?? '',
        'status'         => $row['status'] ?? '',
        'category'       => $row['category'] ?? '',
        'body'           => $body,
        'variable_count' => $variableCount,
    ];
}

$approved = array_values(array_filter($templates, fn($t) => strtoupper($t['status']) === 'APPROVED'));

sendJsonResponse(true, sprintf(
    '%d template(s) found at Meta, %d approved.',
    count($templates),
    count($approved)
), ['templates' => $templates]);
