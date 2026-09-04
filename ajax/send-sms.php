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

/**
 * Active organising-committee members.
 *
 * Columns are aliased to match smsDonorQuery() so the send loop below does
 * not have to care which of the two lists it is walking. Staff have no
 * blood group; the placeholder resolves to an empty string for them.
 */
function smsStaffQuery(): array
{
    return getDB()->query(
        "SELECT id, name AS donor_name, mobile, NULL AS blood_group
         FROM staff
         WHERE status = 'Active'
         ORDER BY name"
    )->fetchAll();
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

    if (!function_exists('curl_init')) {
        return ['status' => 'Failed', 'response' => 'PHP cURL extension is not enabled.'];
    }

    if ($gateway === 'notify') {
        $result = sendNotifySms($phone, $message, $apiKey, $apiSecret, $senderId);

        return [
            'status' => $result['ok'] ? 'Sent' : 'Failed',
            // Prefer Notify's raw body: it carries the reason a send was
            // rejected, which is what makes a failed camp blast diagnosable
            // from the message log weeks later.
            'response' => $result['body'] !== '' ? $result['body'] : $result['error'],
        ];
    }

    if ($gateway !== 'twilio') {
        return ['status' => 'Pending', 'response' => ucfirst($gateway) . ' gateway credentials saved; provider-specific endpoint not configured yet.'];
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
    $isStaff = ($recipientType === 'staff');
    $recipients = $isStaff
        ? smsStaffQuery()
        : smsDonorQuery($recipientType, $bloodGroup, $donorIds);

    if (!$recipients) {
        sendJsonResponse(false, $isStaff
            ? 'No active staff found. Add the committee on the Staff page first.'
            : 'No matching active donors found.');
    }

    // ── Chunking ─────────────────────────────────────────────
    // One outbound HTTP call per recipient, each up to 20 seconds, used
    // to run in a single request. 488 donors could not finish inside
    // max_execution_time, so the request died part-way and the operator
    // was told nothing. The browser now walks the list a chunk at a time.
    $campaignId = normaliseCampaignId($_POST['campaign_id'] ?? null);
    $total      = count($recipients);
    $offset     = max(0, (int) ($_POST['offset'] ?? 0));
    $chunk      = sendChunkSize();

    // Offsets index the ORDERED list and never shift, because already-sent
    // recipients are skipped inside the loop rather than filtered out of
    // the query. That keeps a resumed run aligned with the first one.
    $slice = array_slice($recipients, $offset, $chunk);

    $sent = 0;
    $pending = 0;
    $failed = 0;
    $skipped = 0;

    foreach ($slice as $person) {
        $donorId = $isStaff ? null : (int) $person['id'];
        $staffId = $isStaff ? (int) $person['id'] : null;

        // Resume rather than repeat: a retry of a part-finished campaign
        // must not message everyone who already received it.
        if (alreadySentInCampaign($campaignId, $donorId, $staffId)) {
            $skipped++;
            continue;
        }

        $phone = formatPhoneForAPI($person['mobile']);
        $body = replacePlaceholders($message, [
            'name' => $person['donor_name'],
            'blood_group' => $person['blood_group'] ?? '',
            'date' => $_POST['date'] ?? '',
            'location' => $_POST['location'] ?? '',
            'message' => $_POST['custom_message'] ?? ''
        ]);
        $result = sendSmsText($phone, $body);

        // The id means different things in the two cases, so it goes into
        // whichever column has the matching foreign key.
        logMessage(
            $donorId,
            'SMS',
            $phone,
            $body,
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
        }
    }

    $processed = $offset + count($slice);
    $done      = $processed >= $total;

    $message = $done
        ? "SMS complete: $sent sent, $pending pending, $failed failed"
            . ($skipped > 0 ? ", $skipped already sent" : '') . '.'
        : "Sending... $processed of $total";

    sendJsonResponse(true, $message, [
        'sent'        => $sent,
        'pending'     => $pending,
        'failed'      => $failed,
        'skipped'     => $skipped,
        'processed'   => $processed,
        'total'       => $total,
        'next_offset' => $done ? null : $processed,
        'done'        => $done,
        'campaign_id' => $campaignId
    ]);
} catch (PDOException $e) {
    sendJsonResponse(false, APP_DEBUG ? $e->getMessage() : 'Database error. Please try again.', [], 500);
}
