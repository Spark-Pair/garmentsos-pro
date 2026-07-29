(() => {
    function initReportsFabric() {
        const config = window.__reportsFabric || {};
        window.authLayout = config.authLayout || "table";

        function quantity(value, unit = "") {
            const number = parseFormattedNumber(value);
            const formatted = formatNumbersWithDigits(number, 3, 0);
            return unit && unit !== "-" ? `${formatted} ${unit}` : formatted;
        }

        function badge(source) {
            const text = String(source || "-");
            const color = text.includes("Received") || text.includes("Return") || text.includes("In")
                ? "text-[var(--border-success)]"
                : text.includes("Production") || text.includes("Issued") || text.includes("Out")
                    ? "text-[var(--border-error)]"
                    : "text-[var(--secondary-text)]";

            return `<span class="${color} font-semibold">${text}</span>`;
        }

        window.createRow = function createRow(data) {
            const unit = data.unit || "";
            const movementQuantity = parseFormattedNumber(data.received_quantity)
                || parseFormattedNumber(data.issued_quantity)
                || parseFormattedNumber(data.returned_quantity)
                || parseFormattedNumber(data.production_quantity);
            const party = data.worker_name && data.worker_name !== "-"
                ? data.worker_name
                : (data.supplier_name && data.supplier_name !== "-" ? data.supplier_name : "-");
            const tagRemarks = [data.tag, data.remarks].filter((value) => value && value !== "-").join(" | ") || "-";

            const wrapCell = "whitespace-normal break-words leading-snug px-3";
            const numberCell = "whitespace-normal break-words leading-snug px-3 text-right tabular-nums";

            return `
            <div id="${data.id}"
                class="item row relative group flex items-center border-b border-[var(--h-bg-color)] py-2.5 cursor-pointer hover:bg-[var(--h-secondary-bg-color)] transition-all fade-in ease-in-out text-[13px] text-left"
                data-json='${jsonAttr(data)}'>

                <span class="w-[11%] ${wrapCell}">${data.date || "-"}</span>
                <span class="w-[15%] ${wrapCell}">${party}</span>
                <span class="w-[10%] ${wrapCell}">${badge(data.source)}</span>
                <span class="w-[12%] font-semibold ${wrapCell}">${data.fabric || "-"}</span>
                <span class="w-[8%] ${wrapCell}">${data.color || "-"}</span>
                <span class="w-[7%] ${wrapCell}">${unit || "-"}</span>
                <span class="w-[11%] ${numberCell}" data-sort-value="${movementQuantity}">${quantity(movementQuantity, unit)}</span>
                <span class="grow ${wrapCell}" title="Ref: ${data.reference || "-"}">${tagRemarks}</span>
            </div>`;
        };

        window.renderCalculation = function renderCalculation(data) {
            const setText = (selector, value) => {
                const node = document.querySelector(`#calc-bottom > ${selector} .text-right`);
                if (node) node.innerText = quantity(value ?? 0);
            };

            setText(".total-received", parseFormattedNumber(data.total_received) + parseFormattedNumber(data.total_returned));
            setText(".total-out", parseFormattedNumber(data.total_issued) + parseFormattedNumber(data.total_production_used));
        };
    }

    window.initReportsFabric = initReportsFabric;

    function boot() {
        if (window.__reportsFabric) initReportsFabric();
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", boot);
    } else {
        boot();
    }
})();
