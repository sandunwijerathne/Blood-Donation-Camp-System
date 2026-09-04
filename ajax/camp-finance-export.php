<?php
/**
 * AJAX - Camp Budget Report Export (Excel / CSV)
 *
 * The accounts sheet a committee expects after a camp: a summary,
 * everything that was donated, and everything that was spent.
 *
 * Excel gets three worksheets. CSV can only hold one sheet, so that
 * format exports the section named in ?section= (default: summary).
 *
 * CSRF is deliberately NOT checked here. This endpoint is reached by a
 * plain GET navigation (window.location.href), so the browser sends no
 * token - adding a check would break the download outright. Putting the
 * token in the query string instead would leak it into browser history,
 * referrer headers and server logs, which is a worse trade for a
 * read-only endpoint that the same-origin policy already protects.
 * It is authenticated: isLoggedIn() is enforced below.
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

$campId  = (int) ($_GET['camp_id'] ?? 0);
$format  = strtolower($_GET['format'] ?? 'xlsx');
$section = strtolower($_GET['section'] ?? 'summary');

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

    $stmt = $db->prepare(
        "SELECT contributor_name, mobile, category, item_name, quantity, unit,
                amount, status, received_date, remarks
         FROM camp_contributions WHERE camp_id = ?
         ORDER BY category ASC, contributor_name ASC"
    );
    $stmt->execute([$campId]);
    $contributions = $stmt->fetchAll();

    $stmt = $db->prepare(
        "SELECT expense_date, category, description, paid_to, amount,
                payment_method, status, receipt_no, remarks
         FROM camp_expenses WHERE camp_id = ?
         ORDER BY expense_date ASC, id ASC"
    );
    $stmt->execute([$campId]);
    $expenses = $stmt->fetchAll();

    $summary = getCampFinanceSummary($campId);

} catch (PDOException $e) {
    http_response_code(500);
    echo APP_DEBUG ? $e->getMessage() : 'Unable to export the budget report.';
    exit;
}

$currency = getSetting('currency_symbol', 'Rs.');

$headerStyle = [
    'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '6366F1']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
    'borders'   => ['bottom' => ['borderStyle' => Border::BORDER_THIN]]
];

$bodyBorder = [
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D1D5DB']]]
];

/**
 * Write the camp name and date across the top of a sheet.
 */
function writeTitleBlock($sheet, array $camp, string $subtitle, string $lastColumn): void
{
    $sheet->setCellValue('A1', $camp['title']);
    $sheet->mergeCells("A1:{$lastColumn}1");
    $sheet->getStyle('A1')->applyFromArray([
        'font'      => ['bold' => true, 'size' => 14],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
    ]);

    $sheet->setCellValue('A2', $subtitle . '    |    ' . formatDate($camp['camp_date']) . '    |    ' . $camp['location']);
    $sheet->mergeCells("A2:{$lastColumn}2");
    $sheet->getStyle('A2')->applyFromArray([
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
    ]);
}

$spreadsheet = new Spreadsheet();

// ── Sheet 1: Summary ─────────────────────────────────────────
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Summary');
writeTitleBlock($sheet, $camp, 'Budget Summary', 'C');

$lines = [
    ['Planned budget',                 $summary['budget']],
    ['Cash donations received',        $summary['cash_received']],
    ['Cash pledged (not yet in hand)', $summary['cash_pledged']],
    [null, null],
    ['Expenses paid',                  $summary['expenses_paid']],
    ['Expenses planned (unpaid)',      $summary['expenses_planned']],
    ['Total camp cost',                $summary['total_cost']],
    [null, null],
    ['Balance in hand',                $summary['balance']],
    ['Budget remaining',               $summary['remaining']],
    [null, null],
    ['Donated goods - estimated value', $summary['inkind_value']],
    ['Donated goods - entries',         $summary['inkind_items']],
    ['Contributors',                    $summary['contributors']]
];

$rowNum = 4;
foreach ($lines as [$label, $value]) {
    if ($label === null) {
        $rowNum++;
        continue;
    }

    $sheet->setCellValue('A' . $rowNum, $label);

    // The last two lines are counts, not money.
    $isCount = in_array($label, ['Donated goods - entries', 'Contributors'], true);
    $sheet->setCellValue('B' . $rowNum, $isCount ? (int) $value : (float) $value);

    if (!$isCount) {
        $sheet->getStyle('B' . $rowNum)->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->setCellValue('C' . $rowNum, $currency);
    }

    if (in_array($label, ['Total camp cost', 'Balance in hand'], true)) {
        $sheet->getStyle('A' . $rowNum . ':C' . $rowNum)->applyFromArray(['font' => ['bold' => true]]);
    }

    $rowNum++;
}

$sheet->getStyle('A4:A' . $rowNum)->applyFromArray(['font' => ['bold' => false]]);
foreach (range('A', 'C') as $column) {
    $sheet->getColumnDimension($column)->setAutoSize(true);
}

// ── Sheet 2: Contributions ───────────────────────────────────
$sheet = $spreadsheet->createSheet();
$sheet->setTitle('Contributions');
writeTitleBlock($sheet, $camp, 'Donations Received', 'I');

$headers = ['Donor / Wellwisher', 'T.P. No', 'Category', 'Item', 'Qty', 'Unit', 'Value (' . $currency . ')', 'Status', 'Date'];
$col = 'A';
foreach ($headers as $header) {
    $sheet->setCellValue($col . '4', $header);
    $col++;
}
$sheet->getStyle('A4:I4')->applyFromArray($headerStyle);

$rowNum = 5;
foreach ($contributions as $row) {
    $sheet->setCellValue('A' . $rowNum, $row['contributor_name']);
    // Force text so the leading zero on 07x numbers survives.
    $sheet->setCellValueExplicit(
        'B' . $rowNum,
        (string) ($row['mobile'] ?? ''),
        \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING
    );
    $sheet->setCellValue('C' . $rowNum, $row['category']);
    $sheet->setCellValue('D' . $rowNum, $row['item_name'] ?? '');
    $sheet->setCellValue('E' . $rowNum, $row['quantity'] !== null ? (float) $row['quantity'] : '');
    $sheet->setCellValue('F' . $rowNum, $row['unit'] ?? '');
    $sheet->setCellValue('G' . $rowNum, $row['amount'] !== null ? (float) $row['amount'] : '');
    $sheet->setCellValue('H' . $rowNum, $row['status']);
    $sheet->setCellValue('I' . $rowNum, $row['received_date'] ? formatDate($row['received_date']) : '');
    $rowNum++;
}

if ($contributions) {
    $sheet->getStyle('A5:I' . ($rowNum - 1))->applyFromArray($bodyBorder);
    $sheet->getStyle('G5:G' . ($rowNum - 1))->getNumberFormat()->setFormatCode('#,##0.00');
}

$sheet->setCellValue('F' . ($rowNum + 1), 'Cash received');
$sheet->setCellValue('G' . ($rowNum + 1), $summary['cash_received']);
$sheet->setCellValue('F' . ($rowNum + 2), 'Goods (estimated)');
$sheet->setCellValue('G' . ($rowNum + 2), $summary['inkind_value']);
$sheet->getStyle('F' . ($rowNum + 1) . ':G' . ($rowNum + 2))->applyFromArray(['font' => ['bold' => true]]);
$sheet->getStyle('G' . ($rowNum + 1) . ':G' . ($rowNum + 2))->getNumberFormat()->setFormatCode('#,##0.00');

foreach (range('A', 'I') as $column) {
    $sheet->getColumnDimension($column)->setAutoSize(true);
}

// ── Sheet 3: Expenses ────────────────────────────────────────
$sheet = $spreadsheet->createSheet();
$sheet->setTitle('Expenses');
writeTitleBlock($sheet, $camp, 'Expenses', 'H');

$headers = ['Date', 'Category', 'Description', 'Paid To', 'Amount (' . $currency . ')', 'Method', 'Status', 'Receipt No'];
$col = 'A';
foreach ($headers as $header) {
    $sheet->setCellValue($col . '4', $header);
    $col++;
}
$sheet->getStyle('A4:H4')->applyFromArray($headerStyle);

$rowNum = 5;
foreach ($expenses as $row) {
    $sheet->setCellValue('A' . $rowNum, $row['expense_date'] ? formatDate($row['expense_date']) : '');
    $sheet->setCellValue('B' . $rowNum, $row['category']);
    $sheet->setCellValue('C' . $rowNum, $row['description']);
    $sheet->setCellValue('D' . $rowNum, $row['paid_to'] ?? '');
    $sheet->setCellValue('E' . $rowNum, (float) $row['amount']);
    $sheet->setCellValue('F' . $rowNum, $row['payment_method']);
    $sheet->setCellValue('G' . $rowNum, $row['status']);
    $sheet->setCellValueExplicit(
        'H' . $rowNum,
        (string) ($row['receipt_no'] ?? ''),
        \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING
    );
    $rowNum++;
}

if ($expenses) {
    $sheet->getStyle('A5:H' . ($rowNum - 1))->applyFromArray($bodyBorder);
    $sheet->getStyle('E5:E' . ($rowNum - 1))->getNumberFormat()->setFormatCode('#,##0.00');
}

$sheet->setCellValue('D' . ($rowNum + 1), 'Total paid');
$sheet->setCellValue('E' . ($rowNum + 1), $summary['expenses_paid']);
$sheet->setCellValue('D' . ($rowNum + 2), 'Total planned');
$sheet->setCellValue('E' . ($rowNum + 2), $summary['expenses_planned']);
$sheet->getStyle('D' . ($rowNum + 1) . ':E' . ($rowNum + 2))->applyFromArray(['font' => ['bold' => true]]);
$sheet->getStyle('E' . ($rowNum + 1) . ':E' . ($rowNum + 2))->getNumberFormat()->setFormatCode('#,##0.00');

foreach (range('A', 'H') as $column) {
    $sheet->getColumnDimension($column)->setAutoSize(true);
}

$spreadsheet->setActiveSheetIndex(0);

// ── Output ───────────────────────────────────────────────────
$safeTitle = preg_replace('/[^A-Za-z0-9_-]+/', '_', $camp['title']);
$filename  = 'camp_budget_' . $safeTitle . '_' . date('Y-m-d', strtotime($camp['camp_date']));

if ($format === 'csv') {
    // CSV holds one sheet only - export the one that was asked for.
    $sheetIndex = ['summary' => 0, 'contributions' => 1, 'expenses' => 2][$section] ?? 0;
    $spreadsheet->setActiveSheetIndex($sheetIndex);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '_' . $section . '.csv"');
    $writer = new Csv($spreadsheet);
    $writer->setDelimiter(',');
    $writer->setEnclosure('"');
    $writer->setUseBOM(true); // so Excel reads the Sinhala names correctly
    $writer->setSheetIndex($sheetIndex);
    $writer->save('php://output');
} else {
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '.xlsx"');
    header('Cache-Control: max-age=0');
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
}

exit;
