<?php
/**
 * Messages Page
 *
 * Compose donor notifications and review message history.
 */

$pageTitle = 'Messages';
require_once __DIR__ . '/../includes/header.php';

$db = getDB();
$templates = $db->query("SELECT id, template_name, template_body, template_type FROM message_templates ORDER BY template_name")->fetchAll();
$donors = $db->query("SELECT id, donor_name, mobile, whatsapp, blood_group FROM donors WHERE status = 'Active' ORDER BY donor_name")->fetchAll();
$camps = $db->query("SELECT title, camp_date, location FROM blood_camps WHERE camp_date >= CURDATE() AND status = 'Upcoming' ORDER BY camp_date ASC LIMIT 10")->fetchAll();
?>

<ul class="nav nav-pills mb-4" role="tablist">
    <li class="nav-item">
        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#composeTab" type="button">
            <i class="fas fa-pen me-1"></i> Compose
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#historyTab" type="button">
            <i class="fas fa-clock-rotate-left me-1"></i> History
        </button>
    </li>
</ul>

<div class="tab-content">
    <div class="tab-pane fade show active" id="composeTab">
        <div class="row g-4">
            <div class="col-lg-7">
                <div class="card">
                    <div class="card-body">
                        <form id="messageForm">
                            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                            <input type="hidden" name="date" id="campDateValue" value="">
                            <input type="hidden" name="location" id="campLocationValue" value="">

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Channel</label>
                                    <div class="btn-group w-100" role="group">
                                        <input type="radio" class="btn-check" name="channel" id="channelWhatsapp" value="whatsapp" checked>
                                        <label class="btn btn-outline-success" for="channelWhatsapp">
                                            <i class="fab fa-whatsapp me-1"></i> WhatsApp
                                        </label>

                                        <input type="radio" class="btn-check" name="channel" id="channelSms" value="sms">
                                        <label class="btn btn-outline-primary" for="channelSms">
                                            <i class="fas fa-sms me-1"></i> SMS
                                        </label>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Recipients</label>
                                    <select class="form-select" name="recipient_type" id="recipientType">
                                        <option value="all">All active donors</option>
                                        <option value="blood_group">By blood group</option>
                                        <option value="selected">Selected donors</option>
                                    </select>
                                </div>

                                <div class="col-md-6 d-none" id="bloodGroupWrap">
                                    <label class="form-label">Blood Group</label>
                                    <select class="form-select" name="blood_group" id="bloodGroup">
                                        <option value="">Select blood group</option>
                                        <?php foreach (['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $group): ?>
                                            <option value="<?= sanitize($group) ?>"><?= sanitize($group) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-12 d-none" id="selectedDonorsWrap">
                                    <label class="form-label">Select Donors</label>
                                    <select class="form-select" name="donor_ids[]" id="selectedDonors" multiple size="8">
                                        <?php foreach ($donors as $donor): ?>
                                            <option value="<?= (int) $donor['id'] ?>">
                                                <?= sanitize($donor['donor_name']) ?> - <?= sanitize($donor['blood_group']) ?> - <?= sanitize($donor['mobile']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="form-text">Hold Ctrl to select multiple donors.</div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Template</label>
                                    <select class="form-select" id="templateSelect">
                                        <option value="">Start from blank message</option>
                                        <?php foreach ($templates as $template): ?>
                                            <option value="<?= (int) $template['id'] ?>" data-body="<?= sanitize($template['template_body']) ?>">
                                                <?= sanitize($template['template_name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Upcoming Camp</label>
                                    <select class="form-select" id="campSelect">
                                        <option value="">No camp placeholders</option>
                                        <?php foreach ($camps as $camp): ?>
                                            <option
                                                value="<?= sanitize($camp['title']) ?>"
                                                data-date="<?= sanitize($camp['camp_date']) ?>"
                                                data-location="<?= sanitize($camp['location']) ?>"
                                            >
                                                <?= sanitize($camp['title']) ?> - <?= sanitize(formatDate($camp['camp_date'])) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-12">
                                    <label class="form-label">Message <span class="text-danger">*</span></label>
                                    <textarea class="form-control" name="message" id="messageBody" rows="8" required
                                              placeholder="Use placeholders like {NAME}, {DATE}, {LOCATION}, {BLOOD_GROUP}."></textarea>
                                    <div class="form-text">
                                        Placeholders are replaced per donor when sending.
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex justify-content-end mt-4">
                                <button type="submit" class="btn btn-primary" id="btnSendMessage">
                                    <i class="fas fa-paper-plane me-1"></i> Send Message
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
                            <i class="fas fa-eye me-2 text-primary"></i>Preview
                        </h5>
                        <div class="alert alert-light border mb-3" id="messagePreview" style="white-space: pre-wrap; min-height: 180px;">Your message preview will appear here.</div>
                        <div class="small text-muted">
                            Example values: {NAME} becomes a donor name, {BLOOD_GROUP} becomes their blood group, and camp placeholders use the selected camp.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="tab-pane fade" id="historyTab">
        <div class="card">
            <div class="card-body">
                <div class="row g-2 mb-3">
                    <div class="col-md-3">
                        <select class="form-select form-select-sm" id="historyType">
                            <option value="">All Channels</option>
                            <option value="WhatsApp">WhatsApp</option>
                            <option value="SMS">SMS</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select class="form-select form-select-sm" id="historyStatus">
                            <option value="">All Statuses</option>
                            <option value="Sent">Sent</option>
                            <option value="Pending">Pending</option>
                            <option value="Failed">Failed</option>
                        </select>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover" id="messageLogTable" style="width:100%">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Donor</th>
                                <th>Channel</th>
                                <th>Mobile</th>
                                <th>Message</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function () {
    const sample = {
        NAME: 'Kamal Perera',
        BLOOD_GROUP: 'O+',
        DATE: '',
        LOCATION: '',
        MESSAGE: 'Your custom message'
    };

    function updateRecipientFields() {
        const type = $('#recipientType').val();
        $('#bloodGroupWrap').toggleClass('d-none', type !== 'blood_group');
        $('#selectedDonorsWrap').toggleClass('d-none', type !== 'selected');
    }

    function updateCampPlaceholders() {
        const $selected = $('#campSelect option:selected');
        sample.DATE = $selected.data('date') || '';
        sample.LOCATION = $selected.data('location') || '';
        $('#campDateValue').val(sample.DATE);
        $('#campLocationValue').val(sample.LOCATION);
        updatePreview();
    }

    function updatePreview() {
        let text = $('#messageBody').val() || 'Your message preview will appear here.';
        Object.keys(sample).forEach(function (key) {
            text = text.replaceAll('{' + key + '}', sample[key] || '[' + key + ']');
        });
        $('#messagePreview').text(text);
    }

    $('#recipientType').on('change', updateRecipientFields);
    $('#campSelect').on('change', updateCampPlaceholders);
    $('#messageBody').on('input', updatePreview);

    $('#templateSelect').on('change', function () {
        const body = $(this).find(':selected').data('body') || '';
        if (body) {
            $('#messageBody').val(body);
            updatePreview();
        }
    });

    const logTable = $('#messageLogTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '<?= BASE_URL ?>/ajax/message-log.php',
            type: 'POST',
            data: function (d) {
                d.csrf_token = window.CSRF_TOKEN;
                d.message_type = $('#historyType').val();
                d.status = $('#historyStatus').val();
            }
        },
        columns: [
            { data: 'sent_at' },
            { data: 'donor_name' },
            { data: 'message_type' },
            { data: 'mobile' },
            {
                data: 'message',
                render: function (data) {
                    return '<span class="text-truncate-2">' + $('<span>').text(data || '').html() + '</span>';
                }
            },
            {
                data: 'status',
                render: function (data) {
                    const cls = (data || '').toLowerCase();
                    return '<span class="badge-status ' + cls + '">' + data + '</span>';
                }
            }
        ],
        order: [[0, 'desc']]
    });

    $('#historyType, #historyStatus').on('change', function () {
        logTable.ajax.reload();
    });

    $('#messageForm').on('submit', function (e) {
        e.preventDefault();

        const channel = $('input[name="channel"]:checked').val();
        const url = channel === 'sms'
            ? '<?= BASE_URL ?>/ajax/send-sms.php'
            : '<?= BASE_URL ?>/ajax/send-whatsapp.php';
        const $btn = $('#btnSendMessage');

        setButtonLoading($btn);

        $.post(url, $(this).serialize(), function (res) {
            setButtonLoading($btn, false);
            if (res.success) {
                showToast(res.message, 'success');
                logTable.ajax.reload(null, false);
            } else {
                showToast(res.message, 'error');
            }
        }, 'json').fail(function () {
            setButtonLoading($btn, false);
        });
    });

    updateRecipientFields();
    updateCampPlaceholders();
    updatePreview();
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
