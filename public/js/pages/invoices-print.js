(function () {
    let invoices = [];
    let companyData = null;
    let hasPrinted = false;

    const invoiceContainer = document.getElementById('invoice-container');

    function invoiceDetailLine(orderedArticle, article) {
        const description = String(orderedArticle?.description ?? '').trim();
        const fabricType = String(article?.fabric_type ?? orderedArticle?.fabric_type ?? orderedArticle?.article?.fabric_type ?? '').trim();
        const parts = [description, fabricType].filter((part, index, list) => (
            part && list.findIndex(item => item.toLowerCase() === part.toLowerCase()) === index
        ));

        return parts.length ? parts.join(' | ') : '';
    }

    function customerTitlePhoneLine(customer = {}) {
        const title = String(customer?.urdu_title ?? '').trim();
        const phone = String(customer?.phone_number ?? '').trim();
        return [title, phone].filter(Boolean).join(' | ');
    }

    function deliverToLine(previewData = {}) {
        const deliverTo = String(previewData?.deliver_to ?? previewData?.order?.deliver_to ?? '').trim();
        return deliverTo ? `Deliver To: ${deliverTo}` : '';
    }

    function previewText(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function truthySetting(value) {
        return value === true || value === 1 || value === '1' || value === 'true';
    }

    function invoiceDocumentSettings(previewData = {}) {
        return previewData.branch_branding || companyData || {};
    }

    function invoiceDocumentTotalsHtml(previewData, totalPackets, totalPcs, totalAmount, discountVal, discountAmount, netAmount) {
        const settings = invoiceDocumentSettings(previewData);
        if (truthySetting(settings.discount_disabled)) {
            const note = String(settings.document_note || '').trim();
            return `
                ${note ? `
                    <div class="total col-span-2 flex justify-center items-center border border-black rounded-lg py-1.5 px-4 w-full text-center font-semibold">
                        ${previewText(note)}
                    </div>
                ` : ''}
                <div class="total flex justify-between items-center border border-black rounded-lg py-1.5 px-4 w-full">
                    <div class="text-nowrap">Total Quantity</div>
                    <div class="w-1/4 text-right grow">${formatNumbersDigitLess(totalPackets)} | ${formatNumbersDigitLess(totalPcs)}</div>
                </div>
                <div class="total flex justify-between items-center border border-black rounded-lg py-1.5 px-4 w-full">
                    <div class="text-nowrap font-semibold">Net Amount</div>
                    <div class="w-1/4 text-right grow font-semibold">${formatNumbersWithDigits(totalAmount, 1, 1)}</div>
                </div>
            `;
        }

        return `
            <div class="total flex justify-between items-center border border-black rounded-lg py-1.5 px-4 w-full">
                <div class="text-nowrap">Total Quantity</div>
                <div class="w-1/4 text-right grow">${formatNumbersDigitLess(totalPackets)} | ${formatNumbersDigitLess(totalPcs)}</div>
            </div>
            <div class="total flex justify-between items-center border border-black rounded-lg py-1.5 px-4 w-full">
                <div class="text-nowrap">Gross Amount</div>
                <div class="w-1/4 text-right grow">${formatNumbersWithDigits(totalAmount, 1, 1)}</div>
            </div>
            <div class="total flex justify-between items-center border border-black rounded-lg py-1.5 px-4 w-full">
                <div class="text-nowrap">Discount ${discountVal}%</div>
                <div class="w-1/4 text-right grow">${formatNumbersWithDigits(discountAmount, 1, 1)}</div>
            </div>
            <div class="total flex justify-between items-center border border-black rounded-lg py-1.5 px-4 w-full">
                <div class="text-nowrap">Net Amount</div>
                <div class="w-1/4 text-right grow">${formatNumbersWithDigits(netAmount, 1, 1)}</div>
            </div>
        `;
    }

    function renderInvoices() {
        if (!invoiceContainer) return;
        invoiceContainer.classList.remove('hidden');
        invoiceContainer.innerHTML = '';

        if (!Array.isArray(invoices) || invoices.length === 0) {
            invoiceContainer.innerHTML = `<div class="text-center text-[var(--border-error)] mt-5">No invoices to print.</div>`;
            return;
        }

        const previewsHtml = invoices.flatMap(invoice => {
            const previewDom = document.createElement('div');
            previewDom.classList = 'invoice';

            const customerData = invoice.customer || {};
            const invoiceArticles = invoice.invoice_articles || [];
            const cottonCount = invoice.cotton_count || 0;
            const discount = invoice.discount ?? invoice.shipment?.discount ?? invoice.order?.discount ?? 0;
            let previewData = null;

            if (invoiceArticles.length > 0) {
                const normalizedCustomer = {
                    ...customerData,
                    city: typeof customerData?.city === 'string'
                        ? { title: customerData.city }
                        : (customerData?.city || { title: '' }),
                };

                previewData = {
                    customer: normalizedCustomer,
                    date: invoice.date,
                    invoice_no: invoice.invoice_no,
                    shipment_no: invoice.shipment_no || null,
                    order_no: invoice.order_no || null,
                    deliver_to: invoice.deliver_to || invoice.order?.deliver_to || '',
                    cotton_count: cottonCount,
                    discount: discount,
                    netAmount: invoice.net_amount ?? null,
                    invoice_articles: invoiceArticles,
                    branch_branding: invoice.branch_branding || null,
                };

                previewDom.innerHTML = buildInvoicePreviewLikeModal(previewData, 'Customer');
            }

            if (!previewData) return [];

            const customerCopy = previewDom.innerHTML;
            const officeCopy = buildInvoicePreviewLikeModal(previewData, 'Office');

            return [customerCopy, officeCopy].filter(Boolean);
        }).filter(Boolean);

        previewsHtml.forEach((html, index) => {
            const wrapper = document.createElement('div');
            wrapper.innerHTML = html;
            invoiceContainer.appendChild(wrapper);
            if (index < previewsHtml.length - 1) {
                const pageBreak = document.createElement('div');
                pageBreak.className = 'page-break';
                invoiceContainer.appendChild(pageBreak);
            }
        });

        if (!hasPrinted) {
            hasPrinted = true;
            setTimeout(() => {
                printUsingIframe(previewsHtml.join(''));
            }, 400);
        }
    }

    function initInvoicesPrint(data) {
        invoices = data?.invoices || [];
        companyData = data?.companyData || null;
        renderInvoices();
    }

    function buildInvoicePreviewLikeModal(previewData, copyLabel = 'Customer') {
        const previewCompany = previewData.branch_branding || companyData || {};
        const previewLogoUrl = previewCompany.logo_url || (previewCompany.logo ? `${window.__invoicesPrint.companyLogoBase}/${previewCompany.logo}` : '');
        const cotton = previewData.cotton_count || 0;
        const discountVal = Number(previewData.discount || 0);
        const articles = Array.isArray(previewData.invoice_articles)
            ? previewData.invoice_articles
            : [];

        let totalAmount = 0;
        let totalPcs = 0;
        let totalPackets = 0;

        const invoiceTableHeader = `
            <div class="th text-sm font-medium">S.#</div>
            <div class="th text-sm font-medium">Article</div>
            <div class="th text-sm font-medium">Description</div>
            <div class="th text-sm font-medium">Unit</div>
            <div class="th text-sm font-medium">Pkts</div>
            <div class="th text-sm font-medium">Pcs.</div>
            <div class="th text-sm font-medium">Rate</div>
            <div class="th text-sm font-medium">Amt.</div>
        `;

        const invoiceTableBody = `
            ${articles.map((orderedArticle, index) => {
                const article = orderedArticle.article || {};
                const salesRate = parseFormattedNumber(article.sales_rate);
                const qty = orderedArticle.invoice_pcs ?? orderedArticle.ordered_pcs ?? orderedArticle.shipment_pcs ?? 0;
                const total = salesRate * qty;
                const hrClass = index === 0 ? "mb-2.5" : "my-2.5";

                totalAmount += total;
                totalPcs += qty;
                totalPackets += article?.pcs_per_packet ? Math.floor(qty / article.pcs_per_packet) : 0;
                const detailLine = invoiceDetailLine(orderedArticle, article);

            return `
                <div class="invoice-item-row">
                    <hr class="w-full ${hrClass} border-black">
                    <div class="tr invoice-item-main grid grid-cols-8 justify-between w-full px-4 gap-0.5">
                        <div class="td text-sm font-semibold truncate">${index + 1}.</div>
                        <div class="td invoice-article-cell text-sm font-semibold">
                            <div class="invoice-article-code">${article.article_no ?? ''}</div>
                        </div>
                        <div class="td invoice-description-cell text-sm font-semibold">${detailLine}</div>
                        <div class="td text-sm font-semibold truncate">${article?.pcs_per_packet ?? 0}</div>
                        <div class="td text-sm font-semibold truncate">${article?.pcs_per_packet ? Math.floor(qty / article.pcs_per_packet) : 0}</div>
                        <div class="td text-sm font-semibold truncate">${qty}</div>
                        <div class="td text-sm font-semibold truncate">${formatNumbersDigitLess(salesRate)}</div>
                        <div class="td text-sm font-semibold truncate">${formatNumbersDigitLess(total)}</div>
                    </div>
                </div>
            `;
            }).join('')}
        `;

        const discountAmount = discountVal ? (totalAmount * discountVal) / 100 : 0;
        const netAmount = previewData.netAmount ?? (totalAmount - discountAmount);

        const invoiceBottom = invoiceDocumentTotalsHtml(previewData, totalPackets, totalPcs, totalAmount, discountVal, discountAmount, netAmount);

        return `
            <div class="preview-container w-[148mm] h-[210mm] mx-auto overflow-hidden relative">
                <div id="preview" class="preview w-[148mm] h-[210mm] gos-a5-document gos-a5-invoice overflow-hidden flex flex-col">
                    <div class="flex flex-col h-full">
                        <div id="banner" class="banner w-full flex justify-between items-center px-5">
                            <div class="left">
                                <div class="logo flex flex-col">
                                    <div class="flex items-center gap-3">
                                        ${previewLogoUrl ? `
                                            <div class="h-[3.50rem] w-[13.5rem] flex items-center justify-center gap-2.5">
                                                <img
                                                    src="${previewLogoUrl}"
                                                    alt="garmentsos-pro"
                                                    class="max-h-full max-w-full object-contain"
                                                />
                                                ${previewCompany.logo_text ? `
                                                    <h1 class="text-lg font-bold tracking-wide">${previewCompany.logo_text}</h1>
                                                ` : ''}
                                            </div>
                                        ` : ''}
                                    </div>
                                    ${(previewCompany.phone_number || previewCompany.phone) ? `
                                        <div class="mt-2 text-sm text-gray-600">${previewCompany.phone_number || previewCompany.phone}</div>
                                    ` : ''}
                                </div>
                            </div>
                            <div class="right">
                                <div class="logo text-right">
                                    <h1 class="text-2xl font-medium text-[var(--h-primary-color)]">Sales Invoice</h1>
                                    <div class="mt-1 text-right">Invoice No.: ${previewData.invoice_no}</div>
                                </div>
                            </div>
                        </div>
                        <hr class="w-full my-3 border-black">
                        <div id="header" class="header w-full flex justify-between px-5">
                            <div class="left w-50 space-y-1">
                                <div class="customer text-lg leading-none capitalize font-medium text-nowrap">M/s: ${previewData.customer.customer_name}</div>
                                <div class="person text-md text-lg leading-none">${customerTitlePhoneLine(previewData.customer)}</div>
                                <div class="address text-md leading-none">${previewData.customer.address ?? ''}, ${previewData.customer.city?.title ?? ''}</div>
                                <div class="phone text-md leading-none">${deliverToLine(previewData)}</div>
                            </div>
                            <div class="right w-50 my-auto text-right text-sm text-black space-y-1.5">
                                <div class="date leading-none">Date: ${formatDate(previewData.date)}</div>
                                ${previewData.order_no ? `<div class="number leading-none capitalize">Order No.: ${previewData.order_no}</div>` : previewData.shipment_no ? `<div class="number leading-none capitalize">Shipment No.: ${previewData.shipment_no}</div>` : ''}
                                <div class="preview-copy leading-none capitalize">invoice Copy: ${copyLabel}</div>
                                <div class="number leading-none capitalize">Cotton: ${cotton || '-'}</div>
                            </div>
                        </div>
                        <hr class="w-full my-3 border-black">
                        <div class="body w-full px-5 grow mx-auto">
                            <div class="table w-full">
                                <div class="table w-full border border-black rounded-lg pb-2.5 overflow-hidden">
                                    <div class="thead w-full">
                                <div class="tr grid grid-cols-8 w-full px-4 py-1.5 bg-[var(--primary-color)] text-white">
                                            ${invoiceTableHeader}
                                        </div>
                                    </div>
                                    <div id="tbody" class="tbody w-full">
                                        ${invoiceTableBody}
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-2 px-5">
                            ${invoiceBottom}
                        </div>
                        <hr class="w-full my-3 border-black">
                        <div class="tfooter flex w-full text-sm px-4 justify-between text-black">
                            <p class="leading-none">Powered by SparkPair</p>
                            <p class="leading-none text-sm">&copy; ${new Date().getFullYear()} SparkPair | +92 316 5825495</p>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    window.initInvoicesPrint = initInvoicesPrint;

    function printUsingIframe(previewHtml) {
        if (!previewHtml) return;

        const oldIframe = document.getElementById('printIframe');
        if (oldIframe) oldIframe.remove();

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
                    <title>Print Invoice</title>
                    ${headContent}
                    <style>
                        @page {
                            size: A5 portrait;
                            margin: 3mm;
                        }

                        @media print {
                            body {
                                margin: 0;
                                padding: 0;
                                width: 148mm;
                                height: 210mm;
                            }
                            .preview-container,
                            .preview {
                                width: 148mm !important;
                                height: 210mm !important;
                                max-width: 148mm !important;
                                max-height: 210mm !important;
                            }
                            .preview-container, .preview-container * {
                                page-break-inside: avoid;
                            }
                            .page-break {
                                page-break-after: always;
                            }
                        }
                    </style>
                </head>
                <body>
                    ${previewHtml}
                </body>
            </html>
        `);

        printDocument.close();

        printIframe.onload = () => {
            printDocument.querySelectorAll('.preview').forEach(p => p.classList.remove('py-6'));
            printDocument.querySelectorAll('#banner').forEach(p => p.classList.remove('mt-8'));
            printDocument.querySelectorAll('.footer').forEach(p => p.classList.remove('mb-4'));

            setTimeout(() => {
                printIframe.contentWindow.focus();
                printIframe.contentWindow.print();
            }, 600);
        };
    }

    function boot() {
        if (window.__invoicesPrint) {
            initInvoicesPrint(window.__invoicesPrint);
        }
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", boot);
    } else {
        boot();
    }
})();
