(function () {
    let selectedInvoicesArray = [];

    let lastCargo;
    let companyData;
    let invoices = [];

    const generateListBtn = document.getElementById('generateListBtn');
    const cargoListDOM = document.getElementById('cargo-list');
    const finalTotalCottonsDOM = document.getElementById('finalTotalCottons');

    let totalCottonCount = 0;

    window.trackStateOfgenerateBtn = function trackStateOfgenerateBtn(elem) {
        if (!generateListBtn) return;
        if (elem.value != '') {
            generateListBtn.disabled = false;
        } else {
            generateListBtn.disabled = true;
        }
    };

    if (generateListBtn) {
        generateListBtn.disabled = true;
        generateListBtn.addEventListener('click', () => {
            generateModal();
        });
    }

    window.generateModal = function generateModal() {
        const data = invoices || [];
        let cardData = [];

        if (data.length > 0) {
            cardData.push(
                ...data.map(item => {
                    return {
                        id: item.id,
                        name: item.invoice_no,
                        data: item,
                        checkbox: true,
                        checked: selectedInvoicesArray.some(selected => selected.id === item.id),
                        onclick: 'selectThisInvoice(this)',
                    };
                })
            );
        }

        const modalData = {
            id: 'modalForm',
            class: 'h-[80%] w-full',
            cards: { name: 'Invoices', count: 4, data: cardData },
        };

        createModal(modalData);
    };

    function deselectInvoiceAtIndex(index) {
        if (index !== -1) {
            selectedInvoicesArray.splice(index, 1);
        }
    }

    window.deselectThisInvoice = function deselectThisInvoice(index) {
        totalCottonCount -= selectedInvoicesArray[index].cotton_count;
        deselectInvoiceAtIndex(index);
        renderList();
    };

    function renderList() {
        if (!cargoListDOM || !finalTotalCottonsDOM) return;
        if (selectedInvoicesArray.length > 0) {
            let clutter = '';
            selectedInvoicesArray.forEach((selectedInvoice, index) => {
                clutter += `
                        <div class="flex justify-between items-center border-t border-gray-600 py-3 px-4">
                            <div class="w-[10%]">${index + 1}</div>
                            <div class="w-1/6">${formatDate(selectedInvoice.date)}</div>
                            <div class="w-1/6">${selectedInvoice.invoice_no}</div>
                            <div class="w-1/6">${selectedInvoice.cotton_count ?? '-'}</div>
                            <div class="grow">${selectedInvoice.customer.customer_name}</div>
                            <div class="w-[10%]">${selectedInvoice.customer.city.title}</div>
                            <div class="w-[10%] text-center">
                                <button onclick="deselectThisInvoice(${index})" type="button" class="text-[var(--danger-color)] cursor-pointer text-xs px-2 py-1 rounded-lg hover:text-[var(--h-danger-color)] transition-all duration-300 ease-in-out">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    `;
            });

            cargoListDOM.innerHTML = clutter;
        } else {
            cargoListDOM.innerHTML =
                '<div class="text-center bg-[var(--h-bg-color)] rounded-lg py-2 px-4">No Invoices Yet</div>';
        }
        finalTotalCottonsDOM.textContent = totalCottonCount;
        updateInputinvoicesArray();
    }

    function updateInputinvoicesArray() {
        const inputinvoices = document.getElementById('invoices');
        const finalArticlesArray = selectedInvoicesArray.map(invoice => {
            return {
                id: invoice.id,
                description: invoice.description,
                shipment_quantity: invoice.shipmentQuantity,
            };
        });
        if (inputinvoices) {
            inputinvoices.value = JSON.stringify(finalArticlesArray);
        }
    }

    const previewContainer = document.getElementById('preview-container');

    function chunkCargoRows(rows) {
        const source = Array.isArray(rows) ? rows : [];
        const chunks = [];
        const rowsPerPage = 24;

        for (let index = 0; index < source.length; index += rowsPerPage) {
            chunks.push(source.slice(index, index + rowsPerPage));
        }

        return chunks.length ? chunks : [[]];
    }

    window.generateCargoListPreview = function generateCargoListPreview() {
        const cargoNo = (parseInt(lastCargo.cargo_no) + 1).toString().padStart(4, '0');
        const cargoNameInpDom = document.getElementById('cargo_name');
        const dateInpDom = document.getElementById('date');

        if (!previewContainer) return;
        if (selectedInvoicesArray.length > 0) {
            const pages = chunkCargoRows(selectedInvoicesArray);
            let serial = 1;

            previewContainer.className = 'h-auto mx-auto relative flex flex-col';
            previewContainer.innerHTML = pages.map((pageInvoices, pageIndex) => {
                const bodyRows = pageInvoices.map((invoice, index) => {
                    const hrClass = index === 0 ? 'mb-2.5' : 'my-2.5';
                    return `
                        <div>
                            <hr class="w-full ${hrClass} border-gray-600">
                            <div class="tr flex justify-between w-full px-2 gap-2">
                                <div class="td text-sm font-semibold w-[6%]">${serial++}.</div>
                                <div class="td text-sm font-semibold w-[18%]">${formatDate(invoice.date)}</div>
                                <div class="td text-sm font-semibold w-[19%]">${invoice.invoice_no}</div>
                                <div class="td text-sm font-semibold w-[10%]">${invoice.cotton_count}</div>
                                <div class="td text-sm font-semibold grow">${invoice.customer.customer_name}</div>
                                <div class="td text-sm font-semibold w-[14%]">${invoice.customer.city.title}</div>
                            </div>
                        </div>
                    `;
                }).join('');

                return `
                    <div id="preview" class="preview cargo-list-preview w-[148mm] h-[210mm] gos-a5-document gos-a5-invoice overflow-hidden flex flex-col">
                    <div id="preview-document" class="preview-document cargo-list-document flex flex-col h-full">
                        <div id="preview-banner" class="banner w-full flex justify-between items-center px-5">
                            <div class="left">
                                <div class="logo flex flex-col">
                                    <img src="${window.__cargosGenerate.companyLogoBase}/${companyData.logo}" alt="garmentsos-pro"
                                        class="w-[12rem]" />
                                    <div class="mt-2 text-sm text-gray-600">${companyData.phone_number}</div>
                                </div>
                            </div>
                            <div class="right">
                                <div class="logo text-right">
                                    <h1 class="text-2xl font-medium text-[var(--h-primary-color)]">Cargo List</h1>
                                    <div class="document-number mt-1 text-right">Cargo List No.: ${cargoNo}</div>
                                </div>
                            </div>
                        </div>
                        <hr class="w-full my-3 border-black">
                        <div id="preview-header" class="header w-full flex justify-between px-5">
                            <div class="left grow min-w-0 pr-3 space-y-1">
                                <div class="cargo-name capitalize font-semibold text-md leading-none">Cargo Name: ${cargoNameInpDom.value}</div>
                                <div class="cargo-date leading-none">Date: ${dateInpDom.value}</div>
                                <input type="hidden" name="cargo_no" value="${cargoNo}" />
                            </div>
                            <div class="right shrink-0 min-w-[32%] my-auto text-right text-sm text-black space-y-1.5">
                                <div class="preview-copy leading-none">Cargo List Copy: Cargo</div>
                                <div class="preview-doc leading-none">Document: Cargo List</div>
                            </div>
                        </div>
                        <hr class="w-full my-3 border-black">
                        <div id="preview-body" class="body w-full px-5 grow mx-auto">
                            <div class="preview-table w-full">
                                <div class="table w-full border border-black rounded-lg pb-2.5 overflow-hidden">
                                    <div class="thead w-full">
                                        <div class="tr flex justify-between w-full px-2 py-1.5 bg-[var(--primary-color)] text-white gap-2">
                                            <div class="th text-sm font-medium w-[6%]">S.No</div>
                                            <div class="th text-sm font-medium w-[18%]">Date</div>
                                            <div class="th text-sm font-medium w-[19%]">Invoice No.</div>
                                            <div class="th text-sm font-medium w-[10%]">Cotton</div>
                                            <div class="th text-sm font-medium grow">Customer</div>
                                            <div class="th text-sm font-medium w-[14%]">City</div>
                                        </div>
                                    </div>
                                    <div id="tbody" class="tbody w-full">
                                        ${bodyRows}
                                    </div>
                                </div>
                            </div>
                        </div>
                        <hr class="w-full my-3 border-black">
                        <div class="tfooter flex w-full text-sm px-4 justify-between text-black">
                            <P class="leading-none">Powered by SparkPair</P>
                            ${pages.length > 1 ? `<p class="leading-none text-sm">Page ${pageIndex + 1} of ${pages.length}</p>` : ''}
                            <p class="leading-none text-sm">&copy; ${new Date().getFullYear()} SparkPair | +92 316 5825495</p>
                        </div>
                    </div>
                    </div>
                `;
            }).join('');
        } else {
            previewContainer.className = 'w-[148mm] h-[210mm] mx-auto overflow-hidden relative';
            previewContainer.innerHTML =
                '<div id="preview" class="preview cargo-list-preview w-[148mm] h-[210mm] gos-a5-document gos-a5-invoice overflow-hidden flex flex-col"><h1 class="text-[var(--border-error)] font-medium text-center mt-5">No Preview avalaible.</h1></div>';
        }
    };

    window.selectThisInvoice = function selectThisInvoice(invoiceElem) {
        const checkbox = invoiceElem.querySelector("input[type='checkbox']");
        checkbox.checked = !checkbox.checked;
        toggleInvoice(invoiceElem, checkbox);
    };

    function toggleInvoice(invoiceElem, checkbox) {
        if (checkbox.checked) {
            selectInvoice(invoiceElem);
        } else {
            deselectInvoice(invoiceElem);
        }
    }

    function selectInvoice(invoiceElem) {
        const invoiceData = JSON.parse(invoiceElem.dataset.json).data;

        const index = selectedInvoicesArray.findIndex(invoice => invoice.id === invoiceData.id);
        if (index == -1) {
            selectedInvoicesArray.push(invoiceData);
            totalCottonCount += invoiceData.cotton_count;
        }
        renderList();
    }

    function deselectInvoice(invoiceElem) {
        const invoiceData = JSON.parse(invoiceElem.dataset.json).data;

        const index = selectedInvoicesArray.findIndex(invoice => invoice.id === invoiceData.id);
        if (index > -1) {
            selectedInvoicesArray.splice(index, 1);
            totalCottonCount -= invoiceData.cotton_count;
        }
        renderList();
    }

    window.validateForNextStep = function validateForNextStep() {
        generateCargoListPreview();
        return true;
    };

    function addListenerToPrintAndSaveBtn() {
        const printAndSaveBtn = document.getElementById('printAndSaveBtn');
        if (!printAndSaveBtn) return;

        printAndSaveBtn.addEventListener('click', e => {
            e.preventDefault();
            closeAllDropdowns();

            if (typeof validateForNextStep === 'function' && validateForNextStep() === false) {
                return;
            }

            const preview = document.getElementById('preview-container');
            if (!preview) return;

            const oldIframe = document.getElementById('printIframe');
            if (oldIframe) {
                oldIframe.remove();
            }

            const printIframe = document.createElement('iframe');
            printIframe.id = 'printIframe';
            printIframe.style.position = 'absolute';
            printIframe.style.width = '0px';
            printIframe.style.height = '0px';
            printIframe.style.border = 'none';
            printIframe.style.display = 'none';

            document.body.appendChild(printIframe);

            const printDocument = printIframe.contentDocument || printIframe.contentWindow.document;
            printDocument.open();

            const headContent = document.head.innerHTML;

            printDocument.write(`
                    <html>
                        <head>
                            <title>Print Cargo List</title>
                            ${headContent}
                            <style>
                                @page {
                                    size: A5 portrait;
                                    margin: 3mm;
                                }

                                @media print {
                                    html,
                                    body {
                                        margin: 0;
                                        padding: 0;
                                        width: auto;
                                        min-height: 0;
                                    }

                                    #preview-container {
                                        width: auto !important;
                                        height: auto !important;
                                        max-height: none !important;
                                        overflow: visible !important;
                                    }

                                    .preview {
                                        width: 148mm !important;
                                        height: 210mm !important;
                                        max-width: 148mm !important;
                                        max-height: 210mm !important;
                                        overflow: hidden !important;
                                        break-after: page;
                                        page-break-after: always;
                                        page-break-inside: avoid;
                                    }

                                    .preview,
                                    .preview * {
                                        box-sizing: border-box;
                                    }

                                    .preview-document {
                                        display: flex !important;
                                        flex-direction: column !important;
                                        height: 100% !important;
                                        min-height: 0 !important;
                                    }

                                    .preview-body {
                                        flex: 1 1 auto !important;
                                        min-height: 0 !important;
                                    }

                                    .tfooter,
                                    .footer {
                                        break-inside: avoid;
                                        page-break-inside: avoid;
                                    }

                                    #preview-container .preview:last-child {
                                        break-after: auto;
                                        page-break-after: auto;
                                    }
                                }
                            </style>
                        </head>
                        <body>
                            <div id="preview-container" class="preview-container">${preview.innerHTML}</div>
                        </body>
                    </html>
                `);

            printDocument.close();

            printIframe.onload = () => {
                printDocument.querySelectorAll('.preview').forEach(p => p.classList.remove('py-6'));
                printDocument.querySelectorAll('#banner').forEach(p => p.classList.remove('mt-8'));
                printDocument.querySelectorAll('.footer').forEach(p => p.classList.remove('mb-4'));

                const listCopy = printDocument.querySelector('#preview-container .preview-copy');
                if (listCopy) {
                    listCopy.textContent = 'Cargo List Copy: Office';
                }

                printIframe.contentWindow.onafterprint = () => {
                    document.getElementById('form').submit();
                };

                setTimeout(() => {
                    printIframe.contentWindow.focus();
                    printIframe.contentWindow.print();
                }, 1000);
            };
        });
    }

    function initCargosGenerate(data) {
        lastCargo = data?.lastCargo || null;
        companyData = data?.companyData || null;
        invoices = data?.invoices || [];
        renderList();
        addListenerToPrintAndSaveBtn();
    }

    window.initCargosGenerate = initCargosGenerate;

    function boot() {
        if (window.__cargosGenerate) {
            initCargosGenerate(window.__cargosGenerate);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
