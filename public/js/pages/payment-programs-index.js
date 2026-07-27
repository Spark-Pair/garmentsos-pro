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

    function getCategoryData(value) {
        const subCategorySearchInput = document.getElementById('subCategory');
        const subCategoryHiddenInput = document.querySelector('input.dbInput[data-for="subCategory"]');
        const subCategoryOptionBox = subCategoryHiddenInput?.parentElement.querySelector('ul');
        const subCategoryWrapper = subCategorySearchInput?.closest('.form-group')?.parentElement?.closest('.form-group').parentElement.parentElement;
        const subCategoryLabel = subCategoryWrapper?.querySelector('label');
        const remarksInputDom = document.getElementById('remarks');

        if (!subCategorySearchInput || !subCategoryHiddenInput || !subCategoryOptionBox || !subCategoryWrapper) return;

        if (value !== "waiting") {
            subCategoryWrapper.classList.remove("hidden");
            remarksInputDom.parentElement.parentElement.classList.add("hidden");

            $.ajax({
                url: "/get-category-data",
                type: "POST",
                data: {
                    _token: config.csrfToken,
                    category: value,
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
            remarksInputDom.parentElement.parentElement.classList.remove("hidden");
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
                    disabled: true,
                },
                {
                    category: 'input',
                    label: 'Remarks',
                    name: 'remarks',
                    id: 'remarks',
                    hidden: true,
                    placeholder: 'Enter remarks here',
                },
                {
                    category: 'input',
                    label: 'Amount',
                    type: 'amount',
                    data_validate: 'required|amount',
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

            categoryHidden?.setAttribute('disabled', 'disabled');
            subCategoryHidden?.setAttribute('disabled', 'disabled');
        }

        document.getElementById('updateProgramModalForm').addEventListener('submit', function(e) {
            e.preventDefault();
            formatAmountInput(this.querySelector('#amount'));
            this.submit();
        });
    }

    window.printDetails = function(elem) {
        closeAllDropdowns();

        if (elem.parentElement.tagName.toLowerCase() === 'li') {
            elem.parentElement.parentElement.querySelector('#show-details').click();
            document.getElementById('modalForm').parentElement.classList.add('hidden');
        }

        const preview = document.getElementById('modelInner');

        let oldIframe = document.getElementById('printIframe');
        if (oldIframe) oldIframe.remove();

        let printIframe = document.createElement('iframe');
        printIframe.id = "printIframe";
        printIframe.style.position = "absolute";
        printIframe.style.width = "0px";
        printIframe.style.height = "0px";
        printIframe.style.border = "none";
        printIframe.style.display = "none";
        document.body.appendChild(printIframe);

        let printDocument = printIframe.contentDocument || printIframe.contentWindow.document;
        printDocument.open();

        const headContent = document.head.innerHTML;

        printDocument.write(`
            <html>
                <head>
                    <title>Print Program Details</title>
                    ${headContent}
                    <style>
                        @media print {
                            @page {
                                size: A4;
                                margin: 0.31in, 0.31in, 0.31in, 0.31in;
                            }
                            body, body * {
                                color: #000000 !important;
                            }
                            #table-head, #calc-bottom .final {
                                background-color: transparent !important;
                                border: 1px solid #6b7280 !important;
                                page-break-inside: avoid;
                            }
                            #table-body > div {
                                page-break-inside: avoid;
                                page-break-after: auto;
                            }
                        }
                    </style>
                </head>
                <body>
                    ${preview.innerHTML}
                </body>
            </html>
        `);

        printDocument.close();

        printIframe.onload = () => {
            setTimeout(() => {
                printIframe.contentWindow.focus();
                printIframe.contentWindow.print();
            }, 500);

            document.getElementById('modalForm').parentElement.remove();
        };
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

        createContextMenu(contextMenuData);
    }

    window.generateModal = function(item) {
        let data = JSON.parse(item.dataset.json);
        let tableBody = [];
        let totalAmount = 0;

        const sourceArray = Array.isArray(data.data.payments)
            ? data.data.payments
            : Array.isArray(data.data.payment_programs)
            ? data.data.payment_programs
            : [];

        tableBody = sourceArray.map((item, index) => {
            totalAmount += item.amount;
            return [
                {data: index+1, class: 'w-[5%]'},
                {data: formatDate(item.date), class: 'w-1/4'},
                {data: (item.bank_account?.sub_category?.supplier_name ?? item.bank_account?.sub_category?.customer_name ?? 'Self Account'), class: 'w-1/3 capitalize'},
                {data: (item.bank_account?.account_title ?? '-') + ' | ' + (item.bank_account?.bank?.short_title ?? '-'), class: 'w-1/3 capitalize'},
                {data: formatNumbersWithDigits(item.amount, 1, 1), class: 'w-1/6'},
                {data: item.transaction_id, class: 'w-[10%] capitalize'},
            ];
        });

        let modalData = {
            id: 'modalForm',
            class: 'max-w-4xl h-[37rem]',
            name: `Payment Details - ${data.customer_name} | Customer Bal. ${formatNumbersWithDigits(data.customer_balance, 1, 1)} | Order Bal. ${formatNumbersWithDigits(data.order_balance, 1, 1)}`,
            table: {
                name: 'Details',
                headers: [
                    { label: "#", class: "w-[5%]" },
                    { label: "Data", class: "w-1/4" },
                    { label: "Beneficiary", class: "w-1/3" },
                    { label: "Acc. Title", class: "w-1/3" },
                    { label: "Amount", class: "w-1/6" },
                    { label: "Reff. No.", class: "w-[10%]" },
                ],
                body: tableBody,
                scrollable: true,
            },
            calcBottom: [
                {label: 'Total Amount - Rs.', name: 'total', value: formatNumbersWithDigits(totalAmount, 1, 1), disabled: true},
            ],
            bottomActions: [
                {id: 'print', text: 'Print Details', onclick: 'printDetails(this)'}
            ]
        }

        if (data.status != 'Paid' && data.status != 'Overpaid') {
            modalData.bottomActions.push(
                {id: 'add-payment', text: 'Add Payment', onclick: `goToAddPayment(${JSON.stringify(data)})`},
                {id: 'update-program', text: 'Update Program', onclick: `generateUpdateProgramModal(${JSON.stringify(data)})`},
                {id: 'mark-paid', text: 'Mark as Paid', onclick: `goToMarkPaid(${JSON.stringify(data)})`},
            );
        }

        createModal(modalData);
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
