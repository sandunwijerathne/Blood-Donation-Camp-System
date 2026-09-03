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
    'currency_symbol' => getSetting('currency_symbol', 'Rs.'),
    'whatsapp_api_token' => getSetting('whatsapp_api_token', ''),
    'whatsapp_phone_number_id' => getSetting('whatsapp_phone_number_id', ''),
    'whatsapp_business_account_id' => getSetting('whatsapp_business_account_id', ''),
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
                    <div class="mb-3">
                        <label class="form-label">Default Country Code</label>
                        <input type="text" class="form-control" name="country_code" value="<?= sanitize($settings['country_code']) ?>" required placeholder="+94">
                        <div class="form-text">Used to format local phone numbers for WhatsApp and SMS.</div>
                    </div>
                    <div class="mb-0">
                        <label class="form-label">Currency Symbol</label>
                        <input type="text" class="form-control" name="currency_symbol" value="<?= sanitize($settings['currency_symbol']) ?>" placeholder="Rs.">
                        <div class="form-text">Shown on camp budgets, donations and expense reports.</div>
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
                        <?php $hasToken = $settings['whatsapp_api_token'] !== ''; ?>
                        <input type="password" class="form-control" name="whatsapp_api_token" value=""
                               autocomplete="off"
                               placeholder="<?= $hasToken ? 'Saved — leave blank to keep it' : 'Paste your permanent token (starts EAA…)' ?>">
                        <div class="form-text">
                            <?php if ($hasToken): ?>
                                <i class="fas fa-check text-success me-1"></i>
                                A token is saved (<?= strlen($settings['whatsapp_api_token']) ?> characters).
                                Leave this blank to keep it, or paste a new one to replace it.
                            <?php else: ?>
                                No token saved yet.
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Phone Number ID</label>
                        <input type="text" class="form-control" name="whatsapp_phone_number_id" value="<?= sanitize($settings['whatsapp_phone_number_id']) ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">WhatsApp Business Account ID</label>
                        <input type="text" class="form-control" name="whatsapp_business_account_id" value="<?= sanitize($settings['whatsapp_business_account_id']) ?>">
                        <div class="form-text">Shown above your phone number on Meta's WhatsApp setup screen. Used to read your approved templates.</div>
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
                            <?php foreach (['notify' => 'Notify.lk', 'twilio' => 'Twilio', 'dialog' => 'Dialog', 'mobitel' => 'Mobitel'] as $value => $label): ?>
                                <option value="<?= sanitize($value) ?>" <?= $settings['sms_gateway'] === $value ? 'selected' : '' ?>>
                                    <?= sanitize($label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Notify.lk and Twilio are wired for live API calls; Dialog and Mobitel are stored for provider setup.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">User ID / Account SID</label>
                        <input type="text" class="form-control" name="sms_api_key" value="<?= sanitize($settings['sms_api_key']) ?>">
                        <div class="form-text">Notify.lk: your numeric API User ID. Twilio: the Account SID.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">API Key / Auth Token</label>
                        <?php $hasSmsSecret = $settings['sms_api_secret'] !== ''; ?>
                        <input type="password" class="form-control" name="sms_api_secret" value=""
                               autocomplete="off"
                               placeholder="<?= $hasSmsSecret ? 'Saved — leave blank to keep it' : '' ?>">
                        <?php if ($hasSmsSecret): ?>
                            <div class="form-text">
                                <i class="fas fa-check text-success me-1"></i>
                                A secret is saved. Leave blank to keep it.
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="mb-0">
                        <label class="form-label">Sender ID / From Number</label>
                        <input type="text" class="form-control" name="sms_sender_id" value="<?= sanitize($settings['sms_sender_id']) ?>" placeholder="NotifyDEMO">
                        <div class="form-text">Notify.lk: an approved sender <em>name</em> such as NotifyDEMO - not a phone number. Twilio: the From number.</div>
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
                        <label class="form-label">WhatsApp Test Template</label>
                        <select class="form-select" id="testTemplate">
                            <option value="hello_world|en_US">hello_world (Meta test numbers only)</option>
                            <?php
                            $waTemplates = getDB()->query(
                                "SELECT template_name, whatsapp_template_name, whatsapp_language
                                 FROM message_templates
                                 WHERE whatsapp_template_name IS NOT NULL AND whatsapp_template_name <> ''
                                 ORDER BY template_name"
                            )->fetchAll();
                            foreach ($waTemplates as $t):
                            ?>
                                <option value="<?= sanitize($t['whatsapp_template_name']) ?>|<?= sanitize($t['whatsapp_language']) ?>">
                                    <?= sanitize($t['whatsapp_template_name']) ?> (<?= sanitize($t['whatsapp_language']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">
                            Once you register your own number, <code>hello_world</code> stops working —
                            Meta only allows it from their public test numbers. Pick one of your own
                            approved templates instead.
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">SMS Test Template</label>
                        <select class="form-select" id="testSmsTemplate">
                            <option value="">Custom message (type your own below)</option>
                            <?php
                            $smsTemplates = getDB()->query(
                                "SELECT id, template_name, template_body, whatsapp_language
                                 FROM message_templates
                                 ORDER BY template_name"
                            )->fetchAll();

                            $smsTemplateBodies = [];
                            foreach ($smsTemplates as $t):
                                $smsTemplateBodies[(int) $t['id']] = $t['template_body'];
                            ?>
                                <option value="<?= (int) $t['id'] ?>">
                                    <?= sanitize($t['template_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">
                            Fills the box below with that template, sample values already
                            substituted, so the test reads exactly like a real message.
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Message <span class="text-muted small">(SMS only)</span></label>
                        <textarea class="form-control" id="testMessage" rows="4">This is a test message from the Blood Donor Management System.</textarea>
                        <div class="form-text" id="testMessageMeta"></div>
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

<!-- ── Admin Account ──────────────────────────────────── -->
<div class="divider my-4"></div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-user-shield me-2 text-primary"></i> Admin Account
            </div>
            <div class="card-body">
                <?php if (getAdminEmail() === 'admin@admin.com'): ?>
                    <div class="alert alert-warning py-2 small">
                        <i class="fas fa-triangle-exclamation me-1"></i>
                        You are still signed in with the default account that ships with the
                        installer. Anyone who has seen the setup files knows these credentials.
                        Change the email and password below.
                    </div>
                <?php endif; ?>

                <form id="accountForm" autocomplete="off">
                    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Display Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="account_name" id="accountName"
                                   value="<?= sanitize(getAdminName()) ?>" required>
                            <div class="invalid-feedback"></div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Login Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" name="account_email" id="accountEmail"
                                   value="<?= sanitize(getAdminEmail()) ?>" required autocomplete="username">
                            <div class="invalid-feedback"></div>
                        </div>

                        <div class="col-12">
                            <div class="divider"></div>
                            <p class="text-secondary small mb-3">
                                Leave the two password boxes empty to keep your current password.
                            </p>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">New Password</label>
                            <input type="password" class="form-control" name="new_password" id="newPassword"
                                   autocomplete="new-password" minlength="10">
                            <div class="form-text">At least 10 characters.</div>
                            <div class="invalid-feedback"></div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Confirm New Password</label>
                            <input type="password" class="form-control" name="confirm_password" id="confirmPassword"
                                   autocomplete="new-password">
                            <div class="invalid-feedback"></div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Current Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" name="current_password" id="currentPassword"
                                   required autocomplete="current-password">
                            <div class="form-text">Required to save any change on this card.</div>
                            <div class="invalid-feedback"></div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end mt-4">
                        <button type="submit" class="btn btn-primary" id="btnSaveAccount">
                            <i class="fas fa-user-check me-1"></i> Update Account
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function () {

    // ── Admin account ────────────────────────────────
    $('#accountForm').on('submit', function (e) {
        e.preventDefault();

        const $form = $(this);
        const $btn = $('#btnSaveAccount');
        const pw = $('#newPassword').val();
        const confirmPw = $('#confirmPassword').val();

        $form.find('.is-invalid').removeClass('is-invalid');

        // Catch the mismatch here so the password never leaves the browser
        // just to be rejected.
        if (pw !== confirmPw) {
            showValidationErrors($form, { confirm_password: 'The two passwords do not match.' });
            showToast('The two passwords do not match.', 'error');
            return;
        }

        if (pw && pw.length < 10) {
            showValidationErrors($form, { new_password: 'Use at least 10 characters.' });
            showToast('New password must be at least 10 characters.', 'error');
            return;
        }

        setButtonLoading($btn);

        $.post('<?= BASE_URL ?>/ajax/account-save.php', $form.serialize(), function (res) {
            setButtonLoading($btn, false);

            if (res.success) {
                showToast(res.message, 'success');
                // Never leave typed passwords sitting in the DOM.
                $('#newPassword, #confirmPassword, #currentPassword').val('');

                if (res.data && res.data.password_changed) {
                    $('.alert-warning').remove();
                }
            } else {
                showToast(res.message, 'error');
                if (res.data && res.data.errors) {
                    showValidationErrors($form, res.data.errors);
                }
            }
        }, 'json').fail(function () {
            setButtonLoading($btn, false);
            showToast('Could not update the account. Please try again.', 'error');
        });
    });

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

        if (!phone) {
            showToast('Enter a test phone number.', 'warning');
            return;
        }

        // SMS sends the free text; WhatsApp sends a template.
        if (action === 'test_sms' && !message) {
            showToast('Enter a test message.', 'warning');
            return;
        }

        const picked = ($('#testTemplate').val() || 'hello_world|en_US').split('|');

        setButtonLoading($btn);
        $.post('<?= BASE_URL ?>/ajax/settings-save.php', {
            csrf_token: window.CSRF_TOKEN,
            action: action,
            test_phone: phone,
            test_message: message,
            test_template: picked[0],
            test_language: picked[1] || 'en'
        }, function (res) {
            setButtonLoading($btn, false);
            showToast(res.message, res.success ? 'success' : 'error');
        }, 'json').fail(function () {
            setButtonLoading($btn, false);
        });
    }

    // ── SMS test template picker ─────────────────────
    // Bodies are rendered here rather than fetched, so picking a template
    // is instant and works with the page's existing CSRF-free test flow.
    const smsTemplates = <?= json_encode($smsTemplateBodies, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

    const sampleValues = {
        NAME: 'Test User',
        DATE: '<?= date('d M Y', strtotime('+7 days')) ?>',
        LOCATION: '<?= sanitize(getSetting('organization_name', 'Test Location')) ?>',
        BLOOD_GROUP: 'O+',
        MESSAGE: 'This is a test message.'
    };

    /**
     * Characters and segment count for an SMS.
     *
     * Any character outside ASCII forces the carrier into UCS-2, which
     * carries 70 characters per segment instead of 160 - so a Sinhala
     * message costs roughly three times what the same message costs in
     * English. Counting codepoints (not UTF-16 units) matches what
     * mb_strlen() sees server-side.
     */
    function smsMeta(text) {
        const unicode = [...text].some(function (ch) { return ch.codePointAt(0) > 127; });
        const len = [...text].length;
        const single = unicode ? 70 : 160;
        const multi = unicode ? 67 : 153;
        const segments = len === 0 ? 0 : (len <= single ? 1 : Math.ceil(len / multi));
        return { unicode: unicode, len: len, segments: segments };
    }

    function updateTestMessageMeta() {
        const m = smsMeta($('#testMessage').val());
        const over = m.len > <?= NOTIFY_SMS_MAX_CHARS ?>;
        $('#testMessageMeta')
            .toggleClass('text-danger', over)
            .html(
                m.len + ' characters · '
                + (m.unicode ? 'Unicode (UCS-2), 70 per segment' : 'GSM-7, 160 per segment')
                + ' · ' + m.segments + ' SMS segment' + (m.segments === 1 ? '' : 's')
                + (over ? ' · over the ' + <?= NOTIFY_SMS_MAX_CHARS ?> + ' character limit' : '')
            );
    }

    $('#testSmsTemplate').on('change', function () {
        const id = $(this).val();
        if (!id) return;  // "Custom message" leaves whatever is typed alone

        let body = smsTemplates[id] || '';
        // Mirrors replacePlaceholders() server-side, so the preview matches
        // what a real send would produce.
        Object.keys(sampleValues).forEach(function (k) {
            body = body.split('{' + k + '}').join(sampleValues[k]);
        });
        $('#testMessage').val(body);
        updateTestMessageMeta();
    });

    $('#testMessage').on('input', updateTestMessageMeta);
    updateTestMessageMeta();

    $('#btnTestWhatsapp').on('click', function () {
        sendTest('test_whatsapp', $(this));
    });

    $('#btnTestSms').on('click', function () {
        sendTest('test_sms', $(this));
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
