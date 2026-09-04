<?php
/**
 * AJAX - Send WhatsApp Messages
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
 * Active organising-committee members.
 *
 * Aliased to match messagingDonorQuery() so the send loop treats both the
 * same. Staff keep one number, so `whatsapp` is null and the loop's
 * `whatsapp ?: mobile` fallback picks up the mobile.
 */
function messagingStaffQuery(): array
{
    return getDB()->query(
        "SELECT id, name AS donor_name, mobile, NULL AS whatsapp, NULL AS blood_group
         FROM staff
         WHERE status = 'Active'
         ORDER BY name"
    )->fetchAll();
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
 * Adapt the shared WhatsApp sender to the send loop's shape.
 */
function sendWhatsAppPayload(array $payload): array
{
    $result = whatsAppSend($payload);

    return [
        'status'   => $result['status'],
        'response' => $result['raw'] !== '' ? $result['raw'] : $result['detail'],
    ];
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

    $isStaff = ($recipientType === 'staff');
    $recipients = $isStaff
        ? messagingStaffQuery()
        : messagingDonorQuery($recipientType, $bloodGroup, $donorIds);

    if (!$recipients) {
        sendJsonResponse(false, $isStaff
            ? 'No active staff found. Add the committee on the Staff page first.'
            : 'No matching active donors found.');
    }

    // Chunked for the same reason as the SMS sender: one outbound call
    // per recipient cannot finish inside max_execution_time for a list
    // this size. See migration-campaign-batching.sql.
    $campaignId = normaliseCampaignId($_POST['campaign_id'] ?? null);
    $total      = count($recipients);
    $offset     = max(0, (int) ($_POST['offset'] ?? 0));
    $chunk      = sendChunkSize();
    $slice      = array_slice($recipients, $offset, $chunk);

    $sent = 0;
    $pending = 0;
    $failed = 0;
    $skipped = 0;
    $firstError = '';

    foreach ($slice as $donor) {
        $donorId = $isStaff ? null : (int) $donor['id'];
        $staffId = $isStaff ? (int) $donor['id'] : null;

        // Resume rather than repeat on a retry.
        if (alreadySentInCampaign($campaignId, $donorId, $staffId)) {
            $skipped++;
            continue;
        }

        $mobile = $donor['whatsapp'] ?: $donor['mobile'];
        $phone  = formatPhoneForAPI($mobile);

        $values = [
            'name'        => $donor['donor_name'],
            'blood_group' => $donor['blood_group'] ?? '',
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
        logMessage(
            $donorId,
            'WhatsApp',
            $phone,
            $logBody,
            $result['status'],
            $result['response'],
            $staffId,
            $campaignId
        );

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

    $processed = $offset + count($slice);
    $done      = $processed >= $total;

    $summary = $done
        ? "WhatsApp complete: $sent sent, $pending pending, $failed failed"
            . ($skipped > 0 ? ", $skipped already sent" : '') . '.'
        : "Sending... $processed of $total";

    // Surface the most common setup mistakes instead of a bare count.
    if ($done && $failed > 0 && $firstError !== '') {
        if (stripos($firstError, 'template') !== false && stripos($firstError, 'not exist') !== false) {
            $summary .= ' The template name or language does not match an approved template in WhatsApp Manager.';
        } elseif (stripos($firstError, '24') !== false || stripos($firstError, 're-engagement') !== false) {
            $summary .= ' These donors have not messaged you in the last 24 hours, so free text cannot be delivered - use a template.';
        } elseif (APP_DEBUG) {
            $summary .= ' First error: ' . mb_substr($firstError, 0, 300);
        }
    }

    sendJsonResponse(true, $summary, [
        'sent'        => $sent,
        'pending'     => $pending,
        'failed'      => $failed,
        'skipped'     => $skipped,
        'processed'   => $processed,
        'total'       => $total,
        'next_offset' => $done ? null : $processed,
        'done'        => $done,
        'campaign_id' => $campaignId,
        'send_mode'   => $sendMode
    ]);
} catch (PDOException $e) {
    sendJsonResponse(false, APP_DEBUG ? $e->getMessage() : 'Database error. Please try again.', [], 500);
}
