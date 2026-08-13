/**
 * Blood Donor Management System — Global JavaScript
 * Handles sidebar, AJAX setup, toasts, and common utilities.
 */

$(document).ready(function () {

    // ── AJAX Global Setup ────────────────────────────────
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': window.CSRF_TOKEN || ''
        },
        error: function (xhr, status, error) {
            let msg = 'An unexpected error occurred.';
            if (xhr.responseJSON && xhr.responseJSON.message) {
                msg = xhr.responseJSON.message;
            } else if (xhr.status === 0) {
                msg = 'Unable to connect to server.';
            } else if (xhr.status === 403) {
                msg = 'Access denied. Please login again.';
                setTimeout(() => { window.location.href = window.BASE_URL + '/login.php'; }, 1500);
            } else if (xhr.status === 500) {
                msg = 'Server error. Please try again later.';
            }
            showToast(msg, 'error');
        }
    });

    // ── Sidebar Toggle ───────────────────────────────────
    const $sidebar = $('#sidebar');
    const $mainContent = $('#mainContent');
    const $overlay = $('<div class="sidebar-overlay" id="sidebarOverlay"></div>');
    $('body').append($overlay);

    $('#sidebarToggle').on('click', function () {
        if (window.innerWidth < 992) {
            $sidebar.toggleClass('show');
            $overlay.toggleClass('show');
        } else {
            $sidebar.toggleClass('collapsed');
            $mainContent.toggleClass('expanded');
        }
    });

    $('#sidebarClose, #sidebarOverlay').on('click', function () {
        $sidebar.removeClass('show');
        $overlay.removeClass('show');
    });

    // Close sidebar on resize to desktop
    $(window).on('resize', function () {
        if (window.innerWidth >= 992) {
            $sidebar.removeClass('show');
            $overlay.removeClass('show');
        }
    });

    // ── Tooltips ─────────────────────────────────────────
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(el => new bootstrap.Tooltip(el));
});

// ═══════════════════════════════════════════════════════════
// TOAST NOTIFICATION SYSTEM (SweetAlert2)
// ═══════════════════════════════════════════════════════════

const Toast = Swal.mixin({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 3000,
    timerProgressBar: true,
    didOpen: (toast) => {
        toast.addEventListener('mouseenter', Swal.stopTimer);
        toast.addEventListener('mouseleave', Swal.resumeTimer);
    }
});

/**
 * Show a toast notification.
 * @param {string} message
 * @param {string} type - 'success', 'error', 'warning', 'info'
 */
function showToast(message, type = 'success') {
    Toast.fire({
        icon: type,
        title: message
    });
}

// ═══════════════════════════════════════════════════════════
// CONFIRMATION DIALOG
// ═══════════════════════════════════════════════════════════

/**
 * Show a confirmation dialog.
 * @param {string} title
 * @param {string} text
 * @param {string} confirmText
 * @param {string} type - 'warning', 'danger'
 * @returns {Promise}
 */
function confirmAction(title, text, confirmText = 'Yes, proceed', type = 'warning') {
    return Swal.fire({
        title: title,
        text: text,
        icon: type === 'danger' ? 'error' : 'warning',
        showCancelButton: true,
        confirmButtonColor: type === 'danger' ? '#ef4444' : '#6366f1',
        cancelButtonColor: '#94a3b8',
        confirmButtonText: confirmText,
        cancelButtonText: 'Cancel',
        reverseButtons: true,
        customClass: {
            popup: 'animate__animated animate__fadeIn'
        }
    });
}

// ═══════════════════════════════════════════════════════════
// FORM UTILITIES
// ═══════════════════════════════════════════════════════════

/**
 * Serialize form to object.
 */
function formToObject($form) {
    const data = {};
    $form.serializeArray().forEach(item => {
        data[item.name] = item.value;
    });
    return data;
}

/**
 * Reset form and clear validation states.
 */
function resetForm($form) {
    $form[0].reset();
    $form.find('.is-invalid').removeClass('is-invalid');
    $form.find('.invalid-feedback').text('');
}

/**
 * Show validation errors on form fields.
 */
function showValidationErrors($form, errors) {
    // Clear previous
    $form.find('.is-invalid').removeClass('is-invalid');

    for (const [field, message] of Object.entries(errors)) {
        const $field = $form.find(`[name="${field}"]`);
        $field.addClass('is-invalid');
        $field.siblings('.invalid-feedback').text(message);
    }
}

/**
 * Set button loading state.
 */
function setButtonLoading($btn, loading = true) {
    if (loading) {
        $btn.data('original-text', $btn.html());
        $btn.html('<span class="spinner-border spinner-border-sm me-1"></span> Processing...');
        $btn.prop('disabled', true);
    } else {
        $btn.html($btn.data('original-text'));
        $btn.prop('disabled', false);
    }
}

// ═══════════════════════════════════════════════════════════
// DATA TABLE DEFAULTS
// ═══════════════════════════════════════════════════════════

if ($.fn.dataTable) {
    $.extend(true, $.fn.dataTable.defaults, {
        language: {
            search: '',
            searchPlaceholder: 'Search...',
            lengthMenu: 'Show _MENU_ entries',
            info: 'Showing _START_ to _END_ of _TOTAL_ entries',
            emptyTable: '<div class="empty-state py-4"><i class="fas fa-inbox d-block"></i><p class="mb-0 mt-2">No data available</p></div>',
            zeroRecords: '<div class="empty-state py-4"><i class="fas fa-search d-block"></i><p class="mb-0 mt-2">No matching records found</p></div>'
        },
        pageLength: 25,
        responsive: true,
        dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>rtip'
    });
}

// ═══════════════════════════════════════════════════════════
// NUMBER ANIMATION (for dashboard counters)
// ═══════════════════════════════════════════════════════════

/**
 * Animate a number from 0 to target.
 * @param {HTMLElement} element
 * @param {number} target
 * @param {number} duration ms
 */
function animateNumber(element, target, duration = 1000) {
    let start = 0;
    const increment = target / (duration / 16);
    const timer = setInterval(() => {
        start += increment;
        if (start >= target) {
            element.textContent = target.toLocaleString();
            clearInterval(timer);
        } else {
            element.textContent = Math.floor(start).toLocaleString();
        }
    }, 16);
}

// Animate all stat values on page load
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.stat-value[data-count]').forEach(el => {
        const target = parseInt(el.getAttribute('data-count'), 10);
        if (!isNaN(target)) {
            animateNumber(el, target);
        }
    });
});
