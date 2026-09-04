<?php
/**
 * AJAX - Camp Contribution List (DataTables Server-Side Processing)
 *
 * Also returns the camp's money summary and a per-category breakdown,
 * so the cards and the chart on the page refresh from the same call
 * that refreshes the table.
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

// Every caller of this endpoint already sends the token (DataTables adds
// it via d.csrf_token), so requiring it costs nothing and removes the
// inconsistency of some endpoints checking and others not.
if (!validateCSRF()) {
    echo json_encode(['error' => 'Invalid security token. Please reload the page.']);
    exit;
}

$db = getDB();

$draw        = (int) ($_POST['draw'] ?? 1);
[$start, $length] = dataTablePaging();
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
$columns     = ['contributor_name', 'category', 'item_name', 'quantity', 'amount', 'status', 'received_date'];
$orderColumn = $columns[$orderCol] ?? 'contributor_name';

$where  = ['camp_id = ?'];
$params = [$campId];

if ($searchValue !== '') {
    $where[] = "(contributor_name LIKE ? OR item_name LIKE ? OR mobile LIKE ? OR remarks LIKE ?)";
    $sp = "%$searchValue%";
    $params = array_merge($params, [$sp, $sp, $sp, $sp]);
}

if ($category !== '' && in_array($category, campContributionCategories(), true)) {
    $where[] = 'category = ?';
    $params[] = $category;
}

if ($status !== '' && in_array($status, ['Pledged', 'Received'], true)) {
    $where[] = 'status = ?';
    $params[] = $status;
}

$whereClause = 'WHERE ' . implode(' AND ', $where);

$stmt = $db->prepare("SELECT COUNT(*) FROM camp_contributions WHERE camp_id = ?");
$stmt->execute([$campId]);
$totalRecords = (int) $stmt->fetchColumn();

$stmt = $db->prepare("SELECT COUNT(*) FROM camp_contributions $whereClause");
$stmt->execute($params);
$filteredRecords = (int) $stmt->fetchColumn();

$stmt = $db->prepare(
    "SELECT id, camp_id, contributor_name, mobile, category, item_name, quantity,
            unit, amount, status, received_date, remarks
     FROM camp_contributions
     $whereClause
     ORDER BY $orderColumn $orderDir, id DESC
     LIMIT $length OFFSET $start"
);
$stmt->execute($params);
$data = $stmt->fetchAll();

// Breakdown for the "what came in" panel: one line per category.
$stmt = $db->prepare(
    "SELECT category,
            COUNT(*) AS entries,
            COALESCE(SUM(quantity), 0) AS quantity,
            COALESCE(SUM(amount), 0) AS value
     FROM camp_contributions
     WHERE camp_id = ? AND status = 'Received'
     GROUP BY category
     ORDER BY value DESC, entries DESC"
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
