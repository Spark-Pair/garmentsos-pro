(() => {
function initCustomerPaymentsIndex() {
    let totalAmount = 0;
    let totalPayment = 0;
    const config = window.__customerPaymentsIndex || {};
    let companyData = config.companyData || {};
    let authLayout = config.authLayout || 'table';

    function createRow(data) {
        return `
            <div id="${data.id}" oncontextmenu='${htmlAttr(data.oncontextmenu || "")}' onclick='${htmlAttr(data.onclick || "")}'
                class="item row relative group flex justify-between border-b border-[var(--h-bg-color)] items-center py-2 cursor-pointer hover:bg-[var(--h-secondary-bg-color)] transition-all fade-in ease-in-out"
                data-json='${jsonAttr(data)}'>

                <span class="text-center w-1/8">${data.details['Date']}</span>
                <span class="text-center w-1/4">${data.name}</span>
                <span class="text-center w-1/6">${data.supplier_name ?? '-'}</span>
                <span class="text-center w-1/6">${data.beneficiary}</span>
                <span class="text-center w-1/12 capitalize">${data.details["Method"]}</span>
                <span class="text-center w-1/12">${data.details['Amount']}</span>
                <span class="text-center w-1/12">${data.reff_no}</span>
                <span class="text-center w-1/8">${data.clear_date ?? '-'}</span>
                <span class="text-center w-1/10">${data.cleared_amount ?? '-'}</span>
                <span class="text-center w-1/11">${data.voucher_no}</span>
                <span class="text-center w-1/12">${data.d_r_no}</span>
            </div>
        `;
    }

    let totalAmountDom = document.querySelector('#calc-bottom >.total-Amount .text-right');
    let totalPaymentDom = document.querySelector('#calc-bottom >.total-Payment .text-right');
    let totalBalanceDom = document.querySelector('#calc-bottom >.balance .text-right');

    function renderCalculation(data) {
        totalAmountDom.innerText = formatNumbersWithDigits(data.total_amount, 1, 1);
        totalPaymentDom.innerText = formatNumbersWithDigits(data.total_payment, 1, 1);
        totalBalanceDom.innerText = formatNumbersWithDigits(data.balance, 1, 1);
    }

    window.createRow = createRow;
    window.renderCalculation = renderCalculation;

    window.generateClearModal = function(item) {
        let data = item.data;

        let modalData = {
            id: 'clearModal',
            class: 'h-auto',
            name: 'Clear Payment',
            method: 'POST',
            action: `/customer-payments/${data.id}/clear`,
            fields: [
                {
                    category: 'input',
                    label: 'Method',
                    value: data.customer.customer_name + ' | ' + data.customer.city.short_title + ' | ' + data.method.charAt(0).toUpperCase() + data.method.slice(1) + (data.method === 'cheque' ? ` | Cheque No. ${data.cheque_no}` : data.method === 'slip' ? ` | Slip No. ${data.slip_no}` : '') + ' | ' + formatNumbersWithDigits(data.amount, 1, 1) + ' - Rs.',
                    disabled: true,
                    full: true,
                },
                {
                    category: 'input',
                    name: 'clear_date',
                    label: 'Clear Date',
                    type: 'date',
                    min: (data.date)?.split('T')[0],
                    max: localDateString(),
                    required: true,
                },
                {
                    category: 'explicitHtml',
                    html: config.methodSelectHtml,
                },
                {
                    category: 'explicitHtml',
                    html: config.bankAccountSelectHtml,
                },
                {
                    category: 'explicitHtml',
                    html: config.amountInputHtml,
                },
                {
                    category: 'explicitHtml',
                    html: config.reffNoInputHtml,
                },
                {
                    category: 'input',
                    name: 'remarks',
                    label: 'Remarks',
                    type: 'text',
                    placeholder: 'Enter remarks',
                },
            ],
            fieldsGridCount: '2',
            bottomActions: [
                {id: 'clear', text: 'Clear', type: 'submit'},
            ],
        };
        createModal(modalData);

        let bankAccounts = data.bank_account ? [data.bank_account] : data.cheque?.voucher?.supplier?.bank_accounts ? data.cheque?.voucher?.supplier?.bank_accounts : data.slip?.voucher?.supplier?.bank_accounts ? data.slip?.voucher?.supplier?.bank_accounts : data.cheque?.cr?.voucher?.supplier?.bank_accounts ? data.cheque?.cr?.voucher?.supplier?.bank_accounts : data.slip?.cr?.voucher?.supplier?.bank_accounts ? data.slip?.cr?.voucher?.supplier?.bank_accounts : [];
        let form = document.querySelector('#clearModal');
        let bankAccountInpDom = form.querySelector('input[id="bank_account_id"]');
        let bankAccountDom = form.querySelector('ul[data-for="bank_account_id"]');

        bankAccountInpDom.disabled = true;
        bankAccountDom.innerHTML = `
            <li data-for="bank_account_id" data-value=" " onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg transition hover:bg-[var(--h-bg-color)] text-nowrap overflow-x-auto scrollbar-hidden"">-- Select bank account --</li>
        `;

        bankAccounts.forEach(bankAccount => {
            bankAccountDom.innerHTML += `
                <li data-for="bank_account_id" data-value="${bankAccount.id}" onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg transition hover:bg-[var(--h-bg-color)] text-nowrap overflow-x-auto scrollbar-hidden">${bankAccount.account_title} | ${bankAccount.bank?.short_title}</li>
            `;
        });
    }

    window.generateContextMenu = function(e) {
        e.preventDefault();
        let item = e.target.closest('.item');
        let data = JSON.parse(item.dataset.json);

        let contextMenuData = {
            item: item,
            data: data,
            x: e.pageX,
            y: e.pageY,
            actions: [
                {id: 'edit-payment', text: 'Edit', dataId: data.id}
            ],
        };

        if ((data.data.method === 'cheque' || data.data.method === 'slip') && new Date(data.data.date) <= new Date()) {
            if ((data.clear_date == null || data.clear_date == 'pending' || data.clear_date == 'Pending') && data.issued == 'Issued') {
                contextMenuData.actions.push(
                    {id: 'clear', text: 'Clear', onclick: `generateClearModal(${JSON.stringify(data)})`},
                );
            }
        }

        if (data.method !== "cash") {
            contextMenuData.actions.push(
                {id: 'split-payment', text: 'Split Payment', onclick: `generateSplitPaymentModal(${JSON.stringify(data)})`},
            );
        }

        if (isDeveloperUser()) {
            contextMenuData.actions.push({
                id: 'delete-customer-payment',
                text: 'Delete',
                onclick: `submitResourceDelete('/customer-payments/${data.id}')`,
            });
        }

        createContextMenu(contextMenuData);
    }

    window.generateModal = function(item) {
        let data = JSON.parse(item.dataset.json);
        const clearDetails = Array.isArray(data.clear_details) ? data.clear_details : [];

        const clearTableBody = clearDetails.map((row, index) => ([
            { data: index + 1, class: 'w-[5%]' },
            { data: row.date || '-', class: 'w-[16%]' },
            { data: row.method || '-', class: 'w-[12%] capitalize' },
            { data: ((row.account_title || '-') + ' | ' + (row.bank || '-')).trim(), class: 'w-[28%]' },
            { data: row.amount || formatNumbersWithDigits(row.amount_numeric || 0, 1, 1), class: 'w-[12%]' },
            { data: row.reff_no || '-', class: 'w-[12%]' },
            { data: row.remarks || '-', class: 'grow' },
        ]));

        let modalData = {
            id: 'modalForm',
            class: clearTableBody.length > 0 ? 'h-auto max-w-5xl' : 'h-auto',
            name: data.name,
            details: {
                'Date': formatDate(data.data.date),
                ...(data.program_date && { 'Program Date': data.program_date }),
                'Amount': data.details['Amount'],
                'Type': data.details['Type'],
                'Method': data.details['Method'],
                'hr': true,
                ...(data.data.cheque_no && { 'Cheque No': data.data.cheque_no }),
                ...(data.data.slip_no && { 'Slip No': data.data.slip_no }),
                ...(data.data.transition_id && { 'Transition Id': data.data.transition_id }),
                ...(data.data.bank && { 'Bank': data.data.bank }),
                ...(data.data.cheque_date && { 'Cheque Date': formatDate(data.data.cheque_date) }),
                ...(data.data.slip_date && { 'Slip Date': formatDate(data.data.slip_date) }),
                ...(data.clear_date && data.clear_date !== 'Pending' && { 'Clear Date': data.clear_date }),
                ...(data.cleared_amount && { 'Clear Amount': data.cleared_amount }),
                ...((data.data.method == 'cheque' || data.data.method == 'slip') && (data.clear_date ? { 'Clear Date': data.clear_date } : { 'Clear Date': 'Pending'} )),
                ...((data.data.method == 'cheque' || data.data.method == 'slip' || data.data.method == 'program') && { 'Issued': data.issued }),
                ...(data.voucher_no && data.voucher_no !== '-' && { 'Voucher / CR No.': data.voucher_no }),
                ...(data.cr_no && { 'CR No.': data.cr_no }),
                ...(data.d_r_no && data.d_r_no !== '-' && { 'DR No.': data.d_r_no }),
                'Remarks': data.data.remarks || 'No Remarks',
            },
            ...(clearTableBody.length > 0 ? {
                table: {
                    name: 'Clear Records',
                    headers: [
                        { label: '#', class: 'w-[5%]' },
                        { label: 'Date', class: 'w-[16%]' },
                        { label: 'Method', class: 'w-[12%]' },
                        { label: 'Acc. Title', class: 'w-[28%]' },
                        { label: 'Amount', class: 'w-[12%]' },
                        { label: 'Reff. No.', class: 'w-[12%]' },
                        { label: 'Remarks', class: 'grow' },
                    ],
                    body: clearTableBody,
                    scrollable: true,
                }
            } : {}),
            bottomActions: [
                {id: 'edit-payment', text: 'Edit', dataId: data.id}
            ],
        }

        if ((data.data.method === 'cheque' || data.data.method === 'slip') && new Date(data.data.date) <= new Date()) {
            if ((data.clear_date == null || data.clear_date == 'pending' || data.clear_date == 'Pending') && data.issued == 'Issued') {
                modalData.bottomActions.push(
                    {id: 'clear', text: 'Clear', onclick: `generateClearModal(${JSON.stringify(data)})`},
                );
            }
        }

        if (data.data.method !== "cash") {
            modalData.bottomActions.push(
                {id: 'split-payment', text: 'Split Payment', onclick: `generateSplitPaymentModal(${JSON.stringify(data)})`},
            );
        }

        if (isDeveloperUser()) {
            modalData.bottomActions.push({
                id: 'delete-customer-payment',
                text: 'Delete',
                onclick: `submitResourceDelete('/customer-payments/${data.id}')`,
            });
        }

        createModal(modalData);
    }

    function generateReffNos(rawReffNo, hasPipe, maxSuffix) {
        rawReffNo = rawReffNo.toString().replace('/', '|').trim();
        let base = rawReffNo.includes('|') ? rawReffNo.split('|')[0].trim() : rawReffNo;

        let current, next;

        if (hasPipe) {
            current = rawReffNo;
            next = `${base} | ${maxSuffix + 1}`;
        } else {
            current = `${base} | 1`;
            next = `${base} | ${maxSuffix + 2}`;
        }

        return [current, next];
    }

    window.generateSplitPaymentModal = function(item) {
        let data = item.data
        let rawReffNo =
            data.method === "cheque" ? data.cheque_no :
            data.method === "slip" ? data.slip_no :
            data.method === "program" ? data.transaction_id :
            data.reff_no;

        let [currentRef, newRef] = generateReffNos(rawReffNo, item.has_pipe, item.max_reff_suffix);

        let modalData = {
            id: 'splitModalForm',
            class: 'h-auto',
            method: 'POST',
            action: config.routes?.splitPayment.replace(':id', data.id),
            name: 'Payment Split',
            fields: [
                {
                    category: 'input',
                    label: 'Customer',
                    value: data.customer.customer_name + ' | ' + data.customer.city.short_title,
                    disabled: true,
                },
                {
                    category: 'input',
                    label: 'Method',
                    name: 'method',
                    value: data.method,
                    readonly: true,
                },
                {
                    category: 'input',
                    label: 'Amount',
                    value: formatNumbersWithDigits(data.amount, 1, 1),
                    disabled: true,
                },
                {
                    category: 'input',
                    label: 'Reff. No.',
                    name: 'reff_no',
                    value: currentRef,
                    readonly: true,
                },
                {
                    category: 'input',
                    label: 'New Reff. No.',
                    name: 'new_reff_no',
                    value: newRef,
                    readonly: true,
                },
                {
                    category: 'input',
                    label: 'Split Amount',
                    name: 'split_amount',
                    id: 'split_amount',
                    type: 'amount',
                    data_validate: "required|amount",
                    placeholder: 'Enter split amount',
                    oninput: `validateSplitAmount(this, ${data.amount - 1})`,
                    required: true,
                },
            ],
            fieldsGridCount: '2',
            bottomActions: [
                {id: 'split-payment-btn', text: 'Split Payment', type: 'submit'}
            ]
        }

        createModal(modalData);
    }

    window.validateSplitAmount = function(input, maxAmount) {
        if (parseFloat(input.value) > parseFloat(maxAmount)) {
            input.value = maxAmount;
        }
    }

    window.trackMethodState = function(select) {
        let form = select.closest('form');
        let bankAccountInpDom = form.querySelector('input[id="bank_account_id"]');
        let reffNoDom = form.querySelector('input[id="reff_no"]');

        if (select.value === 'online') {
            bankAccountInpDom.disabled = false;
            bankAccountInpDom.value = '';
            bankAccountInpDom.placeholder = '-- Select Bank Account --';
            reffNoDom.disabled = false;
        } else {
            bankAccountInpDom.disabled = true;
            bankAccountInpDom.value = '';
            bankAccountInpDom.placeholder = '-- No Options Available --';

            reffNoDom.disabled = true;
            reffNoDom.value = '';
        }
    }

    let balanceDom = document.querySelector('#calc-bottom >.balance .text-right');
    let infoDom = document.getElementById('info')?.querySelector('span') || null;
    let allDataArray = window.allDataArray || [];

    window.onFilter = function() {
        const visibleRows = window.visibleData || [];
        totalAmount = visibleRows.reduce((sum, d) => sum + d.data.amount, 0);
        totalPayment = visibleRows.reduce((sum, d) => sum + d.data.clear_amount, 0);

        if (infoDom) {
            infoDom.textContent = `Showing ${visibleRows.length} of ${allDataArray.length} payments.`;
        }

        totalAmountDom.innerText = formatNumbersWithDigits(totalAmount, 1, 1);
        totalPaymentDom.innerText = formatNumbersWithDigits(totalPayment, 1, 1);
        balanceDom.innerText = formatNumbersWithDigits(totalAmount - totalPayment, 1, 1);
    }

    document.addEventListener('app:data:rendered', (event) => {
        allDataArray = event.detail?.items || window.allDataArray || [];
    });

    window.__cpHelpers = { createRow, renderCalculation };
}

window.initCustomerPaymentsIndex = initCustomerPaymentsIndex;

function boot() {
    if (window.__customerPaymentsIndex) initCustomerPaymentsIndex();
}

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", boot);
} else {
    boot();
}
})();
