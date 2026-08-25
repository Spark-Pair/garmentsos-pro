(() => {
    function initInventoryIndex() {
        const config = window.__inventoryIndex || {};
        window.authLayout = config.authLayout || "table";
        const role = config.currentUserRole || '';
        const canUpdate = ['developer', 'owner', 'admin', 'store_keeper'].includes(role);
        const canDelete = ['developer', 'owner', 'admin'].includes(role);

        function formatLabel(value) {
            const text = String(value || "-").replaceAll("_", " ");
            return text === "-" ? text : text.charAt(0).toUpperCase() + text.slice(1);
        }

        window.createRow = function createRow(data) {
            return `
                <div id="${data.row_id || data.id}" onclick='${htmlAttr(data.onclick || "")}' oncontextmenu='${htmlAttr(data.oncontextmenu || "")}'
                    class="item row relative group flex text-center border-b border-[var(--h-bg-color)] items-center py-2 cursor-pointer hover:bg-[var(--h-secondary-bg-color)] transition-all fade-in ease-in-out"
                    data-json='${jsonAttr(data)}'>
                    <span class="w-[9%]">${data.date || '-'}</span>
                    <span class="w-[15%] capitalize">${data.name}</span>
                    <span class="w-[8%] capitalize">${data.type}</span>
                    <span class="w-[10%] capitalize">${data.fabric}</span>
                    <span class="w-[15%] capitalize">${data.supplier_name || '-'}</span>
                    <span class="w-[10%]">${data.tag}</span>
                    <span class="w-[7%] capitalize">${data.unit}</span>
                    <span class="w-[8%]">${data.stock_quantity_formatted}</span>
                    <span class="w-[8%]">${data.unit_price == null ? '-' : formatMoney(data.unit_price)}</span>
                    <span class="w-[10%]">${data.amount == null ? '-' : formatMoney(data.amount)}</span>
                </div>`;
        };

        window.generateModal = function generateModal(item) {
            const row = JSON.parse(item.dataset.json);
            const data = row;
            const canReturn = canUpdate && Number(data.stock_quantity || 0) > 0 && (data.supplier_balances || []).length > 0;
            const bottomActions = canReturn ? [{ id: "return-inventory", text: "Return", link: `/inventory/${data.id}/return` }] : [];
            if (canUpdate) bottomActions.push({ id: "edit", text: "Edit", dataId: data.id });
            if (canDelete) bottomActions.push({ id: "delete-inventory", text: "Delete", onclick: `submitInventoryDelete(${data.id})` });

            createModal({
                id: "modalForm",
                name: data.name || "Inventory Item",
                class: "max-w-5xl h-[38rem] max-h-[calc(100vh-7rem)]",
                details: {
                    Type: formatLabel(data.type),
                    Fabric: formatLabel(data.fabric),
                    Tag: data.tag || "-",
                    Color: formatLabel(data.color),
                    Unit: formatLabel(data.unit),
                    "Stock Quantity": `${data.stock_quantity ?? "-"} ${data.unit || ""}`.trim(),
                    Supplier: data.supplier_name || "-",
                    "Purchase Rate": data.unit_price == null ? "-" : formatMoney(data.unit_price),
                    "Purchase Amount": data.amount == null ? "-" : formatMoney(data.amount),
                    Remarks: data.remarks || "-",
                },
                table: {
                    name: "Inventory Transactions",
                    headers: [{label:"Date",class:"w-[12%]"},{label:"Type",class:"w-[20%]"},{label:"Qty",class:"w-[10%]"},{label:"Article",class:"w-[12%]"},{label:"Worker",class:"w-[14%]"},{label:"Supplier",class:"w-[17%]"},{label:"Reference",class:"w-[15%]"}],
                    body: (data.transaction_history || []).map(row => [
                        {data: row.date || "-",class:"w-[12%]"}, {data: row.type || (row.direction === 'out' ? 'Issued' : 'Received'),class:"w-[20%]"}, {data: `${row.quantity} ${row.unit || ''}`,class:"w-[10%]"}, {data: row.article || "-",class:"w-[12%]"}, {data: row.worker || "-",class:"w-[14%]"}, {data: row.supplier || "-",class:"w-[17%]"}, {data: row.reference || "-",class:"w-[15%]"}
                    ]),
                    scrollable: true,
                },
                bottomActions,
            });
        };

        window.generateContextMenu = function generateContextMenu(e) {
            e.preventDefault();
            const item = e.target.closest(".item");
            const data = JSON.parse(item.dataset.json);
            const canReturn = canUpdate && Number(data.stock_quantity || 0) > 0 && (data.supplier_balances || []).length > 0;
            const actions = canReturn ? [{ id: "return-inventory", text: "Return", link: `/inventory/${data.id}/return` }] : [];
            if (canUpdate) actions.push({ id: "edit", text: "Edit" });
            if (canDelete) actions.push({ id: "delete-inventory", text: "Delete", onclick: `submitInventoryDelete(${data.id})` });

            createContextMenu({
                item,
                data,
                x: e.pageX,
                y: e.pageY,
                actions,
                onlyThisActions: actions.length > 0,
            });
        };

        window.submitInventoryDelete = function submitInventoryDelete(id) {
            submitDeleteForm(`/inventory/${id}`, config.csrfToken);
        };

    }

    function submitDeleteForm(action, csrfToken) {
        const form = document.createElement("form");
        form.method = "POST";
        form.action = action;
        form.innerHTML = `
            <input type="hidden" name="_token" value="${csrfToken || document.querySelector('meta[name="csrf-token"]')?.content || ""}">
            <input type="hidden" name="_method" value="DELETE">
        `;
        document.body.appendChild(form);
        form.submit();
    }

    window.initInventoryIndex = initInventoryIndex;

    function boot() {
        if (window.__inventoryIndex) {
            initInventoryIndex();
        }
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", boot);
    } else {
        boot();
    }
})();
