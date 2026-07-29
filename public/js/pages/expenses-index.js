(() => {
function initExpensesIndex() {
    let authLayout = 'table';
    let totalAmountDom = document.querySelector('#calc-bottom >.total-Amount .text-right');

    window.renderCalculation = function(data) {
        totalAmountDom.innerText = formatNumbersWithDigits(data?.total_amount ?? 0, 1, 1);
    }

    window.createRow = function(data) {
        return `
        <div id="${data.id}" oncontextmenu='${htmlAttr(data.oncontextmenu || "")}' onclick='${htmlAttr(data.onclick || "")}'
            class="item row relative group grid grid-cols-9 text-center border-b border-[var(--h-bg-color)] items-center py-2 cursor-pointer hover:bg-[var(--h-secondary-bg-color)] transition-all fade-in ease-in-out"
            data-json='${jsonAttr(data)}'>

            <span>${data.id}</span>
            <span>${data.date}</span>
            <span class="col-span-2">${data.supplier_name}</span>
            <span>${data.reff_no}</span>
            <span>${data.expense}</span>
            <span>${data.lot_no}</span>
            <span>${data.amount}</span>
            <span class="capitalize">${data.remarks}</span>
        </div>`;
    }

    window.generateContextMenu = function(e) {
        e.preventDefault();
        let item = e.target.closest('.item');
        let data = JSON.parse(item.dataset.json);

        let contextMenuData = {
            item: item,
            data: data,
            x: e.pageX,
            y: e.pageY,
            actions: [
                {id: 'edit', text: 'Edit'}
            ],
        };

        if (isDeveloperUser()) {
            contextMenuData.actions.push({
                id: 'delete-expense',
                text: 'Delete',
                onclick: `submitResourceDelete('/expenses/${data.id}')`,
            });
        }

        createContextMenu(contextMenuData);
    }

    window.generateModal = function(item) {
        let data = JSON.parse(item.dataset.json);

        let modalData = {
            id: 'modalForm',
            name: data.supplier_name,
            details: {
                'Date': data.date,
                'Reff. No.': data.reff_no,
                'Expense': data.expense,
                'Lot No.': data.lot_no,
                'Amount': data.amount,
                'Remarks': data.remarks,
            },
            bottomActions: [
                {id: 'edit', text: 'Edit', dataId: data.id}
            ],
        }

        if (isDeveloperUser()) {
            modalData.bottomActions.push({
                id: 'delete-expense',
                text: 'Delete',
                onclick: `submitResourceDelete('/expenses/${data.id}')`,
            });
        }

        createModal(modalData);
    }
}

window.initExpensesIndex = initExpensesIndex;

function boot() {
    initExpensesIndex();
}

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", boot);
} else {
    boot();
}
})();
