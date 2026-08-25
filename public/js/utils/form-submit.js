function initGlobalFormValidation() {
    document.addEventListener('submit', function (e) {
        if (e.target.matches('form')) {
            if (e.target.action.includes('logout')) {
                return;
            }
            if (e.target.hasAttribute('data-skip-validation')) {
                return;
            }
            if (e.target.hasAttribute('data-layout-toggle')) {
                return;
            }

            if (!validateAllInputs(e.target)) {
                e.preventDefault();
                showValidationToast('Please fix the highlighted fields before saving.');
                hideLoader();
                return;
            }
        }

        document.querySelectorAll('input[type="amount"]').forEach(input => {
            formatAmountInput(input);
        });
    });
}

window.submitModalForm = function submitModalForm(button) {
    const form = button?.closest?.('form');
    if (!form) return;

    if (typeof validateAllInputs === 'function' && !validateAllInputs(form)) {
        showValidationToast('Please fix the highlighted fields before saving.');
        return;
    }

    form.requestSubmit();
}

function showValidationToast(message) {
    if (typeof showToast === 'function') {
        showToast('error', message);
        return;
    }

    if (typeof showMessageBox === 'function') {
        showMessageBox('error', message);
    }
}
