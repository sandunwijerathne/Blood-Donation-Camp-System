<?php
/**
 * AJAX - Camp Register Save
 *
 * Marks a person in at a camp. If the T.P. number is not already a
 * donor, the donor record is created on the spot (walk-in) and linked.
 * Marking someone as "Donated" also updates their last donation date,
 * which is what drives the eligibility figures elsewhere.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') sendJsonResponse(false, 'Invalid request method.', [], 405);
if (!isLoggedIn()) sendJsonResponse(false, 'Unauthorized.', [], 403);
if (!validateCSRF()) sendJsonResponse(false, 'Invalid security token.', [], 403);

$id         = (int) ($_POST['id'] ?? 0);
$campId     = (int) ($_POST['camp_id'] ?? 0);
$mobile     = normalizeMobile($_POST['mobile'] ?? '');
$donorName  = trim($_POST['donor_name'] ?? '');
$address    = trim($_POST['address'] ?? '');
$bloodGroup = trim($_POST['blood_group'] ?? '');
$gender     = trim($_POST['gender'] ?? '');
$dob        = trim($_POST['date_of_birth'] ?? '');
$status     = trim($_POST['status'] ?? 'Registered');
$remarks    = trim($_POST['remarks'] ?? '');

$validGroups   = ['A+','A-','B+','B-','AB+','AB-','O+','O-'];
$validGenders  = ['Male','Female','Other'];
$validStatuses = ['Registered','Donated','Rejected','No Show'];

// ── Validation ───────────────────────────────────────────────
$errors = [];

if ($campId <= 0) {
    sendJsonResponse(false, 'Please choose a camp first.');
}

if ($mobile === '') {
    $errors['mobile'] = 'Enter a valid 10-digit T.P. number (e.g. 0771234567).';
}

if ($donorName === '') {
    $errors['donor_name'] = 'Name is required.';
}

if ($bloodGroup !== '' && !in_array($bloodGroup, $validGroups, true)) {
    $errors['blood_group'] = 'Please select a valid blood group.';
}

if (!in_array($gender, $validGenders, true)) {
    $gender = null;
}

if (!in_array($status, $validStatuses, true)) {
    $status = 'Registered';
}

if ($errors) {
    sendJsonResponse(false, 'Please fix the errors below.', ['errors' => $errors]);
}

$address    = $address !== '' ? $address : null;
$bloodGroup = $bloodGroup !== '' ? $bloodGroup : null;
$dob        = $dob !== '' ? $dob : null;
$remarks    = $remarks !== '' ? $remarks : null;

try {
    $db = getDB();

    // Camp must exist - we need its date to stamp donations.
    $stmt = $db->prepare("SELECT id, camp_date FROM blood_camps WHERE id = ? LIMIT 1");
    $stmt->execute([$campId]);
    $camp = $stmt->fetch();

    if (!$camp) {
        sendJsonResponse(false, 'That camp no longer exists.');
    }

    $db->beginTransaction();

    // ── Update an existing register row ──────────────────────
    if ($id > 0) {
        $stmt = $db->prepare("SELECT * FROM camp_registrations WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        if (!$row) {
            $db->rollBack();
            sendJsonResponse(false, 'That register entry no longer exists.');
        }

        // Changing the T.P. must not collide with another row at this camp.
        $stmt = $db->prepare("SELECT id FROM camp_registrations WHERE camp_id = ? AND mobile = ? AND id != ?");
        $stmt->execute([$campId, $mobile, $id]);
        if ($stmt->fetch()) {
            $db->rollBack();
            sendJsonResponse(false, 'That T.P. number is already on this camp register.', [
                'errors' => ['mobile' => 'Already registered at this camp.']
            ]);
        }

        $stmt = $db->prepare(
            "UPDATE camp_registrations
             SET mobile=?, donor_name=?, address=?, blood_group=?, gender=?,
                 date_of_birth=?, status=?, remarks=?
             WHERE id=?"
        );
        $stmt->execute([$mobile, $donorName, $address, $bloodGroup, $gender, $dob, $status, $remarks, $id]);

        $donorId = $row['donor_id'] ? (int) $row['donor_id'] : null;
    }
    // ── New register entry ───────────────────────────────────
    else {
        // Guard against the same T.P. being marked in twice.
        $stmt = $db->prepare("SELECT id FROM camp_registrations WHERE camp_id = ? AND mobile = ?");
        $stmt->execute([$campId, $mobile]);
        if ($stmt->fetch()) {
            $db->rollBack();
            sendJsonResponse(false, 'This T.P. number is already on the register for this camp.', [
                'errors' => ['mobile' => 'Already registered at this camp.']
            ]);
        }

        // Known donor, or create one for the walk-in.
        $donor = findDonorByMobile($mobile);

        if ($donor) {
            $donorId = (int) $donor['id'];

            // Fill in blanks on the donor record from what the desk collected.
            $stmt = $db->prepare(
                "UPDATE donors
                 SET address = COALESCE(NULLIF(address, ''), ?),
                     blood_group = COALESCE(NULLIF(blood_group, ''), ?),
                     date_of_birth = COALESCE(date_of_birth, ?)
                 WHERE id = ?"
            );
            $stmt->execute([$address, $bloodGroup, $dob, $donorId]);
        } else {
            $stmt = $db->prepare(
                "INSERT INTO donors (donor_name, mobile, whatsapp, address, blood_group, gender, date_of_birth, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 'Active')"
            );
            $stmt->execute([
                $donorName,
                $mobile,
                $mobile,                       // WhatsApp defaults to the same number
                $address,
                $bloodGroup ?: 'O+',           // placeholder if the desk skipped it
                $gender ?: 'Other',
                $dob
            ]);
            $donorId = (int) $db->lastInsertId();
        }

        // Continue the register's own numbering, like the paper book.
        $stmt = $db->prepare("SELECT COALESCE(MAX(serial_no), 0) + 1 FROM camp_registrations WHERE camp_id = ?");
        $stmt->execute([$campId]);
        $serialNo = (int) $stmt->fetchColumn();

        $stmt = $db->prepare(
            "INSERT INTO camp_registrations
                (camp_id, donor_id, serial_no, mobile, donor_name, address, blood_group,
                 gender, date_of_birth, status, remarks, registered_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $campId, $donorId, $serialNo, $mobile, $donorName, $address, $bloodGroup,
            $gender, $dob, $status, $remarks, getAdminId()
        ]);

        $id = (int) $db->lastInsertId();
    }

    // Marking someone as Donated stamps their last donation date.
    if ($status === 'Donated' && $donorId) {
        $stmt = $db->prepare(
            "UPDATE donors
             SET last_donation_date = ?
             WHERE id = ? AND (last_donation_date IS NULL OR last_donation_date < ?)"
        );
        $stmt->execute([$camp['camp_date'], $donorId, $camp['camp_date']]);
    }

    $db->commit();

    sendJsonResponse(true, 'Register entry saved.', ['id' => $id]);

} catch (PDOException $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    sendJsonResponse(false, APP_DEBUG ? $e->getMessage() : 'Database error. Please try again.', [], 500);
}
