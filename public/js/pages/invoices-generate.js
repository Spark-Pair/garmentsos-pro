(() => {
    function initInvoicesGenerate() {
        const config = window.__invoicesGenerate || {};
        const invoiceType = config.invoiceType || "order";
        const csrfToken = config.csrfToken || "";
        const lastInvoice = config.lastInvoice || null;
        const companyData = config.companyData || {};
        const orderNumber = config.orderNumber || null;
        const companyLogoBase = config.companyLogoBase || "";
        const searchFieldsHtml = config.searchFieldsHtml || "";
        const errorAlertTemplate = config.errorAlertTemplate || "";
        let invoicePreviewOffset = 0;

        function safeDocumentNumberPreview(value, fallback = "Will be generated on save") {
            const text = String(value ?? "").trim();
            return text && !text.includes("NaN") ? text : fallback;
        }

        function incrementDocumentNumber(value, offset = 0, fallback = "Will be generated on save") {
            const text = safeDocumentNumberPreview(value, "");
            if (!text) return fallback;

            const replaced = text.replace(/(\d+)(?!.*\d)/, match => {
                const next = Number.parseInt(match, 10) + offset;
                return Number.isFinite(next) ? String(next).padStart(match.length, "0") : match;
            });

            return safeDocumentNumberPreview(replaced, fallback);
        }

        function invoiceDetailLine(orderedArticle, article) {
            const description = String(orderedArticle?.description ?? '').trim();
            const fabricType = String(article?.fabric_type ?? orderedArticle?.fabric_type ?? orderedArticle?.article?.fabric_type ?? '').trim();
            const parts = [description, fabricType].filter((part, index, list) => (
                part && list.findIndex(item => item.toLowerCase() === part.toLowerCase()) === index
            ));

            return parts.length ? parts.join(' | ') : '';
        }

        function customerTitlePhoneLine(customer = {}) {
            const title = String(customer?.urdu_title ?? '').trim();
            const phone = String(customer?.phone_number ?? '').trim();
            return [title, phone].filter(Boolean).join(' | ');
        }

        function deliverToLine(previewData = {}) {
            const deliverTo = String(previewData?.deliver_to ?? previewData?.order?.deliver_to ?? '').trim();
            return `Deliver To: ${deliverTo || '-'}`;
        }

        function articleSortValue(row = {}) {
            return String(row?.article?.article_no ?? row?.article_no ?? '').trim();
        }

        function sortArticleRows(rows) {
            return [...(Array.isArray(rows) ? rows : [])].sort((left, right) => (
                articleSortValue(left).localeCompare(articleSortValue(right), undefined, {
                    numeric: true,
                    sensitivity: 'base',
                })
            ));
        }

        function printDateTime() {
            return new Date().toLocaleString('en-GB', {
                day: '2-digit',
                month: 'short',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
                hour12: true,
            }).replace(',', '');
        }

        function previewText(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function truthySetting(value) {
            return value === true || value === 1 || value === '1' || value === 'true';
        }

        function invoiceDocumentSettings(previewData = {}) {
            return previewData.branch_branding || companyData || {};
        }

        function invoiceDiscountDisabled(previewData = {}) {
            return truthySetting(invoiceDocumentSettings(previewData).discount_disabled);
        }

        function invoiceDocumentTotalsHtml(previewData, totalPackets, totalPcs, totalAmount, discountVal, discountAmount, netAmount) {
            if (invoiceDiscountDisabled(previewData)) {
                const note = String(invoiceDocumentSettings(previewData).document_note || '').trim();
                return `
                    ${note ? `
                        <div class="total col-span-2 flex justify-center items-center border border-black rounded-lg py-1.5 px-4 w-full text-center font-semibold">
                            ${previewText(note)}
                        </div>
                    ` : ''}
                    <div class="total flex justify-between items-center border border-black rounded-lg py-1.5 px-4 w-full">
                        <div class="text-nowrap">Total Quantity</div>
                        <div class="w-1/4 text-right grow">${formatNumbersDigitLess(totalPackets)} | ${formatNumbersDigitLess(totalPcs)}</div>
                    </div>
                    <div class="total flex justify-between items-center border border-black rounded-lg py-1.5 px-4 w-full">
                        <div class="text-nowrap font-semibold">Net Amount</div>
                        <div class="w-1/4 text-right grow font-semibold">${formatNumbersWithDigits(totalAmount, 1, 1)}</div>
                    </div>
                `;
            }

            return `
                <div class="total flex justify-between items-center border border-black rounded-lg py-1.5 px-4 w-full">
                    <div class="text-nowrap">Total Quantity</div>
                    <div class="w-1/4 text-right grow">${formatNumbersDigitLess(totalPackets)} | ${formatNumbersDigitLess(totalPcs)}</div>
                </div>
                <div class="total flex justify-between items-center border border-black rounded-lg py-1.5 px-4 w-full">
                    <div class="text-nowrap">Gross Amount</div>
                    <div class="w-1/4 text-right grow">${formatNumbersWithDigits(totalAmount, 1, 1)}</div>
                </div>
                <div class="total flex justify-between items-center border border-black rounded-lg py-1.5 px-4 w-full">
                    <div class="text-nowrap">Discount ${discountVal}%</div>
                    <div class="w-1/4 text-right grow">${formatNumbersWithDigits(discountAmount, 1, 1)}</div>
                </div>
                <div class="total flex justify-between items-center border border-black rounded-lg py-1.5 px-4 w-full">
                    <div class="text-nowrap">Net Amount</div>
                    <div class="w-1/4 text-right grow">${formatNumbersWithDigits(netAmount, 1, 1)}</div>
                </div>
            `;
        }

        function chunkInvoiceRows(rows) {
            const source = Array.isArray(rows) ? rows : [];
            const chunks = [];
            let remaining = source.slice();
            const maxRowsWithoutTotals = 13;
            const maxRowsWithTotals = 11;

            if (remaining.length <= maxRowsWithTotals) {
                return [remaining];
            }

            while (remaining.length > maxRowsWithTotals) {
                const take = remaining.length <= maxRowsWithoutTotals + maxRowsWithTotals
                    ? Math.min(maxRowsWithoutTotals, remaining.length - 1)
                    : maxRowsWithoutTotals;
                chunks.push(remaining.slice(0, take));
                remaining = remaining.slice(take);
            }

            chunks.push(remaining);
            return chunks;
        }

        function compactInvoicePrintHeaders(printDocument) {
            const labels = {
                'S.No': 'S.#',
                'Packets': 'Pkts',
                'Rate/Pc.': 'Rate',
            };

            printDocument.querySelectorAll('.gos-a5-invoice .thead .th').forEach(header => {
                const compactLabel = labels[header.textContent.trim()];
                if (compactLabel) {
                    header.textContent = compactLabel;
                }
            });
        }

        function nextInvoicePreviewNo() {
            const base = config.nextInvoiceNo || incrementDocumentNumber(lastInvoice?.invoice_no, 1);
            const previewNo = incrementDocumentNumber(base, invoicePreviewOffset);
            invoicePreviewOffset += 1;
            return previewNo;
        }

        let btnTypeGlobal = "order";

        function moveHighlight(btn, btnType) {
            const highlight = document.getElementById("highlight");
            if (!highlight || !btn || !btn.parentElement) return;

            const rect = btn.getBoundingClientRect();
            const parentRect = btn.parentElement.getBoundingClientRect();

            highlight.style.width = `${rect.width}px`;
            highlight.style.left = `${rect.left - parentRect.left - 3}px`;

            btnTypeGlobal = btnType;
        }

        window.setInvoiceType = function setInvoiceType(btn, btnType) {
            if (btnTypeGlobal === btnType) {
                return;
            }

            doHide = true;

            $.ajax({
                url: "/set-invoice-type",
                type: "POST",
                data: {
                    _token: csrfToken,
                    invoice_type: btnType,
                },
                success: function () {
                    location.reload();
                },
                error: function () {
                    appAlert("Failed to update invoice type.");
                    $(btn).prop("disabled", false);
                },
            });

            moveHighlight(btn, btnType);
        };

        const initialBtn =
            invoiceType === "order"
                ? document.querySelector("#orderBtn")
                : document.querySelector("#shipmentBtn");
        moveHighlight(initialBtn, invoiceType === "order" ? "order" : "shipment");

        let articlesInInvoice = [];
        let totalQuantityPcs = 0;
        let totalAmount = 0;
        let netAmount = 0;
        let discount = 0;
        let isModalOpened = false;
        let allDataArray = [];

        function renderError(message) {
            if (typeof messageBox === "undefined") return;
            if (!errorAlertTemplate) return;

            messageBox.innerHTML = errorAlertTemplate.replace("__MESSAGE__", message);
            messageBoxAnimation();
        }

        function buildA5InvoicePreviewPages(previewData, copyLabel, articles, options = {}) {
            const invoiceRows = sortArticleRows(articles);
            const pages = chunkInvoiceRows(invoiceRows);
            const discountVal = Number(previewData.discount || 0);
            let totalAmount = 0;
            let totalPcs = 0;
            let totalPackets = 0;

            invoiceRows.forEach((orderedArticle) => {
                const article = orderedArticle.article || {};
                const salesRate = parseFormattedNumber(article.sales_rate);
                const qty = orderedArticle.invoice_pcs ?? orderedArticle.shipment_pcs ?? orderedArticle.ordered_pcs ?? 0;
                totalAmount += salesRate * qty;
                totalPcs += qty;
                totalPackets += article?.pcs_per_packet ? Math.floor(qty / article.pcs_per_packet) : 0;
            });

            const discountAmount = discountVal ? (totalAmount * discountVal) / 100 : 0;
            const netAmount = previewData.netAmount ?? (totalAmount - discountAmount);
            let rowNumber = 1;

            return pages.map((pageArticles, pageIndex) => {
                const isLastPage = pageIndex === pages.length - 1;
                const invoiceTableBody = pageArticles.map((orderedArticle) => {
                    const article = orderedArticle.article || {};
                    const salesRate = parseFormattedNumber(article.sales_rate);
                    const qty = orderedArticle.invoice_pcs ?? orderedArticle.shipment_pcs ?? orderedArticle.ordered_pcs ?? 0;
                    const total = salesRate * qty;
                    const detailLine = invoiceDetailLine(orderedArticle, article);
                    const serial = String(rowNumber++).padStart(2, '0');

                    return `
                        <div class="invoice-item-row">
                            <div class="tr invoice-item-main grid grid-cols-8 justify-between w-full px-4 gap-0.5">
                                <div class="td text-sm font-semibold truncate">${serial}</div>
                                <div class="td invoice-article-cell text-sm font-semibold">
                                    <div class="invoice-article-code">${article.article_no ?? ''}</div>
                                </div>
                                <div class="td invoice-description-cell text-sm font-semibold">${detailLine}</div>
                                <div class="td text-sm font-semibold truncate">${article?.pcs_per_packet ?? 0}</div>
                                <div class="td text-sm font-semibold truncate">${article?.pcs_per_packet ? Math.floor(qty / article.pcs_per_packet) : 0}</div>
                                <div class="td text-sm font-semibold truncate">${qty}</div>
                                <div class="td text-sm font-semibold truncate">${formatNumbersDigitLess(salesRate)}</div>
                                <div class="td text-sm font-semibold truncate">${formatNumbersDigitLess(total)}</div>
                            </div>
                        </div>
                    `;
                }).join('');

                const invoiceBottom = isLastPage
                    ? invoiceDocumentTotalsHtml(previewData, totalPackets, totalPcs, totalAmount, discountVal, discountAmount, netAmount)
                    : '';

                return `
                        <div id="preview" class="preview w-[148mm] h-[210mm] gos-a5-document gos-a5-invoice overflow-hidden flex flex-col">
                            <div class="flex flex-col h-full">
                                <div id="banner" class="banner w-full flex justify-between items-center px-5">
                                    <div class="left">
                                        <div class="logo flex flex-col">
                                            <div class="flex items-center gap-3">
                                                ${(companyData.logo_url || companyData.logo) ? `
                                                    <div class="h-[3.50rem] w-[13.5rem] flex items-center justify-center gap-2.5">
                                                        <img src="${companyData.logo_url || `${companyLogoBase}/${companyData.logo}`}" alt="garmentsos-pro" class="max-h-full max-w-full object-contain" />
                                                        ${companyData.logo_text ? `<h1 class="text-lg font-bold tracking-wide">${companyData.logo_text}</h1>` : ''}
                                                    </div>
                                                ` : ''}
                                            </div>
                                            ${(companyData.phone_number || companyData.phone) ? `<div class="mt-2 text-sm text-gray-600">${companyData.phone_number || companyData.phone}</div>` : ''}
                                        </div>
                                    </div>
                                            <div class="right">
                                        <div class="logo text-right">
                                            <h1 class="text-2xl font-medium text-[var(--h-primary-color)]">Sales Invoice</h1>
                                            <div class="document-number mt-1 text-right">Invoice No.: ${previewData.invoice_no}</div>
                                        </div>
                                    </div>
                                </div>
                                <hr class="w-full my-3 border-black">
                                <div id="header" class="header w-full flex justify-between px-5">
                                    <div class="left grow min-w-0 pr-3 space-y-1">
                                        <div class="customer text-lg leading-none capitalize font-medium text-nowrap">M/s: ${previewData.customer.customer_name}</div>
                                        <div class="person text-md text-lg leading-none">${customerTitlePhoneLine(previewData.customer)}</div>
                                        <div class="address text-md leading-none">${previewData.customer.address ?? ''}, ${previewData.customer.city?.title ?? ''}</div>
                                        <div class="phone text-md leading-none">${deliverToLine(previewData)}</div>
                                    </div>
                                    <div class="right shrink-0 min-w-[38%] my-auto text-right text-sm text-black space-y-1.5">
                                        <div class="date leading-none">Date: ${formatDate(previewData.date)}</div>
                                        ${options.showOrderNo && previewData.order_no ? `<div class="number leading-none capitalize">Order No.: ${previewData.order_no}</div>` : options.showShipmentNo && previewData.shipment_no ? `<div class="number leading-none capitalize">Shipment No.: ${previewData.shipment_no}</div>` : ''}
                                        <input type="hidden" name="invoice_no" value="${previewData.invoice_no}" />
                                        <div class="preview-copy leading-none capitalize">Invoice Copy: ${copyLabel}</div>
                                        <div class="number leading-none capitalize">Carton: ${previewData.carton_count || '-'}</div>
                                    </div>
                                </div>
                                <hr class="w-full my-3 border-black">
                                <div class="body w-full px-5 grow mx-auto">
                                    <div class="table w-full">
                                        <div class="table w-full border border-black rounded-lg pb-2.5 overflow-hidden">
                                            <div class="thead w-full">
                                                <div class="tr grid grid-cols-8 w-full px-4 py-1.5 bg-[var(--primary-color)] text-white">
                                                    <div class="th text-sm font-medium">S.#</div>
                                                    <div class="th text-sm font-medium">Article</div>
                                                    <div class="th text-sm font-medium">Description</div>
                                                    <div class="th text-sm font-medium">Unit</div>
                                                    <div class="th text-sm font-medium">Pkts</div>
                                                    <div class="th text-sm font-medium">Pcs.</div>
                                                    <div class="th text-sm font-medium">Rate</div>
                                                    <div class="th text-sm font-medium">Amount</div>
                                                </div>
                                            </div>
                                            <div id="tbody" class="tbody w-full">${invoiceTableBody}</div>
                                        </div>
                                    </div>
                                </div>
                                ${invoiceBottom ? `<div class="grid grid-cols-2 gap-2 px-5">${invoiceBottom}</div>` : ''}
                                <hr class="w-full my-3 border-black">
                                <div class="tfooter flex w-full text-sm px-4 justify-between text-black">
                                    <p class="leading-none">Powered by SparkPair</p>
                                    <p class="leading-none text-sm">Page ${pageIndex + 1} of ${pages.length}</p>
                                    <p class="leading-none text-sm">Printed: ${printDateTime()}</p>
                                    <p class="leading-none text-sm">&copy; ${new Date().getFullYear()} SparkPair | +92 316 5825495</p>
                                </div>
                            </div>
                    </div>
                `;
            }).join('');
        }

        if (invoiceType === "shipment") {
            let shipmentArticles = [];
            const shipmentNoDom = document.getElementById("shipment_no");
            const shipmentNoValueDom = document.querySelector('.dbInput[data-for="shipment_no"]');
            const selectCustomersBtn = document.getElementById("selectCustomersBtn");
            if (!shipmentNoDom || !selectCustomersBtn) return;
            selectCustomersBtn.disabled = true;

            let selectedCustomersArray = [];
            let ogMaxCartonCount = 0;
            let allCustomers = [];
            let maxCartonCount = 0;
            const previousApplyFilters =
                typeof window.applyFilters === "function"
                    ? window.applyFilters
                    : null;
            const previousClearAllSearchFields =
                typeof window.clearAllSearchFields === "function"
                    ? window.clearAllSearchFields
                    : null;

            function getValueByPath(source, path) {
                return String(
                    path.split(".").reduce((acc, key) => acc?.[key], source) ?? ""
                ).toLowerCase();
            }

            function getModalFilterInputs() {
                const modal = document.getElementById("modalForm");
                if (!modal) return [];

                return Array.from(modal.querySelectorAll("[data-filter-path]"));
            }

            function getCustomerModal() {
                return document.getElementById("modalForm");
            }

            function getCustomerFilterTrigger() {
                return getCustomerModal()?.querySelector("#search-form .dropdown-trigger") || null;
            }

            function getCustomerFilterMenu() {
                return getCustomerFilterTrigger()?.nextElementSibling || null;
            }

            function selectedShipmentNo() {
                return (shipmentNoValueDom?.value || shipmentNoDom.value || "").trim();
            }

            function isCustomerFilterMenuOpen() {
                const menu = getCustomerFilterMenu();
                return !!menu && !menu.classList.contains("hidden");
            }

            function focusFirstCustomerFilterField() {
                const menu = getCustomerFilterMenu();
                if (!menu) return;

                const firstField = menu.querySelector("[data-filter-path]");
                if (!firstField) return;

                if (firstField.classList.contains("dbInput")) {
                    const targetId = firstField.getAttribute("data-for") || firstField.id;
                    const visibleInput = menu.querySelector(`#${CSS.escape(targetId)}`);
                    visibleInput?.focus();
                    visibleInput?.select?.();
                    return;
                }

                firstField.focus();
                firstField.select?.();
            }

            function openCustomerFilterAndFocusFirstField() {
                const trigger = getCustomerFilterTrigger();
                if (!trigger) return;

                if (!isCustomerFilterMenuOpen()) {
                    trigger.click();
                }

                window.setTimeout(focusFirstCustomerFilterField, 60);
            }

            function toggleCustomerFilterPanel() {
                getCustomerFilterTrigger()?.click();
            }

            function applyCustomerFiltersFromModal() {
                if (!getCustomerModal() || !Array.isArray(allCustomers)) return;

                const filterInputs = getModalFilterInputs();

                const filteredCustomers = allCustomers.filter((customer) => {
                    return filterInputs.every((input) => {
                        const path = input.getAttribute("data-filter-path");
                        const rawValue = String(input.value ?? "").trim().toLowerCase();

                        if (!path || rawValue === "") return true;

                        return getValueByPath(customer, path).includes(rawValue);
                    });
                });

                renderTableBody(generateTableBody(filteredCustomers));
                document.getElementById("total-count").value = filteredCustomers.length;
                updateSelectedCount();
                addListeners();
                updateCustomerRowsState();
                closeAllDropdowns();
            }

            function clearModalSelectField(field, modal) {
                if (!field.classList.contains("dbInput")) return;

                const targetId = field.getAttribute("data-for");
                if (!targetId) return;

                const visibleInput = modal.querySelector(`#${CSS.escape(targetId)}`);
                const defaultOption = modal.querySelector(
                    `.optionsDropdown li[data-for="${CSS.escape(targetId)}"][data-value=""]`
                );

                if (visibleInput) {
                    visibleInput.value = defaultOption?.textContent.trim() || "";
                }

                modal
                    .querySelectorAll(`.optionsDropdown li[data-for="${CSS.escape(targetId)}"]`)
                    .forEach((option) => {
                        option.classList.toggle("selected", option === defaultOption);
                        option.classList.remove("hidden");
                    });
            }

            window.applyFilters = function applyShipmentCustomerFilters() {
                if (getCustomerModal()) {
                    applyCustomerFiltersFromModal();
                    return;
                }

                previousApplyFilters?.();
            };

            window.clearAllSearchFields = function clearAllShipmentCustomerSearchFields() {
                const modal = getCustomerModal();
                const filterInputs = getModalFilterInputs();

                if (modal && filterInputs.length) {
                    filterInputs.forEach((field) => {
                        field.value = "";
                        clearModalSelectField(field, modal);
                    });

                    applyCustomerFiltersFromModal();
                    return;
                }

                if (typeof previousClearAllSearchFields === "function") {
                    previousClearAllSearchFields();
                }
            };

            document.addEventListener("keydown", (event) => {
                if (!getCustomerModal()) return;

                const activeElement = document.activeElement;
                const isTypingTarget =
                    activeElement &&
                    (activeElement.tagName === "INPUT" ||
                        activeElement.tagName === "TEXTAREA" ||
                        activeElement.isContentEditable);

                if (
                    event.key === "`" &&
                    !event.altKey &&
                    !event.ctrlKey &&
                    !event.metaKey &&
                    !isTypingTarget
                ) {
                    event.preventDefault();
                    openCustomerFilterAndFocusFirstField();
                    return;
                }

                if (!event.altKey || event.ctrlKey || event.metaKey) return;

                const shortcutKey = event.key.toLowerCase();

                if (shortcutKey === "f") {
                    event.preventDefault();
                    toggleCustomerFilterPanel();
                } else if (shortcutKey === "s") {
                    event.preventDefault();
                    applyCustomerFiltersFromModal();
                } else if (shortcutKey === "c") {
                    event.preventDefault();
                    window.clearAllSearchFields();
                }
            });

            shipmentNoDom.addEventListener("keydown", (e) => {
                if (e.key === "Enter") {
                    getShipmentDetails();
                }
            });

            selectCustomersBtn.addEventListener("click", () => {
                getShipmentDetails();
            });

            function createRow(data) {
                return `
                <div id="${data.id}" oncontextmenu='${htmlAttr(data.oncontextmenu || "")}' onclick='${htmlAttr(data.onclick || "")}'
                    class="item row relative group grid text- grid-cols-8 border-b border-[var(--h-bg-color)] items-center py-2 cursor-pointer hover:bg-[var(--h-secondary-bg-color)] transition-all fade-in ease-in-out"
                    data-json='${jsonAttr(data)}'>

                    <span class="text-left pl-5">${data.details["Urdu Title"]}</span>
                    <span class="text-center capitalize">${data.details["Category"]}</span>
                    <span class="text-center capitalize">${data.city}</span>
                    <span class="text-center">${data.phone_number}</span>
                    <span class="text-right">${formatMoney(data.details["Balance"])}</span>
                </div>`;
            }

            function getShipmentDetails() {
                $.ajax({
                    url: "/get-shipment-details",
                    type: "POST",
                    data: {
                        _token: csrfToken,
                        shipment_no: selectedShipmentNo(),
                    },
                    success: function (response) {
                        if (!response.error) {
                            shipmentArticles = response.shipment.articles;
                            discount = response.shipment.discount ?? 0;
                            allCustomers = response.customers;

                            allCustomers.forEach((item) => {
                                item.visible = true;
                            });

                            allDataArray = allCustomers;

                            generateModal(allCustomers);
                            search_container = document.querySelector(".search_container");
                            tableHead = document.getElementById("table-head");
                            calculateNoOfSelectableCustomers(shipmentArticles);
                            document.getElementById("total-count").value = allCustomers.length ?? 0;
                            addListeners();
                        } else {
                            shipmentArticles = [];
                            discount = 0;
                            allCustomers = "";
                            allDataArray = [];
                            renderError(response.error);
                        }
                        renderList();
                        renderCalcBottom();
                    },
                });
            }

            function calculateNoOfSelectableCustomers(articlesArray) {
                let countOfCartonsOfArticles = [];

                articlesArray.forEach((article) => {
                    countOfCartonsOfArticles.push(
                        Math.floor(article.available_stock / article.shipment_pcs)
                    );
                });

                maxCartonCount = Math.min(...countOfCartonsOfArticles);
                ogMaxCartonCount = maxCartonCount;

                document.getElementById("max-cartons-count").value = maxCartonCount;
            }

            function generateModal(data, animate = "animate", fieldsHtml = null) {
                let tableBody = [];

                tableBody = generateTableBody(data);

                let modalData = {
                    id: "modalForm",
                    class: "h-[45rem] max-w-6xl",
                    name: "Customers",
                    searchFilter: {
                        fieldsHtml: fieldsHtml || searchFieldsHtml,
                    },
                    table: {
                        name: "Customers",
                        headers: [
                            { label: "Select", class: "text-left pl-5 flex items-center w-[12%]" },
                            { label: "Customer", class: "grow text-center" },
                            { label: "Urdu Title", class: "w-[15%] text-center" },
                            { label: "Category", class: "w-[15%] text-center" },
                            { label: "Balance", class: "w-[15%] text-center" },
                        ],
                        body: tableBody,
                        selectableRow: true,
                        scrollable: true,
                    },
                    calcBottom: [
                        { label: "Total Customers", name: "total-count", value: "0", disabled: true },
                        { label: "Selected Customers", name: "selected-count", value: "0", disabled: true },
                        { label: "Max Cartons Count", name: "max-cartons-count", value: "0", disabled: true },
                    ],
                };

                createModal(modalData, animate);
            }

            function generateTableBody(data) {
                const tableBody = data
                    .filter((item) => item.visible === true)
                    .map((item) => {
                        const selected = selectedCustomersArray.find((c) => c.id === item.id);
                        const isSelected = !!selected;

                        return [
                            {
                                checkbox: true,
                                checked: isSelected,
                                class: "text-left pl-5 flex items-center w-[12%]",
                                jsonData: item,
                                input: {
                                    name: "carton_count",
                                    class: "cartonCount",
                                    type: "number",
                                    value: selected?.carton_count || "1",
                                    min: "1",
                                    oninput: "validateCartonCount(this)",
                                    onclick: "this.select()",
                                },
                            },
                            { data: item.customer_name + " | " + item.city.title, class: "grow text-center" },
                            { data: item.urdu_title, class: "w-[15%] text-center" },
                            { data: item.category, class: "w-[15%] text-center" },
                            { data: item.balance, class: "w-[15%] text-center" },
                        ];
                    });

                return tableBody;
            }

            function setArrayToCustomersArrayInput() {
                const customersArrayInput = document.getElementById("customers_array");
                let finalCustomersArray = selectedCustomersArray.map((customer) => {
                    return {
                        id: customer.id,
                        carton_count: customer.carton_count,
                    };
                });
                customersArrayInput.value = JSON.stringify(finalCustomersArray);
            }

            shipmentNoDom.addEventListener("input", () => {
                trackStateOfShipmentNo(selectedShipmentNo());
            });
            shipmentNoValueDom?.addEventListener("change", () => trackStateOfShipmentNo(selectedShipmentNo()));

            function trackStateOfShipmentNo(value) {
                if (value !== "") {
                    selectCustomersBtn.disabled = false;
                } else {
                    selectCustomersBtn.disabled = true;
                }
            }

            const articleListDOM = document.getElementById("article-list");
            function renderList() {
                if (shipmentArticles && shipmentArticles.length > 0) {
                    totalAmount = 0;
                    totalQuantityPcs = 0;

                    let clutter = "";
                    sortArticleRows(shipmentArticles).forEach((selectedArticle, index) => {
                        if (selectedArticle.available_stock > selectedArticle.shipment_pcs) {
                            totalQuantityPcs += selectedArticle.shipment_pcs;

                            let articleAmount =
                                selectedArticle.article.sales_rate * selectedArticle.shipment_pcs;

                            clutter += `
                                <div class="flex justify-between items-center border-t border-gray-600 py-3 px-4">
                                    <div class="w-[5%]">${index + 1}.</div>
                                    <div class="w-[11%]">${selectedArticle.article.article_no}</div>
                                    <div class="w-[11%] pr-3">${Math.floor(
                                        formatNumbersDigitLess(
                                            selectedArticle.shipment_pcs / selectedArticle.article.pcs_per_packet
                                        )
                                    )}</div>
                                    <div class="w-[10%]">${formatNumbersDigitLess(
                                        selectedArticle.shipment_pcs
                                    )}</div>
                                    <div class="grow">${selectedArticle.description}</div>
                                    <div class="w-[8%]">${selectedArticle.article.pcs_per_packet}</div>
                                    <div class="w-[12%] text-right">${formatNumbersWithDigits(
                                        selectedArticle.article.sales_rate,
                                        1,
                                        1
                                    )}</div>
                                    <div class="w-[15%] text-right">${formatNumbersWithDigits(
                                        articleAmount,
                                        1,
                                        1
                                    )}</div>
                                </div>
                            `;

                            totalAmount += articleAmount;

                            selectedArticle.packets =
                                selectedArticle.available_stock / selectedArticle.article.pcs_per_packet;
                        }
                    });

                    articleListDOM.innerHTML = clutter;
                } else {
                    articleListDOM.innerHTML = `<div class="text-center bg-[var(--h-bg-color)] rounded-lg py-2 px-4">No Orders Yet</div>`;
                }
            }
            renderList();

            let totalQuantityInFormDom = document.getElementById("totalQuantityInForm");
            let totalAmountInFormDom = document.getElementById("totalAmountInForm");
            let dicountInFormDom = document.getElementById("dicountInForm");
            let netAmountInFormDom = document.getElementById("netAmountInForm");

            function renderCalcBottom() {
                netAmount = invoiceDiscountDisabled() ? totalAmount : totalAmount - totalAmount * (discount / 100);
                totalQuantityInFormDom.textContent = formatNumbersDigitLess(totalQuantityPcs);
                totalAmountInFormDom.textContent = formatNumbersWithDigits(totalAmount, 1, 1);
                dicountInFormDom.textContent = invoiceDiscountDisabled() ? 0 : discount;
                netAmountInFormDom.value = formatNumbersWithDigits(netAmount, 1, 1);
            }

            function updateSelectedCount() {
                const selected = document.querySelectorAll(".row-checkbox:checked").length;
                document.getElementById("selected-count").value = selected;
            }

            function addListeners() {
                document.querySelectorAll(".row-checkbox").forEach((cb) => {
                    cb.addEventListener("change", updateSelectedCount);
                });

                document.querySelectorAll(".row-toggle").forEach((row) => {
                    row.addEventListener("click", function (e) {
                        if (e.target.tagName.toLowerCase() === "input") return;
                        const checkbox = this.querySelector(".row-checkbox");
                        checkbox.checked = !checkbox.checked;
                        checkbox.dispatchEvent(new Event("change"));
                    });
                });

                document.querySelectorAll(".row-checkbox").forEach((cb) => {
                    cb.addEventListener("change", function () {
                        const customerRowDOM = this.closest(".row-toggle");
                        selectCustomer(customerRowDOM);
                    });
                });
            }

            function selectCustomer(customerRowDOM) {
                const checkbox = customerRowDOM.querySelector(".row-checkbox");
                const customerData = JSON.parse(customerRowDOM.dataset.json);
                const customerId = customerData.id;

                let cartonCountInput = customerRowDOM.querySelector("input.cartonCount");
                let cartonCount = cartonCountInput.value;
                cartonCountInput.value = 1;

                const availableCartonCount = getAvailableCartonCount(cartonCountInput);

                if (checkbox.checked) {
                    if (availableCartonCount > 0) {
                        customerData["carton_count"] = cartonCount;
                        selectedCustomersArray.push(customerData);
                    }
                } else {
                    const index = selectedCustomersArray.findIndex(
                        (customer) => customer.id === customerId
                    );
                    if (index > -1) {
                        selectedCustomersArray.splice(index, 1);
                    }

                    cartonCountInput.dataset.previousValue = 1;
                }
                updateCustomerRowsState();
            }

            function setOnInput(input) {
                const cartonCount = parseInt(input.value);

                const customerRowDOM = input.closest(".row-toggle");
                const customerData = JSON.parse(customerRowDOM.dataset.json);
                const customerId = customerData.id;
                const index = selectedCustomersArray.findIndex((customer) => customer.id === customerId);

                if (index >= 0) {
                    selectedCustomersArray[index]["carton_count"] = cartonCount;
                }

                updateCustomerRowsState();
            }

            window.validateCartonCount = function validateCartonCount(currentInput) {
                currentInput.value = currentInput.value.replace(/[^\d]/g, "");

                const min = 1;
                const availableCartonCount = getAvailableCartonCount(currentInput);

                if (currentInput.value === "") {
                    currentInput.value = min;
                }

                const value = parseInt(currentInput.value, 10);

                if (value > availableCartonCount) {
                    currentInput.value = availableCartonCount;
                } else if (value < min) {
                    currentInput.value = min;
                }

                setOnInput(currentInput);
            };

            function getAvailableCartonCount(currentInput) {
                let sum = 0;
                document.querySelectorAll(".cartonCount").forEach((input) => {
                    if (input !== currentInput) {
                        const style = window.getComputedStyle(input);
                        if (style.opacity === "0" || style.pointerEvents === "none") return;

                        const val = parseInt(input.value, 10);
                        if (!isNaN(val)) sum += val;
                    }
                });

                let availableCartonCount = ogMaxCartonCount - sum;
                return availableCartonCount;
            }

            function updateCustomerRowsState() {
                const customerRows = document.querySelectorAll(".customer-row");

                const availableCartonCount = getAvailableCartonCount();
                customerRows.forEach((customerRow) => {
                    if (availableCartonCount > 0) {
                        customerRow.style.pointerEvents = "all";
                        customerRow.style.opacity = "1";
                        customerRow.style.cursor = "pointer";
                    } else {
                        const checkbox = customerRow.querySelector(".row-checkbox");
                        if (!checkbox.checked) {
                            customerRow.style.pointerEvents = "none";
                            customerRow.style.opacity = "0.5";
                            customerRow.style.cursor = "not-allowed";
                        }
                    }
                });
            }

            function renderCustomers(customers) {
                const container = document.getElementById("table-body");
                container.innerHTML = "";

                customers.forEach((customer) => {
                    const html = `
                        <div id="customer-${customer.id}" data-json='${jsonAttr(customer)}' class="customer-row contextMenuToggle modalToggle relative text-center group flex border-b border-[var(--h-bg-color)] items-center py-2 cursor-pointer hover:bg-[var(--h-secondary-bg-color)] transition-all fade-in ease-in-out">
                            <span class="text-left pl-5 flex items-center gap-4 checkbox-container w-[12%]">
                                <input type="checkbox" name="selected_customers[]"
                                    class="row-checkbox shrink-0 w-3.5 h-3.5 appearance-none border border-gray-400 rounded-sm checked:bg-[var(--primary-color)] checked:border-transparent focus:outline-none transition duration-150 cursor-pointer" />

                                <input class="cartonCount w-[70%] border border-gray-600 bg-[var(--h-bg-color)] py-0.5 px-2 rounded-md text-xs focus:outline-none opacity-0 pointer-events-none" type="number" name="carton_count" value="1" min="1" oninput="validateCartonCount(this)" onclick="this.select()" />
                            </span>
                            <span class="capitalize grow">${customer.customer_name} | ${customer.city.title}</span>
                            <span class="w-[15%]">${customer.urdu_title}</span>
                            <span class="w-[15%]">${customer.category}</span>
                            <span class="w-[15%]">${formatMoney(customer.balance)}</span>
                            <span class="w-[15%] capitalize">${customer.user?.status ?? ""}</span>
                        </div>
                    `;

                    container.insertAdjacentHTML("beforeend", html);
                });
            }

            let invoiceNo;
            let invoiceDate;
            let cartonCount = 0;
            const previewDom = document.getElementById("preview-container");

            function generateInvoiceNo() {
                return nextInvoicePreviewNo();
            }

            function getInvoiceDate() {
                const date = new Date();

                const day = String(date.getDate()).padStart(2, "0");
                const month = String(date.getMonth() + 1).padStart(2, "0");
                const year = date.getFullYear();
                const dayOfWeek = date.getDay();

                const weekDays = [
                    "Sunday",
                    "Monday",
                    "Tuesday",
                    "Wednesday",
                    "Thursday",
                    "Friday",
                    "Saturday",
                ];

                return `${day}-${month}-${year}, ${weekDays[dayOfWeek]}`;
            }

            function generateInvoice() {
                invoicePreviewOffset = 0;
                const customerData = selectedCustomersArray[0];
                invoiceNo = generateInvoiceNo();
                invoiceDate = new Date();
                cartonCount = customerData?.carton_count || 1;

                if (shipmentArticles.length > 0) {
                    const normalizedCustomer = {
                        ...customerData,
                        city: typeof customerData?.city === 'string'
                            ? { title: customerData.city }
                            : (customerData?.city || { title: '' }),
                    };

                    const previewData = {
                        customer: normalizedCustomer,
                        date: invoiceDate,
                        invoice_no: invoiceNo,
                        shipment_no: selectedShipmentNo(),
                        carton_count: cartonCount,
                        discount: discount || 0,
                        branch_branding: companyData,
                        invoice_articles: sortArticleRows(shipmentArticles).map((article) => ({
                            article: article.article,
                            description: article.description,
                            fabric_type: article.article?.fabric_type ?? article.fabric_type ?? '',
                            shipment_pcs: article.shipment_pcs,
                            invoice_pcs: article.shipment_pcs * cartonCount,
                        })),
                    };

                    previewDom.className = "h-auto mx-auto relative flex flex-col";
                    previewDom.innerHTML = [
                        buildInvoicePreviewLikeModal(previewData, 'Customer'),
                        buildInvoicePreviewLikeModal(previewData, 'Office'),
                    ].join('');
                } else {
                    previewDom.className = "w-[148mm] h-[210mm] mx-auto overflow-hidden relative";
                    previewDom.innerHTML = `
                        <div id="preview" class="preview w-[148mm] h-[210mm] gos-a5-document gos-a5-invoice overflow-hidden flex flex-col">
                            <h1 class="text-[var(--border-error)] font-medium text-center mt-5">No Preview avalaible.</h1>
                        </div>
                    `;
                }
            }

            function buildInvoicePreviewLikeModal(previewData, copyLabel = 'Customer') {
                const carton = previewData.carton_count || 0;
                const discountVal = Number(previewData.discount || 0);
                const articles = Array.isArray(previewData.invoice_articles)
                    ? previewData.invoice_articles
                    : [];

                return buildA5InvoicePreviewPages(previewData, copyLabel, articles, {
                    showCarton: true,
                    showShipmentNo: true,
                });

                let totalAmount = 0;
                let totalPcs = 0;
                let totalPackets = 0;

                const invoiceTableHeader = `
                    <div class="th text-sm font-medium">S.#</div>
                    <div class="th text-sm font-medium">Article</div>
                    <div class="th text-sm font-medium">Description</div>
                    <div class="th text-sm font-medium">Unit</div>
                    <div class="th text-sm font-medium">Pkts</div>
                    <div class="th text-sm font-medium">Pcs.</div>
                    <div class="th text-sm font-medium">Rate</div>
                    <div class="th text-sm font-medium">Amount</div>
                `;

                const invoiceTableBody = `
                    ${articles.map((orderedArticle, index) => {
                        const article = orderedArticle.article || {};
                        const salesRate = parseFormattedNumber(article.sales_rate);
                        const qty = orderedArticle.invoice_pcs ?? orderedArticle.shipment_pcs ?? 0;
                        const total = salesRate * qty;
                        const hrClass = index === 0 ? "mb-2.5" : "my-2.5";

                        totalAmount += total;
                        totalPcs += qty;
                        totalPackets += article?.pcs_per_packet ? Math.floor(qty / article.pcs_per_packet) : 0;
                        const detailLine = invoiceDetailLine(orderedArticle, article);

                        return `
                            <div class="invoice-item-row">
                                <hr class="w-full ${hrClass} border-black">
                                <div class="tr invoice-item-main grid grid-cols-8 justify-between w-full px-4 gap-0.5">
                                    <div class="td text-sm font-semibold truncate">${String(index + 1).padStart(2, '0')}</div>
                                    <div class="td invoice-article-cell text-sm font-semibold">
                                        <div class="invoice-article-code">${article.article_no ?? ''}</div>
                                    </div>
                                    <div class="td invoice-description-cell text-sm font-semibold">${detailLine}</div>
                                    <div class="td text-sm font-semibold truncate">${article?.pcs_per_packet ?? 0}</div>
                                    <div class="td text-sm font-semibold truncate">${article?.pcs_per_packet ? Math.floor(qty / article.pcs_per_packet) : 0}</div>
                                    <div class="td text-sm font-semibold truncate">${qty}</div>
                                    <div class="td text-sm font-semibold truncate">${formatNumbersDigitLess(salesRate)}</div>
                                    <div class="td text-sm font-semibold truncate">${formatNumbersDigitLess(total)}</div>
                                </div>
                            </div>
                        `;
                    }).join('')}
                `;

                const discountAmount = discountVal ? (totalAmount * discountVal) / 100 : 0;
                const netAmount = previewData.netAmount ?? (totalAmount - discountAmount);

                const invoiceBottom = invoiceDocumentTotalsHtml(previewData, totalPackets, totalPcs, totalAmount, discountVal, discountAmount, netAmount);

                return `
                    <div id="preview-container" class="h-auto mx-auto relative flex flex-col">
                        <div id="preview" class="preview w-[148mm] h-[210mm] gos-a5-document gos-a5-invoice overflow-hidden flex flex-col">
                            <div class="flex flex-col h-full">
                                <div id="banner" class="banner w-full flex justify-between items-center px-5">
                                    <div class="left">
                                        <div class="logo flex flex-col">
                                            <div class="flex items-center gap-3">
                                                ${(companyData.logo_url || companyData.logo) ? `
                                                    <div class="h-[3.50rem] w-[13.5rem] flex items-center justify-center gap-2.5">
                                                        <img
                                                            src="${companyData.logo_url || `${companyLogoBase}/${companyData.logo}`}"
                                                            alt="garmentsos-pro"
                                                            class="max-h-full max-w-full object-contain"
                                                        />
                                                        ${companyData.logo_text ? `
                                                            <h1 class="text-lg font-bold tracking-wide">${companyData.logo_text}</h1>
                                                        ` : ''}
                                                    </div>
                                                ` : ''}
                                            </div>
                                            ${(companyData.phone_number || companyData.phone) ? `
                                                <div class="mt-2 text-sm text-gray-600">${companyData.phone_number || companyData.phone}</div>
                                            ` : ''}
                                        </div>
                                    </div>
                                    <div class="right">
                                        <div class="logo text-right">
                                            <h1 class="text-2xl font-medium text-[var(--h-primary-color)]">Sales Invoice</h1>
                                            <div class="document-number mt-1 text-right">Invoice No.: ${previewData.invoice_no}</div>
                                        </div>
                                    </div>
                                </div>
                                <hr class="w-full my-3 border-black">
                                <div id="header" class="header w-full flex justify-between px-5">
                                    <div class="left grow min-w-0 pr-3 space-y-1">
                                        <div class="customer text-lg leading-none capitalize font-medium text-nowrap">M/s: ${previewData.customer.customer_name}</div>
                                        <div class="person text-md text-lg leading-none">${customerTitlePhoneLine(previewData.customer)}</div>
                                        <div class="address text-md leading-none">${previewData.customer.address ?? ''}, ${previewData.customer.city?.title ?? ''}</div>
                                        <div class="phone text-md leading-none">${deliverToLine(previewData)}</div>
                                    </div>
                                    <div class="right shrink-0 min-w-[38%] my-auto text-right text-sm text-black space-y-1.5">
                                        <div class="date leading-none">Date: ${formatDate(previewData.date)}</div>
                                        ${previewData.order_no ? `<div class="number leading-none capitalize">Order No.: ${previewData.order_no}</div>` : previewData.shipment_no ? `<div class="number leading-none capitalize">Shipment No.: ${previewData.shipment_no}</div>` : ''}
                                        <input type="hidden" name="invoice_no" value="${previewData.invoice_no}" />
                                        <div class="preview-copy leading-none capitalize">invoice Copy: ${copyLabel}</div>
                                        <div class="number leading-none capitalize">Carton: ${carton || '-'}</div>
                                    </div>
                                </div>
                                <hr class="w-full my-3 border-black">
                                <div class="body w-full px-5 grow mx-auto">
                                    <div class="table w-full">
                                        <div class="table w-full border border-black rounded-lg pb-2.5 overflow-hidden">
                                            <div class="thead w-full">
                                                <div class="tr grid grid-cols-8 w-full px-4 py-1.5 bg-[var(--primary-color)] text-white">
                                                    ${invoiceTableHeader}
                                                </div>
                                            </div>
                                            <div id="tbody" class="tbody w-full">
                                                ${invoiceTableBody}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="grid grid-cols-2 gap-2 px-5">
                                    ${invoiceBottom}
                                </div>
                                <hr class="w-full my-3 border-black">
                                <div class="tfooter flex w-full text-sm px-4 justify-between text-black">
                                    <p class="leading-none">Powered by SparkPair</p>
                                    <p class="leading-none text-sm">Page 1 of 1</p>
                                    <p class="leading-none text-sm">Printed: ${printDateTime()}</p>
                                    <p class="leading-none text-sm">&copy; ${new Date().getFullYear()} SparkPair | +92 316 5825495</p>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            }

            window.validateForNextStep = function validateForNextStep() {
                generateInvoice();
                document.getElementById("customers_array").value = JSON.stringify(selectedCustomersArray);
                return true;
            };

            function addListenerToPrintAndSaveBtn() {
                const printAndSaveBtn = document.getElementById("printAndSaveBtn");
                if (!printAndSaveBtn) return;
                printAndSaveBtn.addEventListener("click", function () {
                    const form = document.getElementById("form");
                    if (!form) return;
                    document.getElementById("printAfterSave").value = 1;

                    if (typeof validateForNextStep === "function") {
                        const result = validateForNextStep();
                        if (result instanceof Promise) {
                            result.then(res => {
                                if (res === false) return;
                                form.submit();
                            });
                            return;
                        }
                        if (result === false) return;
                    }

                    form.submit();
                });
            }

            addListenerToPrintAndSaveBtn();
        } else {
            let orderedArticles = [];
            let customerData;
            let orderDeliverTo = '';
            const articleModalDom = document.getElementById("articleModal");
            const quantityModalDom = document.getElementById("quantityModal");
            const orderNoDom = document.getElementById("order_no");
            const orderNoValueDom = document.querySelector('.dbInput[data-for="order_no"]');
            const generateInvoiceBtn = document.getElementById("generateInvoiceBtn");
            if (!orderNoDom || !generateInvoiceBtn) return;
            generateInvoiceBtn.disabled = true;

            let totalQuantityInFormDom = document.getElementById("totalQuantityInForm");
            let totalAmountInFormDom = document.getElementById("totalAmountInForm");
            let dicountInFormDom = document.getElementById("dicountInForm");
            let netAmountInFormDom = document.getElementById("netAmountInForm");

            let totalQuantityDOM;
            let totalAmountDOM;

            function selectedOrderNo() {
                return (orderNoValueDom?.value || orderNoDom.value || "").trim();
            }

            orderNoDom.addEventListener("input", () => {
                trackStateOfOrderNo(selectedOrderNo());
            });
            orderNoValueDom?.addEventListener("change", () => trackStateOfOrderNo(selectedOrderNo()));

            orderNoDom.addEventListener("keydown", (e) => {
                if (e.key === "Enter") {
                    generateInvoiceBtn.click();
                }
            });

            generateInvoiceBtn.addEventListener("click", function () {
                getOrderDetails();
            });

            if (orderNumber) {
                orderNoDom.value = orderNumber;
                if (orderNoValueDom) orderNoValueDom.value = orderNumber;
                trackStateOfOrderNo(selectedOrderNo());
                getOrderDetails();
            }

            function getOrderDetails() {
                $.ajax({
                    url: "/get-order-details",
                    type: "POST",
                    data: {
                        _token: csrfToken,
                        order_no: selectedOrderNo(),
                    },
                    success: function (response) {
                        if (!response.error) {
                            orderedArticles = response.articles;
                            discount = response.discount ?? 0;
                            customerData = response.customer;
                            orderDeliverTo = response.deliver_to || '';
                        } else {
                            orderedArticles = [];
                            discount = 0;
                            customerData = "";
                            orderDeliverTo = '';
                            renderError(response.error);
                        }
                        renderList();
                        renderCalcBottom();
                    },
                });
            }

            function trackStateOfOrderNo(value) {
                if (value !== "") {
                    generateInvoiceBtn.disabled = false;
                } else {
                    generateInvoiceBtn.disabled = true;
                }
            }

            const articleListDOM = document.getElementById("article-list");

            function renderList() {
                if (orderedArticles && orderedArticles.length > 0) {
                    totalAmount = 0;
                    totalQuantityPcs = 0;

                    let clutter = "";
                    sortArticleRows(orderedArticles).forEach((selectedArticle, index) => {
                        if (selectedArticle.total_quantity_in_packets > 0) {
                            let totalQuantityInPackets = selectedArticle.total_quantity_in_packets;

                            totalQuantityPcs +=
                                totalQuantityInPackets * selectedArticle.article.pcs_per_packet;

                            let articleAmount =
                                selectedArticle.article.sales_rate *
                                selectedArticle.article.pcs_per_packet *
                                totalQuantityInPackets;

                            clutter += `
                                <div class="flex justify-between items-center border-t border-gray-600 py-3 px-4">
                                    <div class="w-[5%]">${index + 1}.</div>
                                    <div class="w-[11%]">${selectedArticle.article.article_no}</div>
                                    <div class="w-[11%] pr-3">
                                        <input type="number" class="w-full border border-gray-600 bg-[var(--h-bg-color)] py-1 px-2 rounded-md focus:outline-none" value="${totalQuantityInPackets}" max="${totalQuantityInPackets}" onclick='this.select()' oninput="packetEdited(this)" />
                                    </div>
                                    <div class="w-[10%]">${formatNumbersDigitLess(
                                        totalQuantityInPackets * selectedArticle.article.pcs_per_packet
                                    )}</div>
                                    <div class="grow">${selectedArticle.description}</div>
                                    <div class="w-[8%]">${selectedArticle.article.pcs_per_packet}</div>
                                    <div class="w-[12%] text-right">${formatNumbersWithDigits(
                                        selectedArticle.article.sales_rate,
                                        1,
                                        1
                                    )}</div>
                                    <div class="w-[15%] text-right">${formatNumbersWithDigits(
                                        articleAmount,
                                        1,
                                        1
                                    )}</div>
                                    <div class="w-[15%] text-right">
                                        <button onclick="removeArticle(${index})" type="button" class="text-[var(--danger-color)] text-xs px-2 py-1 rounded-lg hover:text-[var(--h-danger-color)] transition-all duration-300 ease-in-out ${
                                            orderedArticles.length > 1
                                                ? "cursor-pointer"
                                                : "cursor-not-allowed opacity-40"
                                        }">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            `;

                            totalAmount += articleAmount;

                            selectedArticle.packets = totalQuantityInPackets;
                            selectedArticle.ordered_quantity =
                                totalQuantityInPackets * selectedArticle.article.pcs_per_packet;
                        }
                    });

                    articleListDOM.innerHTML = clutter;
                } else {
                    articleListDOM.innerHTML = `<div class="text-center bg-[var(--h-bg-color)] rounded-lg py-2 px-4">No Orders Yet</div>`;
                }
            }
            renderList();

            window.removeArticle = function removeArticle(index) {
                if (orderedArticles.length > index && orderedArticles.length > 1) {
                    orderedArticles.splice(index, 1);
                    renderList();
                    renderCalcBottom();
                }
            };

            function updateInputArticlesInInvoice() {
                const articlesInInvoiceInpDom = document.getElementById("articles_in_invoice");
                let finalArticlesArray = sortArticleRows(orderedArticles).map((article) => {
                    return {
                        id: article.article_id,
                        order_article_id: article.id,
                        description: article.description,
                        invoice_quantity: article.ordered_quantity,
                    };
                });
                articlesInInvoiceInpDom.value = JSON.stringify(finalArticlesArray);
            }

            function renderCalcBottom() {
                netAmount = invoiceDiscountDisabled() ? totalAmount : totalAmount - totalAmount * (discount / 100);
                totalQuantityInFormDom.textContent = formatNumbersDigitLess(totalQuantityPcs);
                totalAmountInFormDom.textContent = formatNumbersWithDigits(totalAmount, 1, 1);
                dicountInFormDom.textContent = invoiceDiscountDisabled() ? 0 : discount;
                netAmountInFormDom.value = formatNumbersWithDigits(netAmount, 1, 1);
            }

            window.packetEdited = function packetEdited(elem) {
                let max = parseInt(elem.max);

                if (elem.value > max) {
                    elem.value = max;
                } else if (elem.value < 1) {
                    elem.value = 1;
                }

                elem.value = elem.value.replace(/\./g, "");

                calculateAndApplyChangesOnOrderArticle(elem);
            };

            function calculateAndApplyChangesOnOrderArticle(elem) {
                let childrenDom = elem.parentElement.parentElement.children;

                let packetsValue = parseInt(elem.value);

                let articleNoInRowDom = childrenDom[1];
                let pcsInRowDom = childrenDom[3];
                totalQuantityPcs -= parseInt(pcsInRowDom.textContent.replace(/[,]/g, ""));
                let pcsPerPktInRowDom = childrenDom[5];
                let ratePerPcInRowDom = childrenDom[6];

                let amountInRowDom = childrenDom[childrenDom.length - 2];
                totalAmount -= parseInt(amountInRowDom.textContent.replace(/[,]/g, ""));

                let pcsCalculated = packetsValue * parseInt(pcsPerPktInRowDom.textContent);
                totalQuantityPcs += pcsCalculated;

                pcsInRowDom.textContent = formatNumbersDigitLess(pcsCalculated) || 0;

                let amountCalculated =
                    parseInt(pcsInRowDom.textContent.replace(/[,]/g, "")) *
                    parseInt(ratePerPcInRowDom.textContent.replace(/[,]/g, ""));
                totalAmount += amountCalculated;

                amountInRowDom.textContent = formatNumbersWithDigits(amountCalculated, 1, 1) || 0.0;

                let currentArticle = orderedArticles.find(
                    (article) => article.article.article_no == articleNoInRowDom.textContent
                );

                if (currentArticle) {
                    currentArticle.packets = packetsValue;
                    currentArticle.ordered_quantity = pcsCalculated;
                }

                renderCalcBottom();
            }

            let invoiceNo;
            let invoiceDate;
            const previewDom = document.getElementById("preview-container");

            function generateInvoiceNo() {
                return nextInvoicePreviewNo();
            }

            function getInvoiceDate() {
                const date = new Date();

                const day = String(date.getDate()).padStart(2, "0");
                const month = String(date.getMonth() + 1).padStart(2, "0");
                const year = date.getFullYear();
                const dayOfWeek = date.getDay();

                const weekDays = [
                    "Sunday",
                    "Monday",
                    "Tuesday",
                    "Wednesday",
                    "Thursday",
                    "Friday",
                    "Saturday",
                ];

                return `${day}-${month}-${year}, ${weekDays[dayOfWeek]}`;
            }

            function generateInvoice() {
                invoicePreviewOffset = 0;
                invoiceNo = generateInvoiceNo();
                invoiceDate = new Date();

                if (orderedArticles.length > 0) {
                    const normalizedCustomer = {
                        ...customerData,
                        city: typeof customerData?.city === 'string'
                            ? { title: customerData.city }
                            : (customerData?.city || { title: '' }),
                    };

                    const previewData = {
                        customer: normalizedCustomer,
                        date: invoiceDate,
                        invoice_no: invoiceNo,
                        order_no: selectedOrderNo(),
                        deliver_to: orderDeliverTo,
                        carton_count: 0,
                        discount: discount || 0,
                        netAmount: netAmount || null,
                        branch_branding: companyData,
                        invoice_articles: sortArticleRows(orderedArticles).map((article) => ({
                            article: article.article,
                            description: article.description,
                            fabric_type: article.article?.fabric_type ?? article.fabric_type ?? '',
                            ordered_pcs: article.ordered_quantity,
                            invoice_pcs: article.ordered_quantity,
                        })),
                    };

                    previewDom.className = "h-auto mx-auto relative flex flex-col";
                    previewDom.innerHTML = [
                        buildOrderInvoicePreviewLikeModal(previewData, 'Customer'),
                        buildOrderInvoicePreviewLikeModal(previewData, 'Office'),
                    ].join('');
                } else {
                    previewDom.className = "w-[148mm] h-[210mm] mx-auto overflow-hidden relative";
                    previewDom.innerHTML = `
                        <div id="preview" class="preview w-[148mm] h-[210mm] gos-a5-document gos-a5-invoice overflow-hidden flex flex-col">
                            <h1 class="text-[var(--border-error)] font-medium text-center mt-5">No Preview avalaible.</h1>
                        </div>
                    `;
                }
            }

            function buildOrderInvoicePreviewLikeModal(previewData, copyLabel = 'Customer') {
                const discountVal = Number(previewData.discount || 0);
                const articles = Array.isArray(previewData.invoice_articles)
                    ? previewData.invoice_articles
                    : [];

                return buildA5InvoicePreviewPages(previewData, copyLabel, articles, {
                    showOrderNo: true,
                }).join('');

                let totalAmountCalc = 0;
                let totalPcsCalc = 0;
                let totalPacketsCalc = 0;

                const invoiceTableHeader = `
                    <div class="th text-sm font-medium">S.#</div>
                    <div class="th text-sm font-medium">Article</div>
                    <div class="th text-sm font-medium">Description</div>
                    <div class="th text-sm font-medium">Unit</div>
                    <div class="th text-sm font-medium">Pkts</div>
                    <div class="th text-sm font-medium">Pcs.</div>
                    <div class="th text-sm font-medium">Rate</div>
                    <div class="th text-sm font-medium">Amount</div>
                `;

                const invoiceTableBody = `
                    ${articles.map((orderedArticle, index) => {
                        const article = orderedArticle.article || {};
                        const salesRate = parseFormattedNumber(article.sales_rate);
                        const qty = orderedArticle.invoice_pcs ?? orderedArticle.ordered_pcs ?? 0;
                        const total = salesRate * qty;
                        const hrClass = index === 0 ? "mb-2.5" : "my-2.5";

                        totalAmountCalc += total;
                        totalPcsCalc += qty;
                        totalPacketsCalc += article?.pcs_per_packet ? Math.floor(qty / article.pcs_per_packet) : 0;
                        const detailLine = invoiceDetailLine(orderedArticle, article);

                        return `
                            <div class="invoice-item-row">
                                <hr class="w-full ${hrClass} border-black">
                                <div class="tr invoice-item-main grid grid-cols-8 justify-between w-full px-4 gap-0.5">
                                    <div class="td text-sm font-semibold truncate">${String(index + 1).padStart(2, '0')}</div>
                                    <div class="td invoice-article-cell text-sm font-semibold">
                                        <div class="invoice-article-code">${article.article_no ?? ''}</div>
                                    </div>
                                    <div class="td invoice-description-cell text-sm font-semibold">${detailLine}</div>
                                    <div class="td text-sm font-semibold truncate">${article?.pcs_per_packet ?? 0}</div>
                                    <div class="td text-sm font-semibold truncate">${article?.pcs_per_packet ? Math.floor(qty / article.pcs_per_packet) : 0}</div>
                                    <div class="td text-sm font-semibold truncate">${qty}</div>
                                    <div class="td text-sm font-semibold truncate">${formatNumbersDigitLess(salesRate)}</div>
                                    <div class="td text-sm font-semibold truncate">${formatNumbersDigitLess(total)}</div>
                                </div>
                            </div>
                        `;
                    }).join('')}
                `;

                const discountAmount = discountVal ? (totalAmountCalc * discountVal) / 100 : 0;
                const netAmountCalc = previewData.netAmount ?? (totalAmountCalc - discountAmount);

                const invoiceBottom = invoiceDocumentTotalsHtml(previewData, totalPacketsCalc, totalPcsCalc, totalAmountCalc, discountVal, discountAmount, netAmountCalc);

                return `
                    <div id="preview-container" class="h-auto mx-auto relative flex flex-col">
                        <div id="preview" class="preview w-[148mm] h-[210mm] gos-a5-document gos-a5-invoice overflow-hidden flex flex-col">
                            <div class="flex flex-col h-full">
                                <div id="banner" class="banner w-full flex justify-between items-center px-5">
                                    <div class="left">
                                        <div class="logo flex flex-col">
                                            <div class="flex items-center gap-3">
                                                ${(companyData.logo_url || companyData.logo) ? `
                                                    <div class="h-[3.50rem] w-[13.5rem] flex items-center justify-center gap-2.5">
                                                        <img
                                                            src="${companyData.logo_url || `${companyLogoBase}/${companyData.logo}`}"
                                                            alt="garmentsos-pro"
                                                            class="max-h-full max-w-full object-contain"
                                                        />
                                                        ${companyData.logo_text ? `
                                                            <h1 class="text-lg font-bold tracking-wide">${companyData.logo_text}</h1>
                                                        ` : ''}
                                                    </div>
                                                ` : ''}
                                            </div>
                                            ${(companyData.phone_number || companyData.phone) ? `
                                                <div class="mt-2 text-sm text-gray-600">${companyData.phone_number || companyData.phone}</div>
                                            ` : ''}
                                        </div>
                                    </div>
                                    <div class="right">
                                        <div class="logo text-right">
                                            <h1 class="text-2xl font-medium text-[var(--h-primary-color)]">Sales Invoice</h1>
                                            <div class="document-number mt-1 text-right">Invoice No.: ${previewData.invoice_no}</div>
                                        </div>
                                    </div>
                                </div>
                                <hr class="w-full my-3 border-black">
                                <div id="header" class="header w-full flex justify-between px-5">
                                    <div class="left grow min-w-0 pr-3 space-y-1">
                                        <div class="customer text-lg leading-none capitalize font-medium text-nowrap">M/s: ${previewData.customer.customer_name}</div>
                                        <div class="person text-md text-lg leading-none">${customerTitlePhoneLine(previewData.customer)}</div>
                                        <div class="address text-md leading-none">${previewData.customer.address ?? ''}, ${previewData.customer.city?.title ?? ''}</div>
                                        <div class="phone text-md leading-none">${deliverToLine(previewData)}</div>
                                    </div>
                                    <div class="right shrink-0 min-w-[38%] my-auto text-right text-sm text-black space-y-1.5">
                                        <div class="date leading-none">Date: ${formatDate(previewData.date)}</div>
                                        ${previewData.order_no ? `<div class="number leading-none capitalize">Order No.: ${previewData.order_no}</div>` : previewData.shipment_no ? `<div class="number leading-none capitalize">Shipment No.: ${previewData.shipment_no}</div>` : ''}
                                        <input type="hidden" name="invoice_no" value="${previewData.invoice_no}" />
                                        <div class="preview-copy leading-none capitalize">invoice Copy: ${copyLabel}</div>
                                        <div class="number leading-none capitalize">Carton: ${previewData.carton_count || '-'}</div>
                                    </div>
                                </div>
                                <hr class="w-full my-3 border-black">
                                <div class="body w-full px-5 grow mx-auto">
                                    <div class="table w-full">
                                        <div class="table w-full border border-black rounded-lg pb-2.5 overflow-hidden">
                                            <div class="thead w-full">
                                                <div class="tr grid grid-cols-8 w-full px-4 py-1.5 bg-[var(--primary-color)] text-white">
                                                    ${invoiceTableHeader}
                                                </div>
                                            </div>
                                            <div id="tbody" class="tbody w-full">
                                                ${invoiceTableBody}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="grid grid-cols-2 gap-2 px-5">
                                    ${invoiceBottom}
                                </div>
                                <hr class="w-full my-3 border-black">
                                <div class="tfooter flex w-full text-sm px-4 justify-between text-black">
                                    <p class="leading-none">Powered by SparkPair</p>
                                    <p class="leading-none text-sm">Page 1 of 1</p>
                                    <p class="leading-none text-sm">Printed: ${printDateTime()}</p>
                                    <p class="leading-none text-sm">&copy; ${new Date().getFullYear()} SparkPair | +92 316 5825495</p>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            }

            window.validateForNextStep = function validateForNextStep() {
                generateInvoice();
                updateInputArticlesInInvoice();
                return true;
            };

            function addListenerToPrintAndSaveBtn() {
                const printBtn = document.getElementById("printAndSaveBtn");
                if (!printBtn) return;

                printBtn.addEventListener("click", (e) => {
                    e.preventDefault();
                    closeAllDropdowns();
                    generateInvoice();
                    const preview = document.getElementById("preview-container") || document.getElementById("preview");

                    let oldIframe = document.getElementById("printIframe");
                    if (oldIframe) {
                        oldIframe.remove();
                    }

                    let printIframe = document.createElement("iframe");
                    printIframe.id = "printIframe";
                    printIframe.style.position = "absolute";
                    printIframe.style.width = "0px";
                    printIframe.style.height = "0px";
                    printIframe.style.border = "none";
                    printIframe.style.display = "none";

                    document.body.appendChild(printIframe);

                    let printDocument = printIframe.contentDocument || printIframe.contentWindow.document;
                    printDocument.open();

                    const headContent = document.head.innerHTML;

                    printDocument.write(`
                        <html>
                            <head>
                                <title>Print Invoice</title>
                                ${headContent}
                                <style>
                                    @page {
                                        size: A5 portrait;
                                        margin: 3mm;
                                    }

                                    @media print {
                                        html,
                                        body {
                                            margin: 0;
                                            padding: 0;
                                            width: auto;
                                            min-height: 0;
                                        }

                                        #preview-container {
                                            width: auto !important;
                                            height: auto !important;
                                            max-height: none !important;
                                            overflow: visible !important;
                                        }

                                        .preview {
                                            width: 148mm !important;
                                            height: 210mm !important;
                                            max-width: 148mm !important;
                                            max-height: 210mm !important;
                                            overflow: hidden !important;
                                            break-after: page;
                                            page-break-after: always;
                                        }

                                        #preview-container .preview:last-child {
                                            break-after: auto;
                                            page-break-after: auto;
                                        }

                                        .preview {
                                            page-break-inside: avoid;
                                        }
                                    }
                                </style>
                            </head>
                            <body>
                                <div id="preview-container" class="preview-container">${preview ? preview.innerHTML : ''}</div>
                            </body>
                        </html>
                    `);

                    printDocument.close();

                    printIframe.onload = () => {
                        printDocument.querySelectorAll(".preview").forEach((p) => p.classList.remove("py-6"));

                        printDocument.querySelectorAll("#banner").forEach((p) => p.classList.remove("mt-8"));

                        printDocument
                            .querySelectorAll(".footer")
                            .forEach((p) => p.classList.remove("mb-4"));
                        compactInvoicePrintHeaders(printDocument);

                        let orderCopy = printDocument.querySelector(
                            "#preview-container .invoice-copy"
                        );
                        if (orderCopy) {
                            orderCopy.textContent = "Invoice Copy: Office";
                        }

                        printIframe.contentWindow.onafterprint = () => {
                            document.getElementById("form").submit();
                        };

                        setTimeout(() => {
                            printIframe.contentWindow.focus();
                            printIframe.contentWindow.print();
                        }, 1000);
                    };
                });
            }

            addListenerToPrintAndSaveBtn();
        }
    }

    window.initInvoicesGenerate = initInvoicesGenerate;

    function boot() {
        if (window.__invoicesGenerate) {
            initInvoicesGenerate();
        }
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", boot);
    } else {
        boot();
    }
})();
