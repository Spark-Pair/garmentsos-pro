(() => {
    function initReportsStatement() {
        const config = window.__reportsStatement || {};
        let btnTypeGlobal = config.statementType || "general";
        const portal = config.portal || {};
        const step2 = document.querySelector(".step2");
        let statementRecordRequest = null;

        if (config.companyData) {
            window.companyData = config.companyData;
        }
        if (config.companyLogoBase) {
            window.companyLogoBase = config.companyLogoBase;
        }

        function openExpenseStatementModal(data) {
            if (!data) return;

            createModal({
                id: "modalForm",
                name: data.supplier_name,
                details: {
                    Date: data.date,
                    "Reff. No.": data.reff_no,
                    Expense: data.expense,
                    "Lot No.": data.lot_no,
                    Amount: data.amount,
                    Remarks: data.remarks,
                },
            });
        }

        function openVoucherStatementModal(data) {
            if (!data?.data) return;

            data.data.total_payment = data.total_payment;
            data.data.previous_balance = data.previous_balance;

            createModal({
                id: "modalForm",
                preview: { type: "voucher", data: data.data, document: "Voucher" },
            });
        }

        function openInvoiceStatementModal(data) {
            if (!data?.data) return;

            createModal({
                id: "modalForm",
                preview: { type: "invoice", data: data.data, document: "Sales Invoice" },
            });
        }

        function openCustomerPaymentStatementModal(data) {
            if (!data) return;

            const clearDetails = Array.isArray(data.clear_details) ? data.clear_details : [];
            const clearTableBody = clearDetails.map((row, index) => [
                { data: index + 1, class: "w-[5%]" },
                { data: row.date || "-", class: "w-[16%]" },
                { data: row.method || "-", class: "w-[12%] capitalize" },
                { data: ((row.account_title || "-") + " | " + (row.bank || "-")).trim(), class: "w-[28%]" },
                { data: formatNumbersWithDigits(row.amount || 0, 1, 1), class: "w-[12%]" },
                { data: row.reff_no || "-", class: "w-[12%]" },
                { data: row.remarks || "-", class: "grow" },
            ]);

            createModal({
                id: "modalForm",
                class: clearTableBody.length > 0 ? "h-auto max-w-5xl" : "h-auto",
                name: data.name,
                details: {
                    Date: formatDate(data.data.date),
                    ...(data.program_date && { "Program Date": data.program_date }),
                    Amount: data.details?.Amount,
                    Type: data.details?.Type,
                    Method: data.details?.Method,
                    hr: true,
                    ...(data.data.cheque_no && { "Cheque No": data.data.cheque_no }),
                    ...(data.data.slip_no && { "Slip No": data.data.slip_no }),
                    ...(data.data.transaction_id && { "Transaction Id": data.data.transaction_id }),
                    ...(data.data.bank && { Bank: data.data.bank }),
                    ...(data.data.cheque_date && { "Cheque Date": formatDate(data.data.cheque_date) }),
                    ...(data.data.slip_date && { "Slip Date": formatDate(data.data.slip_date) }),
                    ...(data.clear_date && data.clear_date !== "Pending" && { "Clear Date": data.clear_date }),
                    ...(data.cleared_amount && { "Clear Amount": formatNumbersWithDigits(data.cleared_amount, 1, 1) }),
                    ...((data.data.method === "cheque" || data.data.method === "slip" || data.data.method === "program") && {
                        Issued: data.issued,
                    }),
                    Remarks: data.data.remarks || "No Remarks",
                },
                ...(clearTableBody.length > 0
                    ? {
                          table: {
                              name: "Clear Records",
                              headers: [
                                  { label: "#", class: "w-[5%]" },
                                  { label: "Date", class: "w-[16%]" },
                                  { label: "Method", class: "w-[12%]" },
                                  { label: "Acc. Title", class: "w-[28%]" },
                                  { label: "Amount", class: "w-[12%]" },
                                  { label: "Reff. No.", class: "w-[12%]" },
                                  { label: "Remarks", class: "grow" },
                              ],
                              body: clearTableBody,
                              scrollable: true,
                          },
                      }
                    : {}),
            });
        }

        function openSupplierPaymentStatementModal(data) {
            if (!data) return;

            const clearDetails = Array.isArray(data.clear_details) ? data.clear_details : [];
            const clearTableBody = clearDetails.map((row, index) => [
                { data: index + 1, class: "w-[5%]" },
                { data: row.date || "-", class: "w-[16%]" },
                { data: row.method || "-", class: "w-[12%] capitalize" },
                { data: ((row.account_title || "-") + " | " + (row.bank || "-")).trim(), class: "w-[28%]" },
                { data: formatNumbersWithDigits(row.amount || 0, 1, 1), class: "w-[12%]" },
                { data: row.reff_no || "-", class: "w-[12%]" },
                { data: row.remarks || "-", class: "grow" },
            ]);

            const details = {
                Date: data.date,
                Amount: data.amount,
                Method: data.method,
                "Customer/Self Acc.": data.source_name || "-",
                Source: data.source_type || "-",
                "Reff No.": data.reff_no ?? "-",
                "Voucher No.": data.voucher_no ?? "-",
            };

            if (data.program_no || data.program_date || data.program_customer) {
                details.hr = true;
                details["Program No"] = data.program_no || "-";
                details["Program Date"] = data.program_date || "-";
                details["Program Customer"] = data.program_customer || "-";
                if (data.program_order_no) details["Order No"] = data.program_order_no;
            }

            if (data.cr_no || data.cr_date) {
                details.hr = true;
                details["CR No"] = data.cr_no || "-";
                details["CR Date"] = data.cr_date || "-";
            }

            if (data.dr_no || data.dr_date) {
                details.hr = true;
                details["DR No"] = data.dr_no || "-";
                details["DR Date"] = data.dr_date || "-";
            }

            createModal({
                id: "modalForm",
                class: clearTableBody.length > 0 ? "h-auto max-w-5xl" : "h-auto",
                name: data.name,
                details,
                ...(clearTableBody.length > 0
                    ? {
                          table: {
                              name: "Clear Records",
                              headers: [
                                  { label: "#", class: "w-[5%]" },
                                  { label: "Date", class: "w-[16%]" },
                                  { label: "Method", class: "w-[12%]" },
                                  { label: "Acc. Title", class: "w-[28%]" },
                                  { label: "Amount", class: "w-[12%]" },
                                  { label: "Reff. No.", class: "w-[12%]" },
                                  { label: "Remarks", class: "grow" },
                              ],
                              body: clearTableBody,
                              scrollable: true,
                          },
                      }
                    : {}),
            });
        }

        function openStatementAdjustmentModal(data) {
            if (!data) return;

            createModal({
                id: "modalForm",
                name: data.name,
                details: {
                    Date: data.date,
                    Category: data.category,
                    Entry: data.entry_type,
                    Transaction: data.direction,
                    Amount: data.amount,
                    Remarks: data.remarks,
                },
                bottomActions: [
                    { id: "edit", text: "Edit", dataId: data.id, link: `/statement-adjustments/${data.id}/edit` },
                ],
            });
        }

        function renderStatementRecordModal(payload) {
            if (payload?.type === "expense") {
                openExpenseStatementModal(payload.data);
                return;
            }

            if (payload?.type === "voucher") {
                openVoucherStatementModal(payload.data);
                return;
            }

            if (payload?.type === "supplier_payment") {
                openSupplierPaymentStatementModal(payload.data);
                return;
            }

            if (payload?.type === "invoice") {
                openInvoiceStatementModal(payload.data);
                return;
            }

            if (payload?.type === "customer_payment") {
                openCustomerPaymentStatementModal(payload.data);
                return;
            }

            if (payload?.type === "statement_adjustment") {
                openStatementAdjustmentModal(payload.data);
            }
        }

        function setStatementRowLoading(row, isLoading) {
            if (!row) return;
            row.classList.toggle("opacity-60", isLoading);
            row.classList.toggle("pointer-events-none", isLoading);
        }

        function openStatementRecordModal(row) {
            if (!row?.dataset?.source) return;

            let source = null;
            try {
                source = JSON.parse(row.dataset.source);
            } catch (error) {
                console.error("Invalid statement source payload:", error);
                return;
            }

            if (!source?.type || !source?.id) return;

            if (statementRecordRequest && typeof statementRecordRequest.abort === "function") {
                statementRecordRequest.abort();
            }

            setStatementRowLoading(row, true);

            statementRecordRequest = $.ajax({
                url: config.recordDetailsUrl,
                type: "GET",
                data: source,
                success: function (response) {
                    renderStatementRecordModal(response);
                },
                error: function (xhr, status, error) {
                    if (status === "abort") return;
                    console.error("Error fetching statement record details:", error);
                    appAlert("Failed to load statement record details.");
                },
                complete: function () {
                    setStatementRowLoading(row, false);
                    statementRecordRequest = null;
                },
            });
        }

        window.setVoucherType = function setVoucherType(btn, btnType) {
            doHide = true;
            if (btnTypeGlobal == btnType) {
                return;
            }

            $.ajax({
                url: config.setTypeUrl,
                type: "POST",
                data: {
                    _token: config.csrfToken,
                    statement_type: btnType,
                },
                success: function () {
                    location.reload();
                },
                error: function () {
                    appAlert("Failed to update statement type.");
                    $(btn).prop("disabled", false);
                },
            });

            moveHighlight(btn, btnType);
        };

        function moveHighlight(btn, btnType) {
            const highlight = document.getElementById("highlight");
            const rect = btn.getBoundingClientRect();
            const parentRect = btn.parentElement.getBoundingClientRect();

            highlight.style.width = `${rect.width}px`;
            highlight.style.left = `${rect.left - parentRect.left - 3}px`;
            btnTypeGlobal = btnType;
        }

        const type = config.statementType || "general";
        const activeBtn =
            type === "general"
                ? document.querySelector("#generalBtn")
                : type === "summarized"
                    ? document.querySelector("#summarizedBtn")
                    : document.querySelector("#detailedBtn");
        if (activeBtn) {
            moveHighlight(activeBtn, type);
        }

        const today = new Date();
        const nameSelect = document.getElementById("nameSelect");
        const nameSelectDropDown = nameSelect.parentElement.parentElement.parentElement.querySelector(
            "ul.optionsDropdown"
        );
        const rangeSelect = document.getElementById("range");
        const rangeSelectDropDown = rangeSelect.parentElement.parentElement.parentElement.querySelector(
            "ul.optionsDropdown"
        );

        const dateFrom = document.getElementById("date_from");
        const dateTo = document.getElementById("date_to");
        let regDate;

        window.fetchNames = function fetchNames(category) {
            if (!category) return;

            $.ajax({
                url: config.getNamesUrl,
                type: "POST",
                data: {
                    _token: config.csrfToken,
                    category: category,
                },
                success: function (response) {
                    if (response.length > 0) {
                        let clutter = `
                            <li data-for="nameSelect" data-value="" onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg transition hover:bg-[var(--h-bg-color)]">
                                -- Select Name --
                            </li>
                        `;

                        response.forEach(function (item) {
                            let displayText = "";
                            if (category === "customer") {
                                displayText = item.customer_name + " | " + item.city?.title;
                            } else if (category === "supplier") {
                                displayText = item.supplier_name || "";
                            } else if (category === "bank_account") {
                                displayText = item.account_title || "";
                            }

                            clutter += `
                                <li data-for="nameSelect" data-value="${item.id}" data-reg-date="${item.date}"
                                    onmousedown="selectThisOption(this)"
                                    class="py-2 px-3 cursor-pointer rounded-lg transition hover:bg-[var(--h-bg-color)] text-nowrap overflow-x-auto scrollbar-hidden">
                                    ${displayText}
                                </li>
                            `;
                        });

                        nameSelectDropDown.innerHTML = clutter;
                        nameSelect.disabled = false;

                        if (portal?.category && portal?.id && portal.category === category) {
                            const option = nameSelectDropDown.querySelector(`li[data-value="${portal.id}"]`);
                            if (option) {
                                selectThisOption(option);
                                nameSelect.disabled = true;
                            }
                        }
                    } else {
                        const option = document.createElement("option");
                        option.value = "";
                        option.textContent = `
                            <li data-for="nameSelect" data-value="" onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg transition hover:bg-[var(--h-bg-color)]">
                                -- No Names Found --
                            </li>
                        `;
                        nameSelectDropDown.appendChild(option);
                    }
                },
                error: function (xhr, status, error) {
                    console.error("Error fetching names:", error);
                },
            });
        };

        window.nameChanged = function nameChanged(nameSelectDbInput) {
            if (nameSelectDbInput.value) {
                const selectedName = nameSelectDbInput.nextElementSibling.querySelector(
                    `li[data-value="${nameSelectDbInput.value}"]`
                );
                if (!selectedName) return;

                const rawRegDate = parseLocalDate(selectedName.dataset.regDate);
                regDate = localDateString(rawRegDate);
                dateFrom.min = regDate;
                const todayLocal = new Date();

                function monthDiff(d1, d2) {
                    let months = (d2.getFullYear() - d1.getFullYear()) * 12;
                    months -= d1.getMonth();
                    months += d2.getMonth();
                    return months <= 0 ? 0 : months;
                }

                const monthsSinceReg = monthDiff(rawRegDate, todayLocal);
                const ranges = [];

                if (monthsSinceReg >= 0) ranges.push({ value: "current_month", label: "Current Month" });
                if (monthsSinceReg >= 1) ranges.push({ value: "last_month", label: "Last Month" });
                if (monthsSinceReg >= 3)
                    ranges.push({ value: "last_three_months", label: "Last Three Months" });
                if (monthsSinceReg >= 6) ranges.push({ value: "last_six_months", label: "Last Six Months" });
                ranges.push({ value: "custom", label: "Custom" });

                const clutter = ranges
                    .map(
                        r => `
                    <li data-for="range" data-value="${r.value}"
                        onmousedown="selectThisOption(this)"
                        class="py-2 px-3 cursor-pointer rounded-lg transition hover:bg-[var(--h-bg-color)] text-nowrap overflow-x-auto scrollbar-hidden">
                        ${r.label}
                    </li>`
                    )
                    .join("");

                rangeSelectDropDown.innerHTML = clutter;
                rangeSelect.disabled = false;
            } else {
                rangeSelect.value = "";
                rangeSelect.disabled = true;
            }
        };

        window.updateDateConstraints = function updateDateConstraints() {
            if (dateFrom.value) {
                dateTo.min = dateFrom.value;
            }
            if (dateTo.value) {
                dateFrom.max = dateTo.value;
            }
        };

        function isLastDayOfMonth(date) {
            return date.getDate() === new Date(date.getFullYear(), date.getMonth() + 1, 0).getDate();
        }

        window.applyRange = function applyRange(rangeValue) {
            let from = null,
                to = null;

            switch (rangeValue) {
                case "custom":
                    dateFrom.value = regDate;
                    dateTo.value = localDateString();
                    dateFrom.disabled = false;
                    dateTo.disabled = false;
                    return;

                case "current_month":
                    from = new Date(today.getFullYear(), today.getMonth(), 1);
                    to = today;
                    break;

                case "last_month":
                    from = new Date(today.getFullYear(), today.getMonth() - 1, 1);
                    to = today;
                    break;

                case "last_three_months":
                    from = new Date(today.getFullYear(), today.getMonth() - 2, 1);
                    to = today;
                    break;

                case "last_six_months":
                    from = new Date(today.getFullYear(), today.getMonth() - 5, 1);
                    to = today;
                    break;

                default:
                    dateFrom.value = "";
                    dateTo.value = "";
                    dateFrom.disabled = true;
                    dateTo.disabled = true;
                    return;
            }

            dateFrom.value = localDateString(from);
            dateTo.value = localDateString(to);
            dateFrom.disabled = true;
            dateTo.disabled = true;
        };

        window.getStatement = function getStatement() {
            const category = document
                .querySelector('ul[data-for="category"] li.selected')
                .textContent.trim()
                .toLowerCase();
            const id = document.querySelector('input[data-for="nameSelect"]').value;
            const dateFromVal = document.getElementById("date_from").value;
            const dateToVal = document.getElementById("date_to").value;

            $.ajax({
                url: config.statementUrl,
                type: "GET",
                data: {
                    _token: config.csrfToken,
                    withData: false,
                    type: config.statementType,
                    category: category,
                    id: id,
                    date_from: dateFromVal !== "" ? dateFromVal : regDate,
                    date_to: dateToVal !== "" ? dateToVal : localDateString(today),
                },
                success: function (response) {
                    renderStatement(response);
                },
                error: function (xhr, status, error) {
                    console.error("Error fetching statement:", error);
                },
            });
        };

        function renderStatement(response) {
            const $responseHtml = $(response);
            const $previewInResponse = $responseHtml.find(".step2");

            if ($previewInResponse.length) {
                $(".step2").html($previewInResponse.html());
            } else {
                console.warn(".step2 not found in response HTML.");
            }
        }

        window.onClickOnPrintBtn = function onClickOnPrintBtn() {
            const preview = document.getElementById("preview-container");
            const clone = preview.cloneNode(true);

            clone.querySelectorAll(":scope > hr").forEach(hr => hr.remove());

            const oldIframe = document.getElementById("printIframe");
            if (oldIframe) {
                oldIframe.remove();
            }

            const printIframe = document.createElement("iframe");
            printIframe.id = "printIframe";
            printIframe.style.position = "absolute";
            printIframe.style.width = "0px";
            printIframe.style.height = "0px";
            printIframe.style.border = "none";
            printIframe.style.display = "none";

            document.body.appendChild(printIframe);

            const printDocument = printIframe.contentDocument || printIframe.contentWindow.document;
            printDocument.open();

            const headContent = document.head.innerHTML;

            printDocument.write(`
                <html>
                    <head>
                        <title>Print Statement</title>
                        ${headContent}
                        <style>
                            @page {
                                size: A4;
                                margin: 0;
                            }

                            body {
                                margin: 0;
                                padding: 0;
                                background: #fff;
                            }
                        </style>
                    </head>
                    <body>
                        ${clone.innerHTML}
                    </body>
                </html>
            `);

            printDocument.close();

            printIframe.onload = () => {
                printIframe.contentWindow.focus();
                printIframe.contentWindow.print();
            };
        };

        window.validateForNextStep = function validateForNextStep() {
            getStatement();
            return true;
        };

        function forcePortalSelections() {
            if (!portal?.category || !portal?.id) return;

            const categoryUl = document.querySelector('ul[data-for="category"]');
            const categoryVisible = document.getElementById('category');
            const nameVisible = document.getElementById('nameSelect');

            const categoryLi = categoryUl?.querySelector(`li[data-value="${portal.category}"]`);
            if (categoryLi) {
                selectThisOption(categoryLi);
            }

            if (categoryVisible) categoryVisible.disabled = true;
            if (nameVisible) nameVisible.disabled = true;

            fetchNames(portal.category);
        }

        if (step2 && !step2.dataset.statementModalBound) {
            step2.dataset.statementModalBound = "1";

            step2.addEventListener("click", e => {
                const row = e.target.closest(".statement-record-trigger");
                if (!row) return;
                openStatementRecordModal(row);
            });

            step2.addEventListener("keydown", e => {
                if (e.key !== "Enter" && e.key !== " ") return;

                const row = e.target.closest(".statement-record-trigger");
                if (!row) return;

                e.preventDefault();
                openStatementRecordModal(row);
            });
        }

        forcePortalSelections();
    }

    window.initReportsStatement = initReportsStatement;

    function boot() {
        if (window.__reportsStatement) {
            initReportsStatement();
        }
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", boot);
    } else {
        boot();
    }
})();
