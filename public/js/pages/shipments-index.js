(function () {
    function setAuthLayout(data) {
        if (data?.authLayout) {
            window.authLayout = data.authLayout;
        }
        if (data?.companyData) {
            window.companyData = data.companyData;
        }
    }

    window.createRow = function createRow(data) {
        return `
                <div id="${data.id}" oncontextmenu='${htmlAttr(data.oncontextmenu || "")}' onclick='${htmlAttr(data.onclick || "")}'
                    class="item row relative group grid text- grid-cols-4 border-b border-[var(--h-bg-color)] items-center py-2 cursor-pointer hover:bg-[var(--h-secondary-bg-color)] transition-all fade-in ease-in-out"
                    data-json='${jsonAttr(data)}'>

                    <span class="text-center">${data.name}</span>
                    <span class="text-center capitalize">${data.details["City"] || '-'}</span>
                    <span class="text-center">${data.details["Amount"]}</span>
                    <span class="text-center">${data.details['Date']}</span>
                </div>
            `;
    };

    window.printShipment = function printShipment(elem) {
        closeAllDropdowns();

        if (elem.parentElement.tagName.toLowerCase() === 'li') {
            elem.parentElement.parentElement.querySelector('#show-details').click();
            document.getElementById('modalForm').parentElement.classList.add('hidden');
        }

        const preview = document.getElementById('preview-container');
        if (!preview) return;

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
                        <title>Print Shipment</title>
                        ${headContent}
                        <style>
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

                                #preview-container .preview:last-child {
                                    break-after: auto;
                                    page-break-after: auto;
                                }

                                .page-break {
                                    break-after: page;
                                    page-break-after: always;
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

            printIframe.contentWindow.onafterprint = () => {};

            setTimeout(() => {
                printIframe.contentWindow.focus();
                printIframe.contentWindow.print();
            }, 1000);

            document.getElementById('modalForm').parentElement.remove();
        };
    };

    window.generateContextMenu = function generateContextMenu(e) {
        e.preventDefault();
        const item = e.target.closest('.item');
        if (!item) return;
        const data = JSON.parse(item.dataset.json);

        const contextMenuData = {
            item: item,
            data: data,
            x: e.pageX,
            y: e.pageY,
            actions: [{ id: 'print', text: 'Print', onclick: 'printShipment(this)' }],
        };

        if (!data.isInvoiceHas) {
            contextMenuData.actions.push({ id: 'edit', text: 'Edit', dataId: data.id });
        }

        if (isDeveloperUser()) {
            contextMenuData.actions.push({
                id: 'delete-shipment',
                text: 'Delete',
                onclick: `submitResourceDelete('/shipments/${data.id}')`,
            });
        }

        createContextMenu(contextMenuData);
    };

    window.generateModal = function generateModal(item) {
        const data = JSON.parse(item.dataset.json);

        const modalData = {
            id: 'modalForm',
            preview: { type: 'shipment', size: 'A5', data: data.data, document: 'Shipment' },
            bottomActions: [{ id: 'print', text: 'Print Shipment', onclick: 'printShipment(this)' }],
        };

        if (!data.isInvoiceHas) {
            modalData.bottomActions.push({ id: 'edit', text: 'Edit', dataId: data.id });
        }

        if (isDeveloperUser()) {
            modalData.bottomActions.push({
                id: 'delete-shipment',
                text: 'Delete',
                onclick: `submitResourceDelete('/shipments/${data.id}')`,
            });
        }

        createModal(modalData);
    };

    function initShipmentsIndex(data) {
        setAuthLayout(data);
    }

    window.initShipmentsIndex = initShipmentsIndex;

    function boot() {
        if (window.__shipmentsIndex) {
            initShipmentsIndex(window.__shipmentsIndex);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
