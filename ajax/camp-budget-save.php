<?php
/**
 * AJAX - Camp Budget Save
 *
 * Sets the planned budget for one camp. Kept separate from
 * camp-save.php so the budget can be adjusted from the finance page
 * without reopening the whole camp form.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') sendJsonResponse(false, 'Invalid request method.', [], 405);
if (!isLoggedIn()) sendJsonResponse(false, 'Unauthorized.', [], 403);
if (!validateCSRF()) sendJsonResponse(false, 'Invalid security token.', [], 403);

$campId = (int) ($_POST['camp_id'] ?? 0);
$budget = trim($_POST['budget_amount'] ?? '');

if ($campId <= 0) {
    sendJsonResponse(false, 'Please choose a camp first.');
}

// A blank box clears the budget rather than storing zero, so the page
// can tell "no budget planned yet" from "budgeted nothing".
if ($budget !== '' && (!is_numeric($budget) || (float) $budget < 0)) {
    sendJsonResponse(false, 'Budget must be a number, or leave it blank to clear it.', [
        'errors' => ['budget_amount' => 'Enter a number such as 25000.']
    ]);
}

$budget = $budget !== '' ? (float) $budget : null;

try {
    $db = getDB();

    $stmt = $db->prepare("UPDATE blood_camps SET budget_amount = ? WHERE id = ?");
    $stmt->execute([$budget, $campId]);

    sendJsonResponse(true, $budget === null ? 'Budget cleared.' : 'Budget saved.', [
        'summary' => getCampFinanceSummary($campId)
    ]);
} catch (PDOException $e) {
    sendJsonResponse(false, APP_DEBUG ? $e->getMessage() : 'Database error.', [], 500);
}
