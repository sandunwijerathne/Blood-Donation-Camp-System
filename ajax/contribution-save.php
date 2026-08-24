<?php
/**
 * AJAX - Camp Contribution Save (Insert / Update)
 *
 * Records what a wellwisher gave to a camp: trays of food, cases of
 * soft drinks, water bottles, or cash. Goods take a quantity and an
 * optional estimated value; cash takes an exact amount.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') sendJsonResponse(false, 'Invalid request method.', [], 405);
if (!isLoggedIn()) sendJsonResponse(false, 'Unauthorized.', [], 403);
if (!validateCSRF()) sendJsonResponse(false, 'Invalid security token.', [], 403);

$id           = (int) ($_POST['id'] ?? 0);
$campId       = (int) ($_POST['camp_id'] ?? 0);
$name         = trim($_POST['contributor_name'] ?? '');
$mobile       = trim($_POST['mobile'] ?? '');
$category     = trim($_POST['category'] ?? 'Food');
$itemName     = trim($_POST['item_name'] ?? '');
$quantity     = trim($_POST['quantity'] ?? '');
$unit         = trim($_POST['unit'] ?? '');
$amount       = trim($_POST['amount'] ?? '');
$status       = trim($_POST['status'] ?? 'Received');
$receivedDate = trim($_POST['received_date'] ?? '');
$remarks      = trim($_POST['remarks'] ?? '');

$errors = [];

if ($campId <= 0) {
    sendJsonResponse(false, 'Please choose a camp first.');
}

if ($name === '') {
    $errors['contributor_name'] = 'Who donated this? A name is required.';
}

if (!in_array($category, campContributionCategories(), true)) {
    $category = 'Food';
}

if (!in_array($status, ['Pledged', 'Received'], true)) {
    $status = 'Received';
}

// A T.P. number is optional here - plenty of wellwishers just hand
// things over - but if one is given it is stored the same way the
// donor records store it, so the two can be matched later.
if ($mobile !== '') {
    $normalized = normalizeMobile($mobile);
    if ($normalized === '') {
        $errors['mobile'] = 'Enter a valid 10-digit T.P. number, or leave it blank.';
    } else {
        $mobile = $normalized;
    }
}

if ($category === 'Cash') {
    // Cash with no amount is not a record of anything.
    if ($amount === '' || !is_numeric($amount) || (float) $amount <= 0) {
        $errors['amount'] = 'Enter the cash amount donated.';
    }
} else {
    if ($itemName === '') {
        $errors['item_name'] = 'What was donated? e.g. "Water bottles (500ml)".';
    }
    if ($amount !== '' && (!is_numeric($amount) || (float) $amount < 0)) {
        $errors['amount'] = 'Estimated value must be a number, or leave it blank.';
    }
}

if ($quantity !== '' && (!is_numeric($quantity) || (float) $quantity < 0)) {
    $errors['quantity'] = 'Quantity must be a number.';
}

if ($errors) {
    sendJsonResponse(false, 'Please fix the errors below.', ['errors' => $errors]);
}

// Cash rows carry the category label as their item name so every
// listing and export has something readable in that column.
if ($category === 'Cash') {
    $itemName = $itemName !== '' ? $itemName : 'Cash donation';
    $quantity = '';
    $unit     = '';
}

$mobile       = $mobile !== '' ? $mobile : null;
$itemName     = $itemName !== '' ? $itemName : null;
$quantity     = $quantity !== '' ? (float) $quantity : null;
$unit         = $unit !== '' ? $unit : null;
$amount       = $amount !== '' ? (float) $amount : null;
$remarks      = $remarks !== '' ? $remarks : null;
$receivedDate = $receivedDate !== '' ? $receivedDate : null;

try {
    $db = getDB();

    $stmt = $db->prepare("SELECT id FROM blood_camps WHERE id = ? LIMIT 1");
    $stmt->execute([$campId]);
    if (!$stmt->fetchColumn()) {
        sendJsonResponse(false, 'That camp no longer exists.');
    }

    if ($id > 0) {
        $stmt = $db->prepare(
            "UPDATE camp_contributions
             SET contributor_name = ?, mobile = ?, category = ?, item_name = ?,
                 quantity = ?, unit = ?, amount = ?, status = ?, received_date = ?, remarks = ?
             WHERE id = ? AND camp_id = ?"
        );
        $stmt->execute([
            $name, $mobile, $category, $itemName, $quantity, $unit,
            $amount, $status, $receivedDate, $remarks, $id, $campId
        ]);

        sendJsonResponse(true, 'Contribution updated.', ['summary' => getCampFinanceSummary($campId)]);
    }

    $stmt = $db->prepare(
        "INSERT INTO camp_contributions
            (camp_id, contributor_name, mobile, category, item_name, quantity, unit,
             amount, status, received_date, remarks, recorded_by)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([
        $campId, $name, $mobile, $category, $itemName, $quantity, $unit,
        $amount, $status, $receivedDate, $remarks, getAdminId()
    ]);

    sendJsonResponse(true, 'Contribution recorded. Thank you note worthy!', [
        'id'      => (int) $db->lastInsertId(),
        'summary' => getCampFinanceSummary($campId)
    ]);

} catch (PDOException $e) {
    sendJsonResponse(false, APP_DEBUG ? $e->getMessage() : 'Database error.', [], 500);
}
