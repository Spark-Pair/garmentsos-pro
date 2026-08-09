(() => {
function initVouchersIndex() {
    const config = window.__vouchersIndex || {};
    const companyData = config.companyData;
    const authLayout = config.authLayout;

    if (companyData) {
        window.companyData = companyData;
    }
    if (config.companyLogoBase) {
        window.companyLogoBase = config.companyLogoBase;
    }
    if (typeof authLayout !== 'undefined') {
        window.authLayout = authLayout;
    }

    window.createRow = function(data) {
        return `
            <div id="${data.id}" oncontextmenu='${htmlAttr(data.oncontextmenu || "")}' onclick='${htmlAttr(data.onclick || "")}'
                class="item row relative group grid text- grid-cols-4 border-b border-[var(--h-bg-color)] items-center py-2 cursor-pointer hover:bg-[var(--h-secondary-bg-color)] transition-all fade-in ease-in-out"
                data-json='${jsonAttr(data)}'>

                <span class="text-center">${data.details["Supplier"]}</span>
                <span class="text-center">${data.name}</span>
                <span class="text-center">${data.details['Date']}</span>
                <span class="text-center">${data.details['Amount']}</span>
            </div>
        `;
    }

    window.printVoucher = function(elem) {
        closeAllDropdowns();

        const openedFromContextMenu = elem.parentElement.tagName.toLowerCase() === 'li';
        if (openedFromContextMenu) {
            elem.parentElement.parentElement.querySelector('#show-details').click();
            document.getElementById('modalForm')?.parentElement.classList.add('hidden');
        }

        const preview = document.getElementById('preview-container');
        if (!preview) return;

        window.DocumentPrint.printPreview({
            title: 'Print Voucher',
            preview,
            delay: 1000,
            beforePrint: printDocument => {
                const voucherCopy = printDocument.querySelector('#preview-container .preview-copy');
                if (voucherCopy) voucherCopy.textContent = 'Voucher Copy: Office';
            },
            afterPrint: () => {
                if (openedFromContextMenu) document.getElementById('modalForm')?.parentElement.remove();
            },
        });
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
                {id: 'print', text: 'Print Voucher', onclick: 'printVoucher(this)'},
                {id: 'edit', text: 'Edit'}
            ]
        }

        if (isDeveloperUser()) {
            contextMenuData.actions.push({
                id: 'delete-voucher',
                text: 'Delete',
                onclick: `submitResourceDelete('/vouchers/${data.id}')`,
            });
        }

        createContextMenu(contextMenuData);
    }

    window.generateModal = function(item) {
        let data = JSON.parse(item.dataset.json);

        data.data.total_payment = data.total_payment;
        data.data.previous_balance = data.previous_balance;

        let modalData = {
            id: 'modalForm',
            preview: {type: 'voucher', data: data.data, document: 'Voucher'},
            bottomActions: [
                {id: 'print', text: 'Print Voucher', onclick: 'printVoucher(this)'},
                {id: 'edit', text: 'Edit', dataId: data.id}
            ],
        }

        if (isDeveloperUser()) {
            modalData.bottomActions.push({
                id: 'delete-voucher',
                text: 'Delete',
                onclick: `submitResourceDelete('/vouchers/${data.id}')`,
            });
        }

        createModal(modalData);
    }
}

window.initVouchersIndex = initVouchersIndex;

function boot() {
    if (window.__vouchersIndex) initVouchersIndex();
}

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", boot);
} else {
    boot();
}
})();
