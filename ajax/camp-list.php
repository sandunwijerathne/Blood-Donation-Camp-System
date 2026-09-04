<?php
/**
 * AJAX - Camp List (DataTables Server-Side)
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

$draw = (int) ($_POST['draw'] ?? 1);
[$start, $length] = dataTablePaging();
$searchValue = trim($_POST['search']['value'] ?? '');
$orderCol = (int) ($_POST['order'][0]['column'] ?? 1);
$orderDir = ($_POST['order'][0]['dir'] ?? 'asc') === 'desc' ? 'DESC' : 'ASC';
$filter = trim($_POST['filter'] ?? '');

$columns = ['title', 'camp_date', 'start_time', 'location', 'status', 'id'];
$orderColumn = $columns[$orderCol] ?? 'camp_date';

$where = [];
$params = [];

if (!empty($searchValue)) {
    $where[] = "(title LIKE ? OR location LIKE ? OR description LIKE ?)";
    $sp = "%$searchValue%";
    $params = array_merge($params, [$sp, $sp, $sp]);
}

if ($filter === 'upcoming') {
    $where[] = "camp_date >= CURDATE() AND status = 'Upcoming'";
} elseif ($filter === 'past') {
    $where[] = "(camp_date < CURDATE() OR status IN ('Completed','Cancelled'))";
}

$whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

$totalRecords = (int) $db->query("SELECT COUNT(*) FROM blood_camps")->fetchColumn();

$stmt = $db->prepare("SELECT COUNT(*) FROM blood_camps $whereClause");
$stmt->execute($params);
$filteredRecords = (int) $stmt->fetchColumn();

$stmt = $db->prepare("SELECT * FROM blood_camps $whereClause ORDER BY $orderColumn $orderDir LIMIT $length OFFSET $start");
$stmt->execute($params);
$data = $stmt->fetchAll();

echo json_encode([
    'draw' => $draw,
    'recordsTotal' => $totalRecords,
    'recordsFiltered' => $filteredRecords,
    'data' => $data
], JSON_UNESCAPED_UNICODE);
