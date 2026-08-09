(function (window) {
    'use strict';

    function removeOldIframe(id = 'printIframe') {
        document.getElementById(id)?.remove();
    }

    function createPrintIframe(id = 'printIframe') {
        const printIframe = document.createElement('iframe');
        printIframe.id = id;
        printIframe.style.position = 'absolute';
        printIframe.style.width = '0px';
        printIframe.style.height = '0px';
        printIframe.style.border = 'none';
        printIframe.style.display = 'none';
        document.body.appendChild(printIframe);
        return printIframe;
    }

    function printInkStyle() {
        return `
            @media print {
                body {
                    -webkit-print-color-adjust: exact;
                    print-color-adjust: exact;
                }

                #header,
                #header *,
                .header,
                .header *,
                #preview-header,
                #preview-header *,
                .preview-header,
                .preview-header *,
                .preview-banner .total-bill,
                .document-number,
                .preview-copy,
                .copy,
                .date,
                .number,
                .address,
                .person,
                .phone,
                .deliver-to,
                .customer,
                .supplier-name,
                .cargo-name,
                .total,
                .total *,
                #calc-bottom,
                #calc-bottom *,
                .calc-bottom,
                .calc-bottom *,
                .tfooter,
                .tfooter *,
                .footer,
                .footer * {
                    color: #000 !important;
                    opacity: 1 !important;
                }

                #preview-container .preview,
                #preview-container .preview-page,
                #preview-container .preview-document,
                .preview,
                .preview-page,
                .preview-document {
                    color: #000;
                }

                #preview-container :is(.thead, .head) .tr,
                #preview-container :is(.thead, .head) .tr *,
                .preview :is(.thead, .head) .tr,
                .preview :is(.thead, .head) .tr *,
                .preview-page :is(.thead, .head) .tr,
                .preview-page :is(.thead, .head) .tr * {
                    color: #fff !important;
                    opacity: 1 !important;
                }
            }
        `;
    }

    function a5PrintStyle(extraStyle = '') {
        return `
            @page {
                size: A5 portrait;
                margin: 0;
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

                .preview-document,
                .gos-a5-document > div {
                    display: flex !important;
                    flex-direction: column !important;
                    height: 100% !important;
                    min-height: 0 !important;
                }

                .preview-body,
                .body {
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

                ${extraStyle}
            }

            ${printInkStyle()}
        `;
    }

    function writePrintDocument(printDocument, { title, previewHtml, extraStyle }) {
        printDocument.open();
        printDocument.write(`
            <html>
                <head>
                    <title>${title || 'Print Document'}</title>
                    ${document.head.innerHTML}
                    <style>${a5PrintStyle(extraStyle)}</style>
                </head>
                <body>
                    <div id="preview-container" class="preview-container">${previewHtml}</div>
                </body>
            </html>
        `);
        printDocument.close();
    }

    function writeRawPrintDocument(printDocument, { title, html, style }) {
        printDocument.open();
        printDocument.write(`
            <html>
                <head>
                    <title>${title || 'Print Document'}</title>
                    ${document.head.innerHTML}
                    <style>${style || ''}${printInkStyle()}</style>
                </head>
                <body>${html || ''}</body>
            </html>
        `);
        printDocument.close();
    }

    function writeFullPrintDocument(printDocument, { html }) {
        printDocument.open();
        printDocument.write(html || '');
        printDocument.close();
    }

    function printPreview(options = {}) {
        const preview = typeof options.preview === 'string'
            ? document.querySelector(options.preview)
            : (options.preview || document.getElementById('preview-container'));

        if (!preview && !options.html) return null;

        removeOldIframe(options.iframeId);

        const printIframe = createPrintIframe(options.iframeId);
        const printDocument = printIframe.contentDocument || printIframe.contentWindow.document;

        writePrintDocument(printDocument, {
            title: options.title,
            previewHtml: options.html || preview.innerHTML,
            extraStyle: options.extraStyle || '',
        });

        printIframe.onload = () => {
            printDocument.querySelectorAll('.preview').forEach(page => page.classList.remove('py-6'));
            printDocument.querySelectorAll('#banner').forEach(banner => banner.classList.remove('mt-8'));
            printDocument.querySelectorAll('.footer').forEach(footer => footer.classList.remove('mb-4'));

            options.beforePrint?.(printDocument, printIframe);

            printIframe.contentWindow.onafterprint = () => {
                options.afterPrint?.(printDocument, printIframe);
            };

            setTimeout(() => {
                printIframe.contentWindow.focus();
                printIframe.contentWindow.print();
            }, options.delay ?? 500);
        };

        return printIframe;
    }

    function printHtml(options = {}) {
        if (!options.html) return null;

        removeOldIframe(options.iframeId);

        const printIframe = createPrintIframe(options.iframeId);
        const printDocument = printIframe.contentDocument || printIframe.contentWindow.document;

        writeRawPrintDocument(printDocument, {
            title: options.title,
            html: options.html,
            style: options.style,
        });

        printIframe.onload = () => {
            options.beforePrint?.(printDocument, printIframe);

            printIframe.contentWindow.onafterprint = () => {
                options.afterPrint?.(printDocument, printIframe);
            };

            setTimeout(() => {
                printIframe.contentWindow.focus();
                printIframe.contentWindow.print();
            }, options.delay ?? 200);
        };

        return printIframe;
    }

    function printDocumentHtml(options = {}) {
        if (!options.html) return null;

        removeOldIframe(options.iframeId);

        const printIframe = createPrintIframe(options.iframeId);
        const printDocument = printIframe.contentDocument || printIframe.contentWindow.document;

        writeFullPrintDocument(printDocument, {
            html: options.html,
        });

        printIframe.onload = () => {
            options.beforePrint?.(printDocument, printIframe);

            printIframe.contentWindow.onafterprint = () => {
                options.afterPrint?.(printDocument, printIframe);
            };

            setTimeout(() => {
                printIframe.contentWindow.focus();
                printIframe.contentWindow.print();
            }, options.delay ?? 200);
        };

        return printIframe;
    }

    window.DocumentPrint = {
        printPreview,
        printHtml,
        printDocumentHtml,
    };
})(window);
