(() => {
    function initPhysicalQuantitiesIndex() {
        const config = window.__physicalQuantitiesIndex || {};
        window.authLayout = config.authLayout || "table";

        window.createRow = function createRow(data) {
            return `
            <div id="${data.id}" oncontextmenu='${htmlAttr(data.oncontextmenu || "")}' onclick='${htmlAttr(data.onclick || "")}'
                class="item row relative group flex border-b border-[var(--h-bg-color)] items-center py-2 cursor-pointer hover:bg-[var(--h-secondary-bg-color)] transition-all fade-in ease-in-out text-xs"
                data-json='${jsonAttr(data)}'>

                <div class="w-[7%]">${data.article_no}</div>
                <div class="w-[7%] capitalize">${data.processed_by}</div>
                <div class="w-[4%]">${data.unit}</div>
                <div class="w-[8%]">${data.orderable_quantity}</div>
                <div class="w-[8%]">${data.total_quantity}</div>
                <div class="w-[8%]">${data.received_quantity}</div>
                <div class="w-[8%]">${data.ordered_quantity}</div>
                <div class="w-[8%]">${data.invoiced_quantity}</div>
                <div class="w-[8%]">${data.return_quantity}</div>
                <div class="w-[8%]">${data.adjustment_quantity}</div>
                <div class="w-[8%]">${data.current_stock}</div>
                <div class="w-[8%]">${data.a_category}</div>
                <div class="w-[8%]">${data.b_category}</div>
                <div class="w-[8%]">${data.c_category}</div>
                <div class="w-[8%]">${data.remaining_quantity}</div>
                <div class="w-[8%]">${data.shipment}</div>
            </div>`;
        };

        window.generateContextMenu = function generateContextMenu(e) {
            e.preventDefault();
            const item = e.target.closest(".item");
            if (!item) return;
            const data = JSON.parse(item.dataset.json);

            const actions = [];
            if (isDeveloperUser()) {
                actions.push({
                    id: "edit-physical-quantity",
                    text: "Edit",
                    link: `/physical-quantities/${data.id}/edit`,
                });
                actions.push({
                    id: "delete-physical-quantity",
                    text: "Delete",
                    onclick: `submitResourceDelete('/physical-quantities/${data.id}')`,
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
                id: "modalForm",
                name: data.article_no,
                details: {
                    "Processed By": data.processed_by,
                    Unit: data.unit,
                    "Total Qty.": `${data.total_quantity}`,
                    "Received Qty.": `${data.received_quantity} - Pkts.`,
                    "Ordered Qty.": `${data.ordered_quantity} - Pkts.`,
                    "Invoiced Qty.": `${data.invoiced_quantity} - Pkts.`,
                    "Return Qty.": `${data.return_quantity} - Pkts.`,
                    "Adjustment Qty.": `${data.adjustment_quantity} - Pkts.`,
                    "Current Stock": `${data.current_stock} - Pkts.`,
                    "Remaining Qty.": `${data.remaining_quantity} - Pkts.`,
                    Shipment: data.shipment,
                },
                bottomActions: [],
            };

            if (isDeveloperUser()) {
                modalData.bottomActions.push({
                    id: "edit-physical-quantity",
                    text: "Edit",
                    link: `/physical-quantities/${data.id}/edit`,
                });
                modalData.bottomActions.push({
                    id: "delete-physical-quantity",
                    text: "Delete",
                    onclick: `submitResourceDelete('/physical-quantities/${data.id}')`,
                });
            }

            createModal(modalData);
        };

        const listContainer = document.querySelector(".search_container");
        if (listContainer) {
            listContainer.addEventListener("click", (e) => {
                const row = e.target.closest(".item");
                if (!row || !row.dataset.json) return;
                window.generateModal(row);
            });

            listContainer.addEventListener("contextmenu", (e) => {
                const row = e.target.closest(".item");
                if (!row || !row.dataset.json) return;
                e.preventDefault();
                window.generateContextMenu(e);
            });
        }
    }

    window.initPhysicalQuantitiesIndex = initPhysicalQuantitiesIndex;

    function boot() {
        if (window.__physicalQuantitiesIndex) {
            initPhysicalQuantitiesIndex();
        }
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", boot);
    } else {
        boot();
    }
})();
