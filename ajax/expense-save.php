<?php
/**
 * AJAX - Camp Expense Save (Insert / Update)
 *
 * Records what a camp cost. 'Planned' rows are commitments not yet
 * settled; 'Paid' rows have left the tin.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') sendJsonResponse(false, 'Invalid request method.', [], 405);
if (!isLoggedIn()) sendJsonResponse(false, 'Unauthorized.', [], 403);
if (!validateCSRF()) sendJsonResponse(false, 'Invalid security token.', [], 403);

$id            = (int) ($_POST['id'] ?? 0);
$campId        = (int) ($_POST['camp_id'] ?? 0);
$category      = trim($_POST['category'] ?? 'Other');
$description   = trim($_POST['description'] ?? '');
$paidTo        = trim($_POST['paid_to'] ?? '');
$amount        = trim($_POST['amount'] ?? '');
$paymentMethod = trim($_POST['payment_method'] ?? 'Cash');
$status        = trim($_POST['status'] ?? 'Paid');
$expenseDate   = trim($_POST['expense_date'] ?? '');
$receiptNo     = trim($_POST['receipt_no'] ?? '');
$remarks       = trim($_POST['remarks'] ?? '');

$errors = [];

if ($campId <= 0) {
    sendJsonResponse(false, 'Please choose a camp first.');
}

if ($description === '') {
    $errors['description'] = 'What was this spent on?';
}

if ($amount === '' || !is_numeric($amount) || (float) $amount <= 0) {
    $errors['amount'] = 'Enter an amount greater than zero.';
}

if (!in_array($category, campExpenseCategories(), true)) {
    $category = 'Other';
}

if (!in_array($paymentMethod, campPaymentMethods(), true)) {
    $paymentMethod = 'Cash';
}

if (!in_array($status, ['Planned', 'Paid'], true)) {
    $status = 'Paid';
}

if ($errors) {
    sendJsonResponse(false, 'Please fix the errors below.', ['errors' => $errors]);
}

$paidTo      = $paidTo !== '' ? $paidTo : null;
$receiptNo   = $receiptNo !== '' ? $receiptNo : null;
$remarks     = $remarks !== '' ? $remarks : null;
$expenseDate = $expenseDate !== '' ? $expenseDate : null;
$amount      = (float) $amount;

try {
    $db = getDB();

    $stmt = $db->prepare("SELECT id FROM blood_camps WHERE id = ? LIMIT 1");
    $stmt->execute([$campId]);
    if (!$stmt->fetchColumn()) {
        sendJsonResponse(false, 'That camp no longer exists.');
    }

    if ($id > 0) {
        $stmt = $db->prepare(
            "UPDATE camp_expenses
             SET category = ?, description = ?, paid_to = ?, amount = ?, payment_method = ?,
                 status = ?, expense_date = ?, receipt_no = ?, remarks = ?
             WHERE id = ? AND camp_id = ?"
        );
        $stmt->execute([
            $category, $description, $paidTo, $amount, $paymentMethod,
            $status, $expenseDate, $receiptNo, $remarks, $id, $campId
        ]);

        sendJsonResponse(true, 'Expense updated.', ['summary' => getCampFinanceSummary($campId)]);
    }

    $stmt = $db->prepare(
        "INSERT INTO camp_expenses
            (camp_id, category, description, paid_to, amount, payment_method,
             status, expense_date, receipt_no, remarks, recorded_by)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([
        $campId, $category, $description, $paidTo, $amount, $paymentMethod,
        $status, $expenseDate, $receiptNo, $remarks, getAdminId()
    ]);

    sendJsonResponse(true, 'Expense recorded.', [
        'id'      => (int) $db->lastInsertId(),
        'summary' => getCampFinanceSummary($campId)
    ]);

} catch (PDOException $e) {
    sendJsonResponse(false, APP_DEBUG ? $e->getMessage() : 'Database error.', [], 500);
}
