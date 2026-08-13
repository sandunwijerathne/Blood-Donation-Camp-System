<?php
/**
 * Message Templates Page
 */

$pageTitle = 'Templates';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="action-bar">
    <button class="btn btn-primary" id="btnAddTemplate" data-bs-toggle="modal" data-bs-target="#templateModal">
        <i class="fas fa-plus me-1"></i> Add Template
    </button>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover" id="templatesTable" style="width:100%">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Type</th>
                        <th>Body</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="templateModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="templateModalTitle">
                    <i class="fas fa-file-alt me-2 text-primary"></i> Add Template
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="templateForm">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                <input type="hidden" name="id" id="templateId" value="0">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-7">
                            <label class="form-label">Template Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="template_name" id="templateName" required>
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">Type</label>
                            <select class="form-select" name="template_type" id="templateType">
                                <option value="General">General</option>
                                <option value="Camp Notification">Camp Notification</option>
                                <option value="Emergency Request">Emergency Request</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Template Body <span class="text-danger">*</span></label>
                            <textarea class="form-control" name="template_body" id="templateBody" rows="8" required></textarea>
                            <div class="form-text">Supported placeholders: {NAME}, {DATE}, {LOCATION}, {BLOOD_GROUP}, {MESSAGE}</div>
                            <div class="invalid-feedback"></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="btnSaveTemplate">
                        <i class="fas fa-save me-1"></i> Save
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function () {
    const table = $('#templatesTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '<?= BASE_URL ?>/ajax/template-list.php',
            type: 'POST',
            data: function (d) {
                d.csrf_token = window.CSRF_TOKEN;
            }
        },
        columns: [
            { data: 'template_name' },
            { data: 'template_type' },
            {
                data: 'template_body',
                render: function (data) {
                    return '<span class="text-truncate-2">' + $('<span>').text(data || '').html() + '</span>';
                }
            },
            { data: 'created_at' },
            {
                data: 'id',
                orderable: false,
                searchable: false,
                render: function (data) {
                    return `
                        <div class="d-flex gap-1">
                            <button class="btn btn-icon btn-outline-primary btn-edit-template" data-id="${data}" title="Edit">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-icon btn-outline-danger btn-delete-template" data-id="${data}" title="Delete">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </div>`;
                }
            }
        ],
        order: [[0, 'asc']]
    });

    $('#btnAddTemplate').on('click', function () {
        resetForm($('#templateForm'));
        $('#templateId').val(0);
        $('#templateModalTitle').html('<i class="fas fa-file-alt me-2 text-primary"></i> Add Template');
    });

    $(document).on('click', '.btn-edit-template', function () {
        const row = table.row($(this).closest('tr')).data();
        $('#templateId').val(row.id);
        $('#templateName').val(row.template_name);
        $('#templateType').val(row.template_type);
        $('#templateBody').val(row.template_body);
        $('#templateModalTitle').html('<i class="fas fa-file-alt me-2 text-primary"></i> Edit Template');
        new bootstrap.Modal(document.getElementById('templateModal')).show();
    });

    $('#templateForm').on('submit', function (e) {
        e.preventDefault();
        const $btn = $('#btnSaveTemplate');
        setButtonLoading($btn);

        $.post('<?= BASE_URL ?>/ajax/template-save.php', $(this).serialize(), function (res) {
            setButtonLoading($btn, false);
            if (res.success) {
                showToast(res.message, 'success');
                bootstrap.Modal.getInstance(document.getElementById('templateModal')).hide();
                table.ajax.reload(null, false);
            } else {
                if (res.data && res.data.errors) {
                    showValidationErrors($('#templateForm'), res.data.errors);
                }
                showToast(res.message, 'error');
            }
        }, 'json').fail(function () {
            setButtonLoading($btn, false);
        });
    });

    $(document).on('click', '.btn-delete-template', function () {
        const id = $(this).data('id');
        confirmAction('Delete Template?', 'This template will be removed.', 'Yes, delete', 'danger')
            .then((result) => {
                if (result.isConfirmed) {
                    $.post('<?= BASE_URL ?>/ajax/template-delete.php', {
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
