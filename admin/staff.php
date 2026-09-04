<?php
/**
 * Staff Page
 *
 * The camp organising committee. Only a name and a mobile number are
 * kept - these are people to contact, not donors, so none of the donor
 * medical fields apply.
 */

$pageTitle = 'Staff';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- ── Action Bar ─────────────────────────────────────── -->
<div class="action-bar">
    <div>
        <button class="btn btn-primary" id="btnAddStaff" data-bs-toggle="modal" data-bs-target="#staffModal">
            <i class="fas fa-plus me-1"></i> Add Staff
        </button>
    </div>

    <ul class="nav nav-pills" role="tablist">
        <li class="nav-item">
            <button class="nav-link active" data-status="Active">Active</button>
        </li>
        <li class="nav-item">
            <button class="nav-link" data-status="Inactive">Inactive</button>
        </li>
        <li class="nav-item">
            <button class="nav-link" data-status="">All</button>
        </li>
    </ul>
</div>

<!-- ── Staff Table ────────────────────────────────────── -->
<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover" id="staffTable" style="width:100%">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Mobile</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<!-- ── Staff Modal ────────────────────────────────────── -->
<div class="modal fade" id="staffModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="staffModalTitle">
                    <i class="fas fa-users-gear me-2 text-primary"></i> Add Staff
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="staffForm">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                <input type="hidden" name="id" id="staffId" value="0">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="name" id="staffName" required
                                   placeholder="e.g., K. Perera">
                            <div class="invalid-feedback"></div>
                        </div>

                        <div class="col-md-7">
                            <label class="form-label">Mobile <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="mobile" id="staffMobile" required
                                   placeholder="07XXXXXXXX">
                            <div class="form-text">
                                Any format is accepted - 077 821 1176 and +94778211176 are stored the same way.
                            </div>
                            <div class="invalid-feedback"></div>
                        </div>

                        <div class="col-md-5">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status" id="staffStatus">
                                <option value="Active">Active</option>
                                <option value="Inactive">Inactive</option>
                            </select>
                            <div class="form-text">Only Active staff receive messages.</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="btnSaveStaff">
                        <i class="fas fa-save me-1"></i> Save
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function () {

    let currentStatus = 'Active';

    // ── DataTable ────────────────────────────────────
    const table = $('#staffTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '<?= BASE_URL ?>/ajax/staff-list.php',
            type: 'POST',
            data: function (d) {
                d.csrf_token = window.CSRF_TOKEN;
                d.status = currentStatus;
            }
        },
        columns: [
            { data: 'name', render: $.fn.dataTable.render.text() },
            { data: 'mobile', render: $.fn.dataTable.render.text() },
            {
                data: 'status',
                render: function (data) {
                    return '<span class="badge-status ' + data.toLowerCase() + '">' + data + '</span>';
                }
            },
            {
                data: 'id',
                orderable: false,
                render: function (data) {
                    return '<div class="d-flex gap-1">'
                        + '<button class="btn btn-icon btn-outline-primary btn-edit-staff" data-id="' + data + '" title="Edit">'
                        + '<i class="fas fa-edit"></i></button>'
                        + '<button class="btn btn-icon btn-outline-danger btn-delete-staff" data-id="' + data + '" title="Delete">'
                        + '<i class="fas fa-trash-alt"></i></button>'
                        + '</div>';
                }
            }
        ],
        order: [[0, 'asc']]
    });

    // ── Status Filters ───────────────────────────────
    $('.nav-pills .nav-link').on('click', function () {
        $('.nav-pills .nav-link').removeClass('active');
        $(this).addClass('active');
        currentStatus = $(this).data('status');
        table.ajax.reload();
    });

    // ── Add Staff ────────────────────────────────────
    $('#btnAddStaff').on('click', function () {
        resetForm($('#staffForm'));
        $('#staffId').val(0);
        $('#staffModalTitle').html('<i class="fas fa-users-gear me-2 text-primary"></i> Add Staff');
    });

    // ── Edit Staff ───────────────────────────────────
    $(document).on('click', '.btn-edit-staff', function () {
        const rowData = table.row($(this).closest('tr')).data();

        $('#staffId').val(rowData.id);
        $('#staffName').val(rowData.name);
        $('#staffMobile').val(rowData.mobile);
        $('#staffStatus').val(rowData.status);
        $('#staffModalTitle').html('<i class="fas fa-users-gear me-2 text-primary"></i> Edit Staff');

        const modal = new bootstrap.Modal(document.getElementById('staffModal'));
        modal.show();
    });

    // ── Save Staff ───────────────────────────────────
    $('#staffForm').on('submit', function (e) {
        e.preventDefault();
        const $btn = $('#btnSaveStaff');
        setButtonLoading($btn);
        $(this).find('.is-invalid').removeClass('is-invalid');

        $.post('<?= BASE_URL ?>/ajax/staff-save.php', $(this).serialize(), function (res) {
            setButtonLoading($btn, false);
            if (res.success) {
                showToast(res.message, 'success');
                bootstrap.Modal.getInstance(document.getElementById('staffModal')).hide();
                table.ajax.reload(null, false);
            } else {
                showToast(res.message, 'error');
            }
        }, 'json');
    });

    // ── Delete Staff ─────────────────────────────────
    $(document).on('click', '.btn-delete-staff', function () {
        const id = $(this).data('id');

        confirmAction('Delete Staff Member?', 'Messages already sent to them stay in the log.', 'Yes, delete', 'danger')
        .then((result) => {
            if (result.isConfirmed) {
                $.post('<?= BASE_URL ?>/ajax/staff-delete.php', {
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
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
