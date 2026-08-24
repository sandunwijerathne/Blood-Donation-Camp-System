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

/**
 * Build the Meta payload for a pre-approved template message.
 *
 * WhatsApp templates use numbered variables ({{1}}, {{2}}...). This app
 * writes messages with named placeholders, so $variableOrder says which
 * placeholder feeds which position - e.g. "NAME,DATE,LOCATION" means
 * {{1}}=NAME, {{2}}=DATE, {{3}}=LOCATION.
 */
function buildTemplatePayload(
    string $phone,
    string $templateName,
    string $language,
    string $variableOrder,
    array $values
): array {
    $components = [];
    $order = array_values(array_filter(array_map('trim', explode(',', $variableOrder))));

    if ($order) {
        $parameters = [];
        foreach ($order as $key) {
            // Meta rejects empty parameters, so never send a blank one.
            $value = trim((string) ($values[strtolower($key)] ?? ''));
            $parameters[] = ['type' => 'text', 'text' => $value !== '' ? $value : '-'];
        }
        $components[] = ['type' => 'body', 'parameters' => $parameters];
    }

    $template = [
        'name'     => $templateName,
        'language' => ['code' => $language !== '' ? $language : 'en'],
    ];

    if ($components) {
        $template['components'] = $components;
    }

    return [
        'messaging_product' => 'whatsapp',
        'to'                => ltrim($phone, '+'),
        'type'              => 'template',
        'template'          => $template,
    ];
}

/**
 * Send one WhatsApp message.
 *
 * $payload is either a template payload (business-initiated, works any
 * time) or a plain text payload (only delivered inside the 24-hour
 * window opened by the donor messaging first).
 */
function sendWhatsAppPayload(array $payload): array
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
$message  = trim($_POST['message'] ?? '');

// 'template' is business-initiated and works at any time.
// 'text' only reaches donors who messaged us in the last 24 hours.
$sendMode   = trim($_POST['send_mode'] ?? 'template');
$templateId = (int) ($_POST['template_id'] ?? 0);

if ($sendMode !== 'text') {
    $sendMode = 'template';
}

if ($sendMode === 'text' && $message === '') {
    sendJsonResponse(false, 'Message is required.');
}

try {
    $db = getDB();
    $template = null;

    if ($sendMode === 'template') {
        if ($templateId <= 0) {
            sendJsonResponse(false, 'Choose a template. WhatsApp only delivers business-initiated messages from a template Meta has approved.');
        }

        $stmt = $db->prepare(
            "SELECT template_name, template_body, whatsapp_template_name, whatsapp_language, whatsapp_variables
             FROM message_templates WHERE id = ? LIMIT 1"
        );
        $stmt->execute([$templateId]);
        $template = $stmt->fetch();

        if (!$template) {
            sendJsonResponse(false, 'That template no longer exists.');
        }

        if (trim((string) $template['whatsapp_template_name']) === '') {
            sendJsonResponse(false, sprintf(
                'Template "%s" has no WhatsApp template name set. Add the name exactly as approved in WhatsApp Manager, on the Templates page.',
                $template['template_name']
            ));
        }
    }

    $donors = messagingDonorQuery($recipientType, $bloodGroup, $donorIds);
    if (!$donors) {
        sendJsonResponse(false, 'No matching active donors found.');
    }

    $sent = 0;
    $pending = 0;
    $failed = 0;
    $firstError = '';

    foreach ($donors as $donor) {
        $mobile = $donor['whatsapp'] ?: $donor['mobile'];
        $phone  = formatPhoneForAPI($mobile);

        $values = [
            'name'        => $donor['donor_name'],
            'blood_group' => $donor['blood_group'],
            'date'        => $_POST['date'] ?? '',
            'location'    => $_POST['location'] ?? '',
            'message'     => $_POST['custom_message'] ?? ''
        ];

        if ($sendMode === 'template') {
            $payload = buildTemplatePayload(
                $phone,
                $template['whatsapp_template_name'],
                (string) $template['whatsapp_language'],
                (string) $template['whatsapp_variables'],
                $values
            );
            // Log the readable version, not the raw API payload.
            $logBody = replacePlaceholders($template['template_body'], $values);
        } else {
            $body = replacePlaceholders($message, $values);
            $payload = [
                'messaging_product' => 'whatsapp',
                'to'                => ltrim($phone, '+'),
                'type'              => 'text',
                'text'              => ['preview_url' => false, 'body' => $body]
            ];
            $logBody = $body;
        }

        $result = sendWhatsAppPayload($payload);
        logMessage((int) $donor['id'], 'WhatsApp', $phone, $logBody, $result['status'], $result['response']);

        if ($result['status'] === 'Sent') {
            $sent++;
        } elseif ($result['status'] === 'Pending') {
            $pending++;
        } else {
            $failed++;
            if ($firstError === '') {
                $firstError = (string) $result['response'];
            }
        }
    }

    $summary = "WhatsApp processing complete: $sent sent, $pending pending, $failed failed.";

    // Surface the most common setup mistakes instead of a bare count.
    if ($failed > 0 && $firstError !== '') {
        if (stripos($firstError, 'template') !== false && stripos($firstError, 'not exist') !== false) {
            $summary .= ' The template name or language does not match an approved template in WhatsApp Manager.';
        } elseif (stripos($firstError, '24') !== false || stripos($firstError, 're-engagement') !== false) {
            $summary .= ' These donors have not messaged you in the last 24 hours, so free text cannot be delivered - use a template.';
        } elseif (APP_DEBUG) {
            $summary .= ' First error: ' . mb_substr($firstError, 0, 300);
        }
    }

    sendJsonResponse(true, $summary, [
        'sent'      => $sent,
        'pending'   => $pending,
        'failed'    => $failed,
        'send_mode' => $sendMode
    ]);
} catch (PDOException $e) {
    sendJsonResponse(false, APP_DEBUG ? $e->getMessage() : 'Database error. Please try again.', [], 500);
}
