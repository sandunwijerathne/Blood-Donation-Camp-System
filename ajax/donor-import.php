<?php
/**
 * AJAX - Donor Import from Excel
 * 
 * Uses PhpSpreadsheet to read .xlsx, .xls, .csv files.
 * Skips duplicates by mobile number.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(false, 'Invalid request method.', [], 405);
}
if (!isLoggedIn()) {
    sendJsonResponse(false, 'Unauthorized.', [], 403);
}
if (!validateCSRF()) {
    sendJsonResponse(false, 'Invalid security token.', [], 403);
}

// Check file upload
if (!isset($_FILES['excel_file']) || $_FILES['excel_file']['error'] !== UPLOAD_ERR_OK) {
    sendJsonResponse(false, 'Please select a valid file to upload.');
}

$file = $_FILES['excel_file'];
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$allowed = ['xlsx', 'xls', 'csv'];

if (!in_array($ext, $allowed)) {
    sendJsonResponse(false, 'Invalid file format. Allowed: .xlsx, .xls, .csv');
}

// Move to uploads
$uploadDir = EXCEL_UPLOAD_PATH;
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$filename = 'import_' . date('Y-m-d_His') . '.' . $ext;
$filepath = $uploadDir . '/' . $filename;

if (!move_uploaded_file($file['tmp_name'], $filepath)) {
    sendJsonResponse(false, 'Failed to upload file.');
}

try {
    $spreadsheet = IOFactory::load($filepath);
    $sheet = $spreadsheet->getActiveSheet();
    $rows = $sheet->toArray(null, true, true, true);

    $db = getDB();
    $validGroups = ['A+','A-','B+','B-','AB+','AB-','O+','O-'];
    $validGenders = ['Male','Female','Other'];

    $imported = 0;
    $skipped = 0;
    $failed = 0;
    $isFirstRow = true;

    foreach ($rows as $row) {
        // Skip header row
        if ($isFirstRow) {
            $isFirstRow = false;
            // Check if first row looks like a header
            $firstCell = trim($row['A'] ?? '');
            if (stripos($firstCell, 'name') !== false || stripos($firstCell, 'donor') !== false || $firstCell === '') {
                continue;
            }
        }

        // Map columns: A=Name, B=Mobile, C=WhatsApp, D=Email, E=Address, F=Blood Group, G=Gender, H=DOB, I=Last Donation
        $donorName = trim($row['A'] ?? '');
        $mobile = trim($row['B'] ?? '');
        $whatsapp = trim($row['C'] ?? '');
        $email = trim($row['D'] ?? '');
        $address = trim($row['E'] ?? '');
        $bloodGroup = strtoupper(trim($row['F'] ?? ''));
        $gender = ucfirst(strtolower(trim($row['G'] ?? '')));
        $dob = trim($row['H'] ?? '');
        $lastDonation = trim($row['I'] ?? '');

        // Validate required fields
        if (empty($donorName) || empty($mobile)) {
            $failed++;
            continue;
        }

        // Validate blood group
        if (!in_array($bloodGroup, $validGroups)) {
            $failed++;
            continue;
        }

        // Validate gender
        if (!in_array($gender, $validGenders)) {
            $gender = 'Other'; // Default
        }

        // Normalise the T.P. number the same way the camp register does,
        // so the same person cannot end up stored under two formats.
        $mobile = normalizeMobile($mobile);
        if (empty($mobile)) {
            $failed++;
            continue;
        }

        // Check duplicate
        $stmt = $db->prepare("SELECT id FROM donors WHERE mobile = ?");
        $stmt->execute([$mobile]);
        if ($stmt->fetch()) {
            $skipped++;
            continue;
        }

        // Parse dates
        $dobParsed = null;
        if (!empty($dob)) {
            $ts = strtotime($dob);
            if ($ts) $dobParsed = date('Y-m-d', $ts);
        }

        $lastDonationParsed = null;
        if (!empty($lastDonation)) {
            $ts = strtotime($lastDonation);
            if ($ts) $lastDonationParsed = date('Y-m-d', $ts);
        }

        // Insert
        try {
            $stmt = $db->prepare(
                "INSERT INTO donors (donor_name, mobile, whatsapp, email, address, blood_group, gender, date_of_birth, last_donation_date, status) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Active')"
            );
            $whatsapp = $whatsapp !== '' ? normalizeMobile($whatsapp) : '';

            $stmt->execute([
                $donorName, $mobile,
                !empty($whatsapp) ? $whatsapp : null,
                !empty($email) ? $email : null,
                !empty($address) ? $address : null,
                $bloodGroup, $gender, $dobParsed, $lastDonationParsed
            ]);
            $imported++;
        } catch (PDOException $e) {
            $failed++;
        }
    }

    // Clean up uploaded file
    @unlink($filepath);

    sendJsonResponse(true, "Import complete: $imported imported, $skipped skipped, $failed failed.", [
        'imported' => $imported,
        'skipped' => $skipped,
        'failed' => $failed
    ]);

} catch (\Exception $e) {
    @unlink($filepath);
    sendJsonResponse(false, APP_DEBUG ? 'Import error: ' . $e->getMessage() : 'Failed to process the Excel file.', [], 500);
}
