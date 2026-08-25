(function () {
    let pendingOpenOrderId = null;
    let printPendingOpenOrder = false;
    const canEditOrderRoles = ['developer', 'owner', 'admin', 'accountant'];
    const isDeveloper = () => isDeveloperUser('orders');

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
        printPendingOpenOrder = data?.printOpenOrder === true;
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

        const openedFromContextMenu = elem.parentElement.tagName.toLowerCase() === 'li';
        if (openedFromContextMenu) {
            elem.parentElement.parentElement.querySelector('#show-details').click();
            document.getElementById('modalForm')?.parentElement.classList.add('hidden');
        }

        const preview = document.getElementById('preview-container');
        if (!preview) return;

        window.DocumentPrint.printPreview({
            title: 'Print Order',
            preview,
            delay: 1000,
            beforePrint: printDocument => {
                printDocument.querySelectorAll('#preview-container .preview-copy').forEach(orderCopy => {
                    orderCopy.textContent = 'Order Copy: Office';
                });
            },
            afterPrint: () => {
                if (openedFromContextMenu) document.getElementById('modalForm')?.parentElement.remove();
            },
        });
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

            if (printPendingOpenOrder) {
                printPendingOpenOrder = false;
                window.setTimeout(() => {
                    const printButton = document.querySelector('#print-in-modal');
                    if (printButton) {
                        printOrder(printButton);
                    }
                }, 250);
            }

            const url = new URL(window.location.href);
            url.searchParams.delete('open_order');
            url.searchParams.delete('print_order');
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
