<?php
/**
 * Emergency Request Page
 */

$pageTitle = 'Emergency';
require_once __DIR__ . '/../includes/header.php';

$db = getDB();
$counts = [];
foreach (['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $group) {
    $stmt = $db->prepare("SELECT COUNT(*) FROM donors WHERE status = 'Active' AND blood_group = ?");
    $stmt->execute([$group]);
    $counts[$group] = (int) $stmt->fetchColumn();
}
?>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-body">
                <form id="emergencyForm">
                    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                    <input type="hidden" name="recipient_type" value="blood_group">

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Required Blood Group <span class="text-danger">*</span></label>
                            <select class="form-select" name="blood_group" id="emergencyBloodGroup" required>
                                <option value="">Select blood group</option>
                                <?php foreach ($counts as $group => $count): ?>
                                    <option value="<?= sanitize($group) ?>" data-count="<?= (int) $count ?>">
                                        <?= sanitize($group) ?> - <?= (int) $count ?> active donors
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Channel</label>
                            <select class="form-select" name="channel" id="emergencyChannel">
                                <option value="whatsapp">WhatsApp</option>
                                <option value="sms">SMS</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Hospital / Location <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="location" id="emergencyLocation" required
                                   placeholder="e.g., National Hospital, Colombo">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Contact Number</label>
                            <input type="text" class="form-control" name="contact_number" id="emergencyContact"
                                   placeholder="Optional emergency contact">
                        </div>

                        <div class="col-12">
                            <label class="form-label">Message <span class="text-danger">*</span></label>
                            <textarea class="form-control" name="message" id="emergencyMessage" rows="8" required></textarea>
                        </div>
                    </div>

                    <div class="d-flex align-items-center justify-content-between mt-4">
                        <div class="alert alert-warning py-2 px-3 mb-0">
                            <i class="fas fa-users me-1"></i>
                            <span id="recipientCount">0</span> matching active donors
                        </div>
                        <button type="submit" class="btn btn-danger" id="btnSendEmergency">
                            <i class="fas fa-triangle-exclamation me-1"></i> Send Emergency Request
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title mb-3">
                    <i class="fas fa-eye me-2 text-danger"></i>Preview
                </h5>
                <div class="alert alert-light border" id="emergencyPreview" style="white-space: pre-wrap; min-height: 220px;"></div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function () {
    function buildMessage() {
        const group = $('#emergencyBloodGroup').val() || '[BLOOD GROUP]';
        const location = $('#emergencyLocation').val() || '[LOCATION]';
        const contact = $('#emergencyContact').val();
        let message = `Urgent Blood Request\n\nBlood Group: ${group}\nLocation: ${location}\n\nPlease contact us immediately if you can donate.`;
        if (contact) {
            message += `\nContact: ${contact}`;
        }
        message += '\n\nThank you.';
        return message;
    }

    function refreshEmergency() {
        const count = $('#emergencyBloodGroup option:selected').data('count') || 0;
        const current = $('#emergencyMessage').val();
        if (!current || current.startsWith('Urgent Blood Request')) {
            $('#emergencyMessage').val(buildMessage());
        }
        $('#recipientCount').text(count);
        $('#emergencyPreview').text($('#emergencyMessage').val());
    }

    $('#emergencyBloodGroup, #emergencyLocation, #emergencyContact').on('change input', refreshEmergency);
    $('#emergencyMessage').on('input', function () {
        $('#emergencyPreview').text($(this).val());
    });

    $('#emergencyForm').on('submit', function (e) {
        e.preventDefault();
        const channel = $('#emergencyChannel').val();
        const url = channel === 'sms'
            ? '<?= BASE_URL ?>/ajax/send-sms.php'
            : '<?= BASE_URL ?>/ajax/send-whatsapp.php';
        const $btn = $('#btnSendEmergency');

        confirmAction('Send Emergency Request?', 'This will contact all matching active donors.', 'Send now', 'danger')
            .then((result) => {
                if (!result.isConfirmed) {
                    return;
                }

                setButtonLoading($btn);
                $.post(url, $('#emergencyForm').serialize(), function (res) {
                    setButtonLoading($btn, false);
                    if (res.success) {
                        showToast(res.message, 'success');
                    } else {
                        showToast(res.message, 'error');
                    }
                }, 'json').fail(function () {
                    setButtonLoading($btn, false);
                });
            });
    });

    refreshEmergency();
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
