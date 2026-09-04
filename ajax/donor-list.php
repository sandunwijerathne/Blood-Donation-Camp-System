<?php
/**
 * AJAX - Donor List (DataTables Server-Side Processing)
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

// DataTables parameters
$draw = (int) ($_POST['draw'] ?? 1);
[$start, $length] = dataTablePaging();
$searchValue = trim($_POST['search']['value'] ?? '');
$orderCol = (int) ($_POST['order'][0]['column'] ?? 1);
$orderDir = ($_POST['order'][0]['dir'] ?? 'asc') === 'desc' ? 'DESC' : 'ASC';

// Filters
$bloodGroup = trim($_POST['blood_group'] ?? '');
$status = trim($_POST['status'] ?? '');

// Column mapping
$columns = ['id', 'donor_name', 'mobile', 'blood_group', 'gender', 'last_donation_date', 'status', 'id'];
$orderColumn = $columns[$orderCol] ?? 'donor_name';

// Build WHERE clause
$where = [];
$params = [];

if (!empty($searchValue)) {
    $where[] = "(donor_name LIKE ? OR mobile LIKE ? OR email LIKE ? OR whatsapp LIKE ?)";
    $searchParam = "%$searchValue%";
    $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam]);
}

if (!empty($bloodGroup)) {
    $where[] = "blood_group = ?";
    $params[] = $bloodGroup;
}

if (!empty($status)) {
    $where[] = "status = ?";
    $params[] = $status;
}

$whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Total records (unfiltered)
$totalRecords = (int) $db->query("SELECT COUNT(*) FROM donors")->fetchColumn();

// Filtered records count
$filteredSql = "SELECT COUNT(*) FROM donors $whereClause";
$stmt = $db->prepare($filteredSql);
$stmt->execute($params);
$filteredRecords = (int) $stmt->fetchColumn();

// Data query
$dataSql = "SELECT id, donor_name, mobile, whatsapp, email, blood_group, gender, 
            date_of_birth, last_donation_date, status 
            FROM donors $whereClause 
            ORDER BY $orderColumn $orderDir 
            LIMIT $length OFFSET $start";

$stmt = $db->prepare($dataSql);
$stmt->execute($params);
$data = $stmt->fetchAll();

// DataTables response
echo json_encode([
    'draw' => $draw,
    'recordsTotal' => $totalRecords,
    'recordsFiltered' => $filteredRecords,
    'data' => $data
], JSON_UNESCAPED_UNICODE);
