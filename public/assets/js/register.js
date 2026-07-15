let dtInstance;
let selectedIds = new Set();

function currentFilters() {
    return {
        search:            $('#globalSearch').val() || '',
        branch_id:         $('#f_branch_id').val() || '',
        loan_group:        $('#f_loan_group').val() || '',
        loan_status_id:    $('#f_loan_status_id').val() || '',
        report_status_id:  $('#f_report_status_id').val() || '',
        date_loaded_from:  $('#f_date_loaded_from').val() || '',
        date_loaded_to:    $('#f_date_loaded_to').val() || '',
        action_date_from:  $('#f_action_date_from').val() || '',
        action_date_to:    $('#f_action_date_to').val() || '',
        amount_min:        $('#f_amount_min').val() || '',
        amount_max:        $('#f_amount_max').val() || '',
        loan_count_min:    $('#f_loan_count_min').val() || '',
        loan_count_max:    $('#f_loan_count_max').val() || '',
    };
}

const ORDER_COLUMNS = [
    null, 'reference_number', 'name', 'surname', 'id_number', 'account_number', 'amount',
    'branch_name', 'loan_count', 'loan_group', 'status', 'report_status', 'action_date', 'date_loaded', null,
];

$(function () {
    // preselect group filter from query string (e.g. dashboard group card links)
    const params = new URLSearchParams(window.location.search);
    if (params.get('loan_group')) $('#f_loan_group').val(params.get('loan_group'));

    dtInstance = $('#loanTable').DataTable({
        processing: true,
        serverSide: true,
        searching: false,
        lengthMenu: [10, 25, 50, 100],
        pageLength: 25,
        order: [[13, 'desc']],
        ajax: function (data, callback) {
            const orderIdx = data.order[0].column;
            const orderDir = data.order[0].dir;
            const params = Object.assign({
                draw: data.draw,
                start: data.start,
                length: data.length,
                order_col: ORDER_COLUMNS[orderIdx] || 'date_loaded',
                order_dir: orderDir,
            }, currentFilters());

            $.get(window.APP_URL + '/loans/data', params, function (res) {
                callback(res);
            }).fail(function () {
                callback({ draw: data.draw, recordsTotal: 0, recordsFiltered: 0, data: [] });
            });
        },
        columns: [
            {
                data: 'id', orderable: false, className: 'text-center',
                render: (id) => `<input type="checkbox" class="row-check" value="${id}">`
            },
            { data: 'reference_number', render: (d) => `<span class="fw-semibold">${d}</span>` },
            { data: 'name' },
            { data: 'surname' },
            { data: 'id_number' },
            { data: 'account_number' },
            { data: 'amount', render: (d) => fmtMoney(d) },
            { data: 'branch_name' },
            { data: 'loan_count', className: 'text-center' },
            { data: 'loan_group', render: (d) => `<span class="group-pill ${groupBadgeClass(d)}">${d}</span>` },
            { data: 'status', render: (d) => `<span class="badge-status ${statusBadgeClass(d)}">${d}</span>` },
            { data: 'report_status', render: (d) => `<span class="badge-status ${statusBadgeClass(d)}">${d}</span>` },
            { data: 'action_date', render: (d) => d ? new Date(d).toLocaleDateString('en-ZA') : '' },
            { data: 'date_loaded', render: (d) => d ? new Date(d).toLocaleDateString('en-ZA') : '' },
            {
                data: 'id', orderable: false, className: 'text-nowrap',
                render: (id) => `
                    <button class="btn btn-sm btn-outline-brand edit-loan-btn" data-id="${id}"><i class="bi bi-pencil"></i></button>
                    <button class="btn btn-sm btn-outline-danger delete-loan-btn" data-id="${id}"><i class="bi bi-trash"></i></button>`
            },
        ],
        drawCallback: function () {
            $('#loanTable tbody .row-check').each(function () {
                if (selectedIds.has(String($(this).val()))) $(this).prop('checked', true);
            });
            updateBulkBar();
        },
        language: { emptyTable: 'No loans found for the selected filters.' },
    });

    $('#searchBtn').on('click', () => dtInstance.ajax.reload());
    $('#globalSearch').on('keypress', function (e) { if (e.which === 13) dtInstance.ajax.reload(); });
    $('#f_branch_id, #f_loan_group, #f_loan_status_id, #f_report_status_id, #f_date_loaded_from, #f_date_loaded_to, #f_action_date_from, #f_action_date_to, #f_amount_min, #f_amount_max, #f_loan_count_min, #f_loan_count_max')
        .on('change', () => dtInstance.ajax.reload());

    $('#clearFiltersBtn').on('click', function () {
        $('#globalSearch, #f_amount_min, #f_amount_max, #f_loan_count_min, #f_loan_count_max, #f_date_loaded_from, #f_date_loaded_to, #f_action_date_from, #f_action_date_to').val('');
        $('#f_branch_id, #f_loan_group, #f_loan_status_id, #f_report_status_id').val('');
        dtInstance.ajax.reload();
    });

    $('#selectAll').on('change', function () {
        const checked = $(this).is(':checked');
        $('#loanTable tbody .row-check').each(function () {
            $(this).prop('checked', checked);
            checked ? selectedIds.add(String($(this).val())) : selectedIds.delete(String($(this).val()));
        });
        updateBulkBar();
    });

    $('#loanTable tbody').on('change', '.row-check', function () {
        const id = String($(this).val());
        $(this).is(':checked') ? selectedIds.add(id) : selectedIds.delete(id);
        updateBulkBar();
    });

    function updateBulkBar() {
        const count = selectedIds.size;
        $('#bulkActionBar').toggleClass('show', count > 0);
        $('#selectedCount').text(count + (count === 1 ? ' row selected' : ' rows selected'));
    }

    // ---- Export ----
    $('#exportSelectedBtn').on('click', function () {
        if (selectedIds.size === 0) { alert('Please select at least one row.'); return; }
        window.location = window.APP_URL + '/export/selected?ids=' + Array.from(selectedIds).join(',');
    });
    $('#exportFilteredBtn').on('click', function () {
        const qs = $.param(currentFilters());
        window.location = window.APP_URL + '/export/filtered?' + qs;
    });

    // ---- Bulk status / report status / delete ----
    $('#applyBulkStatusBtn').on('click', function () {
        postBulk('/loans/bulk-status', { loan_status_id: $('#bulkStatusSelect').val() }, '#changeStatusModal');
    });
    $('#applyBulkReportStatusBtn').on('click', function () {
        postBulk('/loans/bulk-report-status', { report_status_id: $('#bulkReportStatusSelect').val() }, '#changeReportStatusModal');
    });
    $('#deleteSelectedBtn').on('click', function () {
        if (selectedIds.size === 0) { alert('Please select at least one row.'); return; }
        if (!confirm('Delete ' + selectedIds.size + ' selected loan(s)? This cannot be undone.')) return;
        postBulk('/loans/bulk-delete', {}, null);
    });

    function postBulk(path, extra, modalSelector) {
        if (selectedIds.size === 0) { alert('Please select at least one row.'); return; }
        const payload = Object.assign({ csrf_token: window.CSRF_TOKEN, ids: JSON.stringify(Array.from(selectedIds)) }, extra);
        $.post(window.APP_URL + path, payload, function (res) {
            if (res.success) {
                if (modalSelector) bootstrap.Modal.getInstance(document.querySelector(modalSelector))?.hide();
                selectedIds.clear();
                updateBulkBar();
                dtInstance.ajax.reload(null, false);
            } else {
                alert(res.message || 'Action failed.');
            }
        }).fail(() => alert('Action failed. Please try again.'));
    }

    // ---- Edit loan ----
    $('#loanTable tbody').on('click', '.edit-loan-btn', function () {
        const id = $(this).data('id');
        $.get(window.APP_URL + '/loans/' + id + '/edit', function (res) {
            if (!res.success) { alert('Loan not found.'); return; }
            const l = res.loan;
            $('#edit_loan_id').val(l.id);
            $('#edit_amount').val(l.amount);
            $('#edit_branch_id').val(l.branch_id);
            $('#edit_action_date').val(l.action_date);
            $('#edit_loan_status_id').val(l.loan_status_id);
            $('#edit_report_status_id').val(l.report_status_id);
            $('#edit_notes').val(l.notes || '');
            new bootstrap.Modal(document.getElementById('editLoanModal')).show();
        });
    });

    $('#saveEditLoanBtn').on('click', function () {
        const id = $('#edit_loan_id').val();
        const payload = {
            csrf_token: window.CSRF_TOKEN,
            amount: $('#edit_amount').val(),
            branch_id: $('#edit_branch_id').val(),
            action_date: $('#edit_action_date').val(),
            loan_status_id: $('#edit_loan_status_id').val(),
            report_status_id: $('#edit_report_status_id').val(),
            notes: $('#edit_notes').val(),
        };
        $.post(window.APP_URL + '/loans/' + id + '/update', payload, function (res) {
            if (res.success) {
                bootstrap.Modal.getInstance(document.getElementById('editLoanModal')).hide();
                dtInstance.ajax.reload(null, false);
            } else {
                alert('Could not save changes.');
            }
        }).fail(() => alert('Could not save changes.'));
    });

    // ---- Single delete ----
    $('#loanTable tbody').on('click', '.delete-loan-btn', function () {
        const id = String($(this).data('id'));
        if (!confirm('Delete this loan? This cannot be undone.')) return;
        $.post(window.APP_URL + '/loans/bulk-delete', { csrf_token: window.CSRF_TOKEN, ids: JSON.stringify([id]) }, function (res) {
            if (res.success) dtInstance.ajax.reload(null, false);
            else alert(res.message || 'Delete failed.');
        }).fail(() => alert('Delete failed.'));
    });
});
