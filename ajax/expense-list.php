<?php
/**
 * AJAX - Camp Expense List (DataTables Server-Side Processing)
 *
 * Returns the camp's money summary alongside the page of rows, plus a
 * per-category breakdown for the spending chart.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$db = getDB();

$draw        = (int) ($_POST['draw'] ?? 1);
$start       = (int) ($_POST['start'] ?? 0);
$length      = (int) ($_POST['length'] ?? 25);
$searchValue = trim($_POST['search']['value'] ?? '');
$orderCol    = (int) ($_POST['order'][0]['column'] ?? 0);
$orderDir    = ($_POST['order'][0]['dir'] ?? 'asc') === 'desc' ? 'DESC' : 'ASC';

$campId   = (int) ($_POST['camp_id'] ?? 0);
$category = trim($_POST['category'] ?? '');
$status   = trim($_POST['status'] ?? '');

if ($campId <= 0) {
    echo json_encode([
        'draw' => $draw, 'recordsTotal' => 0, 'recordsFiltered' => 0,
        'data' => [], 'summary' => getCampFinanceSummary(0), 'by_category' => []
    ]);
    exit;
}

// Whitelist sortable columns - never interpolate user input into ORDER BY.
$columns     = ['expense_date', 'category', 'description', 'paid_to', 'amount', 'status'];
$orderColumn = $columns[$orderCol] ?? 'expense_date';

$where  = ['camp_id = ?'];
$params = [$campId];

if ($searchValue !== '') {
    $where[] = "(description LIKE ? OR paid_to LIKE ? OR receipt_no LIKE ? OR remarks LIKE ?)";
    $sp = "%$searchValue%";
    $params = array_merge($params, [$sp, $sp, $sp, $sp]);
}

if ($category !== '' && in_array($category, campExpenseCategories(), true)) {
    $where[] = 'category = ?';
    $params[] = $category;
}

if ($status !== '' && in_array($status, ['Planned', 'Paid'], true)) {
    $where[] = 'status = ?';
    $params[] = $status;
}

$whereClause = 'WHERE ' . implode(' AND ', $where);

$stmt = $db->prepare("SELECT COUNT(*) FROM camp_expenses WHERE camp_id = ?");
$stmt->execute([$campId]);
$totalRecords = (int) $stmt->fetchColumn();

$stmt = $db->prepare("SELECT COUNT(*) FROM camp_expenses $whereClause");
$stmt->execute($params);
$filteredRecords = (int) $stmt->fetchColumn();

$stmt = $db->prepare(
    "SELECT id, camp_id, category, description, paid_to, amount, payment_method,
            status, expense_date, receipt_no, remarks
     FROM camp_expenses
     $whereClause
     ORDER BY $orderColumn $orderDir, id DESC
     LIMIT $length OFFSET $start"
);
$stmt->execute($params);
$data = $stmt->fetchAll();

// Spending by heading - drives the doughnut chart on the page.
$stmt = $db->prepare(
    "SELECT category, COUNT(*) AS entries, COALESCE(SUM(amount), 0) AS total
     FROM camp_expenses
     WHERE camp_id = ?
     GROUP BY category
     ORDER BY total DESC"
);
$stmt->execute([$campId]);
$byCategory = $stmt->fetchAll();

echo json_encode([
    'draw'            => $draw,
    'recordsTotal'    => $totalRecords,
    'recordsFiltered' => $filteredRecords,
    'data'            => $data,
    'summary'         => getCampFinanceSummary($campId),
    'by_category'     => $byCategory
], JSON_UNESCAPED_UNICODE);
