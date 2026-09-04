<?php
/**
 * Camp Register Page
 *
 * The digital version of the paper register book. Staff type the
 * donor's T.P. number, the system either recognises them or opens a
 * short walk-in form, and the entry is added to that camp's register.
 */

$pageTitle = 'Camp Register';
require_once __DIR__ . '/../includes/header.php';

$db = getDB();
$camps = $db->query(
    "SELECT id, title, camp_date, location, status
     FROM blood_camps
     ORDER BY camp_date DESC"
)->fetchAll();

// Preselect: ?camp_id=, else the nearest upcoming camp, else the latest.
$selectedCampId = (int) ($_GET['camp_id'] ?? 0);

if ($selectedCampId <= 0) {
    $next = getNextUpcomingCamp();
    $selectedCampId = $next ? (int) $next['id'] : (int) ($camps[0]['id'] ?? 0);
}
?>

<!-- ── Camp Selector ──────────────────────────────────── -->
<div class="card mb-3">
    <div class="card-body">
        <div class="row g-3 align-items-end">
            <div class="col-md-7">
                <label class="form-label fw-semibold">
                    <i class="fas fa-campground me-1 text-primary"></i> Select Camp
                </label>
                <select class="form-select form-select-lg" id="campSelect">
                    <?php if (empty($camps)): ?>
                        <option value="0">No camps created yet</option>
                    <?php else: ?>
                        <?php foreach ($camps as $camp): ?>
                            <option value="<?= (int) $camp['id'] ?>"
                                    data-date="<?= sanitize($camp['camp_date']) ?>"
                                    data-location="<?= sanitize($camp['location']) ?>"
                                    <?= (int) $camp['id'] === $selectedCampId ? 'selected' : '' ?>>
                                <?= sanitize($camp['title']) ?>
                                — <?= formatDate($camp['camp_date']) ?>
                                (<?= sanitize($camp['status']) ?>)
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>
            <div class="col-md-5">
                <div class="d-flex gap-2 justify-content-md-end">
                    <a href="<?= BASE_URL ?>/admin/camps.php" class="btn btn-outline-secondary">
                        <i class="fas fa-campground me-1"></i> Manage Camps
                    </a>
                    <div class="btn-group">
                        <button class="btn btn-outline-success dropdown-toggle" data-bs-toggle="dropdown"
                                <?= empty($camps) ? 'disabled' : '' ?>>
                            <i class="fas fa-file-export me-1"></i> Export
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="#" id="exportXlsx">
                                <i class="fas fa-file-excel me-2 text-success"></i> Excel (.xlsx)
                            </a></li>
                            <li><a class="dropdown-item" href="#" id="exportCsv">
                                <i class="fas fa-file-csv me-2 text-primary"></i> CSV
                            </a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if (empty($camps)): ?>
    <div class="card">
        <div class="card-body">
            <div class="empty-state py-5 text-center">
                <i class="fas fa-campground d-block mb-3" style="font-size:2.5rem;"></i>
                <h5>No blood camps yet</h5>
                <p class="text-secondary mb-3">Create a camp first, then you can start marking donors in.</p>
                <a href="<?= BASE_URL ?>/admin/camps.php" class="btn btn-primary">
                    <i class="fas fa-plus me-1"></i> Create a Camp
                </a>
            </div>
        </div>
    </div>
<?php else: ?>

<!-- ── Summary Cards ──────────────────────────────────── -->
<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3">
        <div class="stat-card bg-total">
            <i class="fas fa-clipboard-list stat-icon"></i>
            <div class="stat-value" id="sumTotal">0</div>
            <div class="stat-label">Total on Register</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card bg-eligible">
            <i class="fas fa-tint stat-icon"></i>
            <div class="stat-value" id="sumDonated">0</div>
            <div class="stat-label">Donated</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card bg-messages">
            <i class="fas fa-user-check stat-icon"></i>
            <div class="stat-value" id="sumRegistered">0</div>
            <div class="stat-label">Registered</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card bg-a-neg">
            <i class="fas fa-user-times stat-icon"></i>
            <div class="stat-value" id="sumRejected">0</div>
            <div class="stat-label">Rejected / No Show</div>
        </div>
    </div>
</div>

<!-- ── Mark In ────────────────────────────────────────── -->
<div class="card mb-3">
    <div class="card-header">
        <i class="fas fa-user-plus me-2 text-primary"></i> Mark In a Donor
    </div>
    <div class="card-body">
        <!-- T.P. lookup -->
        <div class="row g-2 align-items-end">
            <div class="col-md-6">
                <label class="form-label fw-semibold">T.P. Number</label>
                <div class="input-group input-group-lg">
                    <span class="input-group-text"><i class="fas fa-phone"></i></span>
                    <input type="text" class="form-control" id="tpInput" inputmode="numeric"
                           placeholder="0771234567" autocomplete="off" autofocus>
                    <button class="btn btn-primary" id="btnLookup">
                        <i class="fas fa-search me-1"></i> Look Up
                    </button>
                </div>
                <div class="form-text">Type the number and press Enter.</div>
            </div>
            <div class="col-md-6">
                <div id="lookupStatus"></div>
            </div>
        </div>

        <!-- Entry form (revealed after a lookup) -->
        <div id="entryPanel" class="d-none">
            <div class="divider"></div>
            <form id="registerForm">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                <input type="hidden" name="id" id="regId" value="0">
                <input type="hidden" name="camp_id" id="regCampId" value="<?= $selectedCampId ?>">
                <input type="hidden" name="mobile" id="regMobile" value="">

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="donor_name" id="regName" required>
                        <div class="invalid-feedback"></div>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Blood Group</label>
                        <select class="form-select" name="blood_group" id="regBloodGroup">
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

                    <div class="col-md-3">
                        <label class="form-label">Gender</label>
                        <select class="form-select" name="gender" id="regGender">
                            <option value="">Select</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Address</label>
                        <input type="text" class="form-control" name="address" id="regAddress">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Date of Birth</label>
                        <input type="date" class="form-control" name="date_of_birth" id="regDob">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status" id="regStatus">
                            <option value="Registered">Registered</option>
                            <option value="Donated">Donated</option>
                            <option value="Rejected">Rejected</option>
                            <option value="No Show">No Show</option>
                        </select>
                    </div>

                    <div class="col-12">
                        <label class="form-label">Remarks</label>
                        <input type="text" class="form-control" name="remarks" id="regRemarks"
                               placeholder="Optional note (e.g. low haemoglobin)">
                    </div>
                </div>

                <div class="d-flex gap-2 justify-content-end mt-3">
                    <button type="button" class="btn btn-outline-secondary" id="btnCancelEntry">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="btnSaveEntry">
                        <i class="fas fa-check me-1"></i> Add to Register
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ── Register Table ─────────────────────────────────── -->
<div class="card">
    <div class="card-header d-flex flex-wrap gap-2 align-items-center justify-content-between">
        <span><i class="fas fa-list-ol me-2 text-primary"></i> Register</span>
        <div class="d-flex gap-2">
            <select class="form-select form-select-sm" id="filterStatus" style="width:auto">
                <option value="">All statuses</option>
                <option value="Registered">Registered</option>
                <option value="Donated">Donated</option>
                <option value="Rejected">Rejected</option>
                <option value="No Show">No Show</option>
            </select>
            <select class="form-select form-select-sm" id="filterBloodGroup" style="width:auto">
                <option value="">All blood groups</option>
                <option value="A+">A+</option>
                <option value="A-">A-</option>
                <option value="B+">B+</option>
                <option value="B-">B-</option>
                <option value="AB+">AB+</option>
                <option value="AB-">AB-</option>
                <option value="O+">O+</option>
                <option value="O-">O-</option>
            </select>
        </div>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover" id="registerTable" style="width:100%">
                <thead>
                    <tr>
                        <th style="width:60px">No</th>
                        <th>Name</th>
                        <th>T.P. No</th>
                        <th>Blood Group</th>
                        <th>Status</th>
                        <th>Time</th>
                        <th style="width:100px">Actions</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<script>
$(document).ready(function () {

    const LOOKUP_URL = '<?= BASE_URL ?>/ajax/registration-lookup.php';
    const SAVE_URL   = '<?= BASE_URL ?>/ajax/registration-save.php';
    const LIST_URL   = '<?= BASE_URL ?>/ajax/registration-list.php';
    const DELETE_URL = '<?= BASE_URL ?>/ajax/registration-delete.php';
    const EXPORT_URL = '<?= BASE_URL ?>/ajax/registration-export.php';

    function currentCampId() {
        return parseInt($('#campSelect').val(), 10) || 0;
    }

    // ── Register table ───────────────────────────────
    const table = $('#registerTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: LIST_URL,
            type: 'POST',
            data: function (d) {
                d.csrf_token  = window.CSRF_TOKEN;
                d.camp_id     = currentCampId();
                d.status      = $('#filterStatus').val();
                d.blood_group = $('#filterBloodGroup').val();
            },
            dataSrc: function (json) {
                const s = json.summary || {};
                const registered = s.Registered || 0;
                const donated    = s.Donated || 0;
                const rejected   = (s.Rejected || 0) + (s['No Show'] || 0);

                $('#sumTotal').text(json.recordsTotal || 0);
                $('#sumDonated').text(donated);
                $('#sumRegistered').text(registered);
                $('#sumRejected').text(rejected);

                return json.data;
            }
        },
        columns: [
            { data: 'serial_no', render: d => d || '—' },
            {
                data: 'donor_name',
                render: function (data, type, row) {
                    let html = '<span class="fw-medium">' + $('<span>').text(data).html() + '</span>';
                    if (row.address) {
                        html += '<br><small class="text-muted">' + $('<span>').text(row.address).html() + '</small>';
                    }
                    return html;
                }
            },
            { data: 'mobile', render: $.fn.dataTable.render.text() },
            {
                data: 'blood_group',
                render: d => d ? '<span class="badge bg-danger-subtle text-danger fw-semibold">' + d + '</span>' : '—'
            },
            {
                data: 'status',
                render: function (data) {
                    const map = {
                        'Donated':    'bg-success-subtle text-success',
                        'Registered': 'bg-primary-subtle text-primary',
                        'Rejected':   'bg-danger-subtle text-danger',
                        'No Show':    'bg-warning-subtle text-warning'
                    };
                    return '<span class="badge ' + (map[data] || 'bg-secondary-subtle text-secondary') + '">' + data + '</span>';
                }
            },
            {
                data: 'registered_at',
                render: function (data) {
                    if (!data) return '—';
                    return new Date(data.replace(' ', 'T')).toLocaleTimeString('en-US', {
                        hour: '2-digit', minute: '2-digit'
                    });
                }
            },
            {
                data: 'id',
                orderable: false,
                render: function (data) {
                    return `
                        <div class="d-flex gap-1">
                            <button class="btn btn-icon btn-outline-primary btn-edit-reg" data-id="${data}" title="Edit">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-icon btn-outline-danger btn-delete-reg" data-id="${data}" title="Remove">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </div>`;
                }
            }
        ],
        order: [[0, 'asc']],
        language: { emptyTable: 'Nobody has been marked in at this camp yet.' }
    });

    // ── Camp / filter changes ────────────────────────
    $('#campSelect').on('change', function () {
        $('#regCampId').val(currentCampId());
        hideEntryPanel();
        table.ajax.reload();
    });

    $('#filterStatus, #filterBloodGroup').on('change', function () {
        table.ajax.reload();
    });

    // ── Lookup ───────────────────────────────────────
    function doLookup() {
        const mobile = $('#tpInput').val().trim();

        if (!mobile) {
            showToast('Enter a T.P. number first.', 'error');
            return;
        }

        if (currentCampId() <= 0) {
            showToast('Select a camp first.', 'error');
            return;
        }

        const $btn = $('#btnLookup');
        setButtonLoading($btn);

        $.post(LOOKUP_URL, {
            csrf_token: window.CSRF_TOKEN,
            camp_id: currentCampId(),
            mobile: mobile
        }, function (res) {
            setButtonLoading($btn, false);

            if (!res.success) {
                showToast(res.message, 'error');
                $('#lookupStatus').html(
                    '<div class="alert alert-danger py-2 mb-0">' + $('<span>').text(res.message).html() + '</div>'
                );
                return;
            }

            const d = res.data;

            if (d.state === 'already_registered') {
                const r = d.registration;
                $('#lookupStatus').html(
                    '<div class="alert alert-warning py-2 mb-0">' +
                    '<i class="fas fa-exclamation-triangle me-1"></i> <strong>' +
                    $('<span>').text(r.donor_name).html() +
                    '</strong> is already on this register (No. ' + (r.serial_no || '—') + ', ' + r.status + ').' +
                    '</div>'
                );
                hideEntryPanel();
                $('#tpInput').val('').focus();
                return;
            }

            if (d.state === 'known_donor') {
                const donor = d.donor;
                $('#lookupStatus').html(
                    '<div class="alert alert-success py-2 mb-0">' +
                    '<i class="fas fa-user-check me-1"></i> Existing donor — <strong>' +
                    $('<span>').text(donor.donor_name).html() + '</strong>' +
                    (d.donation_count ? ' · ' + d.donation_count + ' previous donation(s)' : '') +
                    '</div>'
                );

                fillForm({
                    mobile:        d.mobile,
                    donor_name:    donor.donor_name,
                    address:       donor.address || '',
                    blood_group:   donor.blood_group || '',
                    gender:        donor.gender || '',
                    date_of_birth: donor.date_of_birth || ''
                });
                return;
            }

            // New walk-in
            $('#lookupStatus').html(
                '<div class="alert alert-info py-2 mb-0">' +
                '<i class="fas fa-user-plus me-1"></i> New donor — fill in the details below.' +
                '</div>'
            );

            fillForm({ mobile: d.mobile, donor_name: '', address: '', blood_group: '', gender: '', date_of_birth: '' });

        }, 'json').fail(function () {
            setButtonLoading($btn, false);
            showToast('Lookup failed. Please try again.', 'error');
        });
    }

    function fillForm(data) {
        $('#regId').val(0);
        $('#regCampId').val(currentCampId());
        $('#regMobile').val(data.mobile);
        $('#regName').val(data.donor_name);
        $('#regAddress').val(data.address);
        $('#regBloodGroup').val(data.blood_group);
        $('#regGender').val(data.gender);
        $('#regDob').val(data.date_of_birth);
        $('#regStatus').val('Registered');
        $('#regRemarks').val('');
        $('#btnSaveEntry').html('<i class="fas fa-check me-1"></i> Add to Register');
        $('#registerForm').find('.is-invalid').removeClass('is-invalid');
        $('#entryPanel').removeClass('d-none');
        $('#regName').trigger('focus');
    }

    function hideEntryPanel() {
        $('#entryPanel').addClass('d-none');
        $('#lookupStatus').empty();
    }

    $('#btnLookup').on('click', doLookup);

    $('#tpInput').on('keypress', function (e) {
        if (e.which === 13) {
            e.preventDefault();
            doLookup();
        }
    });

    $('#btnCancelEntry').on('click', function () {
        hideEntryPanel();
        $('#tpInput').val('').focus();
    });

    // ── Save entry ───────────────────────────────────
    $('#registerForm').on('submit', function (e) {
        e.preventDefault();

        const $btn = $('#btnSaveEntry');
        setButtonLoading($btn);
        $(this).find('.is-invalid').removeClass('is-invalid');

        $.post(SAVE_URL, $(this).serialize(), function (res) {
            setButtonLoading($btn, false);

            if (res.success) {
                showToast(res.message, 'success');
                hideEntryPanel();
                $('#tpInput').val('').focus();
                table.ajax.reload(null, false);
            } else {
                showToast(res.message, 'error');
                if (res.data && res.data.errors) {
                    showValidationErrors($('#registerForm'), res.data.errors);
                }
            }
        }, 'json').fail(function () {
            setButtonLoading($btn, false);
            showToast('Could not save. Please try again.', 'error');
        });
    });

    // ── Edit entry ───────────────────────────────────
    $(document).on('click', '.btn-edit-reg', function () {
        const row = table.row($(this).closest('tr')).data();
        if (!row) return;

        $('#regId').val(row.id);
        $('#regCampId').val(row.camp_id);
        $('#regMobile').val(row.mobile);
        $('#regName').val(row.donor_name);
        $('#regAddress').val(row.address || '');
        $('#regBloodGroup').val(row.blood_group || '');
        $('#regGender').val(row.gender || '');
        $('#regDob').val(row.date_of_birth || '');
        $('#regStatus').val(row.status);
        $('#regRemarks').val(row.remarks || '');

        $('#lookupStatus').html(
            '<div class="alert alert-primary py-2 mb-0">' +
            '<i class="fas fa-edit me-1"></i> Editing register entry No. ' + (row.serial_no || '—') +
            ' — ' + $('<span>').text(row.donor_name).html() +
            '</div>'
        );

        $('#btnSaveEntry').html('<i class="fas fa-save me-1"></i> Update Entry');
        $('#registerForm').find('.is-invalid').removeClass('is-invalid');
        $('#entryPanel').removeClass('d-none');

        $('html, body').animate({ scrollTop: $('#entryPanel').offset().top - 120 }, 300);
    });

    // ── Delete entry ─────────────────────────────────
    $(document).on('click', '.btn-delete-reg', function () {
        const id = $(this).data('id');

        confirmAction('Remove from register?', 'The donor record itself will be kept.', 'Yes, remove', 'danger')
        .then(function (result) {
            if (!result.isConfirmed) return;

            $.post(DELETE_URL, { csrf_token: window.CSRF_TOKEN, id: id }, function (res) {
                if (res.success) {
                    showToast(res.message, 'success');
                    table.ajax.reload(null, false);
                } else {
                    showToast(res.message, 'error');
                }
            }, 'json');
        });
    });

    // ── Export ───────────────────────────────────────
    function exportRegister(format) {
        if (currentCampId() <= 0) {
            showToast('Select a camp first.', 'error');
            return;
        }
        const params = $.param({
            camp_id: currentCampId(),
            format: format,
            status: $('#filterStatus').val()
        });
        window.location.href = EXPORT_URL + '?' + params;
    }

    $('#exportXlsx').on('click', function (e) { e.preventDefault(); exportRegister('xlsx'); });
    $('#exportCsv').on('click',  function (e) { e.preventDefault(); exportRegister('csv'); });
});
</script>

<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
