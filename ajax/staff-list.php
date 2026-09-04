<?php
/**
 * AJAX - Staff List (DataTables Server-Side)
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
$orderCol = (int) ($_POST['order'][0]['column'] ?? 0);
$orderDir = ($_POST['order'][0]['dir'] ?? 'asc') === 'desc' ? 'DESC' : 'ASC';
$status = trim($_POST['status'] ?? '');

$columns = ['name', 'mobile', 'status', 'id'];
$orderColumn = $columns[$orderCol] ?? 'name';

$where = [];
$params = [];

if ($searchValue !== '') {
    $where[] = "(name LIKE ? OR mobile LIKE ?)";
    $sp = "%$searchValue%";
    $params = array_merge($params, [$sp, $sp]);
}

if (in_array($status, ['Active', 'Inactive'], true)) {
    $where[] = "status = ?";
    $params[] = $status;
}

$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$totalRecords = (int) $db->query("SELECT COUNT(*) FROM staff")->fetchColumn();

$stmt = $db->prepare("SELECT COUNT(*) FROM staff $whereClause");
$stmt->execute($params);
$filteredRecords = (int) $stmt->fetchColumn();

$stmt = $db->prepare("SELECT id, name, mobile, status FROM staff $whereClause ORDER BY $orderColumn $orderDir LIMIT $length OFFSET $start");
$stmt->execute($params);
$data = $stmt->fetchAll();

echo json_encode([
    'draw' => $draw,
    'recordsTotal' => $totalRecords,
    'recordsFiltered' => $filteredRecords,
    'data' => $data
], JSON_UNESCAPED_UNICODE);
