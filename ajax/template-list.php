<?php
/**
 * AJAX - Template List
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

if (!isset($_POST['draw'])) {
    $stmt = $db->query("SELECT id, template_name, template_body, template_type, whatsapp_template_name, whatsapp_language, whatsapp_variables, created_at FROM message_templates ORDER BY template_name");
    sendJsonResponse(true, 'Templates loaded.', ['templates' => $stmt->fetchAll()]);
}

$draw = (int) ($_POST['draw'] ?? 1);
[$start, $length] = dataTablePaging();
$searchValue = trim($_POST['search']['value'] ?? '');
$orderCol = (int) ($_POST['order'][0]['column'] ?? 0);
$orderDir = ($_POST['order'][0]['dir'] ?? 'asc') === 'desc' ? 'DESC' : 'ASC';

$columns = ['template_name', 'template_type', 'template_body', 'created_at', 'id'];
$orderColumn = $columns[$orderCol] ?? 'template_name';

$where = [];
$params = [];

if ($searchValue !== '') {
    $where[] = "(template_name LIKE ? OR template_type LIKE ? OR template_body LIKE ?)";
    $search = '%' . $searchValue . '%';
    $params = [$search, $search, $search];
}

$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$totalRecords = (int) $db->query("SELECT COUNT(*) FROM message_templates")->fetchColumn();

$stmt = $db->prepare("SELECT COUNT(*) FROM message_templates $whereClause");
$stmt->execute($params);
$filteredRecords = (int) $stmt->fetchColumn();

$stmt = $db->prepare("SELECT id, template_name, template_body, template_type, whatsapp_template_name, whatsapp_language, whatsapp_variables, created_at
                      FROM message_templates
                      $whereClause
                      ORDER BY $orderColumn $orderDir
                      LIMIT $length OFFSET $start");
$stmt->execute($params);

echo json_encode([
    'draw' => $draw,
    'recordsTotal' => $totalRecords,
    'recordsFiltered' => $filteredRecords,
    'data' => $stmt->fetchAll()
], JSON_UNESCAPED_UNICODE);
