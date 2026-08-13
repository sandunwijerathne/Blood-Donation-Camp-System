<?php
/**
 * AJAX - Report Data
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

function reportDateFilters(string $column, array &$params): string
{
    $where = [];
    $startDate = trim($_POST['start_date'] ?? '');
    $endDate = trim($_POST['end_date'] ?? '');

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

function reportBloodGroupFilter(array &$params, string $alias = 'd'): string
{
    $bloodGroup = trim($_POST['blood_group'] ?? '');
    $validGroups = ['A+','A-','B+','B-','AB+','AB-','O+','O-'];

    if ($bloodGroup !== '' && in_array($bloodGroup, $validGroups, true)) {
        $params[] = $bloodGroup;
        return " AND $alias.blood_group = ?";
    }

    return '';
}

try {
    $db = getDB();
    $months = defined('ELIGIBLE_MONTHS') ? ELIGIBLE_MONTHS : 4;

    $donorParams = [];
    $donorDateFilter = reportDateFilters('d.created_at', $donorParams);
    $donorBloodFilter = reportBloodGroupFilter($donorParams, 'd');

    $stmt = $db->prepare("SELECT COUNT(*) FROM donors d WHERE 1=1 $donorDateFilter $donorBloodFilter");
    $stmt->execute($donorParams);
    $totalDonors = (int) $stmt->fetchColumn();

    $eligibleParams = [$months];
    $eligibleDateFilter = reportDateFilters('d.created_at', $eligibleParams);
    $eligibleBloodFilter = reportBloodGroupFilter($eligibleParams, 'd');
    $eligibleSql = "SELECT d.id, d.donor_name, d.mobile, d.blood_group, d.last_donation_date
                    FROM donors d
                    WHERE d.status = 'Active'
                    AND (d.last_donation_date IS NULL OR d.last_donation_date <= DATE_SUB(NOW(), INTERVAL ? MONTH))
                    $eligibleDateFilter
                    $eligibleBloodFilter
                    ORDER BY d.blood_group, d.donor_name";
    $stmt = $db->prepare($eligibleSql);
    $stmt->execute($eligibleParams);
    $eligibleDonors = $stmt->fetchAll();

    $messageParams = [];
    $messageDateFilter = reportDateFilters('ml.sent_at', $messageParams);
    $messageBloodFilter = reportBloodGroupFilter($messageParams, 'd');
    $stmt = $db->prepare("SELECT COUNT(*)
                          FROM message_logs ml
                          LEFT JOIN donors d ON d.id = ml.donor_id
                          WHERE ml.status = 'Sent'
                          $messageDateFilter
                          $messageBloodFilter");
    $stmt->execute($messageParams);
    $messagesSent = (int) $stmt->fetchColumn();

    $upcomingCamps = (int) $db->query("SELECT COUNT(*) FROM blood_camps WHERE camp_date >= CURDATE() AND status = 'Upcoming'")->fetchColumn();

    $bloodParams = [];
    $bloodDateFilter = reportDateFilters('d.created_at', $bloodParams);
    $bloodGroupWhere = reportBloodGroupFilter($bloodParams, 'd');
    $stmt = $db->prepare("SELECT d.blood_group, COUNT(*) AS total
                          FROM donors d
                          WHERE d.status = 'Active'
                          $bloodDateFilter
                          $bloodGroupWhere
                          GROUP BY d.blood_group");
    $stmt->execute($bloodParams);
    $bloodRows = $stmt->fetchAll();
    $bloodGroups = [];
    foreach (['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $group) {
        $bloodGroups[$group] = ['blood_group' => $group, 'total' => 0];
    }
    foreach ($bloodRows as $row) {
        $bloodGroups[$row['blood_group']] = [
            'blood_group' => $row['blood_group'],
            'total' => (int) $row['total']
        ];
    }

    $trendParams = [];
    $trendDateFilter = reportDateFilters('ml.sent_at', $trendParams);
    $trendBloodFilter = reportBloodGroupFilter($trendParams, 'd');
    $stmt = $db->prepare("SELECT DATE(ml.sent_at) AS report_date, COUNT(*) AS total
                          FROM message_logs ml
                          LEFT JOIN donors d ON d.id = ml.donor_id
                          WHERE 1=1
                          $trendDateFilter
                          $trendBloodFilter
                          GROUP BY DATE(ml.sent_at)
                          ORDER BY report_date ASC");
    $stmt->execute($trendParams);
    $messageTrend = $stmt->fetchAll();

    $recentParams = [];
    $recentDateFilter = reportDateFilters('ml.sent_at', $recentParams);
    $recentBloodFilter = reportBloodGroupFilter($recentParams, 'd');
    $stmt = $db->prepare("SELECT ml.sent_at, ml.message_type, ml.status, COALESCE(d.donor_name, 'Unknown donor') AS donor_name
                          FROM message_logs ml
                          LEFT JOIN donors d ON d.id = ml.donor_id
                          WHERE 1=1
                          $recentDateFilter
                          $recentBloodFilter
                          ORDER BY ml.sent_at DESC
                          LIMIT 10");
    $stmt->execute($recentParams);
    $recentMessages = $stmt->fetchAll();

    sendJsonResponse(true, 'Reports loaded.', [
        'summary' => [
            'total_donors' => $totalDonors,
            'eligible_donors' => count($eligibleDonors),
            'messages_sent' => $messagesSent,
            'upcoming_camps' => $upcomingCamps
        ],
        'blood_groups' => array_values($bloodGroups),
        'message_trend' => $messageTrend,
        'eligible_donors' => array_slice($eligibleDonors, 0, 10),
        'recent_messages' => $recentMessages
    ]);
} catch (PDOException $e) {
    sendJsonResponse(false, APP_DEBUG ? $e->getMessage() : 'Unable to load reports.', [], 500);
}
