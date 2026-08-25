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

        const openedFromContextMenu = elem.parentElement.tagName.toLowerCase() === 'li';
        if (openedFromContextMenu) {
            elem.parentElement.parentElement.querySelector('#show-details').click();
            document.getElementById('modalForm')?.parentElement.classList.add('hidden');
        }

        const preview = document.getElementById('preview-container');
        if (!preview) return;

        window.DocumentPrint.printPreview({
            title: 'Print Shipment',
            preview,
            delay: 1000,
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
        if (data?.openRecordId) {
            document.addEventListener('app:data:rendered', function openSavedShipment() {
                const row = document.getElementById(String(data.openRecordId));
                if (!row) return;
                document.removeEventListener('app:data:rendered', openSavedShipment);
                generateModal(row);
                if (data.printOpenRecord) window.setTimeout(() => document.getElementById('print-in-modal')?.click(), 250);
                const url = new URL(window.location.href);
                url.searchParams.delete('open_shipment');
                url.searchParams.delete('print_shipment');
                window.history.replaceState({}, '', url);
            });
        }
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
