$(function () {
    function filters() {
        return {
            branch_id: $('#r_branch_id').val() || '',
            loan_group: $('#r_loan_group').val() || '',
            loan_status_id: $('#r_loan_status_id').val() || '',
            report_status_id: $('#r_report_status_id').val() || '',
            date_from: $('#r_date_from').val() || '',
            date_to: $('#r_date_to').val() || '',
        };
    }

    function generate() {
        $.get(window.APP_URL + '/reports/generate', filters(), function (res) {
            if (!res.success) return;
            $('#rk_total_loans').text(Number(res.totals.total_loans).toLocaleString());
            $('#rk_total_amount').text(fmtMoney(res.totals.total_amount));
            $('#rk_active_loans').text(Number(res.totals.active_loans).toLocaleString());
            $('#rk_paid_loans').text(Number(res.totals.paid_loans).toLocaleString());
            $('#rk_overdue_loans').text(Number(res.totals.overdue_loans).toLocaleString());

            const tbody = $('#reportBranchTable tbody').empty();
            res.by_branch.forEach(function (b) {
                tbody.append(`<tr>
                    <td class="fw-semibold">${b.branch_name}</td>
                    <td>${Number(b.total_loans).toLocaleString()}</td>
                    <td>${fmtMoney(b.total_amount)}</td>
                    <td>${Number(b.active_loans).toLocaleString()}</td>
                    <td>${Number(b.paid_loans).toLocaleString()}</td>
                    <td>${Number(b.overdue_loans).toLocaleString()}</td>
                </tr>`);
            });
            if (res.by_branch.length === 0) {
                tbody.append('<tr><td colspan="6" class="text-center text-muted py-3">No data for the selected filters.</td></tr>');
            }
        });
    }

    $('#generateReportBtn').on('click', generate);
    generate();

    $('#reportExportExcelBtn').on('click', function () {
        const qs = $.param({
            branch_id: filters().branch_id,
            loan_group: filters().loan_group,
            loan_status_id: filters().loan_status_id,
            report_status_id: filters().report_status_id,
            date_loaded_from: filters().date_from,
            date_loaded_to: filters().date_to,
        });
        window.location = window.APP_URL + '/export/filtered?' + qs;
    });

    $('#reportPrintBtn').on('click', function () { window.print(); });
});
