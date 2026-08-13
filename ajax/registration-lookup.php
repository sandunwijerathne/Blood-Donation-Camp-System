<?php
/**
 * AJAX - Camp Register Lookup by T.P. Number
 *
 * Given a T.P. number, tells the register desk whether the person is
 * already a known donor, and whether they have already been marked in
 * at this camp.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') sendJsonResponse(false, 'Invalid request method.', [], 405);
if (!isLoggedIn()) sendJsonResponse(false, 'Unauthorized.', [], 403);
if (!validateCSRF()) sendJsonResponse(false, 'Invalid security token.', [], 403);

$campId = (int) ($_POST['camp_id'] ?? 0);
$mobile = normalizeMobile($_POST['mobile'] ?? '');

if ($campId <= 0) {
    sendJsonResponse(false, 'Please choose a camp first.');
}

if ($mobile === '') {
    sendJsonResponse(false, 'Enter a valid 10-digit T.P. number (e.g. 0771234567).');
}

try {
    $db = getDB();

    // Already on this camp's register?
    $stmt = $db->prepare("SELECT * FROM camp_registrations WHERE camp_id = ? AND mobile = ? LIMIT 1");
    $stmt->execute([$campId, $mobile]);
    $existing = $stmt->fetch();

    if ($existing) {
        sendJsonResponse(true, 'Already marked in at this camp.', [
            'state'        => 'already_registered',
            'mobile'       => $mobile,
            'registration' => $existing
        ]);
    }

    // Known donor?
    $donor = findDonorByMobile($mobile);

    if ($donor) {
        // How many times has this person given blood with us before?
        $stmt = $db->prepare("SELECT COUNT(*) FROM camp_registrations WHERE donor_id = ? AND status = 'Donated'");
        $stmt->execute([$donor['id']]);
        $donationCount = (int) $stmt->fetchColumn();

        sendJsonResponse(true, 'Existing donor found.', [
            'state'          => 'known_donor',
            'mobile'         => $mobile,
            'donor'          => $donor,
            'donation_count' => $donationCount
        ]);
    }

    // Walk-in - the desk will fill in the short form.
    sendJsonResponse(true, 'New donor. Please fill in the details.', [
        'state'  => 'new_donor',
        'mobile' => $mobile
    ]);

} catch (PDOException $e) {
    sendJsonResponse(false, APP_DEBUG ? $e->getMessage() : 'Database error. Please try again.', [], 500);
}
