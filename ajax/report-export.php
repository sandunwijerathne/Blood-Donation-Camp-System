<?php
/**
 * AJAX - Report Export
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
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

if (!isLoggedIn()) {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

function exportDateFilters(string $column, array &$params): string
{
    $where = [];
    $startDate = trim($_GET['start_date'] ?? '');
    $endDate = trim($_GET['end_date'] ?? '');

    if ($startDate !== '') {
        $where[] = "DATE($column) >= ?";
        $params[] = $startDate;
    }

    if ($endDate !== '') {
        $where[] = "DATE($column) <= ?";
        $params[] = $endDate;
    }

    return $where ? ' AND ' . implode(' AND ', $where) : '';
}

function exportBloodGroupFilter(array &$params, string $alias = 'd'): string
{
    $bloodGroup = trim($_GET['blood_group'] ?? '');
    $validGroups = ['A+','A-','B+','B-','AB+','AB-','O+','O-'];

    if ($bloodGroup !== '' && in_array($bloodGroup, $validGroups, true)) {
        $params[] = $bloodGroup;
        return " AND $alias.blood_group = ?";
    }

    return '';
}

function writeRows($sheet, array $headers, array $rows): void
{
    $col = 'A';
    foreach ($headers as $header) {
        $sheet->setCellValue($col . '1', $header);
        $col++;
    }

    $lastCol = chr(ord('A') + count($headers) - 1);
    $sheet->getStyle('A1:' . $lastCol . '1')->applyFromArray([
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '6366F1']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        'borders' => ['bottom' => ['borderStyle' => Border::BORDER_THIN]]
    ]);

    $rowNum = 2;
    foreach ($rows as $row) {
        $col = 'A';
        foreach ($row as $value) {
            $sheet->setCellValue($col . $rowNum, $value);
            $col++;
        }
        $rowNum++;
    }

    foreach (range('A', $lastCol) as $column) {
        $sheet->getColumnDimension($column)->setAutoSize(true);
    }
}

$db = getDB();
$report = strtolower($_GET['report'] ?? 'summary');
$format = strtolower($_GET['format'] ?? 'xlsx');
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$months = defined('ELIGIBLE_MONTHS') ? ELIGIBLE_MONTHS : 4;

try {
    if ($report === 'eligible') {
        $params = [$months];
        $dateFilter = exportDateFilters('d.created_at', $params);
        $bloodFilter = exportBloodGroupFilter($params, 'd');
        $stmt = $db->prepare("SELECT d.donor_name, d.mobile, d.whatsapp, d.email, d.blood_group, d.last_donation_date
                              FROM donors d
                              WHERE d.status = 'Active'
                              AND (d.last_donation_date IS NULL OR d.last_donation_date <= DATE_SUB(NOW(), INTERVAL ? MONTH))
                              $dateFilter
                              $bloodFilter
                              ORDER BY d.blood_group, d.donor_name");
        $stmt->execute($params);
        $rows = array_map(fn($row) => [
            $row['donor_name'],
            $row['mobile'],
            $row['whatsapp'] ?? '',
            $row['email'] ?? '',
            $row['blood_group'],
            $row['last_donation_date'] ?? 'Never'
        ], $stmt->fetchAll());
        $sheet->setTitle('Eligible Donors');
        writeRows($sheet, ['Name', 'Mobile', 'WhatsApp', 'Email', 'Blood Group', 'Last Donation'], $rows);
    } elseif ($report === 'blood_groups') {
        $params = [];
        $dateFilter = exportDateFilters('d.created_at', $params);
        $bloodFilter = exportBloodGroupFilter($params, 'd');
        $stmt = $db->prepare("SELECT d.blood_group, d.status, COUNT(*) AS total
                              FROM donors d
                              WHERE 1=1
                              $dateFilter
                              $bloodFilter
                              GROUP BY d.blood_group, d.status
                              ORDER BY d.blood_group, d.status");
        $stmt->execute($params);
        $rows = array_map(fn($row) => [$row['blood_group'], $row['status'], (int) $row['total']], $stmt->fetchAll());
        $sheet->setTitle('Blood Groups');
        writeRows($sheet, ['Blood Group', 'Status', 'Total'], $rows);
    } elseif ($report === 'messages') {
        $params = [];
        $dateFilter = exportDateFilters('ml.sent_at', $params);
        $bloodFilter = exportBloodGroupFilter($params, 'd');
        $stmt = $db->prepare("SELECT ml.sent_at, COALESCE(d.donor_name, 'Unknown donor') AS donor_name,
                                     ml.message_type, ml.mobile, ml.message, ml.status
                              FROM message_logs ml
                              LEFT JOIN donors d ON d.id = ml.donor_id
                              WHERE 1=1
                              $dateFilter
                              $bloodFilter
                              ORDER BY ml.sent_at DESC");
        $stmt->execute($params);
        $rows = array_map(fn($row) => [
            $row['sent_at'],
            $row['donor_name'],
            $row['message_type'],
            $row['mobile'],
            $row['message'],
            $row['status']
        ], $stmt->fetchAll());
        $sheet->setTitle('Messages');
        writeRows($sheet, ['Date', 'Donor', 'Channel', 'Mobile', 'Message', 'Status'], $rows);
    } else {
        $donorParams = [];
        $donorDateFilter = exportDateFilters('d.created_at', $donorParams);
        $donorBloodFilter = exportBloodGroupFilter($donorParams, 'd');
        $stmt = $db->prepare("SELECT COUNT(*) FROM donors d WHERE 1=1 $donorDateFilter $donorBloodFilter");
        $stmt->execute($donorParams);
        $totalDonors = (int) $stmt->fetchColumn();

        $eligibleParams = [$months];
        $eligibleDateFilter = exportDateFilters('d.created_at', $eligibleParams);
        $eligibleBloodFilter = exportBloodGroupFilter($eligibleParams, 'd');
        $stmt = $db->prepare("SELECT COUNT(*)
                              FROM donors d
                              WHERE d.status = 'Active'
                              AND (d.last_donation_date IS NULL OR d.last_donation_date <= DATE_SUB(NOW(), INTERVAL ? MONTH))
                              $eligibleDateFilter
                              $eligibleBloodFilter");
        $stmt->execute($eligibleParams);
        $eligible = (int) $stmt->fetchColumn();

        $messageParams = [];
        $messageDateFilter = exportDateFilters('ml.sent_at', $messageParams);
        $messageBloodFilter = exportBloodGroupFilter($messageParams, 'd');
        $stmt = $db->prepare("SELECT COUNT(*)
                              FROM message_logs ml
                              LEFT JOIN donors d ON d.id = ml.donor_id
                              WHERE ml.status = 'Sent'
                              $messageDateFilter
                              $messageBloodFilter");
        $stmt->execute($messageParams);
        $messages = (int) $stmt->fetchColumn();

        $camps = (int) $db->query("SELECT COUNT(*) FROM blood_camps WHERE camp_date >= CURDATE() AND status = 'Upcoming'")->fetchColumn();
        $sheet->setTitle('Summary');
        writeRows($sheet, ['Metric', 'Value'], [
            ['Total Donors', $totalDonors],
            ['Eligible Donors', $eligible],
            ['Messages Sent', $messages],
            ['Upcoming Camps', $camps]
        ]);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo APP_DEBUG ? $e->getMessage() : 'Unable to export report.';
    exit;
}

$filename = 'report_' . $report . '_' . date('Y-m-d_His');

if ($format === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    $writer = new Csv($spreadsheet);
    $writer->save('php://output');
} else {
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '.xlsx"');
    header('Cache-Control: max-age=0');
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
}

exit;
