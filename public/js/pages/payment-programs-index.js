(() => {
function initPaymentProgramsIndex() {
    const config = window.__ppIndex || {};
    let authLayout = config.authLayout || 'table';
    let hasAppliedDefaultStatus = false;
    const previousClearAllSearchFields =
        typeof window.clearAllSearchFields === 'function' ? window.clearAllSearchFields : null;
    if (authLayout) {
        window.authLayout = authLayout;
    }
    let totalAmountDom = document.querySelector('#calc-bottom >.total-Amount .text-right');
    let totalPaymentDom = document.querySelector('#calc-bottom >.total-Payment .text-right');
    let totalBalanceDom = document.querySelector('#calc-bottom >.balance .text-right');

    function renderCalculation(data) {
        totalAmountDom.innerText = formatNumbersWithDigits(data?.total_amount ?? 0, 1, 1);
        totalPaymentDom.innerText = formatNumbersWithDigits(data?.total_payment ?? 0, 1, 1);
        totalBalanceDom.innerText = formatNumbersWithDigits(data?.balance ?? 0, 1, 1);
    }

    function createRow(data) {
        return `
        <div id="${data.id}" oncontextmenu='${htmlAttr(data.oncontextmenu || "")}' onclick='${htmlAttr(data.onclick || "")}'
            class="item row relative group flex items-center border-b border-[var(--h-bg-color)] items-center py-2 cursor-pointer hover:bg-[var(--h-secondary-bg-color)] transition-all fade-in ease-in-out"
            data-json='${jsonAttr(data)}'>

            <span class="w-[10%]">${(data.date)}</span>
            <span class="w-[8%]">${data.o_p_no}</span>
            <span class="w-[19%] text-left">${data.customer_name}</span>
            <span class="w-[10%]">${formatNumbersWithDigits(data.customer_balance, 1, 1)}</span>
            <span class="w-[9%] capitalize">${data.category.replace(/_/g, ' ')}</span>
            <span class="w-[15%]">${data.beneficiary}</span>
            <span class="w-[10%]">${formatNumbersWithDigits(data.amount, 1, 1)}</span>
            <span class="w-[10%]">${formatNumbersWithDigits(data.payment, 1, 1)}</span>
            <span class="w-[10%]">${formatNumbersWithDigits(data.balance, 1, 1)}</span>
            <span class="w-[10%]">${data.status}</span>
        </div>`;
    }

    const fetchedData = [];
    let selectedSubCategoryId;
    let lastPaymentProgramDetails = null;

    const cleanPaymentText = (value, fallback = '-') => {
        if (value === null || value === undefined) return fallback;
        const text = String(value).trim();
        return text === '' || text.toLowerCase() === 'null' ? fallback : text;
    };

    const escapePaymentText = (value, fallback = '-') => {
        const div = document.createElement('div');
        div.textContent = cleanPaymentText(value, fallback);
        return div.innerHTML;
    };

    const paymentPartyText = (payment) => cleanPaymentText(
        payment.bank_account?.sub_category?.supplier_name
        ?? payment.bank_account?.sub_category?.customer_name
        ?? payment.sub_category?.supplier_name
        ?? payment.sub_category?.customer_name
        ?? '-'
    );

    const paymentAccountText = (payment) => {
        const title = cleanPaymentText(payment.bank_account?.account_title);
        const bank = cleanPaymentText(payment.bank_account?.bank?.short_title);
        return title === '-' && bank === '-' ? '-' : `${title} | ${bank}`;
    };

    const paymentReferenceLines = (payment) => {
        const lines = [];
        const transactionId = cleanPaymentText(payment.transaction_id, '');
        const chequeNo = cleanPaymentText(payment.cheque_no, '');
        const slipNo = cleanPaymentText(payment.slip_no, '');
        const referenceNo = cleanPaymentText(payment.reff_no ?? payment.reference_no, '');
        const clearDate = cleanPaymentText(payment.clear_date, '');
        const remarks = cleanPaymentText(payment.remarks, '');
        const primaryReference = chequeNo || slipNo || transactionId || referenceNo;
        const primaryLabel = chequeNo ? 'Cheque' : slipNo ? 'Slip' : 'Ref';

        if (primaryReference) lines.push(`${primaryLabel}: ${primaryReference}`);
        if (clearDate) lines.push(`Clear: ${formatDate(clearDate)}`);
        if (remarks) lines.push(remarks);

        return lines.length ? lines : ['-'];
    };

    const paymentReferenceHtml = (payment) => paymentReferenceLines(payment)
        .map((line) => escapePaymentText(line))
        .join('<br>');

    function paymentProgramDetailRows(data) {
        const sourceArray = Array.isArray(data?.data?.payments)
            ? data.data.payments
            : Array.isArray(data?.data?.payment_programs)
            ? data.data.payment_programs
            : [];

        return sourceArray.map((payment, index) => ({
            no: index + 1,
            date: formatDate(payment.date),
            method: cleanPaymentText(payment.method),
            beneficiary: paymentPartyText(payment),
            account: paymentAccountText(payment),
            amount: Number(payment.amount || 0),
            referenceLines: paymentReferenceLines(payment),
        }));
    }

    function buildPaymentProgramPrintData(data) {
        const rows = paymentProgramDetailRows(data);
        const receivedTotal = rows.reduce((sum, row) => sum + row.amount, 0);

        return {
            title: 'Payment Details',
            customerName: cleanPaymentText(data?.customer_name),
            customerBalance: formatNumbersWithDigits(data?.customer_balance ?? 0, 1, 1),
            orderBalance: formatNumbersWithDigits(data?.order_balance ?? 0, 1, 1),
            programAmount: formatNumbersWithDigits(data?.amount ?? 0, 1, 1),
            receivedTotal: formatNumbersWithDigits(receivedTotal, 1, 1),
            rows,
        };
    }

    function renderPaymentDetailsPrintHtml(printData) {
        const rows = printData.rows.length
            ? printData.rows.map((row) => `
                <div class="pp-row">
                    <div class="pp-cell pp-no">${row.no}</div>
                    <div class="pp-cell pp-date">${escapePaymentText(row.date)}</div>
                    <div class="pp-cell pp-method">${escapePaymentText(row.method)}</div>
                    <div class="pp-cell pp-beneficiary">${escapePaymentText(row.beneficiary)}</div>
                    <div class="pp-cell pp-account">${escapePaymentText(row.account)}</div>
                    <div class="pp-cell pp-amount">${escapePaymentText(formatNumbersWithDigits(row.amount, 1, 1))}</div>
                    <div class="pp-cell pp-reference">${row.referenceLines.map((line) => escapePaymentText(line)).join('<br>')}</div>
                </div>
            `).join('')
            : '<div class="pp-empty">No payment details available.</div>';

        return `
            <html>
                <head>
                    <title>${escapePaymentText(printData.title)}</title>
                    <style>
                        @page { size: A4; margin: 3mm; }
                        * { box-sizing: border-box; }
                        body {
                            margin: 0;
                            padding: 0;
                            background: #fff;
                            color: #000;
                            font-family: Arial, Helvetica, sans-serif;
                            font-size: 11px;
                        }
                        .pp-page {
                            width: 210mm;
                            max-width: 100%;
                            margin: 0 auto;
                            padding: 6px 0;
                        }
                        .pp-branch {
                            width: 95%;
                            margin: 0 auto 8px;
                            border: 1px solid #111;
                            border-radius: 8px;
                            padding: 4px 12px;
                            line-height: 1.2;
                            font-weight: 600;
                            font-size: 10.5px;
                        }
                        .pp-slip {
                            width: 95%;
                            margin: 0 auto 10px;
                            border: 1px solid #111;
                            border-radius: 10px;
                            padding: 6px;
                            overflow: hidden;
                            break-inside: avoid;
                            page-break-inside: avoid;
                        }
                        .pp-title {
                            border: 1px solid #111;
                            border-radius: 8px;
                            padding: 5px 12px;
                            margin-bottom: 5px;
                            text-align: center;
                            font-weight: 700;
                            line-height: 1.2;
                            font-size: 11px;
                        }
                        .pp-table {
                            width: 100%;
                        }
                        .pp-head,
                        .pp-row {
                            display: flex;
                            align-items: stretch;
                            width: 100%;
                        }
                        .pp-head {
                            background: #555;
                            color: #fff;
                            font-weight: 700;
                            text-align: center;
                            border-radius: 7px;
                            overflow: hidden;
                        }
                        .pp-row {
                            border-bottom: 1px solid #222;
                            break-inside: avoid;
                            page-break-inside: avoid;
                        }
                        .pp-row:last-child { border-bottom: 0; }
                        .pp-cell {
                            padding: 3px 5px;
                            line-height: 1.12;
                            min-width: 0;
                            overflow-wrap: anywhere;
                        }
                        .pp-no { width: 4%; text-align: center; }
                        .pp-date { width: 15%; text-align: center; white-space: nowrap; }
                        .pp-method { width: 10%; text-align: center; font-weight: 700; text-transform: capitalize; }
                        .pp-beneficiary { width: 16%; text-align: left; }
                        .pp-account { width: 23%; text-align: left; }
                        .pp-amount { width: 12%; text-align: right; font-weight: 700; font-variant-numeric: tabular-nums; }
                        .pp-reference { width: 20%; text-align: left; overflow-wrap: normal; word-break: normal; }
                        .pp-empty {
                            padding: 28px 10px;
                            text-align: center;
                            color: #444;
                            border-bottom: 1px solid #222;
                        }
                        .pp-totals {
                            display: grid;
                            grid-template-columns: repeat(2, 1fr);
                            gap: 6px;
                            border-top: 1px solid #111;
                            margin-top: 5px;
                            padding-top: 5px;
                        }
                        .pp-total {
                            border: 1px solid #111;
                            border-radius: 8px;
                            padding: 5px 8px;
                            display: flex;
                            align-items: center;
                            justify-content: space-between;
                            gap: 8px;
                            text-align: left;
                            font-weight: 700;
                            line-height: 1.15;
                            font-size: 10.5px;
                            white-space: nowrap;
                        }
                        .pp-total span {
                            font-variant-numeric: tabular-nums;
                        }
                        @media print {
                            .pp-slip {
                                page-break-inside: avoid;
                                break-inside: avoid;
                            }
                        }
                    </style>
                </head>
                <body>
                    <main class="pp-page">
                        <div class="pp-branch">Program Payment Details</div>
                        <section class="pp-slip">
                            <div class="pp-title">${escapePaymentText(printData.customerName)}</div>
                            <div class="pp-table">
                                <div class="pp-head">
                                    <div class="pp-cell pp-no">#</div>
                                    <div class="pp-cell pp-date">Date</div>
                                    <div class="pp-cell pp-method">Method</div>
                                    <div class="pp-cell pp-beneficiary">Beneficiary</div>
                                    <div class="pp-cell pp-account">Account</div>
                                    <div class="pp-cell pp-amount">Amount</div>
                                    <div class="pp-cell pp-reference">Reference</div>
                                </div>
                                ${rows}
                            </div>
                            <section class="pp-totals">
                                <div class="pp-total"><span>Customer Bal.</span><span>${escapePaymentText(printData.customerBalance)}</span></div>
                                <div class="pp-total"><span>Order Bal.</span><span>${escapePaymentText(printData.orderBalance)}</span></div>
                                <div class="pp-total"><span>Program Amount</span><span>${escapePaymentText(printData.programAmount)}</span></div>
                                <div class="pp-total"><span>Received Total</span><span>${escapePaymentText(printData.receivedTotal)}</span></div>
                            </section>
                        </section>
                    </main>
                </body>
            </html>
        `;
    }

    function getCategoryData(value) {
        const subCategorySearchInput = document.getElementById('subCategory');
        const subCategoryHiddenInput = document.querySelector('input.dbInput[data-for="subCategory"]');
        const subCategoryOptionBox = subCategoryHiddenInput?.parentElement.querySelector('ul');
        const subCategoryWrapper = subCategorySearchInput?.closest('.form-group')?.parentElement?.closest('.form-group').parentElement.parentElement;
        const subCategoryLabel = subCategoryWrapper?.querySelector('label');
        const remarksInputDom = document.getElementById('remarks');
        const remarksWrapper = remarksInputDom?.parentElement?.parentElement;

        if (!subCategorySearchInput || !subCategoryHiddenInput || !subCategoryOptionBox || !subCategoryWrapper) return;
        remarksWrapper?.classList.remove("hidden");

        if (value !== "waiting") {
            subCategoryWrapper.classList.remove("hidden");

            $.ajax({
                url: "/get-category-data",
                type: "POST",
                data: {
                    _token: config.csrfToken,
                    category: value,
                    module_key: 'payment_programs',
                },
                success: function (response) {
                    let items = [];

                    switch (value) {
                        case 'self_account':
                            subCategoryLabel.textContent = 'Self Account';
                            if (response.length > 0) {
                                items.push(`<li data-for="subCategory" data-value="" onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg hover:bg-[var(--h-bg-color)]">-- Select Self Account --</li>`);
                                response.forEach(acc => {
                                    items.push(`<li data-for="subCategory" data-value="${acc.id}" onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg hover:bg-[var(--h-bg-color)]">${acc.account_title} | ${acc.bank.short_title}</li>`);
                                });
                                subCategorySearchInput.disabled = false;
                            } else {
                                items.push(`<li class="py-2 px-3 text-gray-400">-- No options available --</li>`);
                                subCategorySearchInput.disabled = true;
                            }
                            break;

                        case 'supplier':
                            subCategoryLabel.textContent = 'Supplier';
                            if (response.length > 0) {
                                items.push(`<li data-for="subCategory" data-value="" onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg hover:bg-[var(--h-bg-color)]">-- Select Supplier --</li>`);
                                response.forEach(sup => {
                                    items.push(`<li data-for="subCategory" data-value="${sup.id}" onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg hover:bg-[var(--h-bg-color)]">${sup.supplier_name} | Balance: ${sup.balance_formatted || formatNumbersWithDigits(sup.balance, 1, 1)}</li>`);
                                });
                                subCategorySearchInput.disabled = false;
                            } else {
                                items.push(`<li class="py-2 px-3 text-gray-400">-- No options available --</li>`);
                                subCategorySearchInput.disabled = true;
                            }
                            break;

                        case 'customer':
                            subCategoryLabel.textContent = 'Customer';
                            items.push(`<li data-for="subCategory" data-value="" onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg hover:bg-[var(--h-bg-color)]">-- Select Customer --</li>`);
                            response.forEach(cus => {
                                items.push(`<li data-for="subCategory" data-value="${cus.id}" onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg hover:bg-[var(--h-bg-color)]">${cus.customer_name} | ${cus.city.title ?? cus.city} | Balance: ${cus.balance_formatted || formatNumbersWithDigits(cus.balance, 1, 1)}</li>`);
                            });
                            subCategorySearchInput.disabled = false;
                            break;
                    }

                    subCategoryOptionBox.innerHTML = items.join('');

                    if (selectedSubCategoryId) {
                        const selectedLi = subCategoryOptionBox.querySelector(`li[data-value="${selectedSubCategoryId}"]`);
                        if (selectedLi) {
                            selectThisOption(selectedLi);
                        } else {
                            subCategorySearchInput.value = '';
                            subCategoryHiddenInput.value = '';
                        }
                    } else {
                        subCategorySearchInput.value = '';
                        subCategoryHiddenInput.value = '';
                    }

                    // Lock category/subcategory if payment already exists
                    if (window.__lockCategoryFields) {
                        subCategorySearchInput.disabled = true;
                        subCategoryHiddenInput.disabled = true;
                    }
                },
                error: function (xhr) {
                    console.error("❌ Error:", xhr.responseText);
                    subCategoryOptionBox.innerHTML = `<li class="py-2 px-3 text-red-500">Error loading options</li>`;
                    subCategorySearchInput.disabled = true;
                }
            });
        } else {
            subCategoryWrapper.classList.add("hidden");
        }
    }

    window.trackCategoryState = function(elem) {
        if (elem.value !== "") {
            getCategoryData(elem.value);
        }
    }

    window.generateUpdateProgramModal = function(item) {
        let modalData = {
            id: 'updateProgramModalForm',
            class: 'h-auto',
            method: 'POST',
            action: config.routes?.updateProgram,
            name: 'Update Program',
            fields: [
                {
                    category: 'input',
                    label: 'Date',
                    id: 'date',
                    value: item.date,
                    disabled: true,
                },
                {
                    category: 'input',
                    label: 'Customer',
                    name: 'customer_id',
                    id: 'customer_id',
                    value: item.customer_name,
                    disabled: true,
                },
                {
                    category: 'explicitHtml',
                    html: config.categorySelectHtml,
                },
                {
                    category: 'explicitHtml',
                    html: config.subCategorySelectHtml,
                },
                {
                    category: 'input',
                    type: 'hidden',
                    name: 'program_id',
                    value: item.id,
                },
                {
                    category: 'input',
                    label: 'Remarks',
                    name: 'remarks',
                    id: 'remarks',
                    placeholder: 'Enter remarks here',
                },
                {
                    category: 'input',
                    label: 'Amount',
                    type: 'amount',
                    data_validate: 'amount',
                    name: 'amount',
                    id: 'amount',
                    value: item.amount,
                    placeholder: 'Enter amount here',
                    full: true,
                },
            ],
            fieldsGridCount: '2',
            bottomActions: [
                {id: 'update', text: 'Update Program', type: 'submit'}
            ]
        }

        createModal(modalData);

        const canEditCategory = Number(item.payment || 0) === 0;
        window.__lockCategoryFields = !canEditCategory;
        const form = document.getElementById('updateProgramModalForm');
        const li = form.querySelector(`.optionsDropdown[data-for="category"] li[data-value="${item.category}"]`);
        if (li) {
            selectThisOption(li);
        }

        selectedSubCategoryId = item.sub_category?.id || item.data.sub_category_id || item.sub_category || '';
        if (item.category) {
            getCategoryData(item.category);
        }

        const categoryInput = form.querySelector('#category');
        const subCategoryInput = form.querySelector('#subCategory');

        const categoryHidden = form.querySelector('input.dbInput[data-for="category"]');
        const subCategoryHidden = form.querySelector('input.dbInput[data-for="subCategory"]');

        if (!canEditCategory) {
            categoryInput?.setAttribute('disabled', 'disabled');
            subCategoryInput?.setAttribute('disabled', 'disabled');
        }
    }

    window.printDetails = function(elem) {
        closeAllDropdowns();

        if (elem?.parentElement?.tagName?.toLowerCase() === 'li') {
            const contextData = window.__lastPaymentProgramContextData;
            if (contextData) {
                lastPaymentProgramDetails = contextData;
            }
        }

        if (!lastPaymentProgramDetails) {
            const modalJson = document.getElementById('modalForm')?.dataset?.paymentProgramDetails;
            if (modalJson) {
                lastPaymentProgramDetails = JSON.parse(modalJson);
            }
        }

        if (!lastPaymentProgramDetails) {
            console.error('Payment details are not available for printing.');
            return;
        }

        window.DocumentPrint.printDocumentHtml({
            html: renderPaymentDetailsPrintHtml(buildPaymentProgramPrintData(lastPaymentProgramDetails)),
            delay: 500,
        });
    }

    window.goToAddPayment = function(program) {
        const url = new URL(config.routes?.customerPaymentsCreate, window.location.origin);
        url.searchParams.set("program_id", program.payment_programs?.id ?? program.id);
        window.location.href = url.toString();
    }

    window.goToMarkPaid = function(program) {
        const actionUrl = config.routes?.markPaid.replace(':id', program.id);

        const form = document.createElement('form');
        form.method = 'POST';
        form.action = actionUrl;
        form.style.display = 'none';

        const csrfField = document.createElement('input');
        csrfField.type = 'hidden';
        csrfField.name = '_token';
        csrfField.value = config.csrfToken;

        form.appendChild(csrfField);
        document.body.appendChild(form);
        form.submit();
    }

    window.generateContextMenu = function(e) {
        e.preventDefault();
        let item = e.target.closest('.item');
        let data = JSON.parse(item.dataset.json);
        window.__lastPaymentProgramContextData = data;
        lastPaymentProgramDetails = data;

        let contextMenuData = {
            item: item,
            data: data,
            x: e.pageX,
            y: e.pageY,
            actions: [
                {id: 'print', text: 'Print Details', onclick: 'printDetails(this)'}
            ]
        };

        if (data.status != 'Paid' && data.status != 'Overpaid') {
            contextMenuData.actions.push(
                {id: 'add-payment', text: 'Add Payment', onclick: `goToAddPayment(${JSON.stringify(data)})`},
                {id: 'update-program', text: 'Update Program', onclick: `generateUpdateProgramModal(${JSON.stringify(data)})`},
                {id: 'mark-paid', text: 'Mark as Paid', onclick: `goToMarkPaid(${JSON.stringify(data)})`},
            );
        }

        if (isDeveloperUser()) {
            contextMenuData.actions.push({
                id: 'edit-payment-program',
                text: 'Edit',
                link: `/payment-programs/${data.id}/edit`,
            });
            contextMenuData.actions.push({
                id: 'delete-payment-program',
                text: 'Delete',
                onclick: `submitResourceDelete('/payment-programs/${data.id}')`,
            });
        }

        createContextMenu(contextMenuData);
    }

    window.generateModal = function(item) {
        let data = JSON.parse(item.dataset.json);
        window.__lastPaymentProgramContextData = data;
        lastPaymentProgramDetails = data;
        let tableBody = [];
        let totalAmount = 0;

        const sourceArray = Array.isArray(data.data.payments)
            ? data.data.payments
            : Array.isArray(data.data.payment_programs)
            ? data.data.payment_programs
            : [];

        const cleanText = (value, fallback = '-') => {
            if (value === null || value === undefined) return fallback;
            const text = String(value).trim();
            return text === '' || text.toLowerCase() === 'null' ? fallback : text;
        };

        const escapeCell = (value, fallback = '-') => {
            const div = document.createElement('div');
            div.textContent = cleanText(value, fallback);
            return div.innerHTML;
        };

        const paymentParty = (payment) => escapeCell(
            payment.bank_account?.sub_category?.supplier_name
            ?? payment.bank_account?.sub_category?.customer_name
            ?? payment.sub_category?.supplier_name
            ?? payment.sub_category?.customer_name
            ?? '-'
        );

        const paymentAccount = (payment) => {
            const title = cleanText(payment.bank_account?.account_title);
            const bank = cleanText(payment.bank_account?.bank?.short_title);
            return title === '-' && bank === '-' ? '-' : `${escapeCell(title)} | ${escapeCell(bank)}`;
        };

        const paymentReference = (payment) => {
            const parts = [];
            const transactionId = cleanText(payment.transaction_id, '');
            const chequeNo = cleanText(payment.cheque_no, '');
            const slipNo = cleanText(payment.slip_no, '');
            const referenceNo = cleanText(payment.reff_no ?? payment.reference_no, '');
            const clearDate = cleanText(payment.clear_date, '');
            const remarks = cleanText(payment.remarks, '');
            const primaryReference = chequeNo || slipNo || transactionId || referenceNo;
            const primaryLabel = chequeNo ? 'Cheque' : slipNo ? 'Slip' : 'Ref';

            if (primaryReference) parts.push(`${primaryLabel}: ${escapeCell(primaryReference)}`);
            if (clearDate) parts.push(`Clear: ${escapeCell(formatDate(clearDate))}`);
            if (remarks) parts.push(escapeCell(remarks));

            return parts.length ? parts.join('<br>') : '-';
        };

        tableBody = sourceArray.map((item, index) => {
            totalAmount += Number(item.amount || 0);
            return [
                {data: index+1, class: 'w-[4%] text-center text-[var(--secondary-text)]'},
                {data: formatDate(item.date), class: 'w-[15%] whitespace-nowrap'},
                {data: escapeCell(item.method), class: 'w-[10%] capitalize font-semibold'},
                {data: paymentParty(item), class: 'w-[16%] capitalize text-left px-1'},
                {data: paymentAccount(item), class: 'w-[23%] capitalize text-left px-1 leading-5'},
                {data: formatNumbersWithDigits(item.amount, 1, 1), class: 'w-[12%] text-right font-semibold tabular-nums pr-2'},
                {data: paymentReference(item), class: 'w-[20%] text-left text-[var(--secondary-text)] leading-5 pl-1'},
            ];
        });

        let modalData = {
            id: 'modalForm',
            class: 'max-w-6xl h-[37rem]',
            name: `Payment Details - ${cleanText(data.customer_name)}`,
            table: {
                name: 'Details',
                headers: [
                    { label: "#", class: "w-[4%] text-center" },
                    { label: "Date", class: "w-[15%]" },
                    { label: "Method", class: "w-[10%]" },
                    { label: "Beneficiary", class: "w-[16%] text-left px-1" },
                    { label: "Account", class: "w-[23%] text-left px-1" },
                    { label: "Amount", class: "w-[12%] text-right pr-2" },
                    { label: "Reference / Details", class: "w-[20%] text-left pl-1" },
                ],
                body: tableBody,
                scrollable: true,
                headerPaddingClass: 'px-2',
                rowPaddingClass: 'px-2',
            },
            calcBottom: [
                {label: 'Customer Bal. - Rs.', name: 'customer_balance', value: formatNumbersWithDigits(data.customer_balance, 1, 1), disabled: true},
                {label: 'Order Bal. - Rs.', name: 'order_balance', value: formatNumbersWithDigits(data.order_balance, 1, 1), disabled: true},
                {label: 'Program Amount - Rs.', name: 'program_amount', value: formatNumbersWithDigits(data.amount, 1, 1), disabled: true},
                {label: 'Received Total - Rs.', name: 'total', value: formatNumbersWithDigits(totalAmount, 1, 1), disabled: true},
            ],
            calcBottomClass: 'grid grid-cols-4',
            bottomActions: [
                {id: 'print', text: 'Print', onclick: 'printDetails(this)'}
            ]
        }

        if (data.status != 'Paid' && data.status != 'Overpaid') {
            modalData.bottomActions.push(
                {id: 'add-payment', text: 'Add Payment', onclick: `goToAddPayment(${JSON.stringify(data)})`},
                {id: 'update-program', text: 'Update Program', onclick: `generateUpdateProgramModal(${JSON.stringify(data)})`},
                {id: 'mark-paid', text: 'Mark as Paid', onclick: `goToMarkPaid(${JSON.stringify(data)})`},
            );
        }

        if (isDeveloperUser()) {
            modalData.bottomActions.push({
                id: 'edit-payment-program',
                text: 'Edit',
                link: `/payment-programs/${data.id}/edit`,
            });
            modalData.bottomActions.push({
                id: 'delete-payment-program',
                text: 'Delete',
                onclick: `submitResourceDelete('/payment-programs/${data.id}')`,
            });
        }

        createModal(modalData);
        const modal = document.getElementById('modalForm');
        if (modal) {
            modal.dataset.paymentProgramDetails = JSON.stringify(data);
        }
    }

    window.createRow = createRow;
    window.renderCalculation = renderCalculation;
    window.__ppHelpers = { renderCalculation, createRow, fetchedData };

    window.clearAllSearchFields = function clearPaymentProgramFilters() {
        if (typeof previousClearAllSearchFields === 'function') {
            previousClearAllSearchFields();
        }

        const statusSelected = document.querySelector('ul[data-for="status"] li[data-value="Unpaid"]');
        if (statusSelected) {
            selectThisOption(statusSelected);
        }

        if (typeof window.applyFilters === 'function') {
            window.applyFilters();
        }
    };

    setTimeout(() => {
        const hasQueryFilters = window.location.search.length > 1;
        const statusInput = document.querySelector('input.dbInput[data-for="status"]');

        if (!hasQueryFilters && statusInput && statusInput.value === 'Unpaid' && !hasAppliedDefaultStatus) {
            hasAppliedDefaultStatus = true;
            if (typeof window.applyFilters === 'function') {
                window.applyFilters();
            }
        }
    }, 0);
}

window.initPaymentProgramsIndex = initPaymentProgramsIndex;

function boot() {
    if (window.__ppIndex) {
        initPaymentProgramsIndex();
    }
}

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", boot);
} else {
    boot();
}
})();
