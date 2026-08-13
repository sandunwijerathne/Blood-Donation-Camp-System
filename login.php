<?php
/**
 * Login Page
 * 
 * Secure admin login with glassmorphism UI.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';

// If already logged in, redirect to dashboard
if (isLoggedIn()) {
    header('Location: ' . BASE_URL . '/admin/dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Blood Donor Management System - Admin Login">
    <title>Login — <?= APP_NAME ?></title>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Bootstrap 5.3 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">

    <!-- SweetAlert2 -->
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">

    <!-- Custom CSS -->
    <link href="<?= BASE_URL ?>/assets/css/style.css" rel="stylesheet">
</head>
<body>

<div class="login-wrapper">
    <div class="login-card animate-fade-in-up">
        <div class="login-logo">
            <i class="fas fa-tint"></i>
            <h2><?= APP_NAME ?></h2>
            <p>Admin Portal</p>
        </div>

        <form id="loginForm" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

            <div class="mb-3">
                <label for="email" class="form-label">Email Address</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                    <input type="email" class="form-control" id="email" name="email" 
                           placeholder="admin@example.com" required autofocus>
                </div>
                <div class="invalid-feedback"></div>
            </div>

            <div class="mb-4">
                <label for="password" class="form-label">Password</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                    <input type="password" class="form-control" id="password" name="password" 
                           placeholder="Enter your password" required>
                    <button class="input-group-text toggle-password" type="button" tabindex="-1">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
                <div class="invalid-feedback"></div>
            </div>

            <button type="submit" class="login-btn" id="loginBtn">
                <i class="fas fa-sign-in-alt me-2"></i> Sign In
            </button>
        </form>

        <div class="text-center mt-3">
            <small style="color: rgba(255,255,255,0.25); font-size: 0.75rem;">
                <?= APP_NAME ?> v<?= APP_VERSION ?>
            </small>
        </div>
    </div>
</div>

<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
$(document).ready(function () {

    // Toast setup
    const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true
    });

    // Toggle password visibility
    $('.toggle-password').on('click', function () {
        const $input = $(this).siblings('input');
        const $icon = $(this).find('i');
        if ($input.attr('type') === 'password') {
            $input.attr('type', 'text');
            $icon.removeClass('fa-eye').addClass('fa-eye-slash');
        } else {
            $input.attr('type', 'password');
            $icon.removeClass('fa-eye-slash').addClass('fa-eye');
        }
    });

    // Login form submission
    $('#loginForm').on('submit', function (e) {
        e.preventDefault();

        const $btn = $('#loginBtn');
        const originalText = $btn.html();
        $btn.html('<span class="spinner-border spinner-border-sm me-1"></span> Signing in...');
        $btn.prop('disabled', true);

        // Clear previous errors
        $(this).find('.is-invalid').removeClass('is-invalid');

        $.ajax({
            url: '<?= BASE_URL ?>/ajax/login.php',
            method: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function (res) {
                if (res.success) {
                    Toast.fire({ icon: 'success', title: 'Login successful!' });
                    setTimeout(() => {
                        window.location.href = '<?= BASE_URL ?>/admin/dashboard.php';
                    }, 800);
                } else {
                    Toast.fire({ icon: 'error', title: res.message || 'Login failed.' });
                    $btn.html(originalText);
                    $btn.prop('disabled', false);
                }
            },
            error: function () {
                Toast.fire({ icon: 'error', title: 'Connection error. Please try again.' });
                $btn.html(originalText);
                $btn.prop('disabled', false);
            }
        });
    });
});
</script>

</body>
</html>
