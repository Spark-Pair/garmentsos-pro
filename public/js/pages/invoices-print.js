(function () {
    let invoices = [];
    let companyData = null;
    let hasPrinted = false;

    const invoiceContainer = document.getElementById('invoice-container');

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
            const cartonCount = invoice.carton_count || 0;
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
                    carton_count: cartonCount,
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
        return window.DocumentPreview.render({
            preview: {
                type: 'invoice',
                size: 'A5',
                document: 'Sales Invoice',
                copyLabel,
                data: {
                    ...previewData,
                    copy_label: copyLabel,
                    branch_branding: previewData.branch_branding || companyData,
                },
            },
        }, {
            companyData,
            companyLogoBase: window.__invoicesPrint?.companyLogoBase,
        });
    }

    function printUsingIframe(previewHtml) {
        if (!previewHtml) return;

        window.DocumentPrint.printHtml({
            title: 'Print Invoice',
            html: previewHtml,
            delay: 600,
            style: `
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
            `,
            beforePrint: printDocument => {
                printDocument.querySelectorAll('.preview').forEach(p => p.classList.remove('py-6'));
                printDocument.querySelectorAll('#banner').forEach(p => p.classList.remove('mt-8'));
                printDocument.querySelectorAll('.footer').forEach(p => p.classList.remove('mb-4'));
            },
        });
    }

    window.initInvoicesPrint = initInvoicesPrint;

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
