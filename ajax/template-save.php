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

// WhatsApp template linkage (optional - blank means SMS-only template).
$waName      = trim($_POST['whatsapp_template_name'] ?? '');
$waLanguage  = trim($_POST['whatsapp_language'] ?? 'en');
$waVariables = trim($_POST['whatsapp_variables'] ?? '');

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

// Meta template names are lowercase letters, digits and underscores only.
if ($waName !== '' && !preg_match('/^[a-z0-9_]+$/', $waName)) {
    $errors['whatsapp_template_name'] = 'Use lowercase letters, numbers and underscores only, exactly as approved by Meta.';
}

if ($waLanguage === '') {
    $waLanguage = 'en';
} elseif (!preg_match('/^[a-zA-Z]{2,3}(_[a-zA-Z]{2,4})?$/', $waLanguage)) {
    $errors['whatsapp_language'] = 'Use a language code such as en, en_US or si.';
}

// Variable order is a comma separated list of placeholder names.
if ($waVariables !== '') {
    $waVariables = strtoupper(preg_replace('/\s+/', '', $waVariables));
    if (!preg_match('/^[A-Z_]+(,[A-Z_]+)*$/', $waVariables)) {
        $errors['whatsapp_variables'] = 'Comma separated placeholder names, e.g. NAME,DATE,LOCATION';
    }
}

if ($errors) {
    sendJsonResponse(false, 'Please fix the errors below.', ['errors' => $errors]);
}

$waName      = $waName !== '' ? $waName : null;
$waVariables = $waVariables !== '' ? $waVariables : null;

try {
    $db = getDB();

    if ($id > 0) {
        $stmt = $db->prepare("UPDATE message_templates
                              SET template_name = ?, template_body = ?, template_type = ?,
                                  whatsapp_template_name = ?, whatsapp_language = ?, whatsapp_variables = ?
                              WHERE id = ?");
        $stmt->execute([$name, $body, $type, $waName, $waLanguage, $waVariables, $id]);
        sendJsonResponse(true, 'Template updated successfully.');
    }

    $stmt = $db->prepare("INSERT INTO message_templates
                          (template_name, template_body, template_type,
                           whatsapp_template_name, whatsapp_language, whatsapp_variables)
                          VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$name, $body, $type, $waName, $waLanguage, $waVariables]);
    sendJsonResponse(true, 'Template added successfully.', ['id' => $db->lastInsertId()]);
} catch (PDOException $e) {
    sendJsonResponse(false, APP_DEBUG ? $e->getMessage() : 'Database error. Please try again.', [], 500);
}
