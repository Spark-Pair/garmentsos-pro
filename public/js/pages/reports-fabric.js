(() => {
    function initReportsFabric() {
        const config = window.__reportsFabric || {};
        const rowsPerFirstPage = 30;
        const rowsPerOtherPage = 34;
        let btnTypeGlobal = config.fabricReportType || "worker";
        let lastFabricRows = [];

        window.authLayout = config.authLayout || "table";
        window.__authLayout = window.authLayout;

        if (config.companyData) window.companyData = config.companyData;

        const form = document.getElementById("form");
        const previewContainer = document.getElementById("fabric-preview-container");
        const emptyState = document.getElementById("fabric-preview-empty");
        const dateStart = document.getElementById("date_range_start");
        const dateEnd = document.getElementById("date_range_end");
        const modeInput = document.getElementById("mode");

        function money(value) {
            return formatNumbersWithDigits(parseFormattedNumber(value || 0), 3, 0);
        }

        function quantity(value) {
            return money(value);
        }

        function escapeHtml(value) {
            return String(value ?? "-")
                .replaceAll("&", "&amp;")
                .replaceAll("<", "&lt;")
                .replaceAll(">", "&gt;")
                .replaceAll('"', "&quot;")
                .replaceAll("'", "&#039;");
        }

        function selectedMode() {
            return modeInput?.value || btnTypeGlobal || "worker";
        }

        function selectedModeLabel() {
            const mode = selectedMode();
            if (mode === "tag") return "Tag Wise";
            if (mode === "article") return "Article Wise";
            return "Worker Wise";
        }

        function columnsForMode(mode) {
            if (mode === "tag") {
                return [
                    ["#", "w-[5%]", "text-center"],
                    ["Fabric", "w-[24%]", "text-left"],
                    ["Tag", "w-[35%]", "text-left"],
                    ["Unit", "w-[8%]", "text-center"],
                    ["Receive", "w-[10%]", "text-right"],
                    ["Used", "w-[9%]", "text-right"],
                    ["Available", "w-[9%]", "text-right"],
                ];
            }

            if (mode === "article") {
                return [
                    ["#", "w-[5%]", "text-center"],
                    ["Fabric", "w-[24%]", "text-left"],
                    ["Tag", "w-[34%]", "text-left"],
                    ["Unit", "w-[8%]", "text-center"],
                    ["Article", "w-[19%]", "text-left"],
                    ["Used", "w-[10%]", "text-right"],
                ];
            }

            return [
                ["#", "w-[5%]", "text-center"],
                ["Worker", "w-[17%]", "text-left"],
                ["Tag", "w-[30%]", "text-left"],
                ["Unit", "w-[8%]", "text-center"],
                ["Receive", "w-[10%]", "text-right"],
                ["Used", "w-[10%]", "text-right"],
                ["Return", "w-[10%]", "text-right"],
                ["Available", "w-[10%]", "text-right"],
            ];
        }

        function cellClass(width, align = "text-left") {
            return `td font-medium min-w-0 ${width} ${align} px-2 leading-tight whitespace-normal break-words`;
        }

        function rowCells(row, mode, serial) {
            const unit = row.unit || "";

            if (mode === "tag") {
                return [
                    [serial + ".", "w-[5%]", "text-center"],
                    [row.fabric || "-", "w-[24%]"],
                    [row.tag || "-", "w-[35%]"],
                    [unit || "-", "w-[8%]", "text-center"],
                    [quantity(row.received_quantity || 0, unit), "w-[10%]", "text-right"],
                    [quantity(row.used_quantity || 0, unit), "w-[9%]", "text-right"],
                    [quantity(row.available_quantity || 0, unit), "w-[9%]", "text-right"],
                ];
            }

            if (mode === "article") {
                return [
                    [serial + ".", "w-[5%]", "text-center"],
                    [row.fabric || "-", "w-[24%]"],
                    [row.tag || "-", "w-[34%]"],
                    [unit || "-", "w-[8%]", "text-center"],
                    [row.article_no || "-", "w-[19%]"],
                    [quantity(row.used_quantity || 0, unit), "w-[10%]", "text-right"],
                ];
            }

            return [
                [serial + ".", "w-[5%]", "text-center"],
                [row.worker_name || "-", "w-[17%]"],
                [row.tag || "-", "w-[30%]"],
                [unit || "-", "w-[8%]", "text-center"],
                [quantity(row.received_quantity || 0, unit), "w-[10%]", "text-right"],
                [quantity(row.used_quantity || 0, unit), "w-[10%]", "text-right"],
                [quantity(row.returned_quantity || 0, unit), "w-[10%]", "text-right"],
                [quantity(row.available_quantity || 0, unit), "w-[10%]", "text-right"],
            ];
        }

        function tableHeader(mode) {
            return `
                <div class="thead w-full">
                    <div class="tr flex w-full px-2 py-1.5 bg-[var(--primary-color)] text-white text-[11px] rounded-md">
                        ${columnsForMode(mode).map(([label, width, align]) => `
                            <div class="th font-medium min-w-0 ${width} ${align || "text-left"} px-1">${escapeHtml(label)}</div>
                        `).join("")}
                    </div>
                </div>
            `;
        }

        function tableRows(rows, mode, offset = 0) {
            return rows.map((row, index) => `
                <div>
                    ${index === 0 ? "" : '<hr class="w-full my-1.5 border-gray-600">'}
                    <div class="tr flex w-full px-2 text-[12px] cursor-pointer hover:bg-gray-100" data-fabric-report-index="${offset + index}">
                        ${rowCells(row, mode, offset + index + 1).map(([value, width, align]) => `
                            <div class="${cellClass(width, align)}">${escapeHtml(value)}</div>
                        `).join("")}
                    </div>
                </div>
            `).join("");
        }

        function headerSummaryLines(summary, mode) {
            if (mode === "article") {
                return [
                    ["Total Used", quantity(summary.totalUsed)],
                    ["Rows", money(summary.totalRows)],
                ];
            }

            if (mode === "tag") {
                return [
                    ["Total Receive", quantity(summary.totalReceive)],
                    ["Total Used", quantity(summary.totalUsed)],
                    ["Available", quantity(summary.totalAvailable)],
                ];
            }

            return [
                ["Total Receive", quantity(summary.totalReceive)],
                ["Total Used", quantity(summary.totalUsed)],
                ["Total Return", quantity(summary.totalReturn)],
                ["Available", quantity(summary.totalAvailable)],
            ];
        }

        function summaryBoxes(summary, mode) {
            const lines = headerSummaryLines(summary, mode);
            return `
                <div class="grid ${lines.length === 2 ? "grid-cols-2" : lines.length === 3 ? "grid-cols-3" : "grid-cols-4"} gap-2 text-xs px-4">
                    ${lines.map(([label, value]) => `
                        <div class="flex justify-between gap-2 border border-gray-700 rounded-lg py-2 px-3 leading-none">
                            <span>${escapeHtml(label)}</span><span class="font-semibold">${escapeHtml(value)}</span>
                        </div>
                    `).join("")}
                </div>
            `;
        }

        function pageTemplate({ rows, mode, page, pageCount, offset, summary, showHeader }) {
            const company = config.companyData || {};
            const branches = (config.selectedBranchLabels || ["All Branches"]).join(", ");
            const title = `Fabric Report (${selectedModeLabel()})`;
            const from = dateStart?.value || "-";
            const to = dateEnd?.value || "-";

            return `
        ${page > 1 ? '<hr class="w-full my-2 border-gray-500">' : ""}
        <div class="preview-page w-[210mm] h-[297mm] mx-auto overflow-hidden relative bg-white p-[0.15in] rounded-md">
            <div class="preview flex flex-col h-full">
            <div class="preview-document flex flex-col h-full px-1">
                    <div class="preview-banner w-full flex justify-between items-center pl-5 pr-8">
                        <div class="flex items-center gap-3">
                            ${company.logo_url ? `
                                <div class="h-[3.1rem] w-[13.5rem] flex items-center justify-center gap-2.5">
                                    <img src="${escapeHtml(company.logo_url)}" alt="garmentsos-pro" class="max-h-full max-w-full object-contain" />
                                    ${company.logo_text ? `<h1 class="text-lg font-bold tracking-wide">${escapeHtml(company.logo_text)}</h1>` : ""}
                                </div>
                            ` : ""}
                        </div>
                        <div class="right text-right">
                            <h1 class="text-xl font-medium text-[var(--primary-color)] pr-2">${escapeHtml(title)}</h1>
                            <div class="mt-1 text-[13px]">${escapeHtml(company.phone_number || "")}</div>
                        </div>
                    </div>

                    <hr class="w-full my-2 border-gray-700">

                    ${showHeader ? `
                        <div class="preview-header w-full flex justify-between px-5">
                            <div class="left my-auto pr-3 text-[13px] text-gray-800 space-y-1">
                                <div class="leading-none">Date: ${escapeHtml(from)} - ${escapeHtml(to)}</div>
                                <div class="leading-none">Branches: ${escapeHtml(branches)}</div>
                                <div class="leading-none">Report Type: ${escapeHtml(selectedModeLabel())}</div>
                            </div>
                            <div class="right my-auto pr-3 text-[13px] text-gray-800 space-y-1">
                                ${headerSummaryLines(summary, mode).map(([label, value]) => `
                                    <div class="leading-none">${escapeHtml(label)}: ${escapeHtml(value)}</div>
                                `).join("")}
                            </div>
                        </div>
                        <hr class="w-full my-2 border-gray-700">
                    ` : ""}

                    <div class="preview-body w-full grow mx-auto">
                        <div class="preview-table w-[96%] mx-auto">
                            <div class="table w-full border border-gray-700 rounded-lg p-1 text-xs">
                                ${tableHeader(mode)}
                                <div class="tbody w-full mt-1.5 pb-1">${tableRows(rows, mode, offset)}</div>
                            </div>
                        </div>
                            </div>

                    ${page === pageCount ? `
                        <hr class="w-full my-2 border-gray-700">
                        ${summaryBoxes(summary, mode)}
                    ` : ""}

                    <hr class="w-full my-2 border-gray-700">
                    <div class="tfooter flex w-full px-4 justify-between text-gray-800 leading-none text-xs">
                                <p>Powered by SparkPair &copy; ${new Date().getFullYear()} SparkPair | +92 316 5825495</p>
                                <p>Page ${page} of ${pageCount}</p>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }

        function renderDocument(response) {
            const data = Array.isArray(response?.data) ? response.data : [];
            lastFabricRows = data;
            const mode = selectedMode();
            const first = data.slice(0, rowsPerFirstPage);
            const rest = data.slice(rowsPerFirstPage);
            const pages = [first];
            for (let i = 0; i < rest.length; i += rowsPerOtherPage) {
                pages.push(rest.slice(i, i + rowsPerOtherPage));
            }

            const summary = {
                totalReceive: parseFormattedNumber(response?.calculations?.total_received),
                totalUsed: parseFormattedNumber(response?.calculations?.total_used ?? response?.calculations?.total_production_used),
                totalReturn: parseFormattedNumber(response?.calculations?.total_returned),
                totalAvailable: parseFormattedNumber(response?.calculations?.total_available ?? response?.calculations?.total_balance),
                totalRows: data.length,
            };

            emptyState?.classList.toggle("hidden", data.length > 0);
            previewContainer.innerHTML = data.length
                ? pages.map((rows, index) => pageTemplate({
                    rows,
                    mode,
                    page: index + 1,
                    pageCount: pages.length,
                    offset: index === 0 ? 0 : rowsPerFirstPage + ((index - 1) * rowsPerOtherPage),
                    summary,
                    showHeader: index === 0,
                })).join("")
                : "";
        }

        function detailQuantity(row, key) {
            const sourceKey = {
                receive: "summary_received_quantity",
                used: "summary_used_quantity",
                return: "summary_returned_quantity",
            }[key];
            const value = parseFormattedNumber(row?.[sourceKey] || 0);
            return Math.abs(value) < 0.0001 ? "-" : quantity(value, row.unit || "");
        }

        function detailParty(row) {
            if (row.worker_name && row.worker_name !== "-") return row.worker_name;
            if (row.supplier_name && row.supplier_name !== "-") return row.supplier_name;
            return "-";
        }

        function detailReference(row) {
            const parts = [];
            if (row.reference && row.reference !== "-") parts.push(row.reference);
            if (row.remarks && row.remarks !== "-") parts.push(row.remarks);
            return parts.length ? parts.join(" | ") : "-";
        }

        function detailRows(row) {
            const details = Array.isArray(row?.details) ? row.details : [];
            return details.map((detail, index) => `
                <div class="flex w-full border-b border-gray-300 px-2 py-2 text-xs">
                    <div class="w-[5%] text-center">${index + 1}</div>
                    <div class="w-[13%]">${escapeHtml(detail.date || "-")}</div>
                    <div class="w-[15%]">${escapeHtml(detail.source || "-")}</div>
                    <div class="w-[16%]">${escapeHtml(detailParty(detail))}</div>
                    <div class="w-[13%]">${escapeHtml(detail.article_no || "-")}</div>
                    <div class="w-[10%] text-right">${escapeHtml(detailQuantity(detail, "receive"))}</div>
                    <div class="w-[10%] text-right">${escapeHtml(detailQuantity(detail, "used"))}</div>
                    <div class="w-[10%] text-right">${escapeHtml(detailQuantity(detail, "return"))}</div>
                    <div class="w-[8%] text-right">${escapeHtml(detailReference(detail))}</div>
                </div>
            `).join("");
        }

        function openFabricReportDetail(row) {
            document.getElementById("fabricReportDetailModal")?.remove();
            const mode = selectedMode();
            const title = mode === "worker"
                ? `${row.worker_name || "-"} | ${row.tag || "-"}`
                : mode === "tag"
                    ? `${row.fabric || "-"} | ${row.tag || "-"}`
                    : `${row.article_no || "-"} | ${row.tag || "-"}`;

            const modal = document.createElement("div");
            modal.id = "fabricReportDetailModal";
            modal.className = "fixed inset-0 z-[999] flex items-center justify-center bg-black/45 px-4";
            modal.innerHTML = `
                <div class="w-full max-w-6xl rounded-[18px] bg-[var(--secondary-bg-color)] shadow-2xl">
                    <div class="flex items-start justify-between gap-4 border-b border-gray-300 px-6 py-5">
                        <div>
                            <h2 class="text-2xl font-semibold text-gray-800">${escapeHtml(title)}</h2>
                            <p class="mt-1 text-sm text-gray-600">${escapeHtml(selectedModeLabel())} breakdown</p>
                        </div>
                        <button type="button" tabindex="-1" class="rounded-xl px-3 py-2 text-2xl leading-none text-gray-500 hover:bg-gray-200" data-fabric-report-detail-close>&times;</button>
                    </div>
                    <div class="px-6 py-5">
                        <div class="overflow-hidden rounded-xl border border-gray-400">
                            <div class="flex w-full bg-gray-300 px-2 py-2 text-xs font-semibold">
                                <div class="w-[5%] text-center">#</div>
                                <div class="w-[13%]">Date</div>
                                <div class="w-[15%]">Source</div>
                                <div class="w-[16%]">Party</div>
                                <div class="w-[13%]">Article</div>
                                <div class="w-[10%] text-right">Receive</div>
                                <div class="w-[10%] text-right">Used</div>
                                <div class="w-[10%] text-right">Return</div>
                                <div class="w-[8%] text-right">Ref.</div>
                            </div>
                            <div class="max-h-[440px] overflow-y-auto">
                                ${detailRows(row) || `<div class="px-4 py-8 text-center text-sm text-gray-500">No breakdown rows found.</div>`}
                            </div>
                        </div>
                    </div>
                    <div class="flex justify-end gap-3 border-t border-gray-300 px-6 py-4">
                        <button type="button" class="rounded-xl border border-gray-700 px-5 py-2 text-sm hover:bg-gray-100" data-fabric-report-detail-close>Close</button>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
        }

        function closeFabricReportDetail() {
            document.getElementById("fabricReportDetailModal")?.remove();
        }

        document.addEventListener("click", (event) => {
            if (event.target.closest("[data-fabric-report-detail-close]")) {
                event.preventDefault();
                closeFabricReportDetail();
                return;
            }

            const rowButton = event.target.closest("[data-fabric-report-index]");
            if (!rowButton) return;

            const row = lastFabricRows[Number(rowButton.dataset.fabricReportIndex)];
            if (row) openFabricReportDetail(row);
        });

        document.addEventListener("keydown", (event) => {
            if (event.key === "Escape") closeFabricReportDetail();
        });

        function moveModeHighlight(button, mode) {
            const highlight = document.getElementById("fabric-report-highlight");
            const container = button?.parentElement;
            if (!highlight || !button || !container) return;

            const rect = button.getBoundingClientRect();
            const parentRect = container.getBoundingClientRect();

            highlight.style.width = `${rect.width}px`;
            highlight.style.left = `${rect.left - parentRect.left - 3}px`;
            btnTypeGlobal = mode;
            if (modeInput) modeInput.value = mode;

            container.querySelectorAll("button").forEach((item) => {
                item.classList.remove("text-[var(--primary-color)]", "font-semibold");
            });
        }

        function setFilterVisibility(mode) {
            document.querySelectorAll(".fabric-filter").forEach((wrapper) => {
                const modes = String(wrapper.dataset.modes || "").split(/\s+/).filter(Boolean);
                const visible = modes.includes(mode);
                wrapper.classList.toggle("hidden", !visible);

                if (!visible) {
                    wrapper.querySelectorAll("input, select, textarea").forEach((field) => {
                        if (field.type !== "hidden") field.value = "";
                    });
                    wrapper.querySelectorAll('input[data-for], input[type="hidden"]').forEach((field) => {
                        if (field.id !== "mode") field.value = "";
                    });
                    wrapper.querySelectorAll('[data-selected-text]').forEach((field) => {
                        field.textContent = "";
                    });
                }
            });
        }

        window.setFabricReportType = function setFabricReportType(button, mode) {
            if (!["worker", "tag", "article"].includes(mode)) return;
            if (btnTypeGlobal === mode) return;

            const previousMode = btnTypeGlobal;
            moveModeHighlight(button, mode);

            $.ajax({
                url: config.setTypeUrl,
                type: "POST",
                data: {
                    _token: config.csrfToken,
                    fabric_report_type: mode,
                },
                success: function () {
                    location.reload();
                },
                error: function (xhr, status, error) {
                    console.error("Error setting fabric report type:", error);
                    const previousButton = document.getElementById(
                        previousMode === "tag"
                            ? "fabricTagBtn"
                            : previousMode === "article"
                                ? "fabricArticleBtn"
                                : "fabricWorkerBtn"
                    );
                    if (previousButton) moveModeHighlight(previousButton, previousMode);

                    appAlert(xhr?.responseJSON?.error || "Failed to update fabric report type.");
                },
            });
        };

        function getFabricReport() {
            const payload = Object.fromEntries(new FormData(form).entries());

            $.ajax({
                url: config.fabricUrl,
                type: "GET",
                data: payload,
                success: renderDocument,
                error: function (xhr, status, error) {
                    console.error("Error fetching fabric report:", error);
                    appAlert("Failed to load fabric report.");
                },
            });
        }

        window.applyFabricRange = function applyFabricRange(rangeValue) {
            const today = new Date();
            let from = null;
            let to = today;

            switch (rangeValue) {
                case "custom":
                    dateStart.disabled = false;
                    dateEnd.disabled = false;
                    return;
                case "current_month":
                    from = new Date(today.getFullYear(), today.getMonth(), 1);
                    break;
                case "last_month":
                    from = new Date(today.getFullYear(), today.getMonth() - 1, 1);
                    break;
                case "last_three_months":
                    from = new Date(today.getFullYear(), today.getMonth() - 2, 1);
                    break;
                case "last_six_months":
                    from = new Date(today.getFullYear(), today.getMonth() - 5, 1);
                    break;
                default:
                    return;
            }

            dateStart.value = localDateString(from);
            dateEnd.value = localDateString(to);
            dateStart.disabled = true;
            dateEnd.disabled = true;
        };

        window.validateForNextStep = function validateForNextStep() {
            getFabricReport();
            return true;
        };

        window.onClickOnPrintBtn = function onClickOnPrintBtn() {
            const preview = document.getElementById("fabric-preview-container");
            if (!preview?.innerHTML.trim()) return;

            const clone = preview.cloneNode(true);
            clone.querySelectorAll(":scope > hr").forEach(hr => hr.remove());

            window.DocumentPrint.printHtml({
                title: "Print Fabric Report",
                html: clone.innerHTML,
                style: `
                    @page { size: A4; margin: 0; }
                    body { margin: 0; padding: 0; background: #fff; }
                `,
            });
        };

        setTimeout(() => {
            const initialMode = ["worker", "tag", "article"].includes(config.fabricReportType)
                ? config.fabricReportType
                : "worker";
            const activeButton = document.getElementById(
                initialMode === "tag"
                    ? "fabricTagBtn"
                    : initialMode === "article"
                        ? "fabricArticleBtn"
                        : "fabricWorkerBtn"
            );
            if (modeInput) modeInput.value = initialMode;
            setFilterVisibility(initialMode);
            if (activeButton) moveModeHighlight(activeButton, initialMode);

            window.addEventListener("resize", () => {
                const currentMode = selectedMode();
                const currentButton = document.getElementById(
                    currentMode === "tag"
                        ? "fabricTagBtn"
                        : currentMode === "article"
                            ? "fabricArticleBtn"
                            : "fabricWorkerBtn"
                );
                if (currentButton) moveModeHighlight(currentButton, currentMode);
            });
        }, 0);
    }

    window.initReportsFabric = initReportsFabric;

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", initReportsFabric);
    } else {
        initReportsFabric();
    }
})();
