<?php
/**
 * AJAX - Camp Register Delete
 *
 * Removes a person from a camp register. The donor record itself is
 * left untouched - only the attendance row goes.
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
    sendJsonResponse(false, 'Invalid register entry.');
}

try {
    $db = getDB();

    $stmt = $db->prepare("SELECT id FROM camp_registrations WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    if (!$stmt->fetch()) {
        sendJsonResponse(false, 'That register entry no longer exists.');
    }

    $stmt = $db->prepare("DELETE FROM camp_registrations WHERE id = ?");
    $stmt->execute([$id]);

    sendJsonResponse(true, 'Removed from the register.');

} catch (PDOException $e) {
    sendJsonResponse(false, APP_DEBUG ? $e->getMessage() : 'Database error. Please try again.', [], 500);
}
