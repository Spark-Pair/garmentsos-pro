(() => {
function initSupplierPaymentsIndex() {
    window.totalAmount = 0;
    window.totalPayment = 0;

    window.createRow = function(data) {
        return `
            <div id="${data.id}"
                class="item row relative group flex justify-between border-b border-[var(--h-bg-color)] items-center py-2 cursor-pointer hover:bg-[var(--h-secondary-bg-color)] transition-all fade-in ease-in-out"
                data-json='${jsonAttr(data)}'>

                <span class="text-center w-1/8">${data.date}</span>
                <span class="text-center w-1/6">${data.name}</span>
                <span class="text-center w-1/8 capitalize">${data.method}</span>
                <span class="text-center w-1/8">${formatMoney(data.amount)}</span>
                <span class="text-center w-1/6">${data.source_name}</span>
                <span class="text-center w-1/8">${data.source_type}</span>
                <span class="text-center w-1/8">${data.reff_no ?? '-'}</span>
                <span class="text-center w-1/8">${data.voucher_no}</span>
            </div>
        `;
    }

    window.generateContextMenu = function generateContextMenu(e) {
        e.preventDefault();
        let item = e.target.closest(".item");
        if (!item) return;
        let data = JSON.parse(item.dataset.json);

        let contextMenuData = {
            item: item,
            data: data,
            x: e.pageX,
            y: e.pageY,
            actions: [],
        };

        if (isDeveloperUser()) {
            contextMenuData.actions.push({
                id: 'delete-supplier-payment',
                text: 'Delete',
                onclick: `submitResourceDelete('/supplier-payments/${data.id}')`,
            });
        }

        createContextMenu(contextMenuData);
    };

    window.generateModal = function(item) {
        let data = JSON.parse(item.dataset.json);
        const clearDetails = Array.isArray(data.clear_details) ? data.clear_details : [];
        const clearTableBody = clearDetails.map((row, index) => ([
            { data: index + 1, class: 'w-[5%]' },
            { data: row.date || '-', class: 'w-[16%]' },
            { data: row.method || '-', class: 'w-[12%] capitalize' },
            { data: ((row.account_title || '-') + ' | ' + (row.bank || '-')).trim(), class: 'w-[28%]' },
            { data: formatNumbersWithDigits(row.amount || 0, 1, 1), class: 'w-[12%]' },
            { data: row.reff_no || '-', class: 'w-[12%]' },
            { data: row.remarks || '-', class: 'grow' },
        ]));

        let details = {
            'Date': data.date,
            'Amount': data.amount,
            'Method': data.method,
            'Customer/Self Acc.': data.source_name || '-',
            'Source': data.source_type || '-',
            ...(data.bank_account && { 'Bank Account': `${data.bank_account.account_title || '-'} | ${data.bank_account.bank || '-'}` }),
            ...(data.self_account && { 'Self Account': `${data.self_account.account_title || '-'} | ${data.self_account.bank || '-'}` }),
            ...(data.transaction_id && { 'Transaction Id': data.transaction_id }),
            ...(String(data.method || '').toLowerCase().includes('program') && { 'Issued': data.issued || 'Not Issued' }),
            'Reff No.': data.reff_no ?? '-',
            'Voucher / CR No.': data.voucher_no ?? '-',
            'Remarks': data.remarks || 'No Remarks',
        };

        if (data.program_no || data.program_date || data.program_customer) {
            details.hr = true;
            details['Program No'] = data.program_no || '-';
            details['Program Date'] = data.program_date || '-';
            details['Program Customer'] = data.program_customer || '-';
            if (data.program_order_no) details['Order No'] = data.program_order_no;
        }

        if (data.cr_no || data.cr_date) {
            details.hr = true;
            details['CR No'] = data.cr_no || '-';
            details['CR Date'] = data.cr_date || '-';
        }

        if (data.dr_no || data.dr_date) {
            details.hr = true;
            details['DR No'] = data.dr_no || '-';
            details['DR Date'] = data.dr_date || '-';
        }

        let modalData = {
            id: 'modalForm',
            class: clearTableBody.length > 0 ? 'h-auto max-w-5xl' : 'h-auto',
            name: data.name,
            details,
            ...(clearTableBody.length > 0 ? {
                table: {
                    name: 'Clear Records',
                    headers: [
                        { label: '#', class: 'w-[5%]' },
                        { label: 'Date', class: 'w-[16%]' },
                        { label: 'Method', class: 'w-[12%]' },
                        { label: 'Acc. Title', class: 'w-[28%]' },
                        { label: 'Amount', class: 'w-[12%]' },
                        { label: 'Reff. No.', class: 'w-[12%]' },
                        { label: 'Remarks', class: 'grow' },
                    ],
                    body: clearTableBody,
                    scrollable: true,
                }
            } : {}),
            bottomActions: [],
        };

        if (isDeveloperUser()) {
            modalData.bottomActions.push({
                id: 'delete-supplier-payment',
                text: 'Delete',
                onclick: `submitResourceDelete('/supplier-payments/${data.id}')`,
            });
        }

        createModal(modalData);
    }

    const listContainer = document.querySelector('.search_container');
    if (listContainer) {
        listContainer.addEventListener('click', (e) => {
            const row = e.target.closest('.row');
            if (!row || !row.dataset.json) return;
            window.generateModal(row);
        });

        listContainer.addEventListener('contextmenu', (e) => {
            const row = e.target.closest('.row');
            if (!row || !row.dataset.json) return;
            e.preventDefault();
            window.generateContextMenu(e);
        });
    }
}

window.initSupplierPaymentsIndex = initSupplierPaymentsIndex;

function boot() {
    initSupplierPaymentsIndex();
}

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", boot);
} else {
    boot();
}
})();
