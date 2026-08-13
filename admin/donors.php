<?php
/**
 * Donors List Page
 * 
 * DataTables server-side listing with blood group filter,
 * status filter, import/export, and bulk actions.
 */

$pageTitle = 'Donors';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- ── Action Bar ─────────────────────────────────────── -->
<div class="action-bar">
    <div class="d-flex align-items-center gap-2 flex-wrap">
        <a href="<?= BASE_URL ?>/admin/donor-add.php" class="btn btn-primary">
            <i class="fas fa-plus me-1"></i> Add Donor
        </a>
        <button class="btn btn-success" id="btnImport" data-bs-toggle="modal" data-bs-target="#importModal">
            <i class="fas fa-file-import me-1"></i> Import
        </button>
        <div class="btn-group">
            <button class="btn btn-outline-primary" id="btnExportExcel">
                <i class="fas fa-file-excel me-1"></i> Excel
            </button>
            <button class="btn btn-outline-primary" id="btnExportCSV">
                <i class="fas fa-file-csv me-1"></i> CSV
            </button>
        </div>
    </div>

    <div class="d-flex align-items-center gap-2 flex-wrap">
        <select class="form-select form-select-sm" id="filterBloodGroup" style="width: auto;">
            <option value="">All Blood Groups</option>
            <option value="A+">A+</option>
            <option value="A-">A-</option>
            <option value="B+">B+</option>
            <option value="B-">B-</option>
            <option value="AB+">AB+</option>
            <option value="AB-">AB-</option>
            <option value="O+">O+</option>
            <option value="O-">O-</option>
        </select>
        <select class="form-select form-select-sm" id="filterStatus" style="width: auto;">
            <option value="">All Status</option>
            <option value="Active">Active</option>
            <option value="Inactive">Inactive</option>
        </select>
    </div>
</div>

<!-- ── Donors Table ───────────────────────────────────── -->
<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover" id="donorsTable" style="width:100%">
                <thead>
                    <tr>
                        <th><input type="checkbox" id="selectAll" class="form-check-input"></th>
                        <th>Name</th>
                        <th>Mobile</th>
                        <th>Blood Group</th>
                        <th>Gender</th>
                        <th>Last Donation</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<!-- ── Import Modal ───────────────────────────────────── -->
<div class="modal fade" id="importModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-file-import me-2 text-success"></i>Import Donors</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="importForm" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Select Excel File (.xlsx, .xls, .csv)</label>
                        <input type="file" class="form-control" name="excel_file" id="excelFile" 
                               accept=".xlsx,.xls,.csv" required>
                    </div>
                    <div class="alert alert-info small py-2 mb-0">
                        <i class="fas fa-info-circle me-1"></i>
                        <strong>Expected columns:</strong> Name, Mobile, WhatsApp, Email, Address, Blood Group, Gender, Date Of Birth, Last Donation Date
                        <br>
                        <i class="fas fa-check-circle me-1 mt-1"></i>
                        Duplicates (by mobile number) will be skipped.
                    </div>

                    <div class="import-progress d-none" id="importProgress">
                        <div class="progress">
                            <div class="progress-bar" id="importProgressBar" style="width: 0%"></div>
                        </div>
                        <div class="import-stats mt-2">
                            <div class="stat-item success">
                                <i class="fas fa-check-circle"></i> <span id="importSuccess">0</span> imported
                            </div>
                            <div class="stat-item skipped">
                                <i class="fas fa-forward"></i> <span id="importSkipped">0</span> skipped
                            </div>
                            <div class="stat-item failed">
                                <i class="fas fa-times-circle"></i> <span id="importFailed">0</span> failed
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success" id="btnDoImport">
                        <i class="fas fa-upload me-1"></i> Import
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ── Page Script ────────────────────────────────────── -->
<script>
$(document).ready(function () {

    // ── DataTable Init ───────────────────────────────
    const table = $('#donorsTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '<?= BASE_URL ?>/ajax/donor-list.php',
            type: 'POST',
            data: function (d) {
                d.csrf_token = window.CSRF_TOKEN;
                d.blood_group = $('#filterBloodGroup').val();
                d.status = $('#filterStatus').val();
            }
        },
        columns: [
            {
                data: 'id',
                orderable: false,
                searchable: false,
                render: function (data) {
                    return '<input type="checkbox" class="form-check-input row-select" value="' + data + '">';
                }
            },
            { data: 'donor_name' },
            { data: 'mobile' },
            {
                data: 'blood_group',
                render: function (data) {
                    return '<span class="badge-blood" data-blood="' + data + '">' + data + '</span>';
                }
            },
            { data: 'gender' },
            {
                data: 'last_donation_date',
                render: function (data) {
                    if (!data || data === '0000-00-00') return '<span class="text-muted">—</span>';
                    const d = new Date(data);
                    return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
                }
            },
            {
                data: 'status',
                render: function (data) {
                    return '<span class="badge-status ' + data.toLowerCase() + '">' + data + '</span>';
                }
            },
            {
                data: 'id',
                orderable: false,
                searchable: false,
                render: function (data, type, row) {
                    return `
                        <div class="d-flex gap-1">
                            <a href="<?= BASE_URL ?>/admin/donor-edit.php?id=${data}" 
                               class="btn btn-icon btn-outline-primary" title="Edit"
                               data-bs-toggle="tooltip">
                                <i class="fas fa-edit"></i>
                            </a>
                            <button class="btn btn-icon btn-outline-${row.status === 'Active' ? 'warning' : 'success'} btn-toggle-status" 
                                    data-id="${data}" data-status="${row.status}"
                                    title="${row.status === 'Active' ? 'Deactivate' : 'Activate'}"
                                    data-bs-toggle="tooltip">
                                <i class="fas fa-${row.status === 'Active' ? 'ban' : 'check'}"></i>
                            </button>
                            <button class="btn btn-icon btn-outline-danger btn-delete-donor" 
                                    data-id="${data}" title="Delete"
                                    data-bs-toggle="tooltip">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </div>`;
                }
            }
        ],
        order: [[1, 'asc']],
        drawCallback: function () {
            // Re-init tooltips after draw
            $('[data-bs-toggle="tooltip"]').tooltip();
        }
    });

    // ── Filters ──────────────────────────────────────
    $('#filterBloodGroup, #filterStatus').on('change', function () {
        table.ajax.reload();
    });

    // ── Select All ───────────────────────────────────
    $('#selectAll').on('change', function () {
        $('.row-select').prop('checked', this.checked);
    });

    // ── Toggle Status ────────────────────────────────
    $(document).on('click', '.btn-toggle-status', function () {
        const id = $(this).data('id');
        const currentStatus = $(this).data('status');
        const newStatus = currentStatus === 'Active' ? 'Inactive' : 'Active';

        confirmAction(
            `${newStatus === 'Inactive' ? 'Deactivate' : 'Activate'} Donor?`,
            `This donor will be marked as ${newStatus}.`
        ).then((result) => {
            if (result.isConfirmed) {
                $.post('<?= BASE_URL ?>/ajax/donor-status.php', {
                    csrf_token: window.CSRF_TOKEN,
                    id: id,
                    status: newStatus
                }, function (res) {
                    if (res.success) {
                        showToast(res.message, 'success');
                        table.ajax.reload(null, false);
                    } else {
                        showToast(res.message, 'error');
                    }
                }, 'json');
            }
        });
    });

    // ── Delete Donor ─────────────────────────────────
    $(document).on('click', '.btn-delete-donor', function () {
        const id = $(this).data('id');

        confirmAction(
            'Delete Donor?',
            'This action cannot be undone.',
            'Yes, delete',
            'danger'
        ).then((result) => {
            if (result.isConfirmed) {
                $.post('<?= BASE_URL ?>/ajax/donor-delete.php', {
                    csrf_token: window.CSRF_TOKEN,
                    id: id
                }, function (res) {
                    if (res.success) {
                        showToast(res.message, 'success');
                        table.ajax.reload(null, false);
                    } else {
                        showToast(res.message, 'error');
                    }
                }, 'json');
            }
        });
    });

    // ── Export ────────────────────────────────────────
    $('#btnExportExcel').on('click', function () {
        const bg = $('#filterBloodGroup').val();
        const st = $('#filterStatus').val();
        window.location.href = `<?= BASE_URL ?>/ajax/donor-export.php?format=xlsx&blood_group=${bg}&status=${st}`;
    });

    $('#btnExportCSV').on('click', function () {
        const bg = $('#filterBloodGroup').val();
        const st = $('#filterStatus').val();
        window.location.href = `<?= BASE_URL ?>/ajax/donor-export.php?format=csv&blood_group=${bg}&status=${st}`;
    });

    // ── Import ───────────────────────────────────────
    $('#importForm').on('submit', function (e) {
        e.preventDefault();

        const formData = new FormData(this);
        const $btn = $('#btnDoImport');
        setButtonLoading($btn);

        $('#importProgress').removeClass('d-none');
        $('#importProgressBar').css('width', '0%');

        $.ajax({
            url: '<?= BASE_URL ?>/ajax/donor-import.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            xhr: function () {
                const xhr = new window.XMLHttpRequest();
                xhr.upload.addEventListener('progress', function (e) {
                    if (e.lengthComputable) {
                        const pct = Math.round((e.loaded / e.total) * 100);
                        $('#importProgressBar').css('width', pct + '%');
                    }
                });
                return xhr;
            },
            success: function (res) {
                setButtonLoading($btn, false);
                if (res.success) {
                    $('#importProgressBar').css('width', '100%');
                    $('#importSuccess').text(res.data.imported || 0);
                    $('#importSkipped').text(res.data.skipped || 0);
                    $('#importFailed').text(res.data.failed || 0);
                    showToast(res.message, 'success');
                    table.ajax.reload();
                } else {
                    showToast(res.message, 'error');
                }
            },
            error: function () {
                setButtonLoading($btn, false);
                showToast('Import failed. Please try again.', 'error');
            }
        });
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
