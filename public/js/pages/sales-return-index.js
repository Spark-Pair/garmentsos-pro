(function () {
    const totalAmountDom = document.querySelector('#calc-bottom > .total-Amount .text-right');

    window.renderCalculation = function renderCalculation(data) {
        if (totalAmountDom) {
            totalAmountDom.innerText = formatNumbersWithDigits(data?.total_amount ?? 0, 1, 1);
        }
    };

    window.createRow = function createRow(data) {
        return `
            <div id="${data.id}" oncontextmenu='${htmlAttr(data.oncontextmenu || "")}' onclick='${htmlAttr(data.onclick || "")}'
                class="item row relative group grid grid-cols-[0.5fr_1.25fr_1.8fr_1.15fr_1.15fr_0.85fr_0.9fr_1.05fr] border-b border-[var(--h-bg-color)] items-center py-2 cursor-pointer hover:bg-[var(--h-secondary-bg-color)] transition-all fade-in ease-in-out"
                data-json='${jsonAttr(data)}'>

                <span>${data.id}</span>
                <span>${data.date}</span>
                <span>${data.customer}</span>
                <span>${data.article_no}</span>
                <span>${data.invoice_no}</span>
                <span>${data.type === 'adjustment' ? 'Adjustment' : 'Return'}</span>
                <span>${data.quantity + ' - PCs'}</span>
                <span>${formatMoney(data.amount)}</span>
            </div>`;
    };

    window.generateContextMenu = function generateContextMenu(e) {
        e.preventDefault();
        const item = e.target.closest('.item');
        if (!item) return;
        const data = JSON.parse(item.dataset.json);

        const actions = [];
        if (isDeveloperUser()) {
            actions.push({
                id: 'delete-sales-return',
                text: 'Delete',
                onclick: `submitResourceDelete('/sales-returns/${data.id}')`,
            });
        }

        createContextMenu({
            item,
            data,
            x: e.pageX,
            y: e.pageY,
            actions,
        });
    };

    window.generateModal = function generateModal(item) {
        const data = JSON.parse(item.dataset.json);

        const modalData = {
            id: 'modalForm',
            name: data.article_no,
            details: {
                ID: data.id,
                Date: data.date,
                Customer: data.customer,
                'Invoice No.': data.invoice_no,
                Type: data.type === 'adjustment' ? 'Adjustment' : 'Return',
                Quantity: `${data.quantity} - PCs`,
                Amount: formatMoney(data.amount),
            },
            bottomActions: [],
        };

        if (isDeveloperUser()) {
            modalData.bottomActions.push({
                id: 'delete-sales-return',
                text: 'Delete',
                onclick: `submitResourceDelete('/sales-returns/${data.id}')`,
            });
        }

        createModal(modalData);
    };

    function initSalesReturnIndex(data) {
        if (data?.authLayout) {
            window.authLayout = data.authLayout;
        }

        const listContainer = document.querySelector('.search_container');
        if (listContainer) {
            listContainer.addEventListener('click', (e) => {
                const row = e.target.closest('.item');
                if (!row || !row.dataset.json) return;
                window.generateModal(row);
            });

            listContainer.addEventListener('contextmenu', (e) => {
                const row = e.target.closest('.item');
                if (!row || !row.dataset.json) return;
                e.preventDefault();
                window.generateContextMenu(e);
            });
        }
    }

    window.initSalesReturnIndex = initSalesReturnIndex;

    function boot() {
        if (window.__salesReturnIndex) {
            initSalesReturnIndex(window.__salesReturnIndex);
        }
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", boot);
    } else {
        boot();
    }
})();
