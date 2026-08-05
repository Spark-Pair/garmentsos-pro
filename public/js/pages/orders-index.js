(function () {
    let pendingOpenOrderId = null;
    const canEditOrderRoles = ['developer', 'owner', 'admin', 'accountant'];
    const isDeveloper = () => window.__currentUserRole === 'developer';

    function setAuthLayout(data) {
        if (data?.authLayout) {
            window.authLayout = data.authLayout;
        }
        if (data?.companyData) {
            window.companyData = data.companyData;
        }
        if (data?.openOrderId) {
            pendingOpenOrderId = String(data.openOrderId);
        }
        if (data?.currentUserRole) {
            window.__currentUserRole = data.currentUserRole;
        }
    }

    window.createRow = function createRow(data) {
        return `
            <div id="${data.id}" oncontextmenu='${htmlAttr(data.oncontextmenu || "")}' onclick='${htmlAttr(data.onclick || "")}'
                class="item row relative group grid grid-cols-7 border-b border-[var(--h-bg-color)] items-center py-2 cursor-pointer hover:bg-[var(--h-secondary-bg-color)] transition-all fade-in ease-in-out"
                data-json='${jsonAttr(data)}'>

                <span class="text-center">${data.date ?? data.details['Date'] ?? '-'}</span>
                <span class="text-center">${data.order_no ?? data.name ?? '-'}</span>
                <span class="text-center">${data.customer_name ?? data.details["Customer"] ?? '-'}</span>
                <span class="text-center">${formatNumbersWithDigits(data.discount ?? 0, 1, 1)}%</span>
                <span class="text-center">${formatNumbersWithDigits(data.net_amount ?? 0, 1, 1)}</span>
                <span class="text-center">${formatNumbersWithDigits(data.balance_order ?? 0, 0, 0)} Pcs</span>
                <span class="text-center capitalize">${data.status}</span>
            </div>`;
    };

    window.printOrder = function printOrder(elem) {
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
                        <title>Print Order</title>
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

            const orderCopys = printDocument.querySelectorAll('#preview-container .preview-copy');
            if (orderCopys) {
                orderCopys.forEach(orderCopy => {
                    orderCopy.textContent = 'Order Copy: Office';
                });
            }

            printIframe.contentWindow.onafterprint = () => {};

            setTimeout(() => {
                printIframe.contentWindow.focus();
                printIframe.contentWindow.print();
            }, 1000);

            document.getElementById('modalForm').parentElement.remove();
        };
    };

    window.deleteOrder = function deleteOrder(orderId) {
        if (!isDeveloper() || !orderId) return;

        const form = document.createElement('form');
        form.method = 'POST';
        form.action = `${(window.__ordersIndex?.ordersBaseUrl || '/orders').replace(/\/+$/, '')}/${orderId}`;
        form.className = 'hidden';
        form.innerHTML = `
            <input type="hidden" name="_token" value="${document.querySelector('meta[name="csrf-token"]')?.content || ''}">
            <input type="hidden" name="_method" value="DELETE">
        `;
        document.body.appendChild(form);
        form.submit();
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
            actions: [{ id: 'print', text: 'Print Order', onclick: 'printOrder(this)' }],
        };

        if ((data.status == 'pending' && canEditOrderRoles.includes(window.__currentUserRole)) || isDeveloper()) {
            contextMenuData.actions.push({ id: 'edit', text: 'Edit', dataId: data.id });
        }
        if (isDeveloper()) {
            contextMenuData.actions.push({ id: 'delete-order', text: 'Delete', onclick: `deleteOrder(${data.id})` });
        }

        createContextMenu(contextMenuData);
    };

    window.generateModal = function generateModal(item) {
        const data = JSON.parse(item.dataset.json);

        const modalData = {
            id: 'modalForm',
            preview: { type: 'order', size: 'A5', data: data.data, document: 'Sales Order' },
            bottomActions: [{ id: 'print', text: 'Print Order', onclick: 'printOrder(this)' }],
        };

        if ((data.status == 'pending' && canEditOrderRoles.includes(window.__currentUserRole)) || isDeveloper()) {
            modalData.bottomActions.push({ id: 'edit', text: 'Edit', dataId: data.id });
        }
        if (isDeveloper()) {
            modalData.bottomActions.push({ id: 'delete-order', text: 'Delete', onclick: `deleteOrder(${data.id})` });
        }

        createModal(modalData);
    };

    function initOrdersIndex(data) {
        setAuthLayout(data);

        document.addEventListener('app:data:rendered', () => {
            if (!pendingOpenOrderId) return;

            const targetRow = document.getElementById(pendingOpenOrderId);
            if (!targetRow) return;

            pendingOpenOrderId = null;
            targetRow.scrollIntoView({ block: 'center', behavior: 'smooth' });
            targetRow.classList.add('ring-2', 'ring-[var(--border-success)]');

            setTimeout(() => {
                targetRow.classList.remove('ring-2', 'ring-[var(--border-success)]');
            }, 3000);

            generateModal(targetRow);

            const url = new URL(window.location.href);
            url.searchParams.delete('open_order');
            window.history.replaceState({}, '', url.toString());
        }, { once: false });
    }

    window.initOrdersIndex = initOrdersIndex;

    function boot() {
        if (window.__ordersIndex) {
            initOrdersIndex(window.__ordersIndex);
        }
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", boot);
    } else {
        boot();
    }
})();
