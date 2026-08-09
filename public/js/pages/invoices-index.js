(function () {
    function setAuthLayout(data) {
        if (data?.authLayout) {
            window.authLayout = data.authLayout;
        }
        if (data?.companyData) {
            window.companyData = data.companyData;
        }
    }

    function compactInvoicePrintHeaders(printDocument) {
        const labels = {
            'S.No': 'S.#',
            'Packets': 'Pkts',
            'Rate/Pc.': 'Rate',
        };

        printDocument.querySelectorAll('.gos-a5-invoice .thead .th').forEach(header => {
            const compactLabel = labels[header.textContent.trim()];
            if (compactLabel) {
                header.textContent = compactLabel;
            }
        });
    }

    function invoiceCopyMarkup(preview, copyLabel) {
        const wrapper = document.createElement('div');
        wrapper.className = 'invoice-print-copy';
        wrapper.innerHTML = preview.innerHTML;
        wrapper.querySelectorAll('.preview-copy').forEach(invoiceCopy => {
            invoiceCopy.textContent = `Invoice Copy: ${copyLabel}`;
        });
        return wrapper.outerHTML;
    }

    window.createRow = function createRow(data) {
        return `
                <div id="${data.id}" oncontextmenu='${htmlAttr(data.oncontextmenu || "")}' onclick='${htmlAttr(data.onclick || "")}'
                    class="item row relative group grid grid-cols-5 border-b border-[var(--h-bg-color)] items-center py-2 cursor-pointer hover:bg-[var(--h-secondary-bg-color)] transition-all fade-in ease-in-out"
                    data-json='${jsonAttr(data)}'>

                    <span class="text-center">${data.name}</span>
                    <span class="text-center">${data.details["Reff. No."]}</span>
                    <span class="text-center">${data.details["Customer"]}</span>
                    <span class="text-center">${data.details['Date']}</span>
                    <span class="text-center">${data.details['Amount']}</span>
                </div>`;
    };

    window.printInvoice = function printInvoice(elem) {
        closeAllDropdowns();

        const openedFromContextMenu = elem.parentElement.tagName.toLowerCase() === 'li';
        if (openedFromContextMenu) {
            elem.parentElement.parentElement.querySelector('#show-details').click();
            document.getElementById('modalForm')?.parentElement.classList.add('hidden');
        }

        const preview = document.getElementById('preview-container');
        if (!preview) return;

        window.DocumentPrint.printPreview({
            title: 'Print Invoice',
            preview,
            html: `
                ${invoiceCopyMarkup(preview, 'Customer')}
                ${invoiceCopyMarkup(preview, 'Office')}
            `,
            delay: 1000,
            extraStyle: `
                .invoice-print-copy {
                    width: 148mm !important;
                    height: auto !important;
                    overflow: visible !important;
                }

                .invoice-print-copy:last-child .preview:last-child {
                    break-after: auto;
                    page-break-after: auto;
                }
            `,
            beforePrint: printDocument => compactInvoicePrintHeaders(printDocument),
            afterPrint: () => {
                if (openedFromContextMenu) document.getElementById('modalForm')?.parentElement.remove();
            },
        });
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
            actions: [
                { id: 'print', text: 'Print Invoice', onclick: 'printInvoice(this)' },
            ],
        };

        if (isDeveloperUser()) {
            contextMenuData.actions.push({
                id: 'edit-invoice',
                text: 'Edit',
                link: `/invoices/${data.id}/edit`,
            });

            contextMenuData.actions.push({
                id: 'delete-invoice',
                text: 'Delete',
                onclick: `submitResourceDelete('/invoices/${data.id}')`,
            });
        }

        createContextMenu(contextMenuData);
    };

    window.generateModal = function generateModal(item) {
        const data = JSON.parse(item.dataset.json);

        const modalData = {
            id: 'modalForm',
            preview: { type: 'invoice', size: 'A5', data: data.data, document: 'Sales Invoice' },
            bottomActions: [
                { id: 'print', text: 'Print Invoice', onclick: 'printInvoice(this)' },
            ],
        };

        if (isDeveloperUser()) {
            modalData.bottomActions.push({
                id: 'edit-invoice',
                text: 'Edit',
                link: `/invoices/${data.id}/edit`,
            });

            modalData.bottomActions.push({
                id: 'delete-invoice',
                text: 'Delete',
                onclick: `submitResourceDelete('/invoices/${data.id}')`,
            });
        }

        createModal(modalData);
    };

    function initInvoicesIndex(data) {
        setAuthLayout(data);
    }

    window.initInvoicesIndex = initInvoicesIndex;

    function boot() {
        if (window.__invoicesIndex) {
            initInvoicesIndex(window.__invoicesIndex);
        }
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", boot);
    } else {
        boot();
    }
})();
