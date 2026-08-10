(() => {
    function initProductionsIndex() {
        const config = window.__productionsIndex || {};
        window.authLayout = config.authLayout || "table";
        if (config.companyData) {
            window.companyData = config.companyData;
        }

        function partQuantitiesText(data) {
            const parts = Array.isArray(data.part_quantities) && data.part_quantities.length
                ? data.part_quantities
                : (Array.isArray(data.parts) ? data.parts.map((part) => ({ part, quantity: data.quantity })) : []);

            if (!parts.length) return "-";

            return parts
                .map((item) => `${item.part || "-"} (${formatNumbersWithDigits(item.quantity || 0, 1, 1)})`)
                .join(", ");
        }

        function partQuantitiesHtml(data) {
            const parts = Array.isArray(data.part_quantities) && data.part_quantities.length
                ? data.part_quantities
                : (Array.isArray(data.parts) ? data.parts.map((part) => ({ part, quantity: data.quantity })) : []);

            if (!parts.length) return `<div class="text-center text-sm text-[var(--secondary-text)]">No parts found.</div>`;

            return `
                <div class="overflow-hidden rounded-lg border border-gray-600">
                    <div class="grid grid-cols-[3rem_minmax(0,1fr)_7rem] bg-[var(--h-bg-color)] px-3 py-2 text-xs font-semibold">
                        <span>S.No</span>
                        <span>Part</span>
                        <span class="text-right">Quantity</span>
                    </div>
                    ${parts.map((item, index) => `
                        <div class="grid grid-cols-[3rem_minmax(0,1fr)_7rem] border-t border-[var(--h-bg-color)] px-3 py-2 text-sm">
                            <span>${index + 1}</span>
                            <span class="truncate capitalize">${item.part || "-"}</span>
                            <span class="text-right">${formatNumbersWithDigits(item.quantity || 0, 1, 1)}</span>
                        </div>
                    `).join("")}
                </div>
            `;
        }

        function hasRecordId(data) {
            return data?.id !== null
                && typeof data?.id !== "undefined"
                && String(data.id).trim() !== ""
                && String(data.id) !== "undefined";
        }

        window.createRow = function createRow(data) {
            return `
            <div id="${data.id}" oncontextmenu='${htmlAttr(data.oncontextmenu || "")}' onclick='${htmlAttr(data.onclick || "")}'
                class="item row relative group flex border-b border-[var(--h-bg-color)] items-center py-2 cursor-pointer hover:bg-[var(--h-secondary-bg-color)] transition-all fade-in ease-in-out"
                data-json='${jsonAttr(data)}'>

                <span class="w-1/8" >${data.article_no}</span>
                <span class="w-1/8" >${data.issue_date}</span>
                <span class="w-1/8" >${data.receive_date}</span>
                <span class="w-1/8" >${data.ticket}</span>
                <span class="w-1/7" >${data.worker_name}</span>
                <span class="w-1/8" >${data.movement_type || "-"}</span>
                <span class="w-1/8" title="${htmlAttr(partQuantitiesText(data))}">${formatNumbersWithDigits(data.quantity ?? 0, 1, 1)}</span>
                <span class="w-1/8" >${formatNumbersWithDigits(data.rate ?? 0, 2, 2)}</span>
                <span class="w-1/8" >${formatNumbersWithDigits(data.amount ?? 0, 1, 1)}</span>
            </div>`;
        };

        window.generateContextMenu = function generateContextMenu(e) {
            e.preventDefault();
            const item = e.target.closest(".item");
            const rowData = JSON.parse(item.dataset.json);
            const data = rowData.data || rowData;

            const actions = [
                {
                    id: "print-ticket",
                    text: "Print Ticket",
                    onclick: `printProductionTicket(${JSON.stringify(data)})`,
                },
            ];

            if (isDeveloperUser()) {
                actions.push({
                    id: "edit-production",
                    text: "Edit",
                    onclick: `generateEditProductionModal(${JSON.stringify(data)})`,
                });
                if (hasRecordId(data)) {
                    actions.push({
                        id: "delete-production",
                        text: "Delete",
                        onclick: `submitResourceDelete('/productions/${data.id}')`,
                    });
                }
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
            const rowData = JSON.parse(item.dataset.json);
            const data = rowData.data || rowData;
            const article = data.article || {};
            const worker = data.worker || {};
            const work = data.work || {};
            const details = {
                Article: article.article_no || data.article_no,
                Work: work.title || "-",
                Worker: worker.employee_name || "-",
                Type: data.movement_type || (data.issue_date ? "Issue" : "Receive"),
                "Issue Date": data.issue_date ? formatDate(data.issue_date) : '-',
                "Receive Date": data.receive_date ? formatDate(data.receive_date) : '-',
                "Issued By": data.issued_by_name || '-',
                "Received By": data.received_by_name || '-',
                Quantity: formatNumbersWithDigits(data.quantity ?? 0, 1, 1),
                Rate: data.rate ? formatNumbersWithDigits(data.rate, 2, 2) : '-',
                Amount: data.amount ? formatNumbersWithDigits(data.amount, 1, 1) : '-',
            };

            if (data.parent_ticket) {
                details["Parent Ticket"] = data.parent_ticket;
            }

            const bottomActions = [
                {
                    id: "preview-ticket",
                    text: "Preview Ticket",
                    onclick: `showProductionTicket(${JSON.stringify(data)})`,
                },
                {
                    id: "print-ticket",
                    text: "Print Ticket",
                    onclick: `printProductionTicket(${JSON.stringify(data)})`,
                },
            ];

            if (isDeveloperUser()) {
                bottomActions.push({
                    id: "edit-production",
                    text: "Edit",
                    onclick: `generateEditProductionModal(${JSON.stringify(data)})`,
                });
                if (hasRecordId(data)) {
                    bottomActions.push({
                        id: "delete-production",
                        text: "Delete",
                        onclick: `submitResourceDelete('/productions/${data.id}')`,
                    });
                }
            }

            createModal({
                id: "modalForm",
                name: `Ticket ${data.ticket || "-"}`,
                class: "max-w-3xl h-auto",
                details,
                fieldsGridCount: "1",
                fields: [
                    {
                        category: "explicitHtml",
                        full: true,
                        html: `<div class="mt-2">${partQuantitiesHtml(data)}</div>`,
                    },
                ],
                bottomActions,
            });
        };

        window.generateEditProductionModal = function generateEditProductionModal(data) {
            if (!hasRecordId(data)) {
                appAlert("Production record ID is missing. Please refresh and try again.", "error");
                return;
            }

            const isIssue = Boolean(data.issue_date);
            const dateLabel = isIssue ? "Issue Date" : "Receive Date";
            const dateName = isIssue ? "issue_date" : "receive_date";
            const dateValue = isIssue ? (data.issue_date || "") : (data.receive_date || "");

            createModal({
                id: "editProductionModal",
                name: `Edit Ticket ${data.ticket || "-"}`,
                action: `/productions/${data.id}`,
                method: "POST",
                class: "max-w-xl h-auto",
                fieldsGridCount: "2",
                fields: [
                    {
                        category: "explicitHtml",
                        full: true,
                        html: `
                            <input type="hidden" name="_method" value="PUT">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="form-group">
                                    <label class="block mb-2 font-medium text-[var(--secondary-text)]">${dateLabel}</label>
                                    <input type="date" name="${dateName}" value="${dateValue}" required class="w-full rounded-lg border border-gray-600 bg-[var(--h-bg-color)] px-3 py-2 text-[var(--text-color)]">
                                </div>
                                <div class="form-group">
                                    <label class="block mb-2 font-medium text-[var(--secondary-text)]">Rate</label>
                                    <input type="number" step="0.01" min="0" name="rate" value="${data.rate || ""}" class="w-full rounded-lg border border-gray-600 bg-[var(--h-bg-color)] px-3 py-2 text-[var(--text-color)]">
                                </div>
                                <div class="form-group">
                                    <label class="block mb-2 font-medium text-[var(--secondary-text)]">Issued By</label>
                                    <input type="text" name="issued_by_name" value="${htmlAttr(data.issued_by_name || "")}" class="w-full rounded-lg border border-gray-600 bg-[var(--h-bg-color)] px-3 py-2 text-[var(--text-color)]">
                                </div>
                                <div class="form-group">
                                    <label class="block mb-2 font-medium text-[var(--secondary-text)]">Received By</label>
                                    <input type="text" name="received_by_name" value="${htmlAttr(data.received_by_name || "")}" class="w-full rounded-lg border border-gray-600 bg-[var(--h-bg-color)] px-3 py-2 text-[var(--text-color)]">
                                </div>
                                <div class="form-group md:col-span-2">
                                    <label class="block mb-2 font-medium text-[var(--secondary-text)]">Amount</label>
                                    <input type="number" step="0.01" min="0" name="amount" value="${data.amount || ""}" class="w-full rounded-lg border border-gray-600 bg-[var(--h-bg-color)] px-3 py-2 text-[var(--text-color)]">
                                </div>
                            </div>
                        `,
                    },
                ],
                bottomActions: [
                    { id: "save", text: "Save", onclick: "document.getElementById('editProductionModal').requestSubmit()" },
                ],
            });
        };
    }

    window.initProductionsIndex = initProductionsIndex;

    function boot() {
        if (window.__productionsIndex) {
            initProductionsIndex();
        }
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", boot);
    } else {
        boot();
    }
})();
