<?php
/**
 * AJAX - Camp Register List (DataTables Server-Side Processing)
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

$campId      = (int) ($_POST['camp_id'] ?? 0);
$status      = trim($_POST['status'] ?? '');
$bloodGroup  = trim($_POST['blood_group'] ?? '');

if ($campId <= 0) {
    echo json_encode(['draw' => $draw, 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => []]);
    exit;
}

// Whitelist sortable columns - never interpolate user input into ORDER BY.
$columns     = ['serial_no', 'donor_name', 'mobile', 'blood_group', 'status', 'registered_at'];
$orderColumn = $columns[$orderCol] ?? 'serial_no';

$where  = ['camp_id = ?'];
$params = [$campId];

if ($searchValue !== '') {
    $where[] = "(donor_name LIKE ? OR mobile LIKE ? OR address LIKE ?)";
    $searchParam = "%$searchValue%";
    $params = array_merge($params, [$searchParam, $searchParam, $searchParam]);
}

$validStatuses = ['Registered','Donated','Rejected','No Show'];
if ($status !== '' && in_array($status, $validStatuses, true)) {
    $where[] = "status = ?";
    $params[] = $status;
}

$validGroups = ['A+','A-','B+','B-','AB+','AB-','O+','O-'];
if ($bloodGroup !== '' && in_array($bloodGroup, $validGroups, true)) {
    $where[] = "blood_group = ?";
    $params[] = $bloodGroup;
}

$whereClause = 'WHERE ' . implode(' AND ', $where);

// Total for this camp (unfiltered)
$stmt = $db->prepare("SELECT COUNT(*) FROM camp_registrations WHERE camp_id = ?");
$stmt->execute([$campId]);
$totalRecords = (int) $stmt->fetchColumn();

// Filtered count
$stmt = $db->prepare("SELECT COUNT(*) FROM camp_registrations $whereClause");
$stmt->execute($params);
$filteredRecords = (int) $stmt->fetchColumn();

// Page of data
$stmt = $db->prepare(
    "SELECT id, camp_id, donor_id, serial_no, mobile, donor_name, address,
            blood_group, gender, date_of_birth, status, remarks, registered_at
     FROM camp_registrations
     $whereClause
     ORDER BY $orderColumn $orderDir
     LIMIT $length OFFSET $start"
);
$stmt->execute($params);
$data = $stmt->fetchAll();

// Per-camp tallies for the summary cards
$stmt = $db->prepare(
    "SELECT status, COUNT(*) AS total FROM camp_registrations WHERE camp_id = ? GROUP BY status"
);
$stmt->execute([$campId]);
$summary = ['Registered' => 0, 'Donated' => 0, 'Rejected' => 0, 'No Show' => 0];
while ($row = $stmt->fetch()) {
    $summary[$row['status']] = (int) $row['total'];
}

echo json_encode([
    'draw'            => $draw,
    'recordsTotal'    => $totalRecords,
    'recordsFiltered' => $filteredRecords,
    'data'            => $data,
    'summary'         => $summary
], JSON_UNESCAPED_UNICODE);
