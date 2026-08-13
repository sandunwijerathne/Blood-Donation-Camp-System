<?php
/**
 * AJAX - Donor Status Toggle
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

$id = (int) ($_POST['id'] ?? 0);
$status = trim($_POST['status'] ?? '');

if (!$id || !in_array($status, ['Active', 'Inactive'])) {
    sendJsonResponse(false, 'Invalid parameters.');
}

try {
    $db = getDB();
    $stmt = $db->prepare("UPDATE donors SET status = ? WHERE id = ?");
    $stmt->execute([$status, $id]);

    if ($stmt->rowCount() > 0) {
        sendJsonResponse(true, "Donor marked as $status.");
    } else {
        sendJsonResponse(false, 'Donor not found.');
    }
} catch (PDOException $e) {
    sendJsonResponse(false, APP_DEBUG ? $e->getMessage() : 'Database error.', [], 500);
}
