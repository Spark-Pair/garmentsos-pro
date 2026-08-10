(() => {
    function initPhysicalQuantitiesIndex() {
        const config = window.__physicalQuantitiesIndex || {};
        window.authLayout = config.authLayout || "table";

        window.createRow = function createRow(data) {
            return `
            <div id="${data.id}"
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
            const partialRecords = Array.isArray(data.partial_records) ? data.partial_records : [];
            const canManageRecords = isDeveloperUser();

            const modalData = {
                id: "modalForm",
                name: data.article_no,
                class: "max-w-5xl h-[34rem]",
                details: {
                    "Processed By": data.processed_by,
                    Unit: data.unit,
                    "Received Qty.": `${data.received_quantity} - Pkts.`,
                    "Current Stock": `${data.current_stock} - Pkts.`,
                    "Remaining Qty.": `${data.remaining_quantity} - Pkts.`,
                    Shipment: data.shipment,
                },
                table: {
                    scrollable: true,
                    headerPaddingClass: "px-3",
                    rowPaddingClass: "px-3",
                    headers: [
                        { label: "Date", class: "w-[16%] text-left" },
                        { label: "Category", class: "w-[19%] text-left" },
                        { label: "Packets", class: "w-[13%] text-center" },
                        { label: "Source", class: "w-[19%] text-left" },
                        { label: "Created By", class: "w-[19%] text-left" },
                        { label: "Actions", class: `${canManageRecords ? "w-[14%]" : "hidden"} text-right` },
                    ],
                    body: partialRecords.map((record) => [
                        { data: record.date || "-", class: "w-[16%] text-left" },
                        { data: record.category || "-", class: "w-[19%] text-left capitalize" },
                        { data: record.packets || "0", class: "w-[13%] text-center" },
                        { data: record.source || "-", class: "w-[19%] text-left" },
                        { data: record.created_by || "-", class: "w-[19%] text-left capitalize truncate" },
                        {
                            rawHTML: canManageRecords
                                ? `<div class="w-[14%] flex justify-end gap-1">
                                    <a href="/physical-quantities/${record.id}/edit" class="flex size-8 items-center justify-center rounded-lg text-[var(--secondary-text)] transition-all duration-300 ease-in-out hover:bg-[var(--h-bg-color)] hover:text-[var(--text-color)]" title="Edit" aria-label="Edit">
                                        <i class="fas fa-pen text-xs"></i>
                                    </a>
                                    <button type="button" onclick="event.stopPropagation(); submitResourceDelete('/physical-quantities/${record.id}')" class="flex size-8 items-center justify-center rounded-lg text-[var(--border-error)] transition-all duration-300 ease-in-out hover:bg-[var(--bg-error)] hover:text-[var(--text-error)]" title="Delete" aria-label="Delete">
                                        <i class="fas fa-trash text-xs"></i>
                                    </button>
                                </div>`
                                : `<div class="hidden"></div>`,
                        },
                    ]),
                },
                bottomActions: [],
            };

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
