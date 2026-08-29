(() => {
    function initExpensesEdit() {
        const config = window.__expensesEdit || {};
        const selectedExpense = config.selectedExpense || "";
        const supplierData = config.supplierData || null;
        const balanceInput = document.getElementById("balance");

        window.supplierSelected = function supplierSelected(supplier) {
            const expenseSelect = document.getElementById("expense");
            let selectedSupplierData = supplier;
            const changedFromSupplierSelect = Boolean(supplier?.closest);

            if (changedFromSupplierSelect) {
                const forId = supplier.dataset?.for || "supplier_id";
                const scope = supplier.closest("form") || document;
                const selectedOptionDataset = scope.querySelector(
                    `.optionsDropdown li[data-for="${forId}"].selected`
                )?.dataset?.option;
                selectedSupplierData = selectedOptionDataset ? JSON.parse(selectedOptionDataset) : null;
            } else if (typeof supplier === "string") {
                selectedSupplierData = JSON.parse(supplier);
            }

            if (!selectedSupplierData) return;

            if (balanceInput) {
                balanceInput.value = selectedSupplierData.balance_formatted
                    || formatNumbersWithDigits(selectedSupplierData.balance || 0, 1, 1);
            }

            const supplierCategories = selectedSupplierData.categories || [];
            let expenseOptions = `
                <li data-for="expense" data-value="" onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg transition hover:bg-[var(--h-bg-color)] text-nowrap overflow-x-auto scrollbar-hidden">-- Select Expense --</li>
            `;

            supplierCategories.forEach(category => {
                expenseOptions += `
                    <li data-for="expense" data-value="${category.id}" onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg transition hover:bg-[var(--h-bg-color)] text-nowrap overflow-x-auto scrollbar-hidden">${category.title}</li>
                `;
            });
            if (config.adjustmentId) {
                expenseOptions += `
                    <li data-for="expense" data-value="${config.adjustmentId}" onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg transition hover:bg-[var(--h-bg-color)] text-nowrap overflow-x-auto scrollbar-hidden ">Adjustment</li>
                `;
            }

            const expenseScope = expenseSelect.closest(".selectParent");
            const expenseDropdown = expenseScope?.querySelector(".optionsDropdown");
            if (expenseDropdown) {
                expenseDropdown.innerHTML = expenseOptions;
            }
            if (changedFromSupplierSelect) {
                const expenseDbInput = expenseScope?.querySelector('.dbInput[data-for="expense"]');
                if (expenseDbInput) expenseDbInput.value = "";
                expenseSelect.value = "";
            }
            expenseSelect.disabled = false;
        };

        if (supplierData) {
            supplierSelected(supplierData);
        }

        const expenseOption = document
            .getElementById("expense")
            ?.closest(".selectParent")
            ?.querySelector(`.optionsDropdown li[data-value="${selectedExpense}"]`);
        if (expenseOption) {
            selectThisOption(expenseOption);
        }
    }

    window.initExpensesEdit = initExpensesEdit;

    function boot() {
        if (window.__expensesEdit) initExpensesEdit();
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", boot);
    } else {
        boot();
    }
})();
