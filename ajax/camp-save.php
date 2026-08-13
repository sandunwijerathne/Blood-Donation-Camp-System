<?php
/**
 * AJAX - Camp Save (Insert / Update)
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
$title = trim($_POST['title'] ?? '');
$campDate = trim($_POST['camp_date'] ?? '');
$startTime = trim($_POST['start_time'] ?? '');
$endTime = trim($_POST['end_time'] ?? '');
$location = trim($_POST['location'] ?? '');
$description = trim($_POST['description'] ?? '');
$status = trim($_POST['status'] ?? 'Upcoming');

if (empty($title)) sendJsonResponse(false, 'Title is required.');
if (empty($campDate)) sendJsonResponse(false, 'Camp date is required.');
if (empty($location)) sendJsonResponse(false, 'Location is required.');
if (!in_array($status, ['Upcoming', 'Completed', 'Cancelled'])) $status = 'Upcoming';

$startTime = !empty($startTime) ? $startTime : null;
$endTime = !empty($endTime) ? $endTime : null;
$description = !empty($description) ? $description : null;

try {
    $db = getDB();

    if ($id > 0) {
        $stmt = $db->prepare(
            "UPDATE blood_camps SET title=?, camp_date=?, start_time=?, end_time=?, location=?, description=?, status=? WHERE id=?"
        );
        $stmt->execute([$title, $campDate, $startTime, $endTime, $location, $description, $status, $id]);
        sendJsonResponse(true, 'Camp updated successfully.');
    } else {
        $stmt = $db->prepare(
            "INSERT INTO blood_camps (title, camp_date, start_time, end_time, location, description, status) VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([$title, $campDate, $startTime, $endTime, $location, $description, $status]);
        sendJsonResponse(true, 'Camp created successfully.', ['id' => $db->lastInsertId()]);
    }
} catch (PDOException $e) {
    sendJsonResponse(false, APP_DEBUG ? $e->getMessage() : 'Database error.', [], 500);
}
