<?php
/**
 * Header Template
 * 
 * Includes sidebar navigation, top navbar, CSS/font imports.
 * Set $pageTitle before including this file.
 */

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';
checkLogin();

$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$appName = getSetting('app_name', APP_NAME);
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Blood Donor Management System - Manage donors, blood camps, and notifications">
    <title><?= sanitize($pageTitle ?? 'Dashboard') ?> — <?= sanitize($appName) ?></title>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- DataTables Bootstrap 5 -->
    <link href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/select/1.7.0/css/select.bootstrap5.min.css" rel="stylesheet">

    <!-- Font Awesome 6 -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">

    <!-- SweetAlert2 -->
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">

    <!-- Custom CSS -->
    <link href="<?= BASE_URL ?>/assets/css/style.css" rel="stylesheet">

    <!-- Global JS Variables for AJAX -->
    <script>
        window.CSRF_TOKEN = '<?= csrfToken() ?>';
        window.BASE_URL = '<?= BASE_URL ?>';
    </script>

    <!-- Global JS Libraries -->
    <!-- jQuery 3.7 -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

    <!-- Bootstrap 5.3 Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <!-- DataTables + Extensions -->
    <script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/select/1.7.0/js/dataTables.select.min.js"></script>

    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

    <!-- Custom JS -->
    <script src="<?= BASE_URL ?>/assets/js/app.js"></script>
</head>
<body>

<!-- ═══════════════ SIDEBAR ═══════════════ -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-logo">
            <i class="fas fa-tint"></i>
            <span class="sidebar-brand"><?= sanitize($appName) ?></span>
        </div>
        <button class="sidebar-close d-lg-none" id="sidebarClose">
            <i class="fas fa-times"></i>
        </button>
    </div>

    <nav class="sidebar-nav">
        <div class="nav-section">
            <span class="nav-section-title">Main</span>
            <a href="<?= BASE_URL ?>/admin/dashboard.php" class="nav-link <?= $currentPage === 'dashboard' ? 'active' : '' ?>">
                <i class="fas fa-chart-pie"></i>
                <span>Dashboard</span>
            </a>
        </div>

        <div class="nav-section">
            <span class="nav-section-title">Management</span>
            <a href="<?= BASE_URL ?>/admin/donors.php" class="nav-link <?= $currentPage === 'donors' || $currentPage === 'donor-add' || $currentPage === 'donor-edit' ? 'active' : '' ?>">
                <i class="fas fa-users"></i>
                <span>Donors</span>
            </a>
            <a href="<?= BASE_URL ?>/admin/camps.php" class="nav-link <?= $currentPage === 'camps' ? 'active' : '' ?>">
                <i class="fas fa-campground"></i>
                <span>Blood Camps</span>
            </a>
            <a href="<?= BASE_URL ?>/admin/camp-register.php" class="nav-link <?= $currentPage === 'camp-register' ? 'active' : '' ?>">
                <i class="fas fa-clipboard-list"></i>
                <span>Camp Register</span>
            </a>
            <a href="<?= BASE_URL ?>/admin/camp-finance.php" class="nav-link <?= $currentPage === 'camp-finance' ? 'active' : '' ?>">
                <i class="fas fa-hand-holding-heart"></i>
                <span>Budget &amp; Donations</span>
            </a>
        </div>

        <div class="nav-section">
            <span class="nav-section-title">Communication</span>
            <a href="<?= BASE_URL ?>/admin/messages.php" class="nav-link <?= $currentPage === 'messages' ? 'active' : '' ?>">
                <i class="fas fa-paper-plane"></i>
                <span>Messages</span>
            </a>
            <a href="<?= BASE_URL ?>/admin/templates.php" class="nav-link <?= $currentPage === 'templates' ? 'active' : '' ?>">
                <i class="fas fa-file-alt"></i>
                <span>Templates</span>
            </a>
            <a href="<?= BASE_URL ?>/admin/emergency.php" class="nav-link <?= $currentPage === 'emergency' ? 'active' : '' ?>">
                <i class="fas fa-exclamation-triangle"></i>
                <span>Emergency</span>
            </a>
        </div>

        <div class="nav-section">
            <span class="nav-section-title">Analytics</span>
            <a href="<?= BASE_URL ?>/admin/reports.php" class="nav-link <?= $currentPage === 'reports' ? 'active' : '' ?>">
                <i class="fas fa-chart-bar"></i>
                <span>Reports</span>
            </a>
        </div>

        <div class="nav-section">
            <span class="nav-section-title">System</span>
            <a href="<?= BASE_URL ?>/admin/settings.php" class="nav-link <?= $currentPage === 'settings' ? 'active' : '' ?>">
                <i class="fas fa-cog"></i>
                <span>Settings</span>
            </a>
        </div>
    </nav>
</aside>

<!-- ═══════════════ MAIN CONTENT ═══════════════ -->
<div class="main-content" id="mainContent">

    <!-- Top Navbar -->
    <header class="top-navbar">
        <div class="d-flex align-items-center">
            <button class="btn btn-link sidebar-toggle me-3" id="sidebarToggle">
                <i class="fas fa-bars fa-lg"></i>
            </button>
            <h1 class="page-title mb-0"><?= sanitize($pageTitle ?? 'Dashboard') ?></h1>
        </div>
        <div class="d-flex align-items-center gap-3">
            <div class="admin-info d-none d-md-flex align-items-center gap-2">
                <div class="admin-avatar">
                    <i class="fas fa-user-shield"></i>
                </div>
                <div>
                    <div class="admin-name"><?= sanitize(getAdminName()) ?></div>
                    <div class="admin-role">Administrator</div>
                </div>
            </div>
            <a href="<?= BASE_URL ?>/logout.php" class="btn btn-outline-danger btn-sm" title="Logout">
                <i class="fas fa-sign-out-alt"></i>
                <span class="d-none d-md-inline ms-1">Logout</span>
            </a>
        </div>
    </header>

    <!-- Page Content -->
    <div class="content-wrapper">
