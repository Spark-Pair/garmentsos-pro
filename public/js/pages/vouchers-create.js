(() => {
function initVouchersCreate() {
    const config = window.__vouchersCreate || {};
    const voucherType = config.voucherType;
    const csrfToken = config.csrfToken;
    const lastVoucher = config.lastVoucher;
    const companyData = config.branchBranding || config.companyData;
    const companyLogoUrl = config.branchBranding?.logo_url
        || config.companyLogoUrl
        || (config.companyLogoBase && config.companyData?.logo ? `${config.companyLogoBase}/${config.companyData.logo}` : '');
    const templates = config.templates || {};

    let btnTypeGlobal = voucherType === 'supplier' ? 'supplier' : 'self_account';

    function safeDocumentNumberPreview(value, fallback = 'Will be generated on save') {
        const text = String(value ?? '').trim();
        return text && !text.includes('NaN') ? text : fallback;
    }

    function incrementDocumentNumber(value, offset = 0, fallback = 'Will be generated on save') {
        const text = safeDocumentNumberPreview(value, '');
        if (!text) return fallback;

        const replaced = text.replace(/(\d+)(?!.*\d)/, match => {
            const next = Number.parseInt(match, 10) + offset;
            return Number.isFinite(next) ? String(next).padStart(match.length, '0') : match;
        });

        return safeDocumentNumberPreview(replaced, fallback);
    }

    function renderTemplate(template, values) {
        let html = template || '';
        Object.entries(values || {}).forEach(([key, value]) => {
            html = html.replaceAll(`__${key}__`, value ?? '');
        });
        return html;
    }

    function buildSelfAccountSelect({ id, name, label, onchange }) {
        return renderTemplate(templates.selfAccountSelect, {
            ID: id,
            NAME: name,
            LABEL: label || 'Self Account',
            ONCHANGE: onchange || ''
        });
    }

    function buildEmptySelect({ id, name, label, onchange }) {
        return renderTemplate(templates.emptySelect, {
            ID: id,
            NAME: name,
            LABEL: label || 'Select',
            ONCHANGE: onchange || ''
        });
    }

    function shortAccountLabel(value) {
        const parts = String(value ?? '').split('|').map(part => part.trim()).filter(Boolean);
        return parts.at(-1) || '-';
    }

    function setVoucherType(btn, btnType) {
        if (btnTypeGlobal === btnType) return;

        $.ajax({
            url: "/set-voucher-type",
            type: "POST",
            data: {
                _token: csrfToken,
                voucher_type: btnType
            },
            success: function () {
                location.reload();
            },
            error: function () {
                appAlert("Failed to update vaoucher type.");
                $(btn).prop("disabled", false);
            }
        });

        moveHighlight(btn, btnType);
    }

    function moveHighlight(btn, btnType) {
        const highlight = document.getElementById("highlight");
        if (!highlight || !btn) return;

        const rect = btn.getBoundingClientRect();
        const parentRect = btn.parentElement.getBoundingClientRect();
        highlight.style.width = `${rect.width}px`;
        highlight.style.left = `${rect.left - parentRect.left - 3}px`;

        btnTypeGlobal = btnType;
    }

    const supplierBtn = document.getElementById("supplierBtn");
    const selfAccountBtn = document.getElementById("selfAccountBtn");
    if (supplierBtn) {
        supplierBtn.addEventListener('click', () => setVoucherType(supplierBtn, 'supplier'));
    }
    if (selfAccountBtn) {
        selfAccountBtn.addEventListener('click', () => setVoucherType(selfAccountBtn, 'self_account'));
    }

    const activeBtn = voucherType === 'supplier'
        ? document.querySelector("#supplierBtn")
        : document.querySelector("#selfAccountBtn");
    if (activeBtn) moveHighlight(activeBtn, btnTypeGlobal);

    let payments_options = [];
    let supplierSelectDom = document.getElementById('supplier_id');
    let methodSelectDom = document.getElementById('method');
    let dateDom = document.getElementById('date');
    let balanceDom = document.getElementById('balance');
    let paymentDetailsDom = document.getElementById('paymentDetails');
    let finalTotalPaymentDom = document.getElementById('finalTotalPayment');
    let paymentListDom = document.getElementById('payment-list');
    const paymentDetailsArrayDom = document.getElementById("payment_details_array");

    selectedSupplierData = null;
    let totalPayment = 0;

    let paymentDetailsArray = [];
    let allPayments = [];

    let selectedSupplier;

    const today = localDateString();

    window.trackSupplierState = function() {
        dateDom.value = '';
        balanceDom.value = '';
        methodSelectDom.value = '';

        paymentDetailsArray = [];
        renderList();

        if (supplierSelectDom && supplierSelectDom.value != '') {
            selectedSupplier = JSON.parse(supplierSelectDom.parentElement.parentElement.parentElement.querySelector("ul li.selected").dataset.option);
            dateDom.disabled = false;
            methodSelectDom.disabled = false;
            dateDom.min = selectedSupplier.date.toString().split('T')[0];
            dateDom.max = today;
            balanceDom.value = formatNumbersWithDigits(selectedSupplier.balance, 1, 1);
            selectedSupplierData = selectedSupplier;
        } else {
            dateDom.disabled = true;
            methodSelectDom.disabled = true;
        }
    }

    window.trackDateState = function(elem) {
        paymentDetailsArray = [];
        methodSelectDom.value = '';
        renderList();

        if (elem.value != '') {
            if (selectedSupplier?.id) {
                $.ajax({
                    url: '/vouchers/create',
                    type: 'GET',
                    data: {
                        supplier_id: selectedSupplier.id,
                        date: dateDom.value,
                    },
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function(response) {
                        if (typeof response.supplier_balance_at_date !== 'undefined') {
                            if (selectedSupplier) {
                                selectedSupplier.balance_at_date = response.supplier_balance_at_date;
                            }
                            if (balanceDom) {
                                balanceDom.value = formatNumbersWithDigits(response.supplier_balance_at_date, 1, 1);
                            }
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error(error);
                    }
                });
            }
            gotoStep(2);
        }
    }

    const enterDetailsBtn = document.getElementById("enterDetailsBtn");
    if (enterDetailsBtn) enterDetailsBtn.disabled = true;

    let selectedDom;
    let availableChequesArray = [];

    window.setSelectedAccount = function(elem) {
        let hiddenAccountInSelfAccount = elem.closest('form').querySelector(`ul[data-for="self_account_id"]`);
        hiddenAccountInSelfAccount?.querySelectorAll('li').forEach(li => {
            if (li.style.display == 'none') {
                li.style.display = 'block';
            }
        })

        let selectedOption = elem.nextElementSibling.querySelector('li.selected');
        let selectedAccount = JSON.parse(selectedOption.getAttribute('data-option')) || '';
        elem.closest('form').querySelector('input[name="selected"]').value = JSON.stringify(selectedAccount);

        availableChequesArray = selectedAccount.available_cheques;

        if (elem.closest('form').querySelector('input[name="cheque_no"]')) {
            fetchChequeNumbers();
        }

        const amountInput = elem.closest('form').querySelector('input[name="amount"]');

        const matchingPayments = paymentDetailsArray.filter(item =>
            item.bank_account_id == selectedAccount.id
        );

        const totalAmount = matchingPayments.reduce((sum, item) => {
            return sum + parseFormattedNumber(item.amount);
        }, 0);

        amountInput.dataset.validate += `|max:${parseFormattedNumber(selectedAccount.balance) - totalAmount}`;

        let selectedAccountInSelfAccount = elem.closest('form').querySelector(`ul[data-for="self_account_id"] li[data-value="${selectedAccount.id}"]`);

        if (selectedAccountInSelfAccount) {
            selectedAccountInSelfAccount.style.display = 'none';
        }
    }

    window.updateSelectedAccount = function(elem) {
        const form = elem.closest('form');
        const bankAccountInput = form?.querySelector('input.dbInput[name="bank_account_id"]');
        const bankAccountFor = bankAccountInput?.dataset.for;
        let hiddenAccountInSelfAccount = bankAccountFor ? form.querySelector(`ul[data-for="${bankAccountFor}"]`) : null;
        if (!hiddenAccountInSelfAccount) return;

        hiddenAccountInSelfAccount.querySelectorAll('li').forEach(li => {
            if (li.style.display == 'none') {
                li.style.display = 'block';
            }
        })

        let selectedOption = elem.nextElementSibling.querySelector('li.selected');
        let selectedAccount = JSON.parse(selectedOption.getAttribute('data-option')) || '';

        let selectedAccountInBankAccount = form.querySelector(`ul[data-for="${bankAccountFor}"] li[data-value="${selectedAccount.id}"]`);

        if (selectedAccountInBankAccount) {
            selectedAccountInBankAccount.style.display = 'none';
        }
    }

    function fetchChequeNumbers() {
        const chequeNoSelect = document.querySelector("#cheque_no");
        const chequeNoDropdown = document.querySelector("ul.optionsDropdown[data-for='cheque_no']");

        const usedChequeNumbers = paymentDetailsArray.map(p => String(p.cheque_no));
        const filteredCheques = availableChequesArray.filter(chequeNo => !usedChequeNumbers.includes(String(chequeNo)));

        let clutter = `
            <li data-for="cheque_no" data-value="" onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg transition hover:bg-[var(--h-bg-color)] selected">
                -- Select Cheque Number --
            </li>
            ${filteredCheques.map(chequeNo => `
                <li data-for="cheque_no" data-value="${chequeNo}" onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg transition hover:bg-[var(--h-bg-color)] text-nowrap overflow-x-auto scrollbar-hidden">
                    ${chequeNo}
                </li>
            `).join('')}
        `;

        chequeNoDropdown.innerHTML = clutter;
        chequeNoSelect.disabled = false;
    }

    window.trackMethodState = function(elem) {
        let fieldsData = [];
        const isSelfAccount = voucherType === 'self_account';

        if (elem.value == 'cash') {
            fieldsData.push(
                {
                    category: 'input',
                    name: 'amount',
                    label: 'Amount',
                    type: 'amount',
                    data_validate: 'required|amount',
                    required: true,
                    placeholder: 'Enter amount',
                    oninput: 'validateInput(this)'
                }
            );
            if (isSelfAccount) {
                fieldsData.push({
                    category: 'explicitHtml',
                    html: buildSelfAccountSelect({
                        id: 'self_account_id',
                        name: 'self_account_id',
                        label: 'Self Account'
                    }),
                });
            }
        } else if (elem.value == 'cheque') {
            fieldsData.push(
                {
                    category: 'explicitHtml',
                    html: buildEmptySelect({ id: 'cheque_id', name: 'cheque_id', label: 'Cheque' }),
                    full: true,
                }
            );
            if (isSelfAccount) {
                fieldsData.push({
                    category: 'explicitHtml',
                    html: buildSelfAccountSelect({
                        id: 'self_account_id',
                        name: 'self_account_id',
                        label: 'Self Account'
                    }),
                });
            }
            fieldsData.push(
                {
                    category: 'input',
                    name: 'amount',
                    label: 'Amount',
                    type: 'amount',
                    data_validate: 'required|amount',
                    required: true,
                    placeholder: 'Enter amount',
                    readonly: true,
                    oninput: 'validateInput(this)'
                },
                {
                    category: 'input',
                    id: 'selected',
                    name: 'selected',
                    type: 'hidden',
                }
            );
        } else if (elem.value == 'slip') {
            fieldsData.push(
                {
                    category: 'explicitHtml',
                    html: buildEmptySelect({ id: 'slip_id', name: 'slip_id', label: 'Slip' }),
                    full: true,
                }
            );
            if (isSelfAccount) {
                fieldsData.push({
                    category: 'explicitHtml',
                    html: buildSelfAccountSelect({
                        id: 'self_account_id',
                        name: 'self_account_id',
                        label: 'Self Account'
                    }),
                });
            }
            fieldsData.push(
                {
                    category: 'input',
                    name: 'amount',
                    label: 'Amount',
                    type: 'amount',
                    data_validate: 'required|amount',
                    required: true,
                    placeholder: 'Enter amount',
                    readonly: true,
                    oninput: 'validateInput(this)'
                },
                {
                    category: 'input',
                    id: 'selected',
                    name: 'selected',
                    type: 'hidden',
                }
            );
        } else if (elem.value == 'program') {
            fieldsData.push(
                {
                    category: 'explicitHtml',
                    html: buildEmptySelect({ id: 'program_id', name: 'program_id', label: 'Program' }),
                    full: true,
                },
                {
                    category: 'input',
                    name: 'amount',
                    id: 'amount',
                    label: 'Amount',
                    type: 'amount',
                    data_validate: 'required|amount',
                    required: true,
                    placeholder: 'Enter amount',
                    readonly: true,
                    oninput: 'validateInput(this)'
                },
                {
                    category: 'input',
                    name: 'selected',
                    id: 'selected',
                    type: 'hidden',
                },
                {
                    category: 'input',
                    name: 'payment_id',
                    id: 'payment_id',
                    type: 'hidden',
                }
            );
        } else if (elem.value == 'self_cheque') {
            fieldsData.push(
                {
                    category: 'explicitHtml',
                    html: buildSelfAccountSelect({
                        id: 'self_cheque_id',
                        name: 'bank_account_id',
                        label: isSelfAccount ? 'From Account' : 'Self Account',
                        onchange: 'setSelectedAccount(this)'
                    }),
                },
                {
                    category: 'explicitHtml',
                    html: buildEmptySelect({ id: 'cheque_no', name: 'cheque_no', label: 'Cheque No.' }),
                },
                {
                    category: 'input',
                    name: 'amount',
                    label: 'Amount',
                    type: 'amount',
                    data_validate: 'required|amount',
                    required: true,
                    placeholder: 'Enter amount',
                    oninput: 'validateInput(this)'
                }
            );
            if (isSelfAccount) {
                fieldsData.push(
                    {
                        category: 'explicitHtml',
                        html: buildSelfAccountSelect({
                            id: 'self_account_id',
                            name: 'self_account_id',
                            label: 'To Account',
                            onchange: 'updateSelectedAccount(this)'
                        }),
                    },
                    {
                        category: 'input',
                        name: 'cheque_date',
                        label: 'Cheque Date',
                        type: 'date',
                        required: true,
                    }
                );
            }
            fieldsData.push(
                {
                    category: 'input',
                    name: 'selected',
                    type: 'hidden',
                }
            );
        } else if (elem.value == 'atm') {
            fieldsData.push(
                {
                    category: 'explicitHtml',
                    html: buildSelfAccountSelect({
                        id: 'atm_id',
                        name: 'bank_account_id',
                        label: 'Self Account',
                        onchange: 'setSelectedAccount(this)'
                    }),
                },
                {
                    category: 'input',
                    name: 'reff_no',
                    label: 'Reff. No.',
                    type: 'number',
                    required: true,
                    placeholder: 'Enter reff no.',
                },
                {
                    category: 'input',
                    name: 'amount',
                    label: 'Amount',
                    type: 'amount',
                    data_validate: 'required|amount',
                    required: true,
                    placeholder: 'Enter amount',
                    oninput: 'validateInput(this)'
                }
            );
            if (isSelfAccount) {
                fieldsData.push({
                    category: 'explicitHtml',
                    html: buildSelfAccountSelect({
                        id: 'self_account_id',
                        name: 'self_account_id',
                        label: 'Self Account',
                        onchange: 'updateSelectedAccount(this)'
                    }),
                });
            }
            fieldsData.push(
                {
                    category: 'input',
                    name: 'selected',
                    type: 'hidden',
                }
            );
        } else if (elem.value == 'purchase_return') {
            fieldsData.push(
                {
                    category: 'explicitHtml',
                    html: buildEmptySelect({ id: 'purchase_return_id', name: 'expense_id', label: 'Expense' }),
                },
                {
                    category: 'input',
                    name: 'amount',
                    label: 'Amount',
                    type: 'amount',
                    data_validate: 'required|amount',
                    required: true,
                    placeholder: 'Enter amount',
                    oninput: 'validateInput(this)'
                },
                {
                    category: 'input',
                    name: 'selected',
                    type: 'hidden',
                },
                {
                    category: 'input',
                    name: 'reff_no',
                    type: 'hidden',
                }
            );
        } else if (elem.value == 'adjustment') {
            fieldsData.push(
                {
                    category: 'input',
                    name: 'amount',
                    label: 'Amount',
                    type: 'amount',
                    data_validate: 'required|amount',
                    required: true,
                    placeholder: 'Enter amount',
                    oninput: 'validateInput(this)'
                }
            );
            if (isSelfAccount) {
                fieldsData.push({
                    category: 'explicitHtml',
                    html: buildSelfAccountSelect({
                        id: 'self_account_id',
                        name: 'self_account_id',
                        label: 'Self Account'
                    }),
                });
            }
        }

        if (elem.value != '') {
            fieldsData.push({
                category: 'input',
                name: 'remarks',
                label: 'Remarks',
                placeholder: 'Enter remarks',
                data_validate: 'friendly',
                oninput: 'validateInput(this)'
            });

            const visibleIndexes = fieldsData
            .map((field, index) => field.type !== 'hidden' ? index : null)
            .filter(index => index !== null);

            if (visibleIndexes.length > 0 && elem.value != 'program' && elem.value != 'cheque' && elem.value != 'slip') {
                const lastVisibleIndex = visibleIndexes[visibleIndexes.length - 1];
                fieldsData[lastVisibleIndex].full = visibleIndexes.length % 2 === 1;
            }

            let modalData = {
                id: 'modalForm',
                class: 'h-auto',
                name: 'Payment Details',
                fields: fieldsData,
                fieldsGridCount: '2',
                bottomActions: [
                    {id: 'add-payment-details', text: 'Add Payment', onclick: 'addPaymentDetails()'},
                ],
                defaultListener: false,
            }

            createModal(modalData);

            let amountInpDom = document.getElementById('amount');
            const modalForm = document.getElementById('modalForm');
            selectedDom = document.getElementById('selected') || modalForm?.querySelector('input[name="selected"]');

            let allSelfAccounts = [];

            const filteredAccounts = allSelfAccounts.filter(account => {
                return new Date(account.date) <= new Date(dateDom.value);
            });

            if (['cheque', 'slip', 'program', 'purchase_return'].includes(elem.value)) {
                showLoader();
                $.ajax({
                    url: '/vouchers/create',
                    type: 'GET',
                    data: {
                        supplier_id: selectedSupplier?.id ?? null,
                        payment_method: elem.value,
                        date: dateDom.value,
                    },
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function(response) {
                        payments_options = response.payments_options;
                        if (typeof response.supplier_balance_at_date !== 'undefined') {
                            if (selectedSupplier) {
                                selectedSupplier.balance_at_date = response.supplier_balance_at_date;
                            }
                            if (balanceDom) {
                                balanceDom.value = formatNumbersWithDigits(response.supplier_balance_at_date, 1, 1);
                            }
                        }
                        renderOptions()
                        hideLoader();
                    },
                    error: function(xhr, status, error) {
                        console.error(error);
                    }
                });

                function renderOptions() {
                    let ULDOM = document.querySelector(`ul[data-for="${elem.value}_id"]`);
                    const triggerInput = document.querySelector(`input[data-for="${elem.value}_id"]`);
                    if (!ULDOM || !triggerInput) {
                        return;
                    }

                    triggerInput.addEventListener('change', () => {
                        let selectedOption = ULDOM.querySelector('li.selected');
                        if (!selectedOption || !selectedDom) return;
                        let selectedPayment = JSON.parse(selectedOption.getAttribute('data-option')) || '';

                        selectedDom.value = JSON.stringify(selectedPayment);
                        const amountInput = modalForm?.querySelector('input[name="amount"]');
                        if (amountInput) {
                            amountInput.value = selectedPayment.amount;
                        }
                    })

                    if (payments_options.length > 0) {
                        ULDOM.innerHTML = `
                            <li data-for="${elem.value}_id" data-value="" onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg hover:bg-[var(--h-bg-color)] selected">-- Select ${elem.value} --</li>
                        `;

                        payments_options.forEach(option => {
                            if (paymentDetailsArray.some(pd => (pd.method == 'Slip' && pd.slip_id == option.id) || (pd.method == 'Cheque' && pd.cheque_id == option.id) || (pd.method == 'program' && pd.program_id == option.id) || (pd.method == 'p. return' && pd.expense_id == option.id))) {
                                return; // Skip already selected payments
                            }
                            ULDOM.innerHTML += `
                                <li data-for="${elem.value}_id" data-value="${option.program_id ?? option.id}" data-option='${jsonAttr(option.dataset)}' onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg hover:bg-[var(--h-bg-color)]">${option.text}</li>
                            `;
                        })
                    }

                    if (ULDOM.children.length > 1) {
                        document.querySelector(`input[name="${elem.value}_id_name"]`).disabled = false;
                        document.querySelector(`input[name="${elem.value}_id_name"]`).placeholder = `-- Select ${elem.value} --`;
                    }
                }
            }

            if (elem.value == 'program') {
                let paymentSelectDom = document.querySelector(`ul[data-for="program_id"]`);

                document.querySelector('input[name="program_id"]').addEventListener('change', () => {
                    let selectedOption = paymentSelectDom.querySelector('li.selected');
                    let selectedPayment = JSON.parse(selectedOption.getAttribute('data-option')) || '';

                    selectedDom.value = JSON.stringify(selectedPayment);
                    document.getElementById('amount').value = selectedPayment.amount;
                    document.getElementById('payment_id').value = selectedPayment.id;
                })
            }
        }
    }

    window.addPaymentDetails = function() {
        let detail = {};
        let allDetail = {};
        const inputs = document.querySelectorAll('#modalForm input:not([disabled])');

        inputs.forEach(input => {
            const name = input.getAttribute('name');
            if (name != null) {
                const value = input.value;

                if (name == "amount") {
                    let amountValue = input.value.replace(/[^0-9.]/g, ''); // only digits & dot

                    if (amountValue.includes('.')) {
                        let [intPart, decPart] = amountValue.split('.');
                        decPart = decPart.slice(0, 2); // max 2 decimals
                        amountValue = decPart ? `${intPart}.${decPart}` : intPart;
                    }

                    detail[name] = parseInt(amountValue);
                    allDetail[name] = parseInt(amountValue);
                } else {
                    detail[name] = value;
                    allDetail[name] = value;
                }
            } else {
                const value = JSON.parse(input.value);

                allDetail[name ?? 'selected'] = value;
            }
        });

        const selectBankAccount = document.querySelector("#modalForm select");
        if (selectBankAccount) {
            detail[selectBankAccount.getAttribute('name')] = selectBankAccount.value;
        }

        if (isNaN(detail.amount) || detail.amount <= 0) {
            detail = {};
        }

        if (Object.keys(detail).length > 0) {
            let selectedMethod = methodSelectDom.value;
            if (selectedMethod == 'Payment Program') {
                selectedMethod = 'program';
            }
            if (selectedMethod == 'Purchase Return') {
                selectedMethod = 'p. return';
            }
            totalPayment += detail.amount;
            detail['method'] = selectedMethod;
            allDetail['method'] = selectedMethod;
            paymentDetailsArray.push(detail);
            allPayments.push(allDetail);
            renderList();
        }
        closeModal('modalForm');
    }

    function renderList() {
        const isSelfAccount = voucherType === 'self_account';
        if (paymentDetailsArray.length > 0) {
            let clutter = "";
            paymentDetailsArray.forEach((paymentDetail, index) => {
                let selected = paymentDetail.selected ? JSON.parse(paymentDetail.selected) : null;
                const fromAccount = selected?.display_label
                    ?? shortAccountLabel(paymentDetail.bank_account_id_name ?? selected?.account_title);
                const toAccount = shortAccountLabel(paymentDetail.self_account_id_name);

                const accountCol = isSelfAccount
                    ? `<div class="w-1/3 capitalize">${toAccount} (+)</div>`
                    : '';

                clutter += `
                    <div class="flex justify-between items-center border-t border-gray-600 py-3 px-4">
                        <div class="w-[7%]">${index+1}</div>
                        ${accountCol}
                        <div class="w-1/5 capitalize">${paymentDetail.method}</div>
                        <div class="w-1/3 capitalize">${isSelfAccount && paymentDetail.bank_account_id ? `${fromAccount} (-)` : selected?.customer ? `${selected?.customer?.customer_name} | ${selected?.customer?.city?.title}` : paymentDetail.bank_account_id_name ?? (selected?.program ? `${selected?.program?.customer?.customer_name} | ${selected?.program?.customer?.city?.title}` : '-')}</div>
                        <div class="w-1/5 capitalize">${selected?.slip_no ?? selected?.cheque_no ?? selected?.reff_no ?? selected?.transaction_id ?? paymentDetail.cheque_no ?? paymentDetail.reff_no ?? '-'}</div>
                        <div class="w-1/6 capitalize">${selected?.remarks ?? '-'}</div>
                        <div class="w-[15%]">${formatNumbersWithDigits(paymentDetail.amount, 1, 1)}</div>
                        <div class="w-[10%] text-center">
                            <button onclick="deselectThisPayment(${index})" type="button" class="text-[var(--danger-color)] text-xs px-2 py-1 rounded-lg hover:text-[var(--h-danger-color)] transition-all duration-300 ease-in-out cursor-pointer">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                `;
            });

            paymentListDom.innerHTML = clutter;

            paymentDetailsArrayDom.value = JSON.stringify(paymentDetailsArray);
        } else {
            paymentListDom.innerHTML =
                `<div class="text-center bg-[var(--h-bg-color)] rounded-lg py-2 px-4">No Payment Yet</div>`;
        }
        finalTotalPaymentDom.textContent = formatNumbersWithDigits(totalPayment, 1, 1);
    }

    window.deselectThisPayment = function(index) {
        totalPayment -= paymentDetailsArray[index].amount;
        paymentDetailsArray.splice(index, 1);
        renderList();
    }

    function generateVoucherNo() {
        return 'Assigned after save';
    }

    const previewDom = document.getElementById('preview');
    function voucherPreviewPayments(dateValue) {
        return paymentDetailsArray.map(payment => {
            const selected = payment.selected ? JSON.parse(payment.selected || '{}') : {};
            const selectedBank = selected.bank_account || (selected.account_title ? {
                account_title: selected.account_title,
                display_label: selected.display_label ?? shortAccountLabel(selected.account_title),
                bank: selected.bank || { short_title: selected.bank_short_title },
            } : null);
            const selectedSelfAccount = selected.self_account || (payment.self_account_id_name ? {
                account_title: payment.self_account_id_name,
                display_label: shortAccountLabel(payment.self_account_id_name),
            } : null);

            return {
                ...payment,
                date: dateValue,
                amount: parseFormattedNumber(payment.amount),
                program: payment.program || selected.program,
                cheque: payment.cheque || selected.cheque,
                slip: payment.slip || selected.slip,
                bank_account: payment.bank_account || selectedBank,
                self_account: payment.self_account || selectedSelfAccount,
                voucher_no: payment.voucher_no || safeDocumentNumberPreview(config.nextVoucherNo || lastVoucher?.voucher_no),
                cheque_no: payment.cheque_no || selected.cheque_no,
                slip_no: payment.slip_no || selected.slip_no,
                reff_no: payment.reff_no || selected.reff_no,
                transaction_id: payment.transaction_id || selected.transaction_id,
            };
        });
    }

    function generateVoucherPreview() {
        const voucherNo = generateVoucherNo();
        const dateInpDom = document.getElementById("date");
        const isSupplier = voucherType === 'supplier';
        const previewContainer = document.getElementById('preview-container');

        if (!previewContainer) return;

        if (paymentDetailsArray.length > 0) {
            const rawBalance = selectedSupplier?.balance_at_date ?? selectedSupplier?.balance ?? 0;
            const supplierBalance = Number(rawBalance.toString().replace(/,/g, '')) || 0;
            const safeTotalPayment = Number((totalPayment ?? 0).toString().replace(/,/g, '')) || 0;

            previewContainer.className = 'h-auto mx-auto relative flex flex-col';
            previewContainer.innerHTML = window.DocumentPreview.render({
                preview: {
                    type: 'voucher',
                    size: 'A5',
                    document: 'Voucher',
                    data: {
                        voucher_no: voucherNo,
                        date: dateInpDom?.value,
                        supplier: isSupplier ? selectedSupplier : null,
                        previous_balance: supplierBalance,
                        total_payment: safeTotalPayment,
                        payments: voucherPreviewPayments(dateInpDom?.value),
                        branch_branding: companyData,
                    },
                },
            }, {
                companyData,
                companyLogoBase: config.companyLogoBase,
            });
        } else {
            previewContainer.className = 'w-[148mm] h-[210mm] mx-auto overflow-hidden relative';
            previewContainer.innerHTML = `
                <div id="preview" class="preview w-[148mm] h-[210mm] gos-a5-document gos-a5-invoice overflow-hidden flex flex-col">
                    <h1 class="text-[var(--border-error)] font-medium text-center mt-5">No Preview avalaible.</h1>
                </div>
            `;
        }
    }

    window.validateForNextStep = function() {
        generateVoucherPreview();
        return true;
    }
}

window.initVouchersCreate = initVouchersCreate;

function boot() {
    if (window.__vouchersCreate) initVouchersCreate();
}

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", boot);
} else {
    boot();
}
})();
