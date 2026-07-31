(() => {
    function initProductionsIndex() {
        const config = window.__productionsIndex || {};
        window.authLayout = config.authLayout || "table";
        if (config.companyData) {
            window.companyData = config.companyData;
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
                <span class="w-1/8" >${data.quantity}</span>
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
                    id: "delete-production",
                    text: "Delete",
                    onclick: `submitResourceDelete('/productions/${data.id}')`,
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
            const rowData = JSON.parse(item.dataset.json);
            const data = rowData.data || rowData;
            const article = data.article || {};
            const worker = data.worker || {};
            const work = data.work || {};

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
                    id: "delete-production",
                    text: "Delete",
                    onclick: `submitResourceDelete('/productions/${data.id}')`,
                });
            }

            createModal({
                id: "modalForm",
                name: `Ticket ${data.ticket || "-"}`,
                class: "max-w-3xl h-auto",
                details: {
                    Article: article.article_no || data.article_no,
                    Work: work.title || "-",
                    Worker: worker.employee_name || "-",
                    "Issue Date": data.issue_date ? formatDate(data.issue_date) : '-',
                    "Receive Date": data.receive_date ? formatDate(data.receive_date) : '-',
                    Rate: data.rate ? formatNumbersWithDigits(data.rate, 2, 2) : '-',
                    Amount: data.amount ? formatNumbersWithDigits(data.amount, 1, 1) : '-',
                },
                bottomActions,
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
