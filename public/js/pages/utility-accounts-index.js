(() => {
function initUtilityAccountsIndex() {
    let authLayout = 'table';

    window.createRow = function(data) {
        return `
        <div id="${data.id}" oncontextmenu='${htmlAttr(data.oncontextmenu || "")}' onclick='${htmlAttr(data.onclick || "")}'
            class="item row relative group grid grid-cols-4 border-b border-[var(--h-bg-color)] items-center py-2 cursor-pointer hover:bg-[var(--h-secondary-bg-color)] transition-all fade-in ease-in-out"
            data-json='${jsonAttr(data)}'>

            <span class="capitalize">${data.bill_type}</span>
            <span class="capitalize">${data.location}</span>
            <span class="capitalize">${data.account_title}</span>
            <span class="capitalize">${data.account_no}</span>
        </div>`;
    }

    window.generateContextMenu = function(e) {
        e.preventDefault();
        const item = e.target.closest('.item');
        if (!item) return;
        const data = JSON.parse(item.dataset.json);

        const actions = [
            { id: 'edit', text: 'Edit' },
        ];

        if (isDeveloperUser()) {
            actions.push({
                id: 'delete-utility-account',
                text: 'Delete',
                onclick: `submitResourceDelete('/utility-accounts/${data.id}')`,
            });
        }

        createContextMenu({
            item,
            data,
            x: e.pageX,
            y: e.pageY,
            actions,
            onlyThisActions: true,
        });
    }

    window.generateModal = function(item) {
        const data = JSON.parse(item.dataset.json);

        const modalData = {
            id: 'modalForm',
            name: data.account_title,
            details: {
                'Bill Type': data.bill_type,
                'Location': data.location,
                'Account Title': data.account_title,
                'Account No.': data.account_no,
            },
            bottomActions: [
                { id: 'edit', text: 'Edit', dataId: data.id },
            ],
        };

        if (isDeveloperUser()) {
            modalData.bottomActions.push({
                id: 'delete-utility-account',
                text: 'Delete',
                onclick: `submitResourceDelete('/utility-accounts/${data.id}')`,
            });
        }

        createModal(modalData);
    }
}

window.initUtilityAccountsIndex = initUtilityAccountsIndex;

function boot() {
    initUtilityAccountsIndex();
}

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", boot);
} else {
    boot();
}
})();
