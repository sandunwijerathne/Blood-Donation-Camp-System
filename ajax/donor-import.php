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

// .xls is deliberately NOT accepted. The legacy OLE reader that handles
// it is the subject of CVE-2026-59933 (sector-chain self-loop, memory
// exhaustion) in the installed PhpSpreadsheet 1.30.5. Re-save old .xls
// files as .xlsx or CSV before importing.
$allowed = ['xlsx', 'csv'];

if (!in_array($ext, $allowed, true)) {
    sendJsonResponse(false, 'Invalid file format. Allowed: .xlsx or .csv. Re-save .xls files as .xlsx first.');
}

// Explicit size cap. php.ini's upload_max_filesize is the only other
// limit, and it is set by the host rather than by this application.
if ($file['size'] > IMPORT_MAX_BYTES) {
    sendJsonResponse(false, sprintf(
        'File is %.1f MB. The limit is %d MB.',
        $file['size'] / 1048576,
        IMPORT_MAX_BYTES / 1048576
    ));
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
    // Pin the reader instead of using IOFactory::load(), which sniffs the
    // file's CONTENT and picks a reader accordingly - so a Gnumeric or OLE
    // payload named ".xlsx" would still reach a vulnerable reader despite
    // the extension whitelist above. Naming the reader means the Xls and
    // Gnumeric readers are never instantiated at all, which closes
    // CVE-2026-59933 and CVE-2026-59932.
    $reader = IOFactory::createReader($ext === 'csv' ? 'Csv' : 'Xlsx');

    // Data only: no styles, and no formula evaluation. That also avoids
    // CVE-2026-59931, an SSRF through the WEBSERVICE() formula function.
    $reader->setReadDataOnly(true);

    $spreadsheet = $reader->load($filepath);
    $sheet = $spreadsheet->getActiveSheet();
    $rows = $sheet->toArray(null, true, true, true);

    $db = getDB();
    $validGroups = ['A+','A-','B+','B-','AB+','AB-','O+','O-'];
    $validGenders = ['Male','Female','Other'];

    $imported = 0;
    $skipped = 0;
    $failed = 0;
    $encodingErrors = 0;
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

        // Reject text that arrived as literal "?" characters. That happens
        // when a CSV is re-saved by a spreadsheet program in the Windows
        // ANSI codepage, which cannot represent Sinhala - every character
        // is replaced by "?" before the file is even uploaded. Storing it
        // would silently destroy the name, so refuse the row instead.
        if (preg_match('/^[\?\s\.\-\/]+$/u', $donorName)) {
            $failed++;
            $encodingErrors++;
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

    $message = "Import complete: $imported imported, $skipped skipped, $failed failed.";

    if ($encodingErrors > 0) {
        $message .= " $encodingErrors row(s) had names saved as \"?\" - the file was saved in the wrong"
                  . " character set. Re-save it as \"CSV UTF-8\" and import again.";
    }

    sendJsonResponse(true, $message, [
        'imported' => $imported,
        'skipped' => $skipped,
        'failed' => $failed,
        'encoding_errors' => $encodingErrors
    ]);

} catch (\Exception $e) {
    @unlink($filepath);
    sendJsonResponse(false, APP_DEBUG ? 'Import error: ' . $e->getMessage() : 'Failed to process the Excel file.', [], 500);
}
