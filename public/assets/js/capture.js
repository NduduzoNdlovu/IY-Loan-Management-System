document.addEventListener('DOMContentLoaded', function () {
    const idInput       = document.getElementById('idNumberInput');
    const nameInput      = document.getElementById('nameInput');
    const surnameInput   = document.getElementById('surnameInput');
    const accInput        = document.getElementById('accountNumberInput');
    const phoneInput     = document.getElementById('phoneInput');
    const hint           = document.getElementById('clientLookupHint');
    const banner         = document.getElementById('clientFoundBanner');
    const loanCountBox   = document.getElementById('loanCountDisplay');
    const groupBox       = document.getElementById('groupDisplay');
    const form           = document.getElementById('captureLoanForm');

    let lookupTimer = null;

    function resetClientState() {
        banner.classList.add('d-none');
        loanCountBox.value = '1';
        groupBox.value = 'Group 1 (1 - 3 Loans)';
    }

    idInput.addEventListener('input', function () {
        clearTimeout(lookupTimer);
        const val = idInput.value.trim();
        if (val.length < 4) { resetClientState(); hint.textContent = ''; return; }
        hint.textContent = 'Searching...';
        lookupTimer = setTimeout(() => doLookup(val), 400);
    });

    function doLookup(idNumber) {
        fetch(window.APP_URL + '/clients/lookup?id_number=' + encodeURIComponent(idNumber))
            .then(r => r.json())
            .then(data => {
                if (data.found) {
                    hint.textContent = '';
                    banner.classList.remove('d-none');
                    nameInput.value    = data.client.name;
                    surnameInput.value = data.client.surname;
                    accInput.value     = data.client.account_number || '';
                    phoneInput.value   = data.client.phone || '';
                    loanCountBox.value = data.next_loan_count;
                    groupBox.value     = groupLabel(data.group, data.next_loan_count);
                } else {
                    hint.textContent = 'New client — please fill in the details below.';
                    banner.classList.add('d-none');
                    loanCountBox.value = '1';
                    groupBox.value = 'Group 1 (1 - 3 Loans)';
                }
            })
            .catch(() => { hint.textContent = ''; });
    }

    function groupLabel(group, count) {
        const ranges = { 'Group 1': '1 - 3 Loans', 'Group 2': '4 - 8 Loans', 'Group 3': '9+ Loans' };
        return group + ' (' + ranges[group] + ')';
    }

    document.getElementById('clearFormBtn').addEventListener('click', function () {
        setTimeout(() => { resetClientState(); hint.textContent = ''; clearErrors(); }, 10);
    });

    function clearErrors() {
        form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
        form.querySelectorAll('[data-error-for]').forEach(el => el.textContent = '');
    }

    function showErrors(errors) {
        clearErrors();
        Object.keys(errors).forEach(field => {
            const input = form.querySelector('[name="' + field + '"]');
            if (input) input.classList.add('is-invalid');
            const box = form.querySelector('[data-error-for="' + field + '"]');
            if (box) box.textContent = errors[field];
        });
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        clearErrors();
        const btn = document.getElementById('saveLoanBtn');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Saving...';

        const formData = new FormData(form);
        fetch(window.APP_URL + '/loans', { method: 'POST', body: formData })
            .then(r => r.json().then(data => ({ status: r.status, data })))
            .then(({ status, data }) => {
                if (status === 200 && data.success) {
                    alert('Loan saved successfully.\nReference Number: ' + data.loan.reference_number);
                    form.reset();
                    resetClientState();
                    hint.textContent = '';
                } else if (data.errors) {
                    showErrors(data.errors);
                } else {
                    alert(data.message || 'Something went wrong. Please try again.');
                }
            })
            .catch(() => alert('Network error. Please try again.'))
            .finally(() => {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-check-lg"></i> Save Loan';
            });
    });
});
