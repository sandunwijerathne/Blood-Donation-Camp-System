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
 * Adapt the shared sender to what the send loop and message log expect.
 *
 * The gateway logic itself lives in includes/messaging.php so that real
 * sends and the Settings test cannot drift apart.
 */
function sendSmsText(string $phone, string $message): array
{
    $result = smsSend($phone, $message);

    return [
        'status' => $result['status'],
        // Prefer the provider's raw body: it carries the reason a send
        // was rejected, which is what makes a failed camp blast
        // diagnosable from the message log weeks later.
        'response' => $result['raw'] !== '' ? $result['raw'] : $result['detail'],
    ];
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
