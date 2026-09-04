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

// WhatsApp needs an approved template for donors who have not messaged
// us in the last 24 hours - which is everyone, in an emergency.
$emergencyTemplates = $db->query(
    "SELECT id, template_name, template_body, whatsapp_template_name, whatsapp_variables
     FROM message_templates
     ORDER BY (template_type = 'Emergency Request') DESC, template_name"
)->fetchAll();
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

                        <div class="col-12" id="emergencyTemplateWrap">
                            <label class="form-label">
                                WhatsApp Template <span class="text-danger">*</span>
                            </label>
                            <select class="form-select" name="template_id" id="emergencyTemplate">
                                <option value="">Select an approved template</option>
                                <?php foreach ($emergencyTemplates as $tpl): ?>
                                    <option value="<?= (int) $tpl['id'] ?>"
                                            data-body="<?= sanitize($tpl['template_body']) ?>"
                                            data-wa-name="<?= sanitize($tpl['whatsapp_template_name'] ?? '') ?>"
                                            data-wa-vars="<?= sanitize($tpl['whatsapp_variables'] ?? '') ?>">
                                        <?= sanitize($tpl['template_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text" id="emergencyTemplateInfo">
                                WhatsApp will not deliver free text to donors who have not messaged you
                                in the last 24 hours, so an emergency callout must use an approved template.
                            </div>
                        </div>

                        <div class="col-12">
                            <label class="form-label">Message <span class="text-danger">*</span></label>
                            <textarea class="form-control" name="message" id="emergencyMessage" rows="8" required></textarea>
                            <div class="form-text" id="emergencyMessageHelp"></div>
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

    // ── WhatsApp needs a template; SMS does not ──────
    function isWhatsApp() {
        return $('#emergencyChannel').val() === 'whatsapp';
    }

    function refreshChannel() {
        $('#emergencyTemplateWrap').toggleClass('d-none', !isWhatsApp());
        $('#emergencyTemplate').prop('required', isWhatsApp());

        const $opt = $('#emergencyTemplate option:selected');

        if (isWhatsApp()) {
            $('#emergencyMessageHelp').text(
                'Wording comes from the approved template. This box is a preview and is not sent.'
            );
            if ($opt.val() && !$opt.data('wa-name')) {
                $('#emergencyTemplateInfo').html(
                    '<span class="text-danger"><i class="fas fa-circle-exclamation me-1"></i>' +
                    'This template has no WhatsApp template name — it cannot be sent. Set it on the Templates page.</span>'
                );
            } else if ($opt.val()) {
                $('#emergencyTemplateInfo').html(
                    '<span class="text-success"><i class="fas fa-check me-1"></i>Meta template: <code>' +
                    $('<span>').text($opt.data('wa-name')).html() + '</code>' +
                    ($opt.data('wa-vars') ? ' · variables: ' + $('<span>').text($opt.data('wa-vars')).html() : '') +
                    '</span>'
                );
            }
        } else {
            $('#emergencyMessageHelp').text('');
        }
    }

    $('#emergencyBloodGroup, #emergencyLocation, #emergencyContact').on('change input', refreshEmergency);
    $('#emergencyChannel, #emergencyTemplate').on('change', refreshChannel);
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

        // Fail here rather than after fanning out to every matching donor.
        if (channel === 'whatsapp') {
            const $opt = $('#emergencyTemplate option:selected');
            if (!$opt.val()) {
                showToast('Choose a WhatsApp template. Meta rejects free text for donors who have not messaged you.', 'error');
                return;
            }
            if (!$opt.data('wa-name')) {
                showToast('That template has no WhatsApp template name. Set it on the Templates page first.', 'error');
                return;
            }
        }

        confirmAction('Send Emergency Request?', 'This will contact all matching active donors.', 'Send now', 'danger')
            .then((result) => {
                if (!result.isConfirmed) {
                    return;
                }

                setButtonLoading($btn);
                const originalLabel = $btn.html();

                // Tell the sender which mode to use; SMS ignores it.
                const payload = $('#emergencyForm').serialize() +
                                '&send_mode=' + (channel === 'whatsapp' ? 'template' : 'text');

                // Chunked, like the Messages page. An emergency request
                // goes to every matching donor, so it is exactly the send
                // most likely to exceed max_execution_time - and the one
                // where a silent partial send matters most.
                sendCampaign(url, payload, {
                    onProgress: function (processed, total) {
                        $btn.html('<i class="fas fa-bullhorn me-1"></i> Sending ' + processed + ' / ' + total);
                    },
                    onDone: function (totals, total) {
                        setButtonLoading($btn, false);
                        $btn.html(originalLabel);
                        showToast(campaignSummary(totals, total), totals.failed ? 'error' : 'success');
                    },
                    onError: function (msg) {
                        setButtonLoading($btn, false);
                        $btn.html(originalLabel);
                        showToast(msg, 'error');
                    }
                });
            });
    });

    refreshEmergency();
    refreshChannel();
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
