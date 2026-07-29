(() => {
function initVouchersEdit() {
    const config = window.__vouchersEdit || {};
    const isDeveloper = config.isDeveloper === true;
    const voucherType = config.voucherType;
    const voucher = config.voucher;
    const companyData = config.branchBranding || config.companyData;
    const companyLogoUrl = config.branchBranding?.logo_url
        || config.companyLogoUrl
        || (config.companyLogoBase && config.companyData?.logo
            ? `${config.companyLogoBase}/${config.companyData.logo}`
            : '');
    const templates = config.templates || {};
    const allSelfAccounts = config.selfAccounts || [];

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

    function buildChequeSelect({ id, name, label, onchange }) {
        return renderTemplate(templates.chequeSelect, {
            ID: id,
            NAME: name,
            LABEL: label || 'Cheque',
            ONCHANGE: onchange || ''
        });
    }

    function buildSlipSelect({ id, name, label, onchange }) {
        return renderTemplate(templates.slipSelect, {
            ID: id,
            NAME: name,
            LABEL: label || 'Slip',
            ONCHANGE: onchange || ''
        });
    }

    let supplierSelectDom = document.getElementById('supplier_id');
    let methodSelectDom = document.getElementById('method');
    let dateDom = document.getElementById('date');
    let balanceDom = document.getElementById('balance');
    let paymentDetailsDom = document.getElementById('paymentDetails');
    let finalTotalPaymentDom = document.getElementById('finalTotalPayment');
    let paymentListDom = document.getElementById('payment-list');
    const paymentDetailsArrayDom = document.getElementById("payment_details_array");

    const previewDom = document.getElementById('preview');

    selectedSupplierData = null;
    let totalPayment = 0;

    let paymentDetailsArray = [];
    let allPayments = [];

    let selectedSupplier;

    const today = localDateString();

    window.trackSupplierState = function() {
        balanceDom.value = '';
        methodSelectDom.value = '';

        if (supplierSelectDom && supplierSelectDom.value != '') {
            selectedSupplier = voucher.supplier;
            methodSelectDom.disabled = false;
            const initialBalance = selectedSupplier.balance_at_date ?? selectedSupplier.balance ?? 0;
            balanceDom.value = formatNumbersWithDigits(initialBalance, 1, 1);
            selectedSupplierData = selectedSupplier;
        } else {
            methodSelectDom.disabled = true;
        }
    }

    window.trackDateState = function(elem) {
        paymentDetailsArray = [];
        methodSelectDom.value = '';
        renderList();

        if (elem.value != '') {
            gotoStep(2);
        }
    }

    if (voucherType === 'supplier') {
        trackSupplierState();
    }
    trackDateState(dateDom);

    paymentDetailsArray = voucher.payments;
    allPayments = voucher.payments;
    totalPayment = paymentDetailsArray.reduce((sum, item) => sum + parseFormattedNumber(item.amount), 0);

    renderList();

    const enterDetailsBtn = document.getElementById("enterDetailsBtn");
    if (enterDetailsBtn) enterDetailsBtn.disabled = true;

    window.trackChequeState = function(elem) {
        let selectedCheque = JSON.parse(elem.closest('.selectParent').querySelector('ul[data-for="cheque_id"] li.selected').dataset.option || '{}');
        let amountInpDom = elem.closest('form').querySelector('input[name="amount"]');

        selectedDom.value = JSON.stringify(selectedCheque);
        amountInpDom.value = selectedCheque.amount;
    }

    window.trackSlipState = function(elem) {
        let selectedSlip = JSON.parse(elem.closest('.selectParent').querySelector('ul[data-for="slip_id"] li.selected').dataset.option || '{}');
        let amountInpDom = elem.closest('form').querySelector('input[name="amount"]');

        selectedDom.value = JSON.stringify(selectedSlip);
        amountInpDom.value = selectedSlip.amount;
    }

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
        let selectedAccount = JSON.parse(selectedOption.getAttribute('data-option')) || ''
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

    window.trackExpenseSelect = function(elem) {
        let selectedOption = elem.nextElementSibling.querySelector('li.selected');
        let selectedExpense = JSON.parse(selectedOption.getAttribute('data-option')) || ''
        elem.closest('form').querySelector('input[name="selected"]').value = JSON.stringify(selectedExpense);
        elem.closest('form').querySelector('input[name="reff_no"]').value = selectedExpense.reff_no;
    }

    window.updateSelectedAccount = function(elem) {
        let hiddenAccountInSelfAccount = elem.closest('form').querySelector(`ul[data-for="bank_account_id"]`);
        hiddenAccountInSelfAccount.querySelectorAll('li').forEach(li => {
            if (li.style.display == 'none') {
                li.style.display = 'block';
            }
        })

        let selectedOption = elem.nextElementSibling.querySelector('li.selected');
        let selectedAccount = JSON.parse(selectedOption.getAttribute('data-option')) || ''

        let selectedAccountInBankAccount = elem.closest('form').querySelector(`ul[data-for="bank_account_id"] li[data-value="${selectedAccount.id}"]`);

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
                    html: buildChequeSelect({ id: 'cheque_id', name: 'cheque_id', label: 'Cheque', onchange: 'trackChequeState(this)' }),
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
                    readonly: !isDeveloper,
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
                    html: buildSlipSelect({ id: 'slip_id', name: 'slip_id', label: 'Slip', onchange: 'trackSlipState(this)' }),
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
                    readonly: !isDeveloper,
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
                    readonly: !isDeveloper,
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
                        id: 'bank_account_id',
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
                        id: 'bank_account_id',
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
                    html: buildEmptySelect({ id: 'expense_id', name: 'expense_id', label: 'Expense', onchange: 'trackExpenseSelect(this)' }),
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
            selectedDom = document.getElementById('selected');

            const filteredAccounts = allSelfAccounts.filter(account => {
                return new Date(account.date) <= new Date(dateDom.value);
            });

            if (elem.value == 'program') {
                let paymentSelectDom = document.querySelector(`ul[data-for="program_id"]`);

                let allPayments = selectedSupplier.payments;

                const filteredPayments = allPayments.filter(payment => {
                    return formatDate(payment.date, false, true) <= formatDate(dateDom.value, false, true);
                });

                paymentSelectDom.innerHTML = `
                    <li data-for="program_id" data-value="" onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg hover:bg-[var(--h-bg-color)] selected">-- Select program --</li>
                `;

                filteredPayments.forEach(payment => {
                    paymentSelectDom.innerHTML += `
                        <li data-for="program_id" data-value="${payment.program_id}" data-option='${jsonAttr(payment)}' onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg hover:bg-[var(--h-bg-color)]">${formatNumbersWithDigits(payment.amount, 1, 1)} | ${payment.program.customer.customer_name} | ${payment.program.customer.city.title} | ${payment.transaction_id} | ${formatDate(payment.date)}</li>
                    `;
                })

                if (filteredPayments.length > 0) {
                    document.querySelector('input[name="program_id_name"]').disabled = false;
                    document.querySelector('input[name="program_id_name"]').placeholder = '-- Select program --';
                }

                document.querySelector('input[name="program_id"]').addEventListener('change', () => {
                    let selectedOption = paymentSelectDom.querySelector('li.selected');
                    let selectedPayment = JSON.parse(selectedOption.getAttribute('data-option')) || '';

                    selectedDom.value = JSON.stringify(selectedPayment);
                    document.getElementById('amount').value = selectedPayment.amount;
                    document.getElementById('payment_id').value = selectedPayment.id;
                })
            }

            if (elem.value == 'purchase_return') {
                selectedDom = document.querySelector('input[name="selected"]');
                let expenseSelectDom = document.querySelector(`ul[data-for="expense_id"]`);

                let allExpenses = selectedSupplier.expenses;

                const filteredExpenses = allExpenses.filter(expense => {
                    return new Date(expense.date) <= new Date(dateDom.value);
                });

                expenseSelectDom.innerHTML = `
                    <li data-for="expense_id" data-value="" onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg hover:bg-[var(--h-bg-color)] selected">-- Select expense --</li>
                `;

                filteredExpenses.forEach(expense => {
                    expenseSelectDom.innerHTML += `
                        <li data-for="expense_id" data-value="${expense.id}" data-option='${jsonAttr(expense)}' onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg hover:bg-[var(--h-bg-color)]">${formatNumbersWithDigits(expense.amount, 1, 1)} | ${expense.reff_no}</li>
                    `;
                })

                if (filteredExpenses.length > 0) {
                    document.querySelector('input[name="expense_id_name"]').disabled = false;
                    document.querySelector('input[name="expense_id_name"]').placeholder = '-- Select program --';
                }

                document.querySelector('input[name="expense_id"]').addEventListener('change', () => {
                    let selectedOption = expenseSelectDom.querySelector('li.selected');
                    let selectedExpense = JSON.parse(selectedOption.getAttribute('data-option')) || '';

                    selectedDom.value = JSON.stringify(selectedExpense);
                    document.querySelector('input[name="amount"]').max = selectedExpense.amount;
                })
            }

            if (elem.value === 'slip' || elem.value === 'cheque' || elem.value === 'program') {
                const type = elem.value;
                const key = type + '_id';
                const inputName = key + '_name';

                const usedIds = paymentDetailsArray
                    .map(item => item[key])
                    .filter(id => id !== undefined && id !== null);

                usedIds.forEach(id => {
                    const listItem = document.querySelector(`ul[data-for="${key}"] li[data-value="${id}"]`);
                    if (listItem) {
                        listItem.style.display = 'none';
                    }
                });

                const allListItems = document.querySelectorAll(`ul[data-for="${key}"] li`);
                const visibleListItems = Array.from(allListItems).filter(li => li.style.display !== 'none');

                if (
                    visibleListItems.length === 1 &&
                    visibleListItems[0].getAttribute('data-value') === ''
                ) {
                    const input = document.querySelector(`input[name="${inputName}"]`);
                    if (input) {
                        input.placeholder = '-- No options available --';
                        input.disabled = true;
                    }
                }
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
            paymentDetailsArray.push(detail);
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

                const accountCol = isSelfAccount
                    ? `<div class="w-1/3 capitalize">${paymentDetail.self_account_id_name ?? paymentDetail.self_account?.account_title}</div>`
                    : '';

                clutter += `
                    <div class="flex justify-between items-center border-t border-gray-600 py-3 px-4">
                        <div class="w-[7%]">${index+1}</div>
                        ${accountCol}
                        <div class="w-1/5 capitalize">${paymentDetail.method}</div>
                        <div class="w-1/3 capitalize">${selected?.customer ? `${selected.customer.customer_name} | ${selected.customer.city?.title ?? '-'}` : selected?.program?.customer ? `${selected.program.customer.customer_name} | ${selected.program.customer.city?.title ?? '-'}` : selected?.account_title ? `${selected.account_title} | ${selected.bank?.short_title ?? '-'}` : paymentDetail?.cheque ? `${paymentDetail.cheque.customer?.customer_name ?? '-'} | ${paymentDetail.cheque.customer?.city?.title ?? '-'}` : paymentDetail?.slip ? `${paymentDetail.slip.customer?.customer_name ?? '-'} | ${paymentDetail.slip.customer?.city?.title ?? '-'}` : paymentDetail?.self_account?.account_title ?? paymentDetail?.bank_account?.account_title ?? paymentDetail?.bank_account_id_name ?? paymentDetail?.self_account_id_name ?? '-'}</div>
                        <div class="w-1/5 capitalize">${selected?.slip_no ?? selected?.cheque_no ?? selected?.reff_no ?? selected?.transaction_id ?? paymentDetail?.cheque?.cheque_no ?? paymentDetail.cheque_no ?? paymentDetail.reff_no ?? paymentDetail?.slip?.slip_no ?? paymentDetail.slip_no ?? paymentDetail.transaction_id ?? '-'}</div>
                        <div class="w-1/6 capitalize">${selected?.remarks ?? (paymentDetail.remarks !== '' && paymentDetail.remarks) !== null ? paymentDetail.remarks : '-' ?? '-'}</div>
                        <div class="w-[15%]">${formatNumbersWithDigits(paymentDetail.amount, 1, 1)}</div>
                        <div class="w-[10%] text-center">
                            <button onclick="deselectThisPayment(${index})" type="button" 
                                class="text-[var(--danger-color)] text-xs px-2 py-1 rounded-lg transition-all duration-300 ease-in-out
                                ${paymentDetailsArray.length === 1 ? 'opacity-50 cursor-not-allowed pointer-events-none' : 'hover:text-[var(--h-danger-color)] cursor-pointer'}">
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

    function generateVoucherPreview() {
        let voucherNo = voucher.voucher_no;
        const dateInpDom = document.getElementById("date");
        const isSupplier = voucherType === 'supplier';

        if (allPayments.length > 0) {
            const supplierSection = isSupplier
                ? `<div class="center my-auto">
                        <div class="supplier-name capitalize font-semibold text-md">Supplier Name: ${selectedSupplier.supplier_name}</div>
                    </div>`
                : '';

            const rawBalance = selectedSupplier?.balance_at_date ?? selectedSupplier?.balance ?? 0;
            const supplierBalance = Number(rawBalance.toString().replace(/,/g, '')) || 0;
            const safeTotalPayment = Number((totalPayment ?? 0).toString().replace(/,/g, '')) || 0;
            const totalsSection = isSupplier
                ? `
                    <div class="total flex justify-between items-center border border-gray-600 rounded-lg py-2 px-4 w-full">
                        <div class="text-nowrap">Previous Balance - Rs</div>
                        <div class="w-1/4 text-right grow">${formatNumbersWithDigits(supplierBalance, 1, 1)}</div>
                    </div>
                    <div class="total flex justify-between items-center border border-gray-600 rounded-lg py-2 px-4 w-full">
                        <div class="text-nowrap">Total Payment - Rs</div>
                        <div class="w-1/4 text-right grow">${formatNumbersWithDigits(safeTotalPayment, 1, 1)}</div>
                    </div>
                    <div class="total flex justify-between items-center border border-gray-600 rounded-lg py-2 px-4 w-full">
                        <div class="text-nowrap">Current Balance - Rs</div>
                        <div class="w-1/4 text-right grow">${formatNumbersWithDigits(supplierBalance - safeTotalPayment, 1, 1)}</div>
                    </div>
                `
                : `
                    <div class="total flex justify-between items-center border border-gray-600 rounded-lg py-2 px-4 w-full">
                        <div class="text-nowrap">Total Payment - Rs</div>
                        <div class="w-1/4 text-right grow">${formatNumbersWithDigits(safeTotalPayment, 1, 1)}</div>
                    </div>
                `;

            previewDom.innerHTML = `
                <div id="preview-document" class="preview-document flex flex-col h-full">
                    <div id="preview-banner" class="preview-banner w-full flex justify-between items-center mt-8 pl-5 pr-8">
                        <div class="left">
                            <div class="company-logo">
                                <img src="${companyLogoUrl}" alt="garmentsos-pro"
                                    class="w-[12rem]" />
                                <div class='mt-1'>${ companyData.phone_number }</div>
                            </div>
                        </div>
                        <div class="right">
                            <div>
                                <h1 class="text-2xl font-medium text-[var(--primary-color)] pr-2">Voucher</h1>
                            </div>
                        </div>
                    </div>
                    <hr class="w-full my-3 border-gray-600">
                    <div id="preview-header" class="preview-header w-full flex justify-between px-5">
                        <div class="left my-auto pr-3 text-sm text-gray-600 space-y-1.5">
                            <div class="voucher-date leading-none">Date: ${formatDate(dateInpDom.value)}</div>
                            <div class="voucher-number leading-none">Voucher No.: ${voucherNo}</div>
                            <input type="hidden" name="voucher_no" value="${voucherNo}" />
                        </div>
                        ${supplierSection}
                        <div class="right my-auto pr-3 text-sm text-gray-600 space-y-1.5">
                            <div class="preview-copy leading-none">Voucher Copy: Supplier</div>
                            <div class="preview-doc leading-none">Document: Voucher</div>
                        </div>
                    </div>
                    <hr class="w-full my-3 border-gray-600">
                    <div id="preview-body" class="preview-body w-[95%] grow mx-auto">
                        <div class="preview-table w-full">
                            <div class="table w-full border border-gray-600 rounded-lg pb-2.5 overflow-hidden">
                                <div class="thead w-full">
                                    <div class="tr flex justify-between w-full px-4 py-1.5 bg-[var(--primary-color)] text-white">
                                        <div class="th text-sm font-medium w-[7%]">S.No</div>
                                        <div class="th text-sm font-medium w-[11%]">Method</div>
                                        <div class="th text-sm font-medium w-1/5">Customer</div>
                                        <div class="th text-sm font-medium w-1/4">Account</div>
                                        <div class="th text-sm font-medium w-[14%]">Date</div>
                                        <div class="th text-sm font-medium w-[14%]">Reff. No.</div>
                                        <div class="th text-sm font-medium w-[10%]">Amount</div>
                                    </div>
                                </div>
                                <div id="tbody" class="tbody w-full">
                                    ${paymentDetailsArray.map((payment, index) => {
                                        let selected = JSON.parse(payment.selected || '{}');

                                        const hrClass = index === 0 ? "mb-2.5" : "my-2.5";
                                        return `
                                                <div>
                                                    <hr class="w-full ${hrClass} border-gray-600">
                                                    <div class="tr flex justify-between w-full px-4 text-nowrap gap-0.5">
                                                        <div class="td text-sm font-semibold w-[7%]">${index + 1}.</div>
                                                        <div class="td text-sm font-semibold w-[11%] capitalize">${payment.method ?? '-'}</div>
                                                        <div class="td text-sm font-semibold w-1/5">${payment.program?.customer?.customer_name ? payment.program?.customer?.customer_name : selected.customer?.customer_name ? selected.customer?.customer_name : payment.cheque ? payment.cheque?.customer?.customer_name ? payment.cheque.customer.customer_name : '-' : payment.slip ? payment.slip.customer.customer_name : '-'}</div>
                                                        <div class="td text-sm font-semibold w-1/4">${payment.bank_account ? payment.bank_account.account_title + ' | ' + (payment.bank_account.bank?.short_title ?? '-') : (selected?.bank_account?.account_title ?? '-') + ' | ' + (selected?.bank_account?.bank?.short_title ?? '-')}</div>
                                                        <div class="td text-sm font-semibold w-[14%]">${formatDate(dateInpDom.value, true) ?? '-'}</div>
                                                        <div class="td text-sm font-semibold w-[14%]">${selected?.cheque_no ?? selected?.slip_no ?? selected?.transaction_id ?? selected?.reff_no ?? payment.cheque_no ?? payment.reff_no ?? payment.transaction_id ?? payment.cheque?.cheque_no ?? payment.slip?.slip_no ?? '-'}</div>
                                                        <div class="td text-sm font-semibold w-[10%]">${formatNumbersWithDigits(payment.amount, 1, 1) ?? '-'}</div>
                                                    </div>
                                                </div>
                                            `;
                                    }).join('')}
                                </div>
                            </div>
                        </div>
                    </div>
                    <hr class="w-full my-3 border-gray-600">
                    <div class="flex flex-col space-y-2">
                        <div id="total" class="tr flex justify-between w-full px-2 gap-2 text-sm">
                            ${totalsSection}
                        </div>
                    </div>
                    <hr class="w-full my-3 border-gray-600">
                    <div class="tfooter flex w-full text-sm px-4 justify-between mb-4 text-gray-600">
                        <P class="leading-none">Powered by SparkPair</P>
                        <p class="leading-none text-sm">&copy; ${new Date().getFullYear()} SparkPair | +92 316 5825495</p>
                    </div>
                </div>
            `;
        } else {
            previewDom.innerHTML = `
                <h1 class="text-[var(--border-error)] font-medium text-center mt-5">No Preview avalaible.</h1>
            `;
        }
    }

    window.validateForNextStep = function() {
        generateVoucherPreview();
        return true;
    }
}

window.initVouchersEdit = initVouchersEdit;

function boot() {
    if (window.__vouchersEdit) initVouchersEdit();
}

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", boot);
} else {
    boot();
}
})();
