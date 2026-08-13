<?php
/**
 * AJAX - Donor Export (Excel / CSV)
 * 
 * Uses PhpSpreadsheet for XLSX, native PHP for CSV.
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
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\Border;

if (!isLoggedIn()) {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

$format = strtolower($_GET['format'] ?? 'xlsx');
$bloodGroup = trim($_GET['blood_group'] ?? '');
$status = trim($_GET['status'] ?? '');

// Build query
$where = [];
$params = [];

if (!empty($bloodGroup)) {
    $where[] = "blood_group = ?";
    $params[] = $bloodGroup;
}

if (!empty($status)) {
    $where[] = "status = ?";
    $params[] = $status;
}

$whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

$db = getDB();
$stmt = $db->prepare("SELECT donor_name, mobile, whatsapp, email, address, blood_group, gender, date_of_birth, last_donation_date, status FROM donors $whereClause ORDER BY donor_name ASC");
$stmt->execute($params);
$donors = $stmt->fetchAll();

// Create spreadsheet
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Donors');

// Header row
$headers = ['Name', 'Mobile', 'WhatsApp', 'Email', 'Address', 'Blood Group', 'Gender', 'Date of Birth', 'Last Donation', 'Status'];
$col = 'A';
foreach ($headers as $header) {
    $sheet->setCellValue($col . '1', $header);
    $col++;
}

// Style header row
$headerStyle = [
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '6366F1']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
    'borders' => ['bottom' => ['borderStyle' => Border::BORDER_THIN]]
];
$sheet->getStyle('A1:J1')->applyFromArray($headerStyle);

// Data rows
$rowNum = 2;
foreach ($donors as $donor) {
    $sheet->setCellValue('A' . $rowNum, $donor['donor_name']);
    $sheet->setCellValue('B' . $rowNum, $donor['mobile']);
    $sheet->setCellValue('C' . $rowNum, $donor['whatsapp'] ?? '');
    $sheet->setCellValue('D' . $rowNum, $donor['email'] ?? '');
    $sheet->setCellValue('E' . $rowNum, $donor['address'] ?? '');
    $sheet->setCellValue('F' . $rowNum, $donor['blood_group']);
    $sheet->setCellValue('G' . $rowNum, $donor['gender']);
    $sheet->setCellValue('H' . $rowNum, $donor['date_of_birth'] ?? '');
    $sheet->setCellValue('I' . $rowNum, $donor['last_donation_date'] ?? '');
    $sheet->setCellValue('J' . $rowNum, $donor['status']);
    $rowNum++;
}

// Auto-size columns
foreach (range('A', 'J') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// Output
$filename = 'donors_export_' . date('Y-m-d_His');

if ($format === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    $writer = new Csv($spreadsheet);
    $writer->setDelimiter(',');
    $writer->setEnclosure('"');
    $writer->save('php://output');
} else {
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '.xlsx"');
    header('Cache-Control: max-age=0');
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
}

exit;
