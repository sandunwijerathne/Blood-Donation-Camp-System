<?php
/**
 * Add Donor Page
 */

$pageTitle = 'Add Donor';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card animate-fade-in-up">
            <div class="card-header d-flex align-items-center justify-content-between">
                <span><i class="fas fa-user-plus me-2 text-primary"></i> Add New Donor</span>
                <a href="<?= BASE_URL ?>/admin/donors.php" class="btn btn-outline-secondary btn-sm">
                    <i class="fas fa-arrow-left me-1"></i> Back
                </a>
            </div>
            <div class="card-body">
                <form id="donorForm">
                    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

                    <div class="row g-3">
                        <!-- Name -->
                        <div class="col-md-6">
                            <label class="form-label">Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="donor_name" required>
                            <div class="invalid-feedback"></div>
                        </div>

                        <!-- Mobile -->
                        <div class="col-md-6">
                            <label class="form-label">Mobile Number <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="mobile" required placeholder="07XXXXXXXX">
                            <div class="invalid-feedback"></div>
                        </div>

                        <!-- WhatsApp -->
                        <div class="col-md-6">
                            <label class="form-label">WhatsApp Number</label>
                            <input type="text" class="form-control" name="whatsapp" placeholder="07XXXXXXXX">
                            <div class="invalid-feedback"></div>
                        </div>

                        <!-- Email -->
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" placeholder="email@example.com">
                            <div class="invalid-feedback"></div>
                        </div>

                        <!-- Blood Group -->
                        <div class="col-md-4">
                            <label class="form-label">Blood Group <span class="text-danger">*</span></label>
                            <select class="form-select" name="blood_group" required>
                                <option value="">Select</option>
                                <option value="A+">A+</option>
                                <option value="A-">A-</option>
                                <option value="B+">B+</option>
                                <option value="B-">B-</option>
                                <option value="AB+">AB+</option>
                                <option value="AB-">AB-</option>
                                <option value="O+">O+</option>
                                <option value="O-">O-</option>
                            </select>
                            <div class="invalid-feedback"></div>
                        </div>

                        <!-- Gender -->
                        <div class="col-md-4">
                            <label class="form-label">Gender <span class="text-danger">*</span></label>
                            <select class="form-select" name="gender" required>
                                <option value="">Select</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Other">Other</option>
                            </select>
                            <div class="invalid-feedback"></div>
                        </div>

                        <!-- Date of Birth -->
                        <div class="col-md-4">
                            <label class="form-label">Date of Birth</label>
                            <input type="date" class="form-control" name="date_of_birth">
                            <div class="invalid-feedback"></div>
                        </div>

                        <!-- Address -->
                        <div class="col-12">
                            <label class="form-label">Address</label>
                            <textarea class="form-control" name="address" rows="2" placeholder="Full address"></textarea>
                        </div>

                        <!-- Last Donation Date -->
                        <div class="col-md-6">
                            <label class="form-label">Last Donation Date</label>
                            <input type="date" class="form-control" name="last_donation_date">
                        </div>

                        <!-- Status -->
                        <div class="col-md-6">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status">
                                <option value="Active" selected>Active</option>
                                <option value="Inactive">Inactive</option>
                            </select>
                        </div>
                    </div>

                    <div class="divider"></div>

                    <div class="d-flex gap-2 justify-content-end">
                        <a href="<?= BASE_URL ?>/admin/donors.php" class="btn btn-outline-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary" id="btnSave">
                            <i class="fas fa-save me-1"></i> Save Donor
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function () {
    $('#donorForm').on('submit', function (e) {
        e.preventDefault();

        const $btn = $('#btnSave');
        setButtonLoading($btn);
        $(this).find('.is-invalid').removeClass('is-invalid');

        $.post('<?= BASE_URL ?>/ajax/donor-save.php', $(this).serialize(), function (res) {
            setButtonLoading($btn, false);
            if (res.success) {
                showToast(res.message, 'success');
                setTimeout(() => {
                    window.location.href = '<?= BASE_URL ?>/admin/donors.php';
                }, 800);
            } else {
                showToast(res.message, 'error');
                if (res.data && res.data.errors) {
                    showValidationErrors($('#donorForm'), res.data.errors);
                }
            }
        }, 'json');
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
