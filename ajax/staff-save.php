<?php
/**
 * AJAX - Staff Save (Insert / Update)
 *
 * Staff are the camp organising committee: a name and a mobile number.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') sendJsonResponse(false, 'Invalid request.', [], 405);
if (!isLoggedIn()) sendJsonResponse(false, 'Unauthorized.', [], 403);
if (!validateCSRF()) sendJsonResponse(false, 'Invalid security token.', [], 403);

$id = (int) ($_POST['id'] ?? 0);
$name = trim($_POST['name'] ?? '');
$status = trim($_POST['status'] ?? 'Active');

// Normalise before anything else. donor-save.php once skipped this and a
// number typed as "077 821 1176" stored the spaces, so the same person got
// in twice under two spellings. The UNIQUE key only helps if every write
// arrives in the same shape.
$mobile = normalizeMobile((string) ($_POST['mobile'] ?? ''));

if ($name === '') sendJsonResponse(false, 'Name is required.');
if (!in_array($status, ['Active', 'Inactive'], true)) $status = 'Active';

if ($mobile === '') {
    sendJsonResponse(false, 'Enter a valid Sri Lankan mobile number, e.g. 0771234567.');
}

// A committee member exists to be messaged, so a landline is not a usable
// contact here even though it is a well-formed number. Rejecting it now
// beats a silent delivery failure on the night before a camp.
if (!str_starts_with($mobile, '07')) {
    sendJsonResponse(false, 'That looks like a landline. Staff need a mobile number (07XXXXXXXX) to receive messages.');
}

try {
    $db = getDB();

    $stmt = $db->prepare("SELECT id, name FROM staff WHERE mobile = ? AND id <> ? LIMIT 1");
    $stmt->execute([$mobile, $id]);
    if ($clash = $stmt->fetch()) {
        sendJsonResponse(false, 'That number already belongs to ' . $clash['name'] . '.');
    }

    if ($id > 0) {
        $stmt = $db->prepare("UPDATE staff SET name = ?, mobile = ?, status = ? WHERE id = ?");
        $stmt->execute([$name, $mobile, $status, $id]);
        sendJsonResponse(true, 'Staff member updated.');
    }

    $stmt = $db->prepare("INSERT INTO staff (name, mobile, status) VALUES (?, ?, ?)");
    $stmt->execute([$name, $mobile, $status]);
    sendJsonResponse(true, 'Staff member added.', ['id' => $db->lastInsertId()]);
} catch (PDOException $e) {
    sendJsonResponse(false, APP_DEBUG ? $e->getMessage() : 'Database error.', [], 500);
}
