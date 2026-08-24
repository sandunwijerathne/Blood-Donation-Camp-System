<?php
/**
 * AJAX - Donor Save (Insert / Update)
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

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

// Get inputs
$id = (int) ($_POST['id'] ?? 0);
$donorName = trim($_POST['donor_name'] ?? '');
$mobile = trim($_POST['mobile'] ?? '');
$whatsapp = trim($_POST['whatsapp'] ?? '');
$email = trim($_POST['email'] ?? '');
$address = trim($_POST['address'] ?? '');
$bloodGroup = trim($_POST['blood_group'] ?? '');
$gender = trim($_POST['gender'] ?? '');
$dob = trim($_POST['date_of_birth'] ?? '');
$lastDonation = trim($_POST['last_donation_date'] ?? '');
$status = trim($_POST['status'] ?? 'Active');

// Validation
$errors = [];

if (empty($donorName)) {
    $errors['donor_name'] = 'Name is required.';
}

// The T.P. number is this system's unique identifier for a person, so it
// must be stored in one canonical shape. Without this, "077 821 1176"
// entered here would never match the "0778211176" the camp register
// looks up, and the same donor would be created twice.
if (empty($mobile)) {
    $errors['mobile'] = 'Mobile number is required.';
} else {
    $normalized = normalizeMobile($mobile);
    if ($normalized === '') {
        $errors['mobile'] = 'Enter a valid 10-digit number, e.g. 0771234567.';
    } else {
        $mobile = $normalized;
    }
}

if ($whatsapp !== '') {
    $normalizedWa = normalizeMobile($whatsapp);
    if ($normalizedWa === '') {
        $errors['whatsapp'] = 'Enter a valid 10-digit number, e.g. 0771234567.';
    } else {
        $whatsapp = $normalizedWa;
    }
}

if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = 'Invalid email address.';
}

$validGroups = ['A+','A-','B+','B-','AB+','AB-','O+','O-'];
if (empty($bloodGroup) || !in_array($bloodGroup, $validGroups)) {
    $errors['blood_group'] = 'Please select a valid blood group.';
}

$validGenders = ['Male','Female','Other'];
if (empty($gender) || !in_array($gender, $validGenders)) {
    $errors['gender'] = 'Please select a gender.';
}

if (!in_array($status, ['Active','Inactive'])) {
    $status = 'Active';
}

if (!empty($errors)) {
    sendJsonResponse(false, 'Please fix the errors below.', ['errors' => $errors]);
}

try {
    $db = getDB();

    // Check duplicate mobile (exclude current record if editing)
    $checkSql = "SELECT id FROM donors WHERE mobile = ?";
    $checkParams = [$mobile];
    if ($id > 0) {
        $checkSql .= " AND id != ?";
        $checkParams[] = $id;
    }
    $stmt = $db->prepare($checkSql);
    $stmt->execute($checkParams);
    if ($stmt->fetch()) {
        sendJsonResponse(false, 'A donor with this mobile number already exists.', ['errors' => ['mobile' => 'Duplicate mobile number.']]);
    }

    // Prepare date values
    $dob = !empty($dob) ? $dob : null;
    $lastDonation = !empty($lastDonation) ? $lastDonation : null;
    $whatsapp = !empty($whatsapp) ? $whatsapp : null;
    $email = !empty($email) ? $email : null;
    $address = !empty($address) ? $address : null;

    if ($id > 0) {
        // UPDATE
        $stmt = $db->prepare(
            "UPDATE donors SET donor_name=?, mobile=?, whatsapp=?, email=?, address=?, 
             blood_group=?, gender=?, date_of_birth=?, last_donation_date=?, status=? 
             WHERE id=?"
        );
        $stmt->execute([$donorName, $mobile, $whatsapp, $email, $address, $bloodGroup, $gender, $dob, $lastDonation, $status, $id]);
        sendJsonResponse(true, 'Donor updated successfully.');
    } else {
        // INSERT
        $stmt = $db->prepare(
            "INSERT INTO donors (donor_name, mobile, whatsapp, email, address, blood_group, gender, date_of_birth, last_donation_date, status) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([$donorName, $mobile, $whatsapp, $email, $address, $bloodGroup, $gender, $dob, $lastDonation, $status]);
        sendJsonResponse(true, 'Donor added successfully.', ['id' => $db->lastInsertId()]);
    }

} catch (PDOException $e) {
    sendJsonResponse(false, APP_DEBUG ? $e->getMessage() : 'Database error. Please try again.', [], 500);
}
