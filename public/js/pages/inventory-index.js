(() => {
    function initInventoryIndex() {
        const config = window.__inventoryIndex || {};
        window.authLayout = config.authLayout || "table";
        const canDeveloperManage = config.currentUserRole === "developer";

        window.createRow = function createRow(data) {
            return `
                <div id="${data.id}" onclick='${htmlAttr(data.onclick || "")}' oncontextmenu='${htmlAttr(data.oncontextmenu || "")}'
                    class="item row relative group grid grid-cols-7 text-center border-b border-[var(--h-bg-color)] items-center py-2 cursor-pointer hover:bg-[var(--h-secondary-bg-color)] transition-all fade-in ease-in-out"
                    data-json='${jsonAttr(data)}'>
                    <span>${data.name}</span>
                    <span>${data.type}</span>
                    <span>${data.fabric}</span>
                    <span>${data.tag}</span>
                    <span>${data.unit}</span>
                    <span>${data.stock_quantity_formatted}</span>
                    <span>${data.status}</span>
                </div>`;
        };

        window.generateModal = function generateModal(item) {
            const row = JSON.parse(item.dataset.json);
            const data = row.data || row;
            const bottomActions = canDeveloperManage ? [
                { id: "edit", text: "Edit", dataId: data.id },
                { id: "delete-inventory", text: "Delete", onclick: `submitInventoryDelete(${data.id})` },
            ] : [];

            createModal({
                id: "modalForm",
                name: data.name || "Inventory Item",
                class: "max-w-2xl h-auto",
                details: {
                    Type: data.type || "-",
                    Fabric: data.fabric || "-",
                    Tag: data.tag || "-",
                    Color: data.color || "-",
                    Unit: data.unit || "-",
                    "Stock Quantity": data.stock_quantity ?? "-",
                    Status: data.is_active ? "Active" : "Inactive",
                    Remarks: data.remarks || "-",
                },
                bottomActions,
            });
        };

        window.generateContextMenu = function generateContextMenu(e) {
            e.preventDefault();
            const item = e.target.closest(".item");
            const data = JSON.parse(item.dataset.json);
            const actions = canDeveloperManage ? [
                { id: "edit", text: "Edit" },
                { id: "delete-inventory", text: "Delete", onclick: `submitInventoryDelete(${data.id})` },
            ] : [];

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
