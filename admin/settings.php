<?php
/**
 * Settings Page
 *
 * Manage general app settings and messaging provider credentials.
 */

$pageTitle = 'Settings';
require_once __DIR__ . '/../includes/header.php';

$settings = [
    'app_name' => getSetting('app_name', APP_NAME),
    'organization_name' => getSetting('organization_name', ''),
    'country_code' => getSetting('country_code', '+94'),
    'whatsapp_api_token' => getSetting('whatsapp_api_token', ''),
    'whatsapp_phone_number_id' => getSetting('whatsapp_phone_number_id', ''),
    'whatsapp_api_version' => getSetting('whatsapp_api_version', 'v23.0'),
    'sms_gateway' => getSetting('sms_gateway', 'twilio'),
    'sms_api_key' => getSetting('sms_api_key', ''),
    'sms_api_secret' => getSetting('sms_api_secret', ''),
    'sms_sender_id' => getSetting('sms_sender_id', '')
];
?>

<form id="settingsForm">
    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
    <input type="hidden" name="action" value="save">

    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header">
                    <i class="fas fa-sliders me-2 text-primary"></i> General
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">App Name</label>
                        <input type="text" class="form-control" name="app_name" value="<?= sanitize($settings['app_name']) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Organization Name</label>
                        <input type="text" class="form-control" name="organization_name" value="<?= sanitize($settings['organization_name']) ?>">
                    </div>
                    <div class="mb-0">
                        <label class="form-label">Default Country Code</label>
                        <input type="text" class="form-control" name="country_code" value="<?= sanitize($settings['country_code']) ?>" required placeholder="+94">
                        <div class="form-text">Used to format local phone numbers for WhatsApp and SMS.</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header">
                    <i class="fab fa-whatsapp me-2 text-success"></i> WhatsApp Cloud API
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">API Token</label>
                        <input type="password" class="form-control" name="whatsapp_api_token" value="<?= sanitize($settings['whatsapp_api_token']) ?>" autocomplete="off">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Phone Number ID</label>
                        <input type="text" class="form-control" name="whatsapp_phone_number_id" value="<?= sanitize($settings['whatsapp_phone_number_id']) ?>">
                    </div>
                    <div class="mb-0">
                        <label class="form-label">API Version</label>
                        <input type="text" class="form-control" name="whatsapp_api_version" value="<?= sanitize($settings['whatsapp_api_version']) ?>" placeholder="v23.0">
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header">
                    <i class="fas fa-sms me-2 text-primary"></i> SMS Gateway
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Gateway</label>
                        <select class="form-select" name="sms_gateway">
                            <?php foreach (['twilio' => 'Twilio', 'dialog' => 'Dialog', 'mobitel' => 'Mobitel'] as $value => $label): ?>
                                <option value="<?= sanitize($value) ?>" <?= $settings['sms_gateway'] === $value ? 'selected' : '' ?>>
                                    <?= sanitize($label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Twilio is wired for live API calls; Dialog and Mobitel are stored for provider setup.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">API Key / Account SID</label>
                        <input type="text" class="form-control" name="sms_api_key" value="<?= sanitize($settings['sms_api_key']) ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">API Secret / Auth Token</label>
                        <input type="password" class="form-control" name="sms_api_secret" value="<?= sanitize($settings['sms_api_secret']) ?>" autocomplete="off">
                    </div>
                    <div class="mb-0">
                        <label class="form-label">Sender ID / From Number</label>
                        <input type="text" class="form-control" name="sms_sender_id" value="<?= sanitize($settings['sms_sender_id']) ?>" placeholder="+1234567890">
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header">
                    <i class="fas fa-vial me-2 text-primary"></i> Test Message
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Recipient Phone</label>
                        <input type="text" class="form-control" id="testPhone" placeholder="0771234567 or +94771234567">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Message</label>
                        <textarea class="form-control" id="testMessage" rows="4">This is a test message from the Blood Donor Management System.</textarea>
                    </div>
                    <div class="d-flex gap-2 flex-wrap">
                        <button type="button" class="btn btn-outline-success" id="btnTestWhatsapp">
                            <i class="fab fa-whatsapp me-1"></i> Test WhatsApp
                        </button>
                        <button type="button" class="btn btn-outline-primary" id="btnTestSms">
                            <i class="fas fa-sms me-1"></i> Test SMS
                        </button>
                    </div>
                    <div class="form-text mt-3">Save settings before sending a test message.</div>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-end mt-4">
        <button type="submit" class="btn btn-primary" id="btnSaveSettings">
            <i class="fas fa-save me-1"></i> Save Settings
        </button>
    </div>
</form>

<script>
$(document).ready(function () {
    $('#settingsForm').on('submit', function (e) {
        e.preventDefault();
        const $btn = $('#btnSaveSettings');
        setButtonLoading($btn);

        $.post('<?= BASE_URL ?>/ajax/settings-save.php', $(this).serialize(), function (res) {
            setButtonLoading($btn, false);
            showToast(res.message, res.success ? 'success' : 'error');
        }, 'json').fail(function () {
            setButtonLoading($btn, false);
        });
    });

    function sendTest(action, $btn) {
        const phone = $('#testPhone').val();
        const message = $('#testMessage').val();

        if (!phone || !message) {
            showToast('Enter a test phone number and message.', 'warning');
            return;
        }

        setButtonLoading($btn);
        $.post('<?= BASE_URL ?>/ajax/settings-save.php', {
            csrf_token: window.CSRF_TOKEN,
            action: action,
            test_phone: phone,
            test_message: message
        }, function (res) {
            setButtonLoading($btn, false);
            showToast(res.message, res.success ? 'success' : 'error');
        }, 'json').fail(function () {
            setButtonLoading($btn, false);
        });
    }

    $('#btnTestWhatsapp').on('click', function () {
        sendTest('test_whatsapp', $(this));
    });

    $('#btnTestSms').on('click', function () {
        sendTest('test_sms', $(this));
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
