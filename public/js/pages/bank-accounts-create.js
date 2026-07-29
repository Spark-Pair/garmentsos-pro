(() => {
    function initBankAccountsCreate(config) {
        const csrfToken = config?.csrfToken;
        const selectedCategory = config?.selectedCategory || "";
        const selectedSubCategory = config?.selectedSubCategory ? String(config.selectedSubCategory) : "";

    const serialEnd = document.getElementById('cheque_book_serial_end');
    if (serialEnd) {
        serialEnd.addEventListener('input', () => {
            const start = parseInt(document.getElementById('cheque_book_serial_start').value);
            const end = parseInt(document.getElementById('cheque_book_serial_end').value);
            const errorDiv = document.getElementById('cheque_book_serial_error');

            if (end < start) {
                errorDiv.innerText = 'End serial must be greater than or equal to start serial.';
                errorDiv.classList.remove('hidden');
            } else {
                errorDiv.innerText = '';
                errorDiv.classList.add('hidden');
            }
        });
    }

    let subCategoryLabelDom = document.querySelector('[for=sub_category]');
    let accountNoLabelDom = document.querySelector('[for=account_no]');
    let chequeBookSerialDom = document.getElementById('cheque_book_serial');
    let remarksLabelDom = document.querySelector('[for=remarks]');
    let subCategorySelectDom = document.getElementById('subCategory');
    let subCategorySelectOptionsDom = subCategorySelectDom.parentElement.parentElement.parentElement.querySelector('.optionsDropdown');

    accountNoLabelDom.closest('.form-group').classList.add('hidden');
    chequeBookSerialDom.classList.add('hidden');

    window.getCategoryData = function(value) {
        if (value != "waiting") {
            $.ajax({
                url: "/get-category-data",
                type: "POST",
                data: {
                    _token: csrfToken,
                    category: value,
                },
                success: function (response) {
                    let clutter = '';
                    switch (value) {
                        case 'supplier':
                            subCategoryLabelDom.closest('.form-group').classList.remove('hidden');
                            remarksLabelDom.closest('.form-group').classList.remove('hidden');
                            accountNoLabelDom.closest('.form-group').classList.add('hidden');
                            chequeBookSerialDom.classList.add('hidden');
                            clutter += `
                                <li data-for="subCategory" data-value="" onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg transition hover:bg-[var(--h-bg-color)]">
                                    -- Select Supplier --
                                </li>
                            `;

                            response.forEach(subCat => {
                                clutter += `
                                    <li data-for="subCategory" data-value="${subCat.id}" onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg transition hover:bg-[var(--h-bg-color)] text-nowrap overflow-x-auto scrollbar-hidden">
                                        ${subCat.supplier_name}
                                    </li>
                                `;
                            });

                            subCategoryLabelDom.textContent = 'Supplier';
                            subCategorySelectDom.disabled = false;
                            break;

                        case 'customer':
                            subCategoryLabelDom.closest('.form-group').classList.remove('hidden');
                            remarksLabelDom.closest('.form-group').classList.remove('hidden');
                            accountNoLabelDom.closest('.form-group').classList.add('hidden');
                            chequeBookSerialDom.classList.add('hidden');
                            clutter += `
                                <li data-for="subCategory" data-value="" onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg transition hover:bg-[var(--h-bg-color)]">
                                    -- Select Customer --
                                </li>
                            `;

                            response.forEach(subCat => {
                                clutter += `
                                    <li data-for="subCategory" data-value="${subCat.id}" onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg transition hover:bg-[var(--h-bg-color)] text-nowrap overflow-x-auto scrollbar-hidden">
                                        ${subCat.customer_name}
                                    </li>
                                `;
                            });

                            subCategoryLabelDom.textContent = 'Customer';
                            subCategorySelectDom.disabled = false;
                            break;

                        case 'self':
                            subCategoryLabelDom.closest('.form-group').classList.add('hidden');
                            remarksLabelDom.closest('.form-group').classList.add('hidden');
                            accountNoLabelDom.closest('.form-group').classList.remove('hidden');
                            chequeBookSerialDom.classList.remove('hidden');
                            break;

                        default:
                            subCategoryLabelDom.closest('.form-group').classList.remove('hidden');
                            remarksLabelDom.closest('.form-group').classList.remove('hidden');
                            accountNoLabelDom.closest('.form-group').classList.add('hidden');
                            chequeBookSerialDom.classList.add('hidden');
                            clutter += `
                                <option value=''>
                                    -- No options available --
                                </option>
                            `;

                            subCategoryLabelDom.textContent = 'Disabled';
                            subCategorySelectDom.disabled = true;
                            break;
                    }
                    subCategorySelectOptionsDom.innerHTML = clutter;
                    if (selectedSubCategory && (value === 'supplier' || value === 'customer')) {
                        const option = subCategorySelectOptionsDom.querySelector(`[data-value="${CSS.escape(selectedSubCategory)}"]`);
                        if (option) {
                            selectThisOption(option);
                        }
                    }
                }
            });
        }
    }

    if (selectedCategory && selectedCategory !== "waiting") {
        getCategoryData(selectedCategory);
    }
}

    window.initBankAccountsCreate = initBankAccountsCreate;

    function boot() {
        if (window.__bankAccountsCreate) {
            initBankAccountsCreate(window.__bankAccountsCreate);
        }
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", boot);
    } else {
        boot();
    }
})();
