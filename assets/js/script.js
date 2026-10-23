/* LabLend – small client-side helpers (no frameworks) */
document.addEventListener('DOMContentLoaded', function () {
    // 1. Confirm before submitting forms marked data-confirm
    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            if (!confirm(form.getAttribute('data-confirm'))) { e.preventDefault(); }
        });
    });
    // 2. Bootstrap validation styling
    document.querySelectorAll('form.needs-validation').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            if (!form.checkValidity()) { e.preventDefault(); e.stopPropagation(); }
            form.classList.add('was-validated');
        });
    });
    // 3. Filter dropdowns submit on change
    document.querySelectorAll('select[data-autosubmit]').forEach(function (sel) {
        sel.addEventListener('change', function () { sel.form.submit(); });
    });
    // 4. Prevent double submit (e.g. double-click on Approve)
    document.querySelectorAll('form[method="post"]').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            if (e.defaultPrevented) { return; }
            if (form.dataset.sent) { e.preventDefault(); return; }
            form.dataset.sent = '1';
        });
    });
    // 5. Dropdowns inside scrollable tables use fixed positioning (not clipped)
    if (window.bootstrap) {
        document.querySelectorAll('.table-responsive [data-bs-toggle="dropdown"]').forEach(function (el) {
            bootstrap.Dropdown.getOrCreateInstance(el, {
                popperConfig: function (cfg) { return Object.assign({}, cfg, { strategy: 'fixed' }); }
            });
        });
    }
});
