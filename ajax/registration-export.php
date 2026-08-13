<?php
/**
 * AJAX - Camp Register Export (Excel / CSV)
 *
 * Produces the camp's attendance register as a spreadsheet, laid out
 * like the paper book: No, Name, Address, T.P. No, Blood Group.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

if (!isLoggedIn()) {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

$campId = (int) ($_GET['camp_id'] ?? 0);
$format = strtolower($_GET['format'] ?? 'xlsx');
$status = trim($_GET['status'] ?? '');

if ($campId <= 0) {
    http_response_code(400);
    echo 'Please choose a camp to export.';
    exit;
}

try {
    $db = getDB();

    $stmt = $db->prepare("SELECT * FROM blood_camps WHERE id = ? LIMIT 1");
    $stmt->execute([$campId]);
    $camp = $stmt->fetch();

    if (!$camp) {
        http_response_code(404);
        echo 'Camp not found.';
        exit;
    }

    $where  = ['camp_id = ?'];
    $params = [$campId];

    $validStatuses = ['Registered','Donated','Rejected','No Show'];
    if ($status !== '' && in_array($status, $validStatuses, true)) {
        $where[] = 'status = ?';
        $params[] = $status;
    }

    $stmt = $db->prepare(
        "SELECT serial_no, donor_name, address, mobile, blood_group, gender,
                status, remarks, registered_at
         FROM camp_registrations
         WHERE " . implode(' AND ', $where) . "
         ORDER BY serial_no ASC"
    );
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

} catch (PDOException $e) {
    http_response_code(500);
    echo APP_DEBUG ? $e->getMessage() : 'Unable to export the register.';
    exit;
}

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Camp Register');

// ── Title block, mirroring the register book heading ─────────
$sheet->setCellValue('A1', $camp['title']);
$sheet->mergeCells('A1:H1');
$sheet->getStyle('A1')->applyFromArray([
    'font'      => ['bold' => true, 'size' => 14],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
]);

$sheet->setCellValue('A2', 'Date: ' . formatDate($camp['camp_date']) . '    |    Location: ' . $camp['location']);
$sheet->mergeCells('A2:H2');
$sheet->getStyle('A2')->applyFromArray([
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
]);

// ── Header row ───────────────────────────────────────────────
$headers = ['No', 'Name', 'Address', 'T.P. No', 'Blood Group', 'Gender', 'Status', 'Remarks'];
$col = 'A';
foreach ($headers as $header) {
    $sheet->setCellValue($col . '4', $header);
    $col++;
}

$sheet->getStyle('A4:H4')->applyFromArray([
    'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '6366F1']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
    'borders'   => ['bottom' => ['borderStyle' => Border::BORDER_THIN]]
]);

// ── Data rows ────────────────────────────────────────────────
$rowNum = 5;
foreach ($rows as $row) {
    $sheet->setCellValue('A' . $rowNum, $row['serial_no']);
    $sheet->setCellValue('B' . $rowNum, $row['donor_name']);
    $sheet->setCellValue('C' . $rowNum, $row['address'] ?? '');
    // Force text so the leading zero on 07x numbers survives.
    $sheet->setCellValueExplicit(
        'D' . $rowNum,
        $row['mobile'],
        \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING
    );
    $sheet->setCellValue('E' . $rowNum, $row['blood_group'] ?? '');
    $sheet->setCellValue('F' . $rowNum, $row['gender'] ?? '');
    $sheet->setCellValue('G' . $rowNum, $row['status']);
    $sheet->setCellValue('H' . $rowNum, $row['remarks'] ?? '');
    $rowNum++;
}

if ($rows) {
    $sheet->getStyle('A5:H' . ($rowNum - 1))->applyFromArray([
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D1D5DB']]]
    ]);
}

// Total line
$sheet->setCellValue('A' . ($rowNum + 1), 'Total entries: ' . count($rows));
$sheet->getStyle('A' . ($rowNum + 1))->applyFromArray(['font' => ['bold' => true]]);

foreach (range('A', 'H') as $column) {
    $sheet->getColumnDimension($column)->setAutoSize(true);
}

// ── Output ───────────────────────────────────────────────────
$safeTitle = preg_replace('/[^A-Za-z0-9_-]+/', '_', $camp['title']);
$filename  = 'camp_register_' . $safeTitle . '_' . date('Y-m-d', strtotime($camp['camp_date']));

if ($format === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    $writer = new Csv($spreadsheet);
    $writer->setDelimiter(',');
    $writer->setEnclosure('"');
    $writer->setUseBOM(true); // so Excel reads the Sinhala names correctly
    $writer->save('php://output');
} else {
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '.xlsx"');
    header('Cache-Control: max-age=0');
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
}

exit;
