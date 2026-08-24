<?php
/**
 * Blood Camps Page
 * 
 * Manage blood donation camps with tabs for Upcoming/Past/All.
 * Add/Edit via Bootstrap modal.
 */

$pageTitle = 'Blood Camps';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- ── Action Bar ─────────────────────────────────────── -->
<div class="action-bar">
    <div>
        <button class="btn btn-primary" id="btnAddCamp" data-bs-toggle="modal" data-bs-target="#campModal">
            <i class="fas fa-plus me-1"></i> Add Camp
        </button>
    </div>

    <ul class="nav nav-pills" role="tablist">
        <li class="nav-item">
            <button class="nav-link active" data-filter="upcoming">Upcoming</button>
        </li>
        <li class="nav-item">
            <button class="nav-link" data-filter="past">Past</button>
        </li>
        <li class="nav-item">
            <button class="nav-link" data-filter="">All</button>
        </li>
    </ul>
</div>

<!-- ── Camps Table ────────────────────────────────────── -->
<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover" id="campsTable" style="width:100%">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Location</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<!-- ── Camp Modal ─────────────────────────────────────── -->
<div class="modal fade" id="campModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="campModalTitle">
                    <i class="fas fa-campground me-2 text-primary"></i> Add Blood Camp
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="campForm">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                <input type="hidden" name="id" id="campId" value="0">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Title <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="title" id="campTitle" required 
                                   placeholder="e.g., Community Blood Donation Camp">
                            <div class="invalid-feedback"></div>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Camp Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="camp_date" id="campDate" required>
                            <div class="invalid-feedback"></div>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Start Time</label>
                            <input type="time" class="form-control" name="start_time" id="campStartTime">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">End Time</label>
                            <input type="time" class="form-control" name="end_time" id="campEndTime">
                        </div>

                        <div class="col-12">
                            <label class="form-label">Location <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="location" id="campLocation" required
                                   placeholder="e.g., Town Hall, Kandy">
                            <div class="invalid-feedback"></div>
                        </div>

                        <div class="col-md-8">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" id="campDescription" rows="3" 
                                      placeholder="Additional details about the camp..."></textarea>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status" id="campStatus">
                                <option value="Upcoming">Upcoming</option>
                                <option value="Completed">Completed</option>
                                <option value="Cancelled">Cancelled</option>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Planned Budget</label>
                            <div class="input-group">
                                <span class="input-group-text"><?= sanitize(getSetting('currency_symbol', 'Rs.')) ?></span>
                                <input type="number" class="form-control" name="budget_amount" id="campBudget"
                                       step="0.01" min="0" placeholder="Optional">
                            </div>
                            <div class="form-text">Track spending against this on Budget &amp; Donations.</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="btnSaveCamp">
                        <i class="fas fa-save me-1"></i> Save
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function () {

    let currentFilter = 'upcoming';

    // ── DataTable ────────────────────────────────────
    const table = $('#campsTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '<?= BASE_URL ?>/ajax/camp-list.php',
            type: 'POST',
            data: function (d) {
                d.csrf_token = window.CSRF_TOKEN;
                d.filter = currentFilter;
            }
        },
        columns: [
            { data: 'title' },
            {
                data: 'camp_date',
                render: function (data) {
                    if (!data) return '—';
                    const d = new Date(data);
                    return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
                }
            },
            {
                data: null,
                render: function (data) {
                    const start = data.start_time ? new Date('2000-01-01T' + data.start_time).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' }) : '';
                    const end = data.end_time ? new Date('2000-01-01T' + data.end_time).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' }) : '';
                    return start && end ? `${start} — ${end}` : (start || '—');
                }
            },
            {
                data: 'location',
                render: function (data) {
                    return data ? '<span class="text-truncate-2">' + $('<span>').text(data).html() + '</span>' : '—';
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
                render: function (data, type, row) {
                    return `
                        <div class="d-flex gap-1">
                            <a href="<?= BASE_URL ?>/admin/camp-register.php?camp_id=${data}"
                               class="btn btn-icon btn-outline-success" title="Open register">
                                <i class="fas fa-clipboard-list"></i>
                            </a>
                            <a href="<?= BASE_URL ?>/admin/camp-finance.php?camp_id=${data}"
                               class="btn btn-icon btn-outline-warning" title="Budget & donations">
                                <i class="fas fa-hand-holding-heart"></i>
                            </a>
                            <button class="btn btn-icon btn-outline-primary btn-edit-camp" data-id="${data}" title="Edit">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-icon btn-outline-danger btn-delete-camp" data-id="${data}" title="Delete">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </div>`;
                }
            }
        ],
        order: [[1, 'asc']]
    });

    // ── Tab Filters ──────────────────────────────────
    $('.nav-pills .nav-link').on('click', function () {
        $('.nav-pills .nav-link').removeClass('active');
        $(this).addClass('active');
        currentFilter = $(this).data('filter');
        table.ajax.reload();
    });

    // ── Add Camp ─────────────────────────────────────
    $('#btnAddCamp').on('click', function () {
        resetForm($('#campForm'));
        $('#campId').val(0);
        $('#campModalTitle').html('<i class="fas fa-campground me-2 text-primary"></i> Add Blood Camp');
    });

    // ── Edit Camp ────────────────────────────────────
    $(document).on('click', '.btn-edit-camp', function () {
        const id = $(this).data('id');

        // Get camp data from the table row
        const rowData = table.row($(this).closest('tr')).data();

        $('#campId').val(rowData.id);
        $('#campTitle').val(rowData.title);
        $('#campDate').val(rowData.camp_date);
        $('#campStartTime').val(rowData.start_time || '');
        $('#campEndTime').val(rowData.end_time || '');
        $('#campLocation').val(rowData.location);
        $('#campDescription').val(rowData.description || '');
        $('#campStatus').val(rowData.status);
        $('#campBudget').val(rowData.budget_amount !== null ? rowData.budget_amount : '');
        $('#campModalTitle').html('<i class="fas fa-campground me-2 text-primary"></i> Edit Blood Camp');

        const modal = new bootstrap.Modal(document.getElementById('campModal'));
        modal.show();
    });

    // ── Save Camp ────────────────────────────────────
    $('#campForm').on('submit', function (e) {
        e.preventDefault();
        const $btn = $('#btnSaveCamp');
        setButtonLoading($btn);
        $(this).find('.is-invalid').removeClass('is-invalid');

        $.post('<?= BASE_URL ?>/ajax/camp-save.php', $(this).serialize(), function (res) {
            setButtonLoading($btn, false);
            if (res.success) {
                showToast(res.message, 'success');
                bootstrap.Modal.getInstance(document.getElementById('campModal')).hide();
                table.ajax.reload(null, false);
            } else {
                showToast(res.message, 'error');
            }
        }, 'json');
    });

    // ── Delete Camp ──────────────────────────────────
    $(document).on('click', '.btn-delete-camp', function () {
        const id = $(this).data('id');

        confirmAction('Delete Camp?', 'This action cannot be undone.', 'Yes, delete', 'danger')
        .then((result) => {
            if (result.isConfirmed) {
                $.post('<?= BASE_URL ?>/ajax/camp-delete.php', {
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
