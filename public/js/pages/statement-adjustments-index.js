(() => {
    function initStatementAdjustmentsIndex() {
        const config = window.__statementAdjustmentsIndex || {};
        const canDeveloperManage = config.currentUserRole === 'developer';

        window.createRow = function createRow(data) {
            return `
                <div id="${data.id}" oncontextmenu='${htmlAttr(data.oncontextmenu || "")}' onclick='${htmlAttr(data.onclick || "")}'
                    class="item row relative group grid grid-cols-8 text-center border-b border-[var(--h-bg-color)] items-center py-2 cursor-pointer hover:bg-[var(--h-secondary-bg-color)] transition-all fade-in ease-in-out"
                    data-json='${jsonAttr(data)}'>
                    <span>${data.id}</span>
                    <span data-sort-value="${data.date_raw || ''}">${data.date}</span>
                    <span>${data.category}</span>
                    <span class="col-span-2">${data.name}</span>
                    <span class="capitalize">${data.entry_type}</span>
                    <span>${data.direction}</span>
                    <span data-sort-value="${data.amount_raw}">${data.amount}</span>
                </div>`;
        };

        window.createCard = function createCard(data) {
            return `
                <div id="${data.id}" oncontextmenu='${htmlAttr(data.oncontextmenu || "")}' onclick='${htmlAttr(data.onclick || "")}'
                    class="item card bg-[var(--h-bg-color)] border border-[var(--glass-border-color)]/20 rounded-lg p-4 text-left cursor-pointer hover:border-[var(--primary-color)] transition-all fade-in"
                    data-json='${jsonAttr(data)}'>
                    <div class="flex justify-between gap-3">
                        <h3 class="font-semibold text-[var(--text-color)]">${data.name}</h3>
                        <span class="text-sm">${data.amount}</span>
                    </div>
                    <div class="text-xs text-[var(--secondary-text)] mt-2">${data.category} | ${data.entry_type} | ${data.direction}</div>
                    <div class="text-xs text-[var(--secondary-text)] mt-1">${data.date}</div>
                </div>`;
        };

        window.generateContextMenu = function generateContextMenu(e) {
            e.preventDefault();
            const item = e.target.closest('.item');
            const data = JSON.parse(item.dataset.json);
            const actions = [
                { id: 'edit', text: 'Edit' },
            ];

            if (canDeveloperManage) {
                actions.push({
                    id: 'delete-balance-entry',
                    text: 'Delete',
                    onclick: `submitStatementAdjustmentDelete(${data.id})`,
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

            const bottomActions = [
                { id: 'edit', text: 'Edit', dataId: data.id },
            ];

            if (canDeveloperManage) {
                bottomActions.push({
                    id: 'delete-balance-entry',
                    text: 'Delete',
                    onclick: `submitStatementAdjustmentDelete(${data.id})`,
                });
            }

            createModal({
                id: 'modalForm',
                name: data.name,
                details: {
                    Date: data.date,
                    Category: data.category,
                    Entry: data.entry_type,
                    Transaction: data.direction,
                    Amount: data.amount,
                    Remarks: data.remarks,
                },
                bottomActions,
            });
        };

        window.submitStatementAdjustmentDelete = function submitStatementAdjustmentDelete(id) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = `/statement-adjustments/${id}`;
            form.innerHTML = `
                <input type="hidden" name="_token" value="${config.csrfToken || document.querySelector('meta[name="csrf-token"]')?.content || ''}">
                <input type="hidden" name="_method" value="DELETE">
            `;
            document.body.appendChild(form);
            form.submit();
        };
    }

    window.initStatementAdjustmentsIndex = initStatementAdjustmentsIndex;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initStatementAdjustmentsIndex);
    } else {
        initStatementAdjustmentsIndex();
    }
})();
