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
    <button class="btn btn-outline-success" id="btnCheckMeta">
        <i class="fab fa-whatsapp me-1"></i> Check Approved Templates at Meta
    </button>
</div>

<!-- What Meta actually has approved, which is what WhatsApp will accept -->
<div class="card mb-3 d-none" id="metaTemplatesCard">
    <div class="card-header d-flex align-items-center justify-content-between">
        <span><i class="fab fa-whatsapp me-2 text-success"></i> Approved at Meta</span>
        <button class="btn-close" id="btnCloseMeta"></button>
    </div>
    <div class="card-body">
        <div id="metaTemplatesBody"></div>
    </div>
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

                        <div class="col-12">
                            <div class="divider"></div>
                            <div class="alert alert-info py-2 small mb-3">
                                <i class="fab fa-whatsapp me-1"></i>
                                <strong>WhatsApp delivery.</strong> To message donors who have not written to you
                                in the last 24 hours, WhatsApp requires a template approved in
                                <em>WhatsApp Manager &rarr; Message Templates</em>. Enter its details below so
                                this template can be sent. Leave blank to use this template for SMS only.
                            </div>
                        </div>

                        <div class="col-md-5">
                            <label class="form-label">WhatsApp Template Name</label>
                            <input type="text" class="form-control" name="whatsapp_template_name"
                                   id="whatsappTemplateName" placeholder="blood_camp_notification">
                            <div class="form-text">Exactly as approved by Meta (lowercase, underscores).</div>
                            <div class="invalid-feedback"></div>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Language Code</label>
                            <input type="text" class="form-control" name="whatsapp_language"
                                   id="whatsappLanguage" placeholder="en" value="en">
                            <div class="form-text">e.g. en, en_US, si</div>
                            <div class="invalid-feedback"></div>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Variable Order</label>
                            <input type="text" class="form-control" name="whatsapp_variables"
                                   id="whatsappVariables" placeholder="NAME,DATE,LOCATION">
                            <div class="form-text">Feeds Meta's &#123;&#123;1&#125;&#125;, &#123;&#123;2&#125;&#125;, &#123;&#123;3&#125;&#125; in order.</div>
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

    // ── What does Meta actually have approved? ───────
    $('#btnCloseMeta').on('click', function () {
        $('#metaTemplatesCard').addClass('d-none');
    });

    $('#btnCheckMeta').on('click', function () {
        const $btn = $(this);
        setButtonLoading($btn);

        $.post('<?= BASE_URL ?>/ajax/template-sync.php', { csrf_token: window.CSRF_TOKEN }, function (res) {
            setButtonLoading($btn, false);

            if (!res.success) {
                showToast(res.message, 'error');
                return;
            }

            const list = (res.data && res.data.templates) || [];
            let html = '';

            if (!list.length) {
                html = '<div class="alert alert-warning mb-0">' +
                       '<i class="fas fa-triangle-exclamation me-1"></i>' +
                       'No templates exist at Meta yet. Until you create and get one approved in ' +
                       'WhatsApp Manager, WhatsApp will reject every message to donors who have not ' +
                       'written to you in the last 24 hours.</div>';
            } else {
                html = '<div class="table-responsive"><table class="table table-sm mb-0">' +
                       '<thead><tr><th>Name</th><th>Language</th><th>Status</th>' +
                       '<th>Variables</th><th>Body</th></tr></thead><tbody>';

                list.forEach(function (t) {
                    const approved = (t.status || '').toUpperCase() === 'APPROVED';
                    const esc = s => $('<span>').text(s || '').html();
                    html += '<tr>' +
                        '<td><code>' + esc(t.name) + '</code></td>' +
                        '<td>' + esc(t.language) + '</td>' +
                        '<td><span class="badge ' +
                            (approved ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-warning') +
                            '">' + esc(t.status) + '</span></td>' +
                        '<td>' + (t.variable_count || 0) + '</td>' +
                        '<td><small>' + esc((t.body || '').substring(0, 90)) + '</small></td>' +
                        '</tr>';
                });

                html += '</tbody></table></div>' +
                    '<div class="form-text mt-2">Only <strong>APPROVED</strong> templates can be sent. ' +
                    'The name and language here must match exactly what you enter on a template below. ' +
                    'Note <code>hello_world</code> only works from Meta\'s public test numbers, not your own.</div>';
            }

            $('#metaTemplatesBody').html(html);
            $('#metaTemplatesCard').removeClass('d-none');
            showToast(res.message, 'success');
        }, 'json').fail(function () {
            setButtonLoading($btn, false);
            showToast('Could not reach Meta. Check your token in Settings.', 'error');
        });
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
        $('#whatsappTemplateName').val(row.whatsapp_template_name || '');
        $('#whatsappLanguage').val(row.whatsapp_language || 'en');
        $('#whatsappVariables').val(row.whatsapp_variables || '');
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
