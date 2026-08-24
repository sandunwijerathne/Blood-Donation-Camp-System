<?php
/**
 * AJAX - Camp Contribution Delete
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') sendJsonResponse(false, 'Invalid request method.', [], 405);
if (!isLoggedIn()) sendJsonResponse(false, 'Unauthorized.', [], 403);
if (!validateCSRF()) sendJsonResponse(false, 'Invalid security token.', [], 403);

$id = (int) ($_POST['id'] ?? 0);

if ($id <= 0) {
    sendJsonResponse(false, 'Invalid contribution.');
}

try {
    $db = getDB();

    // Read the camp first so the refreshed totals can be sent back.
    $stmt = $db->prepare("SELECT camp_id FROM camp_contributions WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $campId = (int) $stmt->fetchColumn();

    if ($campId <= 0) {
        sendJsonResponse(false, 'Contribution not found.');
    }

    $stmt = $db->prepare("DELETE FROM camp_contributions WHERE id = ?");
    $stmt->execute([$id]);

    sendJsonResponse(true, 'Contribution deleted.', ['summary' => getCampFinanceSummary($campId)]);
} catch (PDOException $e) {
    sendJsonResponse(false, APP_DEBUG ? $e->getMessage() : 'Database error.', [], 500);
}
