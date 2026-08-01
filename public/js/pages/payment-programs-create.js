(function () {
    function getSubCategoryElements() {
        const subCategorySearchInput = document.getElementById('subCategory');
        const subCategoryHiddenInput = document.querySelector('input.dbInput[data-for="subCategory"]');
        const subCategoryOptionBox = subCategoryHiddenInput
            ? subCategoryHiddenInput.parentElement.querySelector('ul')
            : null;
        const subCategoryWrapper = subCategorySearchInput
            ? subCategorySearchInput.closest('.form-group')?.parentElement?.closest('.form-group')
            : null;
        const subCategoryLabel = subCategoryWrapper ? subCategoryWrapper.querySelector('label') : null;

        return {
            subCategorySearchInput,
            subCategoryHiddenInput,
            subCategoryOptionBox,
            subCategoryWrapper,
            subCategoryLabel,
        };
    }

    window.trackDateState = function () {
        const customerSelect = document.getElementById('customer_id');
        if (customerSelect) customerSelect.disabled = false;
    };

    window.trackCustomerState = function (elem) {
        const categorySelectDom = document.getElementById('category');
        if (!categorySelectDom) return;
        categorySelectDom.disabled = !elem?.value;
    };

    window.getCategoryData = function (value) {
        const remarksInputDom = document.getElementById('remarks');
        const {
            subCategorySearchInput,
            subCategoryHiddenInput,
            subCategoryOptionBox,
            subCategoryWrapper,
            subCategoryLabel,
        } = getSubCategoryElements();
        const customerSelect = document.getElementById('customer_id');
        const remarksWrapper = remarksInputDom?.parentElement?.parentElement;

        if (!subCategorySearchInput || !subCategoryHiddenInput || !subCategoryOptionBox || !subCategoryWrapper || !subCategoryLabel) {
            return;
        }
        remarksWrapper?.classList.remove('hidden');

        if (value !== 'waiting') {
            subCategoryWrapper.classList.remove('hidden');

            $.ajax({
                url: '/get-category-data',
                type: 'POST',
                data: {
                    _token: window.__paymentProgramsCreate?.csrfToken || '',
                    category: value,
                },
                success: function (response) {
                    let items = [];

                    switch (value) {
                        case 'self_account':
                            subCategoryLabel.textContent = 'Self Account';
                            if (response.length > 0) {
                                items.push(
                                    '<li data-for="subCategory" data-value="" onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg hover:bg-[var(--h-bg-color)]">-- Select Self Account --</li>'
                                );
                                response.forEach(acc => {
                                    items.push(
                                        `<li data-for="subCategory" data-value="${acc.id}" onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg hover:bg-[var(--h-bg-color)]">${acc.account_title} | ${acc.bank.short_title}</li>`
                                    );
                                });
                                subCategorySearchInput.disabled = false;
                            } else {
                                items.push('<li class="py-2 px-3 text-gray-400">-- No options available --</li>');
                                subCategorySearchInput.disabled = true;
                            }
                            break;

                        case 'supplier':
                            subCategoryLabel.textContent = 'Supplier';
                            if (response.length > 0) {
                                items.push(
                                    '<li data-for="subCategory" data-value="" onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg hover:bg-[var(--h-bg-color)]">-- Select Supplier --</li>'
                                );
                                response.forEach(sup => {
                                    items.push(
                                        `<li data-for="subCategory" data-value="${sup.id}" onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg hover:bg-[var(--h-bg-color)]">${sup.supplier_name} | Balance: ${sup.balance_formatted || formatNumbersWithDigits(sup.balance, 1, 1)}</li>`
                                    );
                                });
                                subCategorySearchInput.disabled = false;
                            } else {
                                items.push('<li class="py-2 px-3 text-gray-400">-- No options available --</li>');
                                subCategorySearchInput.disabled = true;
                            }
                            break;

                        case 'customer':
                            subCategoryLabel.textContent = 'Customer';
                            items.push(
                                '<li data-for="subCategory" data-value="" onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg hover:bg-[var(--h-bg-color)]">-- Select Customer --</li>'
                            );
                            response.forEach(cus => {
                                if (!customerSelect || cus.id != customerSelect.value) {
                                    items.push(
                                        `<li data-for="subCategory" data-value="${cus.id}" onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg hover:bg-[var(--h-bg-color)]">${cus.customer_name} | ${cus.city.title} | Balance: ${cus.balance_formatted || formatNumbersWithDigits(cus.balance, 1, 1)}</li>`
                                    );
                                }
                            });
                            subCategorySearchInput.disabled = false;
                            break;
                    }

                    subCategoryOptionBox.innerHTML = items.join('');
                    subCategorySearchInput.value = '';
                    subCategoryHiddenInput.value = '';
                },
                error: function (xhr) {
                    console.error('Error:', xhr.responseText);
                    subCategoryOptionBox.innerHTML = '<li class="py-2 px-3 text-red-500">Error loading options</li>';
                    subCategorySearchInput.disabled = true;
                },
            });
        } else {
            subCategoryWrapper.classList.add('hidden');
        }
    };

    function initPaymentProgramsCreate() {
        const customerSelect = document.getElementById('customer_id');
        const categorySelectDom = document.getElementById('category');
        const remarksInputDom = document.getElementById('remarks');
        const editConfig = window.__paymentProgramsEdit || null;

        if (!editConfig && customerSelect) customerSelect.disabled = true;
        if (!editConfig && categorySelectDom) categorySelectDom.disabled = true;
        remarksInputDom?.parentElement?.parentElement?.classList.remove('hidden');

        if (editConfig) {
            if (customerSelect) customerSelect.disabled = false;
            if (categorySelectDom) categorySelectDom.disabled = false;

            const selectedCustomer = document.querySelector(`li[data-for="customer_id"][data-value="${editConfig.customer_id}"]`);
            if (selectedCustomer) selectThisOption(selectedCustomer);

            const selectedCategory = document.querySelector(`li[data-for="category"][data-value="${editConfig.category}"]`);
            if (selectedCategory) {
                selectThisOption(selectedCategory);
                getCategoryData(editConfig.category);
            }

            setTimeout(() => {
                const selectedSubCategory = document.querySelector(`li[data-for="subCategory"][data-value="${editConfig.sub_category}"]`);
                if (selectedSubCategory) selectThisOption(selectedSubCategory);
            }, 250);
        }
    }

    window.initPaymentProgramsCreate = initPaymentProgramsCreate;

    function boot() {
        if (window.__paymentProgramsCreate) {
            initPaymentProgramsCreate();
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
