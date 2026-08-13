<?php
/**
 * Edit Donor Page
 */

$pageTitle = 'Edit Donor';
require_once __DIR__ . '/../includes/header.php';

$id = (int) ($_GET['id'] ?? 0);
if (!$id) {
    header('Location: ' . BASE_URL . '/admin/donors.php');
    exit;
}

$db = getDB();
$stmt = $db->prepare("SELECT * FROM donors WHERE id = ?");
$stmt->execute([$id]);
$donor = $stmt->fetch();

if (!$donor) {
    header('Location: ' . BASE_URL . '/admin/donors.php');
    exit;
}
?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card animate-fade-in-up">
            <div class="card-header d-flex align-items-center justify-content-between">
                <span><i class="fas fa-user-edit me-2 text-primary"></i> Edit Donor</span>
                <a href="<?= BASE_URL ?>/admin/donors.php" class="btn btn-outline-secondary btn-sm">
                    <i class="fas fa-arrow-left me-1"></i> Back
                </a>
            </div>
            <div class="card-body">
                <form id="donorForm">
                    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                    <input type="hidden" name="id" value="<?= $donor['id'] ?>">

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="donor_name" 
                                   value="<?= sanitize($donor['donor_name']) ?>" required>
                            <div class="invalid-feedback"></div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Mobile Number <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="mobile" 
                                   value="<?= sanitize($donor['mobile']) ?>" required>
                            <div class="invalid-feedback"></div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">WhatsApp Number</label>
                            <input type="text" class="form-control" name="whatsapp" 
                                   value="<?= sanitize($donor['whatsapp'] ?? '') ?>">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" 
                                   value="<?= sanitize($donor['email'] ?? '') ?>">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Blood Group <span class="text-danger">*</span></label>
                            <select class="form-select" name="blood_group" required>
                                <?php foreach (['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $bg): ?>
                                    <option value="<?= $bg ?>" <?= $donor['blood_group'] === $bg ? 'selected' : '' ?>><?= $bg ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Gender <span class="text-danger">*</span></label>
                            <select class="form-select" name="gender" required>
                                <?php foreach (['Male','Female','Other'] as $g): ?>
                                    <option value="<?= $g ?>" <?= $donor['gender'] === $g ? 'selected' : '' ?>><?= $g ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Date of Birth</label>
                            <input type="date" class="form-control" name="date_of_birth" 
                                   value="<?= $donor['date_of_birth'] ?? '' ?>">
                        </div>

                        <div class="col-12">
                            <label class="form-label">Address</label>
                            <textarea class="form-control" name="address" rows="2"><?= sanitize($donor['address'] ?? '') ?></textarea>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Last Donation Date</label>
                            <input type="date" class="form-control" name="last_donation_date" 
                                   value="<?= $donor['last_donation_date'] ?? '' ?>">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status">
                                <option value="Active" <?= $donor['status'] === 'Active' ? 'selected' : '' ?>>Active</option>
                                <option value="Inactive" <?= $donor['status'] === 'Inactive' ? 'selected' : '' ?>>Inactive</option>
                            </select>
                        </div>
                    </div>

                    <div class="divider"></div>

                    <div class="d-flex gap-2 justify-content-end">
                        <a href="<?= BASE_URL ?>/admin/donors.php" class="btn btn-outline-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary" id="btnSave">
                            <i class="fas fa-save me-1"></i> Update Donor
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
