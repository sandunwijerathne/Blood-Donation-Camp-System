<?php
/**
 * AJAX - Staff Delete
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
if (!$id) sendJsonResponse(false, 'Invalid staff ID.');

try {
    $db = getDB();

    // message_logs.staff_id is ON DELETE SET NULL, so past messages survive
    // as an audit trail; only the link to the person goes.
    $stmt = $db->prepare("DELETE FROM staff WHERE id = ?");
    $stmt->execute([$id]);
    sendJsonResponse($stmt->rowCount() > 0, $stmt->rowCount() > 0 ? 'Staff member deleted.' : 'Staff member not found.');
} catch (PDOException $e) {
    sendJsonResponse(false, APP_DEBUG ? $e->getMessage() : 'Database error.', [], 500);
}
