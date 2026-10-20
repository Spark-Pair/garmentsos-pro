(() => {
    function initBiltiesShow() {
        const config = window.__biltiesShow || {};
        window.authLayout = config.authLayout || "table";

        window.createRow = function createRow(data) {
            return `
            <div id="${data.id}" oncontextmenu='generateContextMenu(event)' onclick='generateModal(this)'
                class="item row relative group grid grid-cols-7 text-center border-b border-[var(--h-bg-color)] items-center py-2 cursor-pointer hover:bg-[var(--h-secondary-bg-color)] transition-all fade-in ease-in-out"
                data-json='${jsonAttr(data)}'>

                <span>${data.date}</span>
                <span class="col-span-2">${data.customer_name}</span>
                <span>${data.invoice_no}</span>
                <span>${data.amount}</span>
                <span>${data.cargo_name}</span>
                <span>${data.bilty_no}</span>
            </div>`;
        };

        window.generateContextMenu = function generateContextMenu(event) {
            event.preventDefault();
            const item = event.target.closest('.item');
            if (!item) return;

            const data = JSON.parse(item.dataset.json);
            const actions = [
                { id: 'edit-bilty', text: 'Edit', onclick: `window.location.href='/bilties/${data.id}/edit'` },
            ];

            if (isDeveloperUser()) {
                actions.push({
                    id: 'delete-bilty',
                    text: 'Delete',
                    onclick: `submitResourceDelete('/bilties/${data.id}')`,
                });
            }

            createContextMenu({ item, data, x: event.pageX, y: event.pageY, actions });
        };

        window.generateModal = function generateModal(item) {
            const data = JSON.parse(item.dataset.json);
            const modalData = {
                id: 'modalForm',
                name: data.bilty_no,
                details: {
                    'Date': data.date,
                    'Bilty No.': data.bilty_no,
                    'Invoice No.': data.invoice_no,
                    'Amount': data.amount,
                    'Customer': data.customer_name,
                    'Cargo Name': data.cargo_name || '-',
                },
                bottomActions: [
                    { id: 'edit-bilty', text: 'Edit', onclick: `window.location.href='/bilties/${data.id}/edit'` },
                ],
            };

            if (isDeveloperUser()) {
                modalData.bottomActions.push({
                    id: 'delete-bilty',
                    text: 'Delete',
                    onclick: `submitResourceDelete('/bilties/${data.id}')`,
                });
            }

            createModal(modalData);
        };
    }

    window.initBiltiesShow = initBiltiesShow;

    function boot() {
        if (window.__biltiesShow) {
            initBiltiesShow();
        }
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", boot);
    } else {
        boot();
    }
})();
