(function (window) {
    'use strict';

    const currentUser = window.currentUser || {};

    const invoiceDetailLine = (orderedArticle, article) => {
        const description = String(orderedArticle?.description ?? '').trim();
        const fabricType = String(article?.fabric_type ?? orderedArticle?.fabric_type ?? orderedArticle?.article?.fabric_type ?? orderedArticle?.articles?.fabric_type ?? '').trim();
        const parts = [description, fabricType].filter((part, index, list) => (
            part && list.findIndex(item => item.toLowerCase() === part.toLowerCase()) === index
        ));

        return parts.length ? parts.join(' | ') : '';
    };

    const dispatchText = (orderedArticle, article) => {
        const dispatchedPcs = Number(orderedArticle?.dispatched_pcs || 0);
        const pcsPerPacket = Number(article?.pcs_per_packet || 0);
        if (!dispatchedPcs) return '';

        const packets = pcsPerPacket ? Math.floor(dispatchedPcs / pcsPerPacket) : 0;
        return packets ? formatNumbersDigitLess(packets) : '';
    };

    const customerTitlePhoneLine = (customer = {}) => {
        const title = String(customer?.urdu_title ?? '').trim();
        const phone = String(customer?.phone_number ?? '').trim();
        return [title, phone].filter(Boolean).join(' | ');
    };

    const deliverToLine = previewData => {
        const deliverTo = String(previewData?.deliver_to ?? previewData?.order?.deliver_to ?? '').trim();
        return `Deliver To: ${deliverTo || '-'}`;
    };

    const previewText = value => String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');

    const truthySetting = value => value === true || value === 1 || value === '1' || value === 'true';

    const documentSettings = previewData => previewData?.branch_branding || {};

    const documentDiscountDisabled = (type, previewData) => (
        ['order', 'invoice'].includes(type) && truthySetting(documentSettings(previewData).discount_disabled)
    );

    const documentNote = previewData => String(documentSettings(previewData).document_note || '').trim();

    const previewLogoUrl = (company, companyLogoBase) => {
        if (company.logo_url) return company.logo_url;
        if (!company.logo) return '';

        const base = String(companyLogoBase || '/').replace(/\/+$/, '');
        return base.endsWith('/images') ? `${base}/${company.logo}` : `${base}/images/${company.logo}`;
    };

    const renderTotalRow = ({
        label,
        value,
        borderClass = 'border-black',
        fontWeight = '',
        wrapperClass = '',
    }) => `
        <div class="total flex justify-between items-center border ${borderClass} rounded-lg py-1.5 px-2.5 w-full ${wrapperClass}">
            <div class="text-nowrap ${fontWeight}">
                ${label}
            </div>

            <div class="w-1/4 text-right grow ${fontWeight}">
                ${value}
            </div>
        </div>
    `;

    const renderTotalQuantityRow = (totalPackets = 0, totalPcs = 0) => (
        renderTotalRow({
            label: 'Total Quantity',
            value: `
                ${formatNumbersDigitLess(totalPackets)}
                |
                ${formatNumbersDigitLess(totalPcs)}
            `,
        })
    );

    const renderVoucherTotals = (totals = {}, previewData = {}) => {
        const {
            previousBalance = 0,
            totalPayment = 0,
            currentBalance = 0,
        } = totals;

        const hasSupplier = !!previewData?.supplier;

        const rows = [];

        if (hasSupplier) {
            rows.push(
                renderTotalRow({
                    label: 'P. Balance',
                    value: formatNumbersWithDigits(previousBalance, 1, 1),
                    borderClass: 'border-gray-600',
                })
            );
        }

        rows.push(
            renderTotalRow({
                label: 'T. Payment',
                value: formatNumbersWithDigits(totalPayment, 1, 1),
                borderClass: 'border-gray-600',
            })
        );

        if (hasSupplier) {
            rows.push(
                renderTotalRow({
                    label: 'C. Balance',
                    value: formatNumbersWithDigits(currentBalance, 1, 1),
                    borderClass: 'border-gray-600',
                    fontWeight: 'font-semibold',
                })
            );
        }

        return rows.join('');
    };

    const renderDocumentTotals = (
        totals = {},
        previewData = {},
        type = ''
    ) => {
        const {
            totalPackets = 0,
            totalPcs = 0,
            totalAmount = 0,
            discount = 0,
            discountAmount = 0,
            netAmount = 0,
        } = totals;

        const rows = [];

        const discountDisabled = documentDiscountDisabled(
            type,
            previewData
        );

        const note = documentNote(previewData);

        /*
        * Optional document note
        */
        if (discountDisabled && note) {
            rows.push(`
                <div class="total col-span-2 flex justify-center items-center border border-black rounded-lg py-1.5 px-2.5 w-full text-center font-semibold">
                    ${previewText(note)}
                </div>
            `);
        }

        /*
        * Quantity
        */
        rows.push(
            renderTotalQuantityRow(
                totalPackets,
                totalPcs
            )
        );

        /*
        * Discount disabled
        */
        if (discountDisabled) {
            rows.push(
                renderTotalRow({
                    label: 'Net Amount',
                    value: formatNumbersWithDigits(
                        netAmount,
                        1,
                        1
                    ),
                    fontWeight: 'font-semibold',
                })
            );

            return rows.join('');
        }

        /*
        * Gross Amount
        */
        rows.push(
            renderTotalRow({
                label: 'Gross Amount',
                value: formatNumbersWithDigits(
                    totalAmount,
                    1,
                    1
                ),
            })
        );

        /*
        * Discount
        */
        rows.push(
            renderTotalRow({
                label: `Discount ${discount}%`,
                value: formatNumbersWithDigits(
                    discountAmount,
                    1,
                    1
                ),
            })
        );

        /*
        * Net Amount
        */
        rows.push(
            renderTotalRow({
                label: 'Net Amount',
                value: formatNumbersWithDigits(
                    netAmount,
                    1,
                    1
                ),
                fontWeight: 'font-semibold',
            })
        );

        return rows.join('');
    };

    const documentTotalsRenderers = {
        voucher: renderVoucherTotals,
        order: renderDocumentTotals,
        invoice: renderDocumentTotals,
        shipment: renderDocumentTotals,
    };

    const documentTotalsHtml = (
        type,
        previewData = {},
        totals = {}
    ) => {
        const renderer =
            documentTotalsRenderers[type] ||
            renderDocumentTotals;

        return renderer(
            totals,
            previewData,
            type
        );
    };

    function render(data, options = {}) {
        const companyData = options.companyData || data.companyData || window.companyData || {};
        const companyLogoBase = (options.companyLogoBase || data.companyLogoBase || window.companyLogoBase || '/').replace(/\/+$/, '/') ;
        let clutter = '';

    // function chunkArray(array, size, hasTotal = false) {
    //     const total = array.length;
    //     const chunks = [];

    //     if (size <= 11) {
    //         for (let i = 0; i < total; i += size) {
    //             chunks.push(array.slice(i, i + size));
    //         }
    //         return chunks;
    //     }

    //     if (!hasTotal || total <= 18) {
    //         for (let i = 0; i < total; i += size) {
    //             chunks.push(array.slice(i, i + size));
    //         }
    //         return chunks;
    //     }

    //     let startIndex = 0;

    //     while (total - startIndex > 18) {
    //         chunks.push(array.slice(startIndex, startIndex + size));
    //         startIndex += size;
    //     }

    //     chunks.push(array.slice(startIndex));

    //     return chunks;
    // }

    // function chunkInvoiceRows(array) {
    //     const rows = Array.isArray(array) ? array : [];
    //     const chunks = [];
    //     let remaining = rows.slice();
    //     const maxRowsWithoutTotals = 13;
    //     const maxRowsWithTotals = 11;

    //     if (remaining.length <= maxRowsWithTotals) {
    //         return [remaining];
    //     }

    //     while (remaining.length > maxRowsWithTotals) {
    //         const take = remaining.length <= maxRowsWithoutTotals + maxRowsWithTotals
    //             ? Math.min(maxRowsWithoutTotals, remaining.length - 1)
    //             : maxRowsWithoutTotals;
    //         chunks.push(remaining.slice(0, take));
    //         remaining = remaining.slice(take);
    //     }

    //     chunks.push(remaining);
    //     return chunks;
    // }

    function chunkRowsForDocument(
        rows = [],
        {
            normalMaxRows = 12,
            totalMaxRows = 11,
            hasTotals = false,
        } = {}
    ) {
        const source = Array.isArray(rows) ? rows : [];
        const totalRows = source.length;

        if (!totalRows) {
            return [[]];
        }

        // No totals: simple pagination.
        if (!hasTotals) {
            const pages = [];

            for (let start = 0; start < totalRows; start += normalMaxRows) {
                pages.push(
                    source.slice(
                        start,
                        Math.min(start + normalMaxRows, totalRows)
                    )
                );
            }

            return pages;
        }

        const pages = [];
        let start = 0;

        while (start < totalRows) {
            const remaining = totalRows - start;

            /*
            * Agar remaining total page ki maximum capacity
            * ke andar hai, to ye final page hai.
            *
            * Minimum 1 row guaranteed.
            */
            if (remaining <= totalMaxRows) {
                pages.push(
                    source.slice(start)
                );

                break;
            }

            /*
            * Normal page mein maximum rows.
            *
            * Lekin kam az kam 1 row final totals page ke
            * liye reserve rehni chahiye.
            */
            const rowsForNormalPage = Math.min(
                normalMaxRows,
                remaining - 1
            );

            pages.push(
                source.slice(
                    start,
                    start + rowsForNormalPage
                )
            );

            start += rowsForNormalPage;
        }

        return pages;
    }

    function articleSortValue(row = {}) {
        return String(row?.article?.article_no ?? row?.article_no ?? '').trim();
    }

    function sortArticleRows(rows) {
        return [...(Array.isArray(rows) ? rows : [])].sort((left, right) => (
            articleSortValue(left).localeCompare(articleSortValue(right), undefined, {
                numeric: true,
                sensitivity: 'base',
            })
        ));
    }

    function printDateTime() {
        return new Date().toLocaleString('en-GB', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            hour12: true,
        });
    }

    function documentCreatedTime(value) {
        const rawValue = String(value || '').trim();
        const timestamp = rawValue || printDateTime();
        const parts = timestamp.split(',');

        return (parts[1] || parts[0] || '').trim();
    }

    function rowDivider(index, borderClass = 'border-black') {
        return index === 0 ? '' : `<hr class="w-full my-2 ${borderClass} border-dashed">`;
    }

    // Preview section ko clean karo
    if (data.preview) {
        let previewData = data.preview.data;
        let cartonCount = previewData.carton_count || 0;
        let discount = previewData.discount || previewData.shipment?.discount || previewData.order?.discount;
        let netAmount = previewData.netAmount || previewData.shipment?.netAmount;

        let invoiceTableHeader = "";
        let invoiceTableBody = "";
        let invoiceBottom = "";

        // Check if totals will be shown
        const hasTotal = ['order', 'invoice', 'shipment'].includes(data.preview.type);
        const rawPreviewArticles = previewData.articles || previewData.invoice_articles || [];
        const previewArticles = sortArticleRows(Array.isArray(rawPreviewArticles) ? rawPreviewArticles : Object.values(rawPreviewArticles || {}))
            .filter(row => row && (row.article || row.article_no || row.article_id));
        const articlePages = previewArticles.length
            ? chunkRowsForDocument(previewArticles, {
                normalMaxRows: 13,
                totalMaxRows: 11,
                hasTotals: hasTotal,
            })
            : [['__empty__']];

        // Preview container start
        clutter += `<div id="preview-container" class="h-auto mx-auto relative flex flex-col">`;

        if (data.preview.type == "voucher") {
            const hasSupplier = !!previewData.supplier;

            // Office copy hamesha, Supplier copy sirf jab supplier voucher ho
            const voucherCopies = hasSupplier
                ? [{ label: 'Office' }, { label: 'Supplier' }]
                : [{ label: 'Office' }];

            const voucherRows = Array.isArray(previewData.payments) ? previewData.payments : [];
            // 26 rows/page - lamba text (Customer/Account) accommodate karne ke liye
            // pehle se available chunkArray helper use kar rahe hain (hasTotal=true taake
            // aakhri page par totals ke liye jagah bachi rahe)
            const voucherPages = voucherRows.length
                ? chunkRowsForDocument(voucherRows, {
                    normalMaxRows: 16,
                    totalMaxRows: 15,
                    hasTotals: true,
                })
                : [[]];

            const prevBalance = parseFormattedNumber(previewData.previous_balance);
            const totalPaymentVal = Number((previewData.total_payment ?? 0).toString().replace(/,/g, '')) || 0;

            voucherCopies.forEach(copy => {
                let voucherSerial = 1;

                voucherPages.forEach((pageRows, pageIndex) => {
                    invoiceTableHeader = `
                        <div class="th text-sm font-medium w-[4%]">S.#</div>
                        <div class="th text-sm font-medium w-[12%]">Method</div>
                        ${hasSupplier ? '<div class="th text-sm font-medium w-[17%]">Customer</div>' : ''}
                        <div class="th text-sm font-medium ${hasSupplier ? 'w-[21%]' : 'grow'}">Account</div>
                        <div class="th text-sm font-medium w-[15%]">Date</div>
                        <div class="th text-sm font-medium w-[11%]">Reff. No.</div>
                        <div class="th text-sm font-medium w-[12%] text-right">Amount</div>
                    `;

                    const cellStyle = 'white-space:normal !important;overflow:visible !important;text-overflow:clip !important;word-break:normal !important;overflow-wrap:normal !important;line-height:1.15 !important;display:block !important;';

                    invoiceTableBody = pageRows.map((payment, index) => `
                        <div>
                            ${rowDivider(index, 'border-gray-600')}
                            <div class="tr flex justify-between w-full px-2 gap-2" style="align-items:center !important;">
                                <div class="td text-sm font-semibold w-[4%]" style="${cellStyle}">${voucherSerial++}.</div>
                                <div class="td text-sm font-semibold w-[12%] capitalize" style="${cellStyle}">${payment.method ?? '-'}</div>
                                ${hasSupplier ? `<div class="td text-sm font-semibold w-[17%] capitalize" style="${cellStyle}">${payment.program?.customer?.customer_name ?? payment.cheque?.customer?.customer_name ?? payment.slip?.customer?.customer_name ?? '-'}</div>` : ''}
                                ${hasSupplier
                                    ? `<div class="td text-sm font-semibold w-[21%]" style="${cellStyle}">${(payment.bank_account?.account_title?.split('|')[0] ?? '-') + ' | ' + (payment.bank_account?.bank?.short_title ?? '-')}</div>`
                                    : `<div class="td text-sm font-semibold grow" style="${cellStyle}">${(payment.self_account?.account_title?.split('|')[0] ?? '-') + ' | ' + (payment.self_account?.bank?.short_title ?? '-')}</div>`}
                                <div class="td text-sm font-semibold w-[15%]" style="${cellStyle}">${formatDate(payment.date, true) ?? '-'}</div>
                                <div class="td text-sm font-semibold w-[11%]" style="${cellStyle}">${payment.cheque?.cheque_no ?? payment.cheque_no ?? payment.reff_no ?? payment.slip?.slip_no ?? payment.transaction_id ?? payment.reff_no ?? '-'}</div>
                                <div class="td text-sm font-semibold w-[12%] text-right" style="${cellStyle}">${formatNumbersWithDigits(payment.amount, 1, 1) ?? '-'}</div>
                            </div>
                        </div>
                    `).join('');

                    invoiceBottom = '';

                    if (pageIndex === voucherPages.length - 1) {
                        invoiceBottom = documentTotalsHtml(
                            data.preview.type,
                            previewData,
                            {
                                previousBalance: prevBalance,
                                totalPayment: totalPaymentVal,
                                currentBalance: prevBalance - totalPaymentVal,
                            }
                        );
                    }

                    clutter += renderPreviewPage(
                        data, previewData, cartonCount,
                        invoiceTableHeader, invoiceTableBody, invoiceBottom,
                        pageIndex, voucherPages.length,
                        copy.label
                    );
                });
            });

        } else if (data.preview.type == "cargo_list") {
            const cargoInvoices = Array.isArray(previewData.invoices) ? previewData.invoices : [];
            const cargoPages = chunkRowsForDocument(cargoInvoices, {
                normalMaxRows: 38,
                totalMaxRows: 38,
                hasTotals: false,
            });
            let cargoSerial = 1;

            cargoPages.forEach((cargoChunk, pageIndex) => {
                invoiceTableHeader = `
                    <div class="th text-sm font-medium w-[5%]">S.No</div>
                    <div class="th text-sm font-medium w-[13%]">Date</div>
                    <div class="th text-sm font-medium w-[15%]">Invoice No.</div>
                    <div class="th text-sm font-medium w-[15%]">Shipment No.</div>
                    <div class="th text-sm font-medium w-[8%]">Carton</div>
                    <div class="th text-sm font-medium grow">Customer</div>
                    <div class="th text-sm font-medium w-[16%]">City</div>
                `;

                invoiceTableBody = cargoChunk.map((invoice, index) => {
                    return `
                    <div>
                        ${rowDivider(index)}
                        <div class="tr flex justify-between w-full px-2 gap-2">
                            <div class="td text-sm font-semibold w-[5%]">${cargoSerial++}.</div>
                            <div class="td text-sm font-semibold w-[13%]">${formatDate(invoice.date)}</div>
                            <div class="td text-sm font-semibold w-[15%]">${invoice.invoice_no || '-'}</div>
                            <div class="td text-sm font-semibold w-[15%]">${invoice.shipment_no || '-'}</div>
                            <div class="td text-sm font-semibold w-[8%]">${invoice.carton_count}</div>
                            <div class="td text-sm font-semibold grow capitalize">${invoice.customer?.customer_name || '-'}</div>
                            <div class="td text-sm font-semibold w-[16%]">${invoice.customer?.city?.title || '-'}</div>
                        </div>
                    </div>
                    `;
                }).join('');

                invoiceBottom = '';
                clutter += renderPreviewPage(data, previewData, cartonCount, invoiceTableHeader, invoiceTableBody, invoiceBottom, pageIndex, cargoPages.length);
            });

        } else if (data.preview.type == "form") {
            clutter += renderPreviewPage(data, previewData, cartonCount, '', '', '', 0, 1);

        } else {
            // Order, Invoice, Shipment - with pagination
            let totalAmount = 0;
            let totalPcs = 0;
            let totalPackets = 0;
            let rowSerial = 1;

            articlePages.forEach((articlesChunk, pageIndex) => {
                invoiceTableHeader = data.preview.type == 'invoice' || data.preview.type == 'order' || data.preview.type == 'shipment' ? `
                    <div class="th text-sm font-medium ">S.#</div>
                    <div class="th text-sm font-medium ">Article</div>
                    <div class="th text-sm font-medium ">Description</div>
                    <div class="th text-sm font-medium ">Unit</div>
                    <div class="th text-sm font-medium ">Pkts</div>
                    <div class="th text-sm font-medium ">Pcs.</div>
                    <div class="th text-sm font-medium ">Rate</div>
                    <div class="th text-sm font-medium ">Amount</div>
                    ${data.preview.type == 'order' ? '<div class="th text-sm font-medium text-right">Dispatch</div>' : ''}
                ` : `
                    <div class="th text-sm font-medium ">S.No</div>
                    <div class="th text-sm font-medium ">Article</div>
                    <div class="th text-sm font-medium col-span-2">Description</div>
                    <div class="th text-sm font-medium ">Packets</div>
                    <div class="th text-sm font-medium ">Pcs.</div>
                    <div class="th text-sm font-medium ">Rate/Pc.</div>
                    <div class="th text-sm font-medium ">Amount</div>
                    ${data.preview.type == 'order' ? '<div class="th text-sm font-medium text-right ">Dispatch</div>' : ''}
                `;

                // Agar empty array hai (second page for totals only)
                if (articlesChunk.length === 0 || articlesChunk[0] === '__empty__') {
                    invoiceTableBody = `
                        <div class="px-4 py-6 text-center text-sm text-[var(--secondary-text)]">
                            No invoice articles found for this document.
                        </div>
                    `;
                } else {
                    invoiceTableBody = `
                        ${articlesChunk.map((orderedArticle, index) => {
                            const article = orderedArticle.article || orderedArticle;
                            const salesRate = Number(article?.sales_rate || 0);
                            const qtyPriority = {
                                order: ['ordered_pcs', 'invoice_pcs', 'shipment_pcs'],
                                shipment: ['shipment_pcs', 'ordered_pcs', 'invoice_pcs'],
                                default: ['invoice_pcs', 'ordered_pcs', 'shipment_pcs'],
                            };
                            const qty = (qtyPriority[data.preview.type] || qtyPriority.default)
                                .map(key => orderedArticle[key])
                                .find(v => v !== null && v !== undefined) ?? 0;
                            const total = salesRate * Number(qty || 0);

                            totalAmount += total;
                            totalPcs += Number(qty || 0);
                            totalPackets += article?.pcs_per_packet ? Math.floor(qty / article.pcs_per_packet) : 0;

                            if (data.preview.type == 'invoice' || data.preview.type == 'order' || data.preview.type == 'shipment') {
                                const detailLine = invoiceDetailLine(orderedArticle, article);
                                const rowGridClass = data.preview.type == 'order' ? 'grid-cols-9' : 'grid-cols-8';
                                const dispatched = dispatchText(orderedArticle, article);

                                return `
                                    <div class="invoice-item-row">
                                        ${rowDivider(index)}
                                        <div class="tr invoice-item-main grid ${rowGridClass} justify-between w-full px-4 gap-0.5">
                                            <div class="td text-sm font-semibold">${String(rowSerial++).padStart(2, '0')}</div>
                                            <div class="td invoice-article-cell text-sm font-semibold">
                                                <div class="invoice-article-code">${article?.article_no || '-'}</div>
                                            </div>
                                            <div class="td invoice-description-cell text-sm font-semibold">${detailLine}</div>
                                            <div class="td text-sm font-semibold">${article?.pcs_per_packet || '-'}</div>
                                            <div class="td text-sm font-semibold">${article?.pcs_per_packet ? Math.floor(qty / article.pcs_per_packet) : 0}</div>
                                            <div class="td text-sm font-semibold">${qty}</div>
                                            <div class="td text-sm font-semibold">${formatNumbersDigitLess(salesRate)}</div>
                                            <div class="td text-sm font-semibold">${formatNumbersDigitLess(total)}</div>
                                            ${data.preview.type == 'order' ? `<div class="td text-sm font-semibold text-right">${dispatched}</div>` : ''}
                                        </div>
                                    </div>
                                `;
                            }

                            return `
                                <div>
                                    ${rowDivider(index)}
                                    <div class="tr grid grid-cols-${data.preview.type == 'shipment' ? '8' : '9'} justify-between w-full px-4 gap-0.5">
                                        <div class="td text-sm font-semibold">${rowSerial++}.</div>
                                        <div class="td text-sm font-semibold">${article?.article_no || '-'}</div>
                                        <div class="td text-sm font-semibold col-span-2 capitalize">${orderedArticle.description}</div>
                                        ${data.preview.type == 'invoice' ? `<div class="td text-sm font-semibold">${article?.pcs_per_packet}</div>` : ''}
                                        <div class="td text-sm font-semibold">${article?.pcs_per_packet ? Math.floor(qty / article.pcs_per_packet) : 0}</div>
                                        <div class="td text-sm font-semibold">${qty}</div>
                                        <div class="td text-sm font-semibold">${formatNumbersDigitLess(salesRate)}</div>
                                        <div class="td text-sm font-semibold">${formatNumbersDigitLess(total)}</div>
                                        ${data.preview.type == 'order' ? `<div class="td text-sm text-right font-semibold">${orderedArticle.dispatched_pcs > 0 ? orderedArticle.dispatched_pcs : ''}</div>` : ''}
                                    </div>
                                </div>
                            `;
                        }).join('')}
                    `;
                }

                const discountAmount = discount
                    ? (totalAmount * discount) / 100
                    : 0;

                const calculatedNetAmount =
                    totalAmount - discountAmount;

                invoiceBottom = '';

                if (pageIndex === articlePages.length - 1) {
                    invoiceBottom = documentTotalsHtml(
                        data.preview.type,
                        previewData,
                        {
                            totalPackets,
                            totalPcs,
                            totalAmount,
                            discount,
                            discountAmount,
                            netAmount: calculatedNetAmount,
                        }
                    );
                }

                clutter += renderPreviewPage(data, previewData, cartonCount, invoiceTableHeader, invoiceTableBody, invoiceBottom, pageIndex, articlePages.length);
            });
        }

        // Preview container end
        clutter += `</div>`;
    }

    // Helper function - Preview page render karne ke liye
    function renderPreviewPage(data, previewData, cartonCount, invoiceTableHeader, invoiceTableBody, invoiceBottom, pageIndex, totalPages = 1, copyLabelOverride = null) {
        const previewCompany = previewData?.branch_branding || companyData;
        const previewCompanyLogoUrl = previewLogoUrl(previewCompany, companyLogoBase);
        const isCargoList = data.preview.type == "cargo_list";
        const useCargoListLayout = isCargoList;

        const isCompactDocument = data.preview.size == "A5" || data.preview.type == "order" || data.preview.type == "invoice" || data.preview.type == "shipment" || data.preview.type == "voucher";
        const pageSizeClass = useCargoListLayout ? "w-[210mm] h-[297mm]" : (isCompactDocument ? "w-[148mm] h-[210mm]" : "w-[208mm] h-[302mm]");
        const pageTextClass = useCargoListLayout ? "gos-a4-document cargo-list-a4-document" : (isCompactDocument ? `gos-a5-document ${data.preview.type == "invoice" || data.preview.type == "order" || data.preview.type == "shipment" || data.preview.type == "voucher" ? "gos-a5-invoice" : ""}` : "");
        const documentNo = data.preview.type == 'order'
            ? previewData.order_no
            : data.preview.type == 'invoice'
                ? previewData.invoice_no
                : data.preview.type == 'shipment'
                    ? previewData.shipment_no
                    : data.preview.type == 'cargo_list'
                        ? previewData.cargo_no
                        : data.preview.type == 'voucher'
                            ? previewData.voucher_no
                            : '';
        const documentNoLabel = data.preview.type == 'order'
            ? 'Order No.'
            : data.preview.type == 'invoice'
                ? 'Invoice No.'
                : data.preview.type == 'shipment'
                    ? 'Shipment No.'
                    : data.preview.type == 'cargo_list'
                        ? 'Cargo List No.'
                        : data.preview.type == 'voucher'
                            ? 'Voucher No.'
                            : '';
        const invoiceSourceNo = previewData.order_no
            ? `Order No.: ${previewData.order_no}`
            : previewData.shipment_no
                ? `Shipment No.: ${previewData.shipment_no}`
                : '';
        const documentTime = documentCreatedTime(previewData.created_at);
        const documentTimeSuffix = documentTime ? `, ${documentTime}` : '';

        return `
            <div id="preview" class="preview ${useCargoListLayout ? 'cargo-list-preview ' : ''}${pageSizeClass} ${pageTextClass} overflow-hidden flex flex-col">
                <div class="${useCargoListLayout ? 'cargo-list-document ' : ''}flex flex-col h-full">
                    <div id="banner" class="banner w-full flex justify-between items-center px-5">
                        <div class="left">
                            <div class="logo flex flex-col">
                                <!-- Top Row: Image + Logo Text -->
                                <div class="flex items-center gap-3">
                                    ${previewCompanyLogoUrl ? `
                                        <div class="h-[3.50rem] w-[13.5rem] flex items-center justify-center gap-2.5">
                                            <img
                                                src="${previewCompanyLogoUrl}"
                                                alt="garmentsos-pro"
                                                class="max-h-full max-w-full object-contain"
                                            />
                                            ${previewCompany.logo_text ? `
                                                <h1 class="text-lg font-bold tracking-wide">
                                                    ${previewCompany.logo_text}
                                                </h1>
                                            ` : ''}
                                        </div>
                                    ` : ''}
                                </div>
                            </div>
                        </div>
                        <div class="right">
                            <div class="logo text-right py-1">
                                <h1 class="text-2xl font-medium text-[var(--h-primary-color)]">${data.preview.document}</h1>
                                ${documentNo ? `<div class="document-number mt-1 text-right">${documentNoLabel}: ${documentNo}</div>` : ''}
                                ${!['invoice', 'order', 'shipment'].includes(data.preview.type) && previewData.order_no ? '<div class="mt-1 text-right">Order No.: ' + previewData.order_no + '</div>' : ''}
                                ${data.preview.type == 'form' ? `<div class='mt-1 text-sm'>${previewCompany.phone_number || ''}</div>` : ''}
                            </div>
                        </div>
                    </div>
                    <hr class="w-full my-3 border-black">
                    ${data.preview.type != 'form' ? `
                        <div id="header" class="header w-full flex justify-between px-5">
                            <div class="left ${data.preview.type == "order" || data.preview.type == "invoice" || data.preview.type == "cargo_list" ? 'grow min-w-0 pr-3' : 'w-50'} space-y-1">
                                ${data.preview.type == "order" || data.preview.type == "invoice" ? `
                                    <div class="customer text-lg leading-none capitalize font-medium text-nowrap">M/s: ${previewData.customer.customer_name}</div>
                                    <div class="person text-md text-lg leading-none">${customerTitlePhoneLine(previewData.customer)}</div>
                                    <div class="address text-md leading-none">${previewData.customer.address ?? ''}${previewData.customer.city?.title ? ', ' + previewData.customer.city.title : ''}</div>
                                    <div class="phone deliver-to text-md leading-none">${deliverToLine(previewData)}</div>
                                ` : data.preview.type == "shipment" ? `
                                    <div class="address text-md leading-none capitalize">${previewData.city ? 'City: ' + previewData.city : ''}</div>
                                ` : data.preview.type == "cargo_list" ? `
                                    <div class="cargo-name capitalize font-semibold text-sm leading-none">Cargo Name: ${previewData.cargo_name}</div>
                                    <div class="date leading-none text-sm">Date: ${formatDate(previewData.date)}${documentTimeSuffix}</div>
                                ` :  data.preview.type == "voucher" ? `
                                    <div class="supplier-name capitalize font-semibold text-[12px] leading-none">Supplier: ${previewData.supplier?.supplier_name ?? '-'}</div>
                                    <div class="date leading-none text-sm">Date: ${formatDate(previewData.date)}${documentTimeSuffix}</div>
                                ` : `
                                    <div class="date leading-none">Date: ${formatDate(previewData.date)}${documentTimeSuffix}</div>
                                    <div class="number leading-none capitalize">${data.preview.type.replace('_', ' ')} No.: ${data.preview.type == 'shipment' ? previewData.shipment_no : data.preview.type == 'voucher' ? previewData.voucher_no : data.preview.type == 'cargo_list' ? previewData.cargo_no : ''}</div>
                                `}
                            </div>
                            <div class="right ${data.preview.type == "order" || data.preview.type == "invoice" ? 'shrink-0 min-w-[38%]' : 'w-50'} my-auto text-right text-sm text-black space-y-1.5">
                                ${data.preview.type == "order" || data.preview.type == "invoice" || data.preview.type == "shipment" ? `
                                    <div class="date leading-none">Date: ${formatDate(previewData.date)}${documentTimeSuffix}</div>
                                    ${data.preview.type == 'invoice' && invoiceSourceNo ? `<div class="number leading-none capitalize">${invoiceSourceNo}</div>` : ''}
                                ` : ''}
                                ${data.preview.type != 'shipment' ? `<div class="preview-copy leading-none capitalize">${data.preview.type.replace('_', ' ')} Copy: ${copyLabelOverride || previewData.copy_label || data.preview.copyLabel || (data.preview.type == 'cargo_list' ? 'Cargo' : 'Customer')}</div>` : ''}
                                ${data.preview.type == 'invoice' ? `<div class="number leading-none capitalize">Carton: ${cartonCount || '-'}</div>` : ''}
                                ${!['invoice', 'order', 'shipment'].includes(data.preview.type) ? `<div class="copy leading-none">Document: ${data.preview.document}</div>` : ''}
                            </div>
                        </div>
                        <hr class="w-full my-3 border-black">
                        <div class="body w-full px-5 grow mx-auto">
                            <div class="table w-full">
                                <div class="table w-full border border-black rounded-lg p-1">
                                    <div class="thead w-full">
                                        <div class="tr ${data.preview.type == 'voucher' || data.preview.type == 'cargo_list' ? 'flex justify-between' : 'grid'} ${data.preview.type == 'order' ? 'grid-cols-9' : data.preview.type == 'invoice' ? 'grid-cols-8' : data.preview.type == 'shipment' ? 'grid-cols-8' : 'grid-cols-9'} w-full px-4 py-1.5 bg-[var(--primary-color)] text-white rounded-md">
                                            ${invoiceTableHeader}
                                        </div>
                                    </div>
                                    <div id="tbody" class="tbody block w-full">
                                        ${invoiceTableBody}
                                    </div>
                                </div>
                            </div>
                        </div>
                    ` : `
                        <div class="grow flex flex-col px-5">
                            <div class="fields grow flex flex-col gap-3 pt-1">
                                ${data.preview.data.formFields.map(field => `
                                    <div class="flex gap-3">
                                        <label>${field.label}:</label>
                                        <div class="grow border-b border-black capitalize ps-1">${field.text}</div>
                                    </div>
                                `).join('')}
                            </div>
                            <div class="signatureFields flex gap-6 w-full">
                                <div class="grow flex gap-3">
                                    <label>Admin Sig.:</label>
                                    <div class="grow border-b border-black"></div>
                                </div>
                                <div class="grow flex gap-3">
                                    <label>Emp. Sig.:</label>
                                    <div class="grow border-b border-black"></div>
                                </div>
                            </div>
                        </div>
                    `}
                    ${invoiceBottom != '' ? `<hr class="w-full my-3 border-black">` : ''}
                    <div class="grid ${(data.preview.type == 'voucher' && previewData.supplier) ? 'grid-cols-3' : data.preview.type == 'voucher' && !previewData.supplier ? 'flex' : 'grid-cols-2'} gap-2 px-5">
                        ${invoiceBottom}
                    </div>
                    <hr class="w-full my-3 border-black">
                    <div class="footer flex w-full text-sm px-5 justify-between text-black">
                        <p class="leading-none text-sm">Powered by SparkPair | +92 316 5825495</p>
                        <p class="leading-none text-sm"><span class="capitalize">${data.preview.data.creator?.name || currentUser?.name || '-'}</span> | ${printDateTime()} | ${pageIndex + 1} of ${totalPages}</p>
                    </div>
                </div>
            </div>
        `;
    }


        return clutter;
    }

    function renderInto(container, preview, options = {}) {
        const target = typeof container === 'string' ? document.querySelector(container) : container;
        if (!target) return '';

        const markup = render({ preview }, options);
        target.innerHTML = markup;
        return markup;
    }

    function renderType(type, data, options = {}) {
        const documents = {
            order: 'Sales Order',
            invoice: 'Sales Invoice',
            shipment: 'Shipment',
            cargo_list: 'Cargo List',
            voucher: 'Voucher',
        };

        return render({
            preview: {
                type,
                size: options.size || 'A5',
                document: options.document || documents[type] || 'Document',
                copyLabel: options.copyLabel,
                data,
            },
        }, options);
    }

    window.DocumentPreview = {
        render,
        renderInto,
        order: (data, options = {}) => renderType('order', data, options),
        invoice: (data, options = {}) => renderType('invoice', data, options),
        shipment: (data, options = {}) => renderType('shipment', data, options),
        cargoList: (data, options = {}) => renderType('cargo_list', data, options),
        voucher: (data, options = {}) => renderType('voucher', data, options),
    };
})(window);
