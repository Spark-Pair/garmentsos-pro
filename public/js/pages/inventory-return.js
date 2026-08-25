(() => {
    function selectedSupplierBalance(elem) {
        const selected = elem.closest('.selectParent')?.querySelector('.optionsDropdown li.selected');
        try {
            return JSON.parse(selected?.dataset.option || '{}');
        } catch (error) {
            return {};
        }
    }

    function setCustomSelectValue(id, value) {
        const visibleInput = document.getElementById(id);
        const selectParent = visibleInput?.closest('.selectParent');
        if (!selectParent) return;

        const options = Array.from(selectParent.querySelectorAll('.optionsDropdown li'));
        const selected = options.find(option => String(option.dataset.value || '') === String(value || ''))
            || options.find(option => String(option.dataset.value || '') === '');
        if (!selected) return;

        options.forEach(option => option.classList.toggle('selected', option === selected));
        visibleInput.value = selected.textContent.trim();
        const hiddenInput = selectParent.querySelector('.dbInput');
        if (hiddenInput) hiddenInput.value = selected.dataset.value || '';
    }

    window.trackInventoryReturnSupplier = function trackInventoryReturnSupplier(elem) {
        const balance = selectedSupplierBalance(elem);
        const quantityInput = document.getElementById('return_quantity');
        const rateInput = document.getElementById('return_unit_price');
        if (!quantityInput) return;

        const available = Number(balance.available_quantity || 0);
        quantityInput.disabled = available <= 0;
        quantityInput.max = available > 0 ? String(available) : '';
        quantityInput.placeholder = available > 0
            ? `Maximum ${formatNumbersWithDigits(available)}`
            : 'No quantity available';

        const existingQuantity = Number(quantityInput.value || window.__inventoryReturn?.oldQuantity || 0);
        quantityInput.value = existingQuantity > 0 ? String(Math.min(existingQuantity, available)) : '';
        if (rateInput) rateInput.value = balance.unit_price == null ? '' : Number(balance.unit_price).toFixed(2);
        setCustomSelectValue('return_payment_method', balance.payment_method || '');
        window.calculateInventoryReturnAmount();
        document.getElementById('summary_supplier').value = balance.supplier_name || '-';
        document.getElementById('summary_available').value = available > 0 ? formatNumbersWithDigits(available) : '-';
        window.updateInventoryReturnSummary();
        if (available > 0) quantityInput.focus();
    };

    window.clampInventoryReturnQuantity = function clampInventoryReturnQuantity(input) {
        const maximum = Number(input.max || 0);
        if (maximum > 0 && Number(input.value || 0) > maximum) {
            input.value = String(maximum);
        }
        if (Number(input.value || 0) < 0) input.value = '';
    };

    window.calculateInventoryReturnAmount = function calculateInventoryReturnAmount() {
        const quantity = Number(document.getElementById('return_quantity')?.value || 0);
        const rate = Number(document.getElementById('return_unit_price')?.value || 0);
        const amountInput = document.getElementById('return_amount');
        if (!amountInput) return;
        amountInput.value = quantity > 0 && rate >= 0 ? (quantity * rate).toFixed(2) : '';
        window.updateInventoryReturnSummary();
    };

    window.updateInventoryReturnSummary = function updateInventoryReturnSummary() {
        const quantity = document.getElementById('return_quantity')?.value || '-';
        const rate = document.getElementById('return_unit_price')?.value || '-';
        const amount = document.getElementById('return_amount')?.value || '-';
        const payment = document.getElementById('return_payment_method')?.value || '-';
        const summaryQuantity = document.getElementById('summary_quantity');
        const summaryRate = document.getElementById('summary_rate');
        const summaryAmount = document.getElementById('summary_amount');
        const summaryPayment = document.getElementById('summary_payment');
        if (summaryQuantity) summaryQuantity.value = quantity;
        if (summaryRate) summaryRate.value = rate;
        if (summaryAmount) summaryAmount.value = amount;
        if (summaryPayment) summaryPayment.value = String(payment).replaceAll('_', ' ');
    };

    window.validateForNextStep = function validateForNextStep() {
        return true;
    };

    function boot() {
        const supplierInput = document.querySelector('#form input[name="supplier_id"]');
        if (supplierInput?.value) window.trackInventoryReturnSupplier(supplierInput);

        const saveBtn = document.getElementById('saveBtn');
        if (saveBtn) {
            const label = saveBtn.querySelector('div');
            if (label) label.textContent = 'Return';
            saveBtn.disabled = !supplierInput?.value;
        }
    }

    document.addEventListener('wizard:step-changed', event => {
        document.getElementById('lastRecordStep1')?.classList.toggle('hidden', event.detail.step !== 1);
        document.getElementById('lastRecordStep2')?.classList.toggle('hidden', event.detail.step !== 2);
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
