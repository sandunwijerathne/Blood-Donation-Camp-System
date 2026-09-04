<?php
/**
 * AJAX - Message Log (DataTables Server-Side)
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

$draw = (int) ($_POST['draw'] ?? 1);
$start = max(0, (int) ($_POST['start'] ?? 0));
$length = (int) ($_POST['length'] ?? 25);
$length = $length > 0 ? min($length, 100) : 25;
$searchValue = trim($_POST['search']['value'] ?? '');
$orderCol = (int) ($_POST['order'][0]['column'] ?? 0);
$orderDir = ($_POST['order'][0]['dir'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';
$messageType = trim($_POST['message_type'] ?? '');
$status = trim($_POST['status'] ?? '');

$columns = ['ml.sent_at', 'COALESCE(d.donor_name, st.name)', 'ml.message_type', 'ml.mobile', 'ml.message', 'ml.status'];
$orderColumn = $columns[$orderCol] ?? 'ml.sent_at';

$where = [];
$params = [];

if ($searchValue !== '') {
    $where[] = "(d.donor_name LIKE ? OR st.name LIKE ? OR ml.mobile LIKE ? OR ml.message LIKE ? OR ml.status LIKE ?)";
    $search = '%' . $searchValue . '%';
    $params = array_merge($params, [$search, $search, $search, $search, $search]);
}

if (in_array($messageType, ['WhatsApp', 'SMS'], true)) {
    $where[] = "ml.message_type = ?";
    $params[] = $messageType;
}

if (in_array($status, ['Sent', 'Pending', 'Failed'], true)) {
    $where[] = "ml.status = ?";
    $params[] = $status;
}

$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$totalRecords = (int) $db->query("SELECT COUNT(*) FROM message_logs")->fetchColumn();

$countSql = "SELECT COUNT(*)
             FROM message_logs ml
             LEFT JOIN donors d ON d.id = ml.donor_id
             LEFT JOIN staff  st ON st.id = ml.staff_id
             $whereClause";
$stmt = $db->prepare($countSql);
$stmt->execute($params);
$filteredRecords = (int) $stmt->fetchColumn();

$sql = "SELECT ml.id, ml.sent_at, ml.message_type, ml.mobile, ml.message, ml.status,
               COALESCE(d.donor_name, st.name, 'Unknown recipient') AS donor_name,
               CASE WHEN ml.staff_id IS NOT NULL THEN 'Staff' ELSE 'Donor' END AS recipient_kind
        FROM message_logs ml
        LEFT JOIN donors d ON d.id = ml.donor_id
        LEFT JOIN staff  st ON st.id = ml.staff_id
        $whereClause
        ORDER BY $orderColumn $orderDir
        LIMIT $length OFFSET $start";
$stmt = $db->prepare($sql);
$stmt->execute($params);

echo json_encode([
    'draw' => $draw,
    'recordsTotal' => $totalRecords,
    'recordsFiltered' => $filteredRecords,
    'data' => $stmt->fetchAll()
], JSON_UNESCAPED_UNICODE);
