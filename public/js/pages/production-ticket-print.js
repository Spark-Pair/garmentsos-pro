(() => {
    let currentProductionTicket = null;

    function escapeText(value) {
        return String(value ?? "-")
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    function money(value) {
        if (value === null || typeof value === "undefined" || value === "") return "-";

        if (typeof formatNumbersWithDigits === "function") {
            return formatNumbersWithDigits(value || 0, 1, 1);
        }

        return Number(value || 0).toFixed(1);
    }

    function dateText(value, formatted) {
        if (formatted && formatted !== "-") return formatted;
        if (!value) return "-";
        if (typeof formatDate === "function") return formatDate(value);
        return value;
    }

    function listText(items, key = null) {
        if (!Array.isArray(items) || items.length === 0) return "-";

        return items
            .map((item) => {
                if (key && item && typeof item === "object") return item[key];

                if (item && typeof item === "object") {
                    const title = item.title || item.tag || item.name || "";
                    const quantity = item.quantity ? ` (${item.quantity})` : "";
                    const unit = item.unit ? ` ${item.unit}` : "";
                    return `${title}${quantity}${unit}`.trim();
                }

                return item;
            })
            .filter(Boolean)
            .join(", ");
    }

    function hasItems(items) {
        return Array.isArray(items) && items.length > 0;
    }

    function productionParts(data) {
        if (hasItems(data.part_quantities)) return data.part_quantities;
        if (hasItems(data.parts)) {
            return data.parts.map((part) => ({
                part,
                quantity: data.quantity,
                movement_type: data.movement_type,
            }));
        }
        return [];
    }

    function detailRow(label, value) {
        return `
            <tr>
                <td class="pt-label">${escapeText(label)}</td>
                <td class="pt-value">${escapeText(value)}</td>
            </tr>
        `;
    }

    function detailLine(label, value) {
        return `
            <div class="pt-detail-line">
                <span class="pt-label">${escapeText(label)}</span>
                <span class="pt-value">${escapeText(value)}</span>
            </div>
        `;
    }

    function partsDetailsTable(data) {
        const parts = productionParts(data);
        if (!hasItems(parts)) return "";

        return `
            <section class="pt-card pt-selected-card">
                <div class="pt-section-title">Production Parts</div>

                <div class="pt-table-wrap">
                    <table class="pt-items-table">
                        <thead>
                            <tr>
                                <th class="pt-col-sno">S.No</th>
                                <th class="pt-text-left">Part</th>
                                <th class="pt-col-unit">Type</th>
                                <th class="pt-col-qty">Qty</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${parts.map((item, index) => `
                                <tr>
                                    <td>${index + 1}</td>
                                    <td class="pt-item-name">${escapeText(item.part || "-")}</td>
                                    <td>${escapeText(item.movement_type || data.movement_type || "-")}</td>
                                    <td>${escapeText(item.quantity || "-")}</td>
                                </tr>
                            `).join("")}
                        </tbody>
                    </table>
                </div>
            </section>
        `;
    }

    function selectedDetailsTable(data) {
        if (hasItems(data.tags)) {
            return `
                <section class="pt-card pt-selected-card">
                    <div class="pt-section-title">Selected Tags</div>

                    <div class="pt-table-wrap">
                        <table class="pt-items-table">
                            <thead>
                                <tr>
                                    <th class="pt-col-sno">S.No</th>
                                    <th class="pt-text-left">Tag</th>
                                    <th class="pt-col-qty">Qty</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${data.tags.map((item, index) => `
                                    <tr>
                                        <td>${index + 1}</td>
                                        <td class="pt-item-name">${escapeText(item.tag || "-")}</td>
                                        <td>${escapeText(item.quantity || "-")}</td>
                                    </tr>
                                `).join("")}
                            </tbody>
                        </table>
                    </div>
                </section>
            `;
        }

        if (hasItems(data.materials)) {
            return `
                <section class="pt-card pt-selected-card">
                    <div class="pt-section-title">Selected Materials</div>

                    <div class="pt-table-wrap">
                        <table class="pt-items-table">
                            <thead>
                                <tr>
                                    <th class="pt-col-sno">S.No</th>
                                    <th class="pt-text-left">Material</th>
                                    <th class="pt-col-unit">Unit</th>
                                    <th class="pt-col-qty">Qty</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${data.materials.map((item, index) => `
                                    <tr>
                                        <td>${index + 1}</td>
                                        <td class="pt-item-name">${escapeText(item.title || item.name || "-")}</td>
                                        <td>${escapeText(item.unit || "-")}</td>
                                        <td>${escapeText(item.quantity || "-")}</td>
                                    </tr>
                                `).join("")}
                            </tbody>
                        </table>
                    </div>
                </section>
            `;
        }

        return "";
    }

    function inlineInfo(label, value) {
        return `
            <div class="pt-inline-info">
                <span class="pt-inline-label">${escapeText(label)}</span>
                <span class="pt-inline-value">${escapeText(value)}</span>
            </div>
        `;
    }

    function selectedItemsText(data) {
        const chunks = [];
        const parts = productionParts(data);

        if (hasItems(parts)) {
            chunks.push(`Parts: ${parts.map((item) => {
                const qty = item.quantity ? ` ${item.quantity}` : "";
                return `${item.part || "-"}${qty}`.trim();
            }).join(" | ")}`);
        }

        if (hasItems(data.tags)) {
            chunks.push(`Tags: ${data.tags.map((item) => {
                const qty = item.quantity ? ` ${item.quantity}${item.unit ? ` ${item.unit}` : ""}` : "";
                return `${item.tag || "-"}${qty}`.trim();
            }).join(" | ")}`);
        }

        if (hasItems(data.materials)) {
            chunks.push(`Materials: ${data.materials.map((item) => {
                const title = item.title || item.name || "-";
                const qty = item.quantity ? ` ${item.quantity}${item.unit ? ` ${item.unit}` : ""}` : "";
                return `${title}${qty}`.trim();
            }).join(" | ")}`);
        }

        return chunks.join(" | ");
    }

    function buildProductionTicketHtml(data) {
        const article = data.article || {};
        const work = data.work || {};
        const worker = data.worker || {};
        const company = data.branch_branding || window.__productionTicketPrint?.company || window.companyData || {};

        const companyLogoBase = (window.companyLogoBase || "/").replace(/\/+$/, "/");
        const companyLogoUrl = company.logo_url || (company.logo ? `${companyLogoBase}images/${company.logo}` : "");

        const issueDate = dateText(data.issue_date_raw, data.issue_date);
        const receiveDate = dateText(data.receive_date_raw, data.receive_date);

        const quantity = data.quantity || article.quantity || "-";
        const amount = money(data.amount);
        const rate = money(data.rate);
        const balance = money(worker.balance);
        const issuedByName = data.issued_by_name || "-";
        const receivedByName = data.received_by_name || "-";
        const addedByName = data.creator || "-";

        const status = data.receive_date_raw || (data.receive_date && data.receive_date !== "-") ? "Received" : "Issued";
        const partsText = listText(productionParts(data), "part");
        const remarksText = selectedItemsText(data);
        const partsQuantityText = productionParts(data)
            .map((item) => `${item.part || "-"}: ${item.quantity || "-"}`)
            .join(" | ");
        const articleSummary = [
            article.article_no,
            article.category,
            article.season,
            article.size,
        ].filter(Boolean).join(" | ");

        const ticketCopy = (copyTitle) => `
            <section class="pt-copy">
                <header class="pt-header">
                    <div class="pt-brand">
                        ${companyLogoUrl ? `
                            <div class="pt-logo">
                                <img src="${escapeText(companyLogoUrl)}" alt="">
                            </div>
                        ` : ""}
                    </div>

                    <div class="pt-doc">
                        <div class="pt-doc-title">Production Ticket</div>
                        <div class="pt-doc-sub">${escapeText(copyTitle)}</div>
                    </div>
                </header>

                <section class="pt-meta-panel">
                    <div class="pt-meta-grid">
                        ${inlineInfo("Ticket", data.ticket)}
                        ${inlineInfo("Issue", issueDate)}
                        ${inlineInfo("Receive", receiveDate)}
                        ${inlineInfo("Status", status)}
                    </div>
                </section>

                <section class="pt-grid-2">
                    <div class="pt-card">
                        <div class="pt-section-title">Article</div>
                        <div class="pt-card-body">
                            ${detailLine("Article", articleSummary || article.article_no || "-")}
                            ${detailLine("Category", article.category || "-")}
                            ${detailLine("Size", article.size || "-")}
                        </div>
                    </div>

                    <div class="pt-card">
                        <div class="pt-section-title">Issued For</div>
                        <div class="pt-card-body">
                            ${detailLine("Work", work.title)}
                            ${detailLine("Worker", worker.employee_name)}
                            ${detailLine("Parts Qty", partsQuantityText || partsText || "-")}
                        </div>
                    </div>
                </section>

                <section class="pt-totals">
                    ${inlineInfo("Quantity", quantity)}
                    ${inlineInfo("Rate", rate)}
                    ${inlineInfo("Amount", amount)}
                </section>

                <section class="pt-card pt-remarks">
                    <div class="pt-section-title">Remarks / Instructions</div>
                    <div class="pt-notes-area">${escapeText(remarksText)}</div>
                </section>

                <section class="pt-signatures">
                    <div class="pt-signature">
                        <span>Issued By: ${escapeText(issuedByName)}</span>
                    </div>
                    <div class="pt-signature">
                        <span>Received By: ${escapeText(receivedByName)}</span>
                    </div>
                </section>

                <footer class="pt-footer">
                    <span>Added by: ${escapeText(addedByName)}</span>
                    <span>Powered by SparkPair | +92 316 5825495</span>
                </footer>
            </section>
        `;

        return `
            <div id="production-ticket-preview">
                <style>
                    #production-ticket-preview {
                        width: 148mm;
                        height: 210mm;
                        margin: 0 auto;
                        padding: 0;
                        box-sizing: border-box;
                        background: #ffffff;
                        color: #111827;
                        font-family: Arial, Helvetica, sans-serif;
                        font-size: 11px;
                        font-weight: 400;
                        line-height: 1.22;
                        overflow: hidden;
                        -webkit-print-color-adjust: exact;
                        print-color-adjust: exact;
                    }

                    #production-ticket-preview * {
                        box-sizing: border-box;
                    }

                    #production-ticket-preview table {
                        width: 100%;
                        border-collapse: collapse;
                    }

                    .pt-page {
                        width: 100%;
                        height: 100%;
                        padding: 0;
                        display: flex;
                        flex-direction: column;
                        background: #ffffff;
                        overflow: hidden;
                    }

                    .pt-copy {
                        height: calc((100% - 0.5mm) / 2);
                        padding: 6mm 6mm 3.6mm;
                        display: flex;
                        flex-direction: column;
                        overflow: hidden;
                    }

                    .pt-cut-line {
                        height: 0.5mm;
                        margin: 0 6mm;
                        border-top: 1.4px dashed #111827;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        color: #4b5563;
                        font-size: 8.8px;
                        font-weight: 700;
                        letter-spacing: 0;
                    }

                    .pt-header {
                        display: grid;
                        grid-template-columns: 1fr auto;
                        gap: 4mm;
                        align-items: start;
                        padding: 1mm 1mm 1.5mm;
                        border-bottom: 1.4px solid #111827;
                    }

                    .pt-brand {
                        display: flex;
                        align-items: center;
                        gap: 7px;
                        min-width: 0;
                    }

                    .pt-logo {
                        width: 34mm;
                        height: 9mm;
                        min-width: 34mm;
                        background: transparent;
                        display: flex;
                        align-items: center;
                        justify-content: flex-start;
                        overflow: hidden;
                    }

                    .pt-logo img {
                        width: 100%;
                        height: 100%;
                        object-fit: contain;
                    }

                    .pt-doc {
                        text-align: right;
                        min-width: 42mm;
                    }

                    .pt-doc-title {
                        font-size: 15px;
                        font-weight: 700;
                        color: #2563eb;
                        line-height: 1.05;
                    }

                    .pt-doc-sub {
                        margin-top: 3px;
                        font-size: 10px;
                        font-weight: 500;
                        color: #4b5563;
                    }

                    .pt-meta-panel {
                        margin-top: 1.2mm;
                        padding: 2px;
                        border: 1.2px solid #111827;
                        border-radius: 7px;
                        overflow: hidden;
                        background: #ffffff;
                    }

                    .pt-meta-grid {
                        display: grid;
                        grid-template-columns: repeat(4, 1fr);
                    }

                    .pt-inline-info {
                        min-width: 0;
                        padding: 0.95mm 1.25mm;
                        display: grid;
                        grid-template-columns: auto 1fr;
                        gap: 1.7mm;
                        align-items: baseline;
                        border-right: 1px solid #dcdfe3;
                        background: #ffffff;
                    }

                    .pt-inline-info:last-child {
                        border-right: 0;
                    }

                    .pt-inline-label {
                        font-size: 8.8px;
                        font-weight: 700;
                        color: #334155;
                        text-transform: uppercase;
                        letter-spacing: 0;
                        white-space: nowrap;
                    }

                    .pt-inline-value {
                        width: 100%;
                        font-size: 10px;
                        font-weight: 650;
                        color: #000000;
                        text-align: right;
                        white-space: nowrap;
                        overflow: hidden;
                        text-overflow: ellipsis;
                    }

                    .pt-grid-2 {
                        display: grid;
                        grid-template-columns: 1fr 1fr;
                        gap: 1.8mm;
                        margin-top: 1mm;
                    }

                    .pt-card {
                        border: 1.2px solid #111827;
                        border-radius: 7px;
                        padding: 2px;
                        overflow: hidden;
                        background: #ffffff;
                    }

                    .pt-section-title {
                        margin: 0;
                        padding: 1mm 2mm;
                        border-radius: 5px;
                        background: #2563eb;
                        color: #ffffff;
                        font-size: 10px;
                        font-weight: 700;
                        line-height: 1;
                        text-align: center;
                    }

                    .pt-card-body {
                        padding: 1mm 1.2mm 0.8mm;
                        display: flex;
                        min-height: 21.2mm;
                        flex-direction: column;
                        justify-content: stretch;
                    }

                    .pt-detail-line {
                        flex: 1 1 0;
                        min-height: 6.3mm;
                        display: grid;
                        grid-template-columns: 17mm 1fr;
                        gap: 1.5mm;
                        align-items: center;
                        border-bottom: 1px solid #dcdfe3;
                    }

                    .pt-detail-line:last-child {
                        border-bottom: 0;
                    }

                    .pt-label {
                        font-size: 10.5px;
                        font-weight: 500;
                        color: #374151;
                        white-space: nowrap;
                    }

                    .pt-value {
                        font-size: 11px;
                        font-weight: 650;
                        color: #000000;
                        text-align: right;
                        word-break: break-word;
                    }

                    .pt-selected-card {
                        margin-top: 1mm;
                    }

                    .pt-table-wrap {
                        padding: 0.6mm 1.2mm 0.8mm;
                    }

                    .pt-items-table {
                        font-size: 10px;
                    }

                    .pt-items-table th {
                        padding: 0.65mm 1mm;
                        border-bottom: 1px solid #cbd5e1;
                        color: #111827;
                        font-size: 9.2px;
                        font-weight: 600;
                        text-align: center;
                        background: #f8fafc;
                    }

                    .pt-items-table td {
                        padding: 0.6mm 1mm;
                        border-bottom: 1px solid #dcdfe3;
                        color: #000000;
                        font-size: 9.2px;
                        font-weight: 500;
                        text-align: center;
                    }

                    .pt-items-table tbody tr:last-child td {
                        border-bottom: 0;
                    }

                    .pt-text-left {
                        text-align: left !important;
                    }

                    .pt-item-name {
                        text-align: left !important;
                        font-weight: 600 !important;
                    }

                    .pt-col-sno {
                        width: 10mm;
                    }

                    .pt-col-qty {
                        width: 15mm;
                    }

                    .pt-col-unit {
                        width: 14mm;
                    }

                    .pt-totals {
                        display: grid;
                        grid-template-columns: repeat(3, 1fr);
                        gap: 1.8mm;
                        margin-top: 1mm;
                    }

                    .pt-totals .pt-inline-info {
                        border: 1.2px solid #111827;
                        border-radius: 7px;
                        padding: 1.05mm 1.35mm;
                    }

                    .pt-totals .pt-inline-label {
                        font-size: 9.4px;
                    }

                    .pt-totals .pt-inline-value {
                        font-size: 11px;
                    }

                    .pt-remarks {
                        margin-top: 1mm;
                    }

                    .pt-notes-area {
                        min-height: 17mm;
                        max-height: 20mm;
                        padding: 1.2mm 1.7mm;
                        color: #000000;
                        font-size: 10px;
                        line-height: 1.22;
                        white-space: normal;
                        overflow: hidden;
                    }

                    .pt-signatures {
                        display: grid;
                        grid-template-columns: 1fr 1fr;
                        gap: 12mm;
                        padding: 0 8mm;
                        margin-top: auto;
                        margin-bottom: 0.8mm;
                    }

                    .pt-signature {
                        border-top: 1.2px solid #111827;
                        padding-top: 1.1mm;
                        font-size: 10.4px;
                        font-weight: 500;
                        color: #000000;
                        text-align: center;
                    }

                    .pt-signature span {
                        display: block;
                        max-width: 100%;
                        color: #000000;
                        font-weight: 500;
                        white-space: nowrap;
                        overflow: hidden;
                        text-overflow: ellipsis;
                    }

                    .pt-footer {
                        display: flex;
                        justify-content: space-between;
                        gap: 4mm;
                        border-top: 1px solid #111827;
                        padding: 0.7mm 1mm 0;
                        color: #000000;
                        font-size: 8.6px;
                        font-weight: 600;
                        line-height: 1;
                        white-space: nowrap;
                    }

                    .pt-footer span {
                        min-width: 0;
                        color: #000000;
                        font-weight: 700;
                        overflow: hidden;
                        text-overflow: ellipsis;
                    }

                    @media print {
                        #production-ticket-preview {
                            margin: 0 !important;
                            padding: 0 !important;
                        }

                        .pt-page {
                            padding: 0 !important;
                        }

                        .pt-inline-label,
                        .pt-doc-sub,
                        .pt-cut-line {
                            color: #374151 !important;
                        }

                        .pt-inline-value,
                        .pt-value,
                        .pt-label,
                        .pt-items-table th,
                        .pt-items-table td,
                        .pt-signature,
                        .pt-signature span,
                        .pt-footer,
                        .pt-footer span {
                            color: #000000 !important;
                        }
                    }
                </style>

                <div class="pt-page">
                    ${ticketCopy("Worker Copy")}
                    <div class="pt-cut-line"></div>
                    ${ticketCopy("Gate Copy")}
                </div>
            </div>
        `;
    }

    window.showProductionTicket = function showProductionTicket(data, autoPrint = false) {
        currentProductionTicket = data;

            createModal({
                id: "productionTicketModal",
                name: "Production Ticket",
                class: "max-w-[170mm] h-[216mm]",
            fieldsGridCount: "1",
            fields: [
                {
                    category: "explicitHtml",
                    full: true,
                    html: buildProductionTicketHtml(data),
                },
            ],
            bottomActions: [
                { id: "print", text: "Print Ticket", onclick: "printProductionTicket()" },
            ],
        });

        if (autoPrint) {
            setTimeout(() => window.printProductionTicket(data), 500);
        }
    };

    window.printProductionTicket = function printProductionTicket(data = currentProductionTicket) {
        if (!data) return;

        window.DocumentPrint.printHtml({
            title: `Production Ticket ${escapeText(data.ticket)}`,
            html: buildProductionTicketHtml(data),
            delay: 300,
            style: `
                @page {
                    size: A5 portrait;
                    margin: 3mm;
                }

                html,
                body {
                    margin: 0 !important;
                    padding: 0 !important;
                    width: 148mm !important;
                    height: 210mm !important;
                    background: #ffffff !important;
                    overflow: hidden !important;
                    -webkit-print-color-adjust: exact !important;
                    print-color-adjust: exact !important;
                }

                #production-ticket-preview {
                    width: 148mm !important;
                    height: 210mm !important;
                    margin: 0 !important;
                    padding: 0 !important;
                    box-shadow: none !important;
                }
            `,
        });
    };
})();
