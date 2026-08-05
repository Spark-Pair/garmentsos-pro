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

    function detailRow(label, value) {
        return `
            <tr>
                <td class="pt-label">${escapeText(label)}</td>
                <td class="pt-value">${escapeText(value)}</td>
            </tr>
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

        const status = data.receive_date_raw || (data.receive_date && data.receive_date !== "-") ? "Received" : "Issued";
        const partsText = listText(data.parts);

        return `
            <div id="production-ticket-preview">
                <style>
                    #production-ticket-preview {
                        width: 148mm;
                        height: 210mm;
                        margin: 0 auto;
                        padding: 3.5mm;
                        box-sizing: border-box;
                        background: #ffffff;
                        color: #111827;
                        font-family: Arial, Helvetica, sans-serif;
                        font-size: 9.4px;
                        font-weight: 400;
                        line-height: 1.25;
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
                        padding: 3.5mm;
                        display: flex;
                        flex-direction: column;
                        background: #ffffff;
                        overflow: hidden;
                    }

                    .pt-header {
                        display: grid;
                        grid-template-columns: 1fr auto;
                        gap: 6mm;
                        align-items: start;
                        padding-bottom: 3mm;
                        border-bottom: 1px solid #111827;
                    }

                    .pt-brand {
                        display: flex;
                        align-items: center;
                        gap: 7px;
                        min-width: 0;
                    }

                    .pt-logo {
                        width: 42mm;
                        height: 13mm;
                        min-width: 42mm;
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
                        min-width: 40mm;
                    }

                    .pt-doc-title {
                        font-size: 14px;
                        font-weight: 700;
                        color: #2563eb;
                        line-height: 1.15;
                    }

                    .pt-doc-sub {
                        margin-top: 2px;
                        font-size: 7.8px;
                        font-weight: 500;
                        color: #4b5563;
                    }

                    .pt-meta-panel {
                        margin-top: 2.4mm;
                        padding: 3.5px;
                        border: 1px solid #111827;
                        border-radius: 8px;
                        overflow: hidden;
                        background: #ffffff;
                    }

                    .pt-meta-grid {
                        display: grid;
                        grid-template-columns: 24mm 1fr 1fr 22mm;
                    }

                    .pt-inline-info {
                        min-width: 0;
                        padding: 2mm 2.5mm;
                        display: flex;
                        gap: 2px;
                        align-items: center;
                        border-right: 1px solid #dcdfe3;
                        background: #ffffff;
                    }

                    .pt-inline-info:last-child {
                        border-right: 0;
                    }

                    .pt-inline-label {
                        font-size: 7.5px;
                        font-weight: 700;
                        color: #334155;
                        text-transform: uppercase;
                        letter-spacing: 0.15px;
                        white-space: nowrap;
                    }

                    .pt-inline-value {
                        width: 100%;
                        font-size: 7.5px;
                        font-weight: 800;
                        color: #000000;
                        text-align: right;
                        white-space: nowrap;
                        overflow: hidden;
                        text-overflow: ellipsis;
                    }

                    .pt-grid-2 {
                        display: grid;
                        grid-template-columns: 1fr 1fr;
                        gap: 2.4mm;
                        margin-top: 2.4mm;
                    }

                    .pt-card {
                        border: 1px solid #111827;
                        border-radius: 8px;
                        overflow: hidden;
                        background: #ffffff;
                    }

                    .pt-section-title {
                        margin: 2px 2px 0;
                        padding: 1.55mm 3mm;
                        border-radius: 6px;
                        background: #2563eb;
                        color: #ffffff;
                        font-size: 8px;
                        font-weight: 700;
                        line-height: 1;
                        text-align: center;
                    }

                    .pt-card-body {
                        padding: 1.2mm 2mm 1.6mm;
                    }

                    .pt-detail-table td {
                        padding: 1.2mm 1.3mm;
                        border-bottom: 1px solid #dcdfe3;
                        vertical-align: top;
                    }

                    .pt-detail-table tr:last-child td {
                        border-bottom: 0;
                    }

                    .pt-label {
                        width: 37%;
                        font-size: 9.2px;
                        font-weight: 500;
                        color: #374151;
                    }

                    .pt-value {
                        font-size: 9.2px;
                        font-weight: 700;
                        color: #000000;
                        text-align: right;
                        word-break: break-word;
                    }

                    .pt-selected-card {
                        margin-top: 2.4mm;
                    }

                    .pt-table-wrap {
                        padding: 1.2mm 2mm 1.7mm;
                    }

                    .pt-items-table {
                        font-size: 8.8px;
                    }

                    .pt-items-table th {
                        padding: 1.35mm 1.6mm;
                        border-bottom: 1px solid #cbd5e1;
                        color: #111827;
                        font-size: 8px;
                        font-weight: 700;
                        text-align: center;
                        background: #f8fafc;
                    }

                    .pt-items-table td {
                        padding: 1.25mm 1.6mm;
                        border-bottom: 1px solid #dcdfe3;
                        color: #000000;
                        font-size: 8.8px;
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
                        width: 14mm;
                    }

                    .pt-col-qty {
                        width: 18mm;
                    }

                    .pt-col-unit {
                        width: 17mm;
                    }

                    .pt-totals {
                        display: grid;
                        grid-template-columns: repeat(3, 1fr);
                        gap: 2.4mm;
                        margin-top: 2.4mm;
                    }

                    .pt-totals .pt-inline-info {
                        border: 1px solid #111827;
                        border-radius: 8px;
                    }

                    .pt-remarks {
                        margin-top: 2.4mm;
                    }

                    .pt-notes-area {
                        min-height: 12mm;
                        padding: 2mm 2.5mm;
                        color: #000000;
                        font-size: 8.8px;
                        line-height: 1.3;
                    }

                    .pt-flex-space {
                        flex: 1 1 auto;
                        min-height: 1mm;
                    }

                    .pt-signatures {
                        display: grid;
                        grid-template-columns: 1fr 1fr;
                        gap: 14mm;
                        padding: 0 7mm;
                        margin-top: 4.5mm;
                        margin-bottom: 3.2mm;
                    }

                    .pt-signature {
                        border-top: 1px solid #111827;
                        padding-top: 1.7mm;
                        font-size: 8.2px;
                        font-weight: 600;
                        color: #000000;
                        text-align: center;
                    }

                    .pt-footer {
                        display: grid;
                        grid-template-columns: 1fr auto;
                        gap: 6mm;
                        align-items: center;
                        padding-top: 1.7mm;
                        border-top: 1px solid #111827;
                        font-size: 7.4px;
                        color: #4b5563;
                        line-height: 1.2;
                    }

                    .pt-footer-brand {
                        font-size: 7.7px;
                        font-weight: 500;
                        color: #111827;
                    }

                    @media print {
                        #production-ticket-preview {
                            margin: 0 !important;
                            padding: 3.5mm !important;
                        }

                        .pt-page {
                            padding: 3.5mm !important;
                        }

                        .pt-inline-label,
                        .pt-doc-sub,
                        .pt-footer {
                            color: #374151 !important;
                        }

                        .pt-inline-value,
                        .pt-value,
                        .pt-label,
                        .pt-items-table th,
                        .pt-items-table td,
                        .pt-signature,
                        .pt-footer-brand {
                            color: #000000 !important;
                        }
                    }
                </style>

                <div class="pt-page">
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
                            <div class="pt-doc-sub">Document: Issue Ticket</div>
                        </div>
                    </header>

                    <section class="pt-meta-panel">
                        <div class="pt-meta-grid">
                            ${inlineInfo("Ticket", data.ticket)}
                            ${inlineInfo("Issue Date", issueDate)}
                            ${inlineInfo("Receive Date", receiveDate)}
                            ${inlineInfo("Status", status)}
                        </div>
                    </section>

                    <section class="pt-grid-2">
                        <div class="pt-card">
                            <div class="pt-section-title">Article</div>
                            <div class="pt-card-body">
                                <table class="pt-detail-table">
                                    <tbody>
                                        ${detailRow("Article No.", article.article_no)}
                                        ${detailRow("Category", article.category)}
                                        ${detailRow("Season", article.season)}
                                        ${detailRow("Size", article.size)}
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="pt-card">
                            <div class="pt-section-title">Issued For</div>
                            <div class="pt-card-body">
                                <table class="pt-detail-table">
                                    <tbody>
                                        ${detailRow("Work", work.title)}
                                        ${detailRow("Worker", worker.employee_name)}
                                        ${detailRow("Parts", partsText)}
                                        ${detailRow("Worker Balance", `Rs. ${balance}`)}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </section>

                    ${selectedDetailsTable(data)}

                    <section class="pt-totals">
                        ${inlineInfo("Quantity", quantity)}
                        ${inlineInfo("Rate", rate)}
                        ${inlineInfo("Amount", amount)}
                    </section>

                    <section class="pt-card pt-remarks">
                        <div class="pt-section-title">Remarks / Instructions</div>
                        <div class="pt-notes-area"></div>
                    </section>

                    <div class="pt-flex-space"></div>

                    <section class="pt-signatures">
                        <div class="pt-signature">Issued By</div>
                        <div class="pt-signature">Received By</div>
                    </section>

                    <footer class="pt-footer">
                        <div class="pt-footer-brand">Powered by SparkPair</div>
                        <div>&copy; 2026 SparkPair | +92 316 5825495</div>
                    </footer>
                </div>
            </div>
        `;
    }

    window.showProductionTicket = function showProductionTicket(data, autoPrint = false) {
        currentProductionTicket = data;

        createModal({
            id: "productionTicketModal",
            name: "Production Ticket",
            class: "max-w-[158mm] h-[212mm]",
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

        const oldIframe = document.getElementById("printIframe");
        if (oldIframe) oldIframe.remove();

        const printIframe = document.createElement("iframe");
        printIframe.id = "printIframe";
        printIframe.style.position = "absolute";
        printIframe.style.width = "0";
        printIframe.style.height = "0";
        printIframe.style.border = "0";
        printIframe.style.display = "none";
        document.body.appendChild(printIframe);

        const printDocument = printIframe.contentDocument || printIframe.contentWindow.document;

        printDocument.open();
        printDocument.write(`
            <html>
                <head>
                    <title>Production Ticket ${escapeText(data.ticket)}</title>
                    ${document.head.innerHTML}
                    <style>
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
                            padding: 3.5mm !important;
                            box-shadow: none !important;
                        }
                    </style>
                </head>
                <body>${buildProductionTicketHtml(data)}</body>
            </html>
        `);

        printDocument.close();

        printIframe.onload = () => {
            setTimeout(() => {
                printIframe.contentWindow.focus();
                printIframe.contentWindow.print();
            }, 300);
        };
    };
})();
