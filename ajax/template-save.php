<?php
/**
 * AJAX - Template Save
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
$name = trim($_POST['template_name'] ?? '');
$body = trim($_POST['template_body'] ?? '');
$type = trim($_POST['template_type'] ?? 'General');
$validTypes = ['Camp Notification', 'Emergency Request', 'General'];
$errors = [];

if ($name === '') {
    $errors['template_name'] = 'Template name is required.';
}

if ($body === '') {
    $errors['template_body'] = 'Template body is required.';
}

if (!in_array($type, $validTypes, true)) {
    $type = 'General';
}

if ($errors) {
    sendJsonResponse(false, 'Please fix the errors below.', ['errors' => $errors]);
}

try {
    $db = getDB();

    if ($id > 0) {
        $stmt = $db->prepare("UPDATE message_templates
                              SET template_name = ?, template_body = ?, template_type = ?
                              WHERE id = ?");
        $stmt->execute([$name, $body, $type, $id]);
        sendJsonResponse(true, 'Template updated successfully.');
    }

    $stmt = $db->prepare("INSERT INTO message_templates (template_name, template_body, template_type)
                          VALUES (?, ?, ?)");
    $stmt->execute([$name, $body, $type]);
    sendJsonResponse(true, 'Template added successfully.', ['id' => $db->lastInsertId()]);
} catch (PDOException $e) {
    sendJsonResponse(false, APP_DEBUG ? $e->getMessage() : 'Database error. Please try again.', [], 500);
}
