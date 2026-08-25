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
        const discountDisabled = Boolean(config.discountDisabled);
        let invoicePreviewOffset = 0;

        function invoiceDiscountDisabled() {
            return discountDisabled;
        }

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

        if (invoiceType === "manual") {
            const availableArticles = Array.isArray(config.manualArticles) ? config.manualArticles : [];
            const availableCustomers = Array.isArray(config.manualCustomers) ? config.manualCustomers : Object.values(config.manualCustomers || {});
            const selectedLines = [];
            const physicalQuantityEnabled = Boolean(config.physicalQuantityEnabled);
            const linesInput = document.getElementById('articles_in_invoice');
            const linesContainer = document.getElementById('manual_article_list');
            const customerValue = document.querySelector('.dbInput[data-for="customer_id"]');

            const descriptionFor = article => [article.size, article.category, article.season, article.fabric_type].filter(Boolean).map(value => String(value).replaceAll('_', ' ')).join(' | ');

            function renderManualLines() {
                const totalPcs = selectedLines.reduce((sum, line) => sum + line.invoice_pcs, 0);
                const totalAmount = selectedLines.reduce((sum, line) => sum + (line.invoice_pcs * line.rate), 0);
                document.getElementById('totalQuantityInForm').textContent = totalPcs;
                document.getElementById('manualTotalAmount').textContent = formatNumbersWithDigits(totalAmount, 1, 1);
                document.getElementById('netAmountInForm').value = formatNumbersWithDigits(totalAmount, 1, 1);
                const modalQuantity = document.querySelector('#modalForm #totalShipmentedQty');
                const modalAmount = document.querySelector('#modalForm #totalShipmentAmount');
                if (modalQuantity) modalQuantity.value = totalPcs;
                if (modalAmount) modalAmount.value = formatNumbersWithDigits(totalAmount, 1, 1);
                const modalInfo = document.querySelector('#modalForm .modalFormInfo span');
                if (modalInfo) modalInfo.textContent = `Selected ${selectedLines.length}/500`;
                linesInput.value = JSON.stringify(selectedLines.map(line => ({ article_id: line.article_id, description: line.description, invoice_pcs: line.invoice_pcs })));
                linesContainer.innerHTML = selectedLines.length ? selectedLines.map((line, index) => `
                    <div class="flex justify-between items-center border-t border-gray-600 py-3 px-4">
                        <div class="w-[10%]">${line.article.article_no}</div>
                        <div class="w-1/6">${line.invoice_pcs} pcs</div>
                        <div class="grow capitalize">${line.description}</div>
                        <div class="w-1/6">${formatNumbersWithDigits(line.rate, 1, 1)}</div>
                        <div class="w-1/5">${formatNumbersWithDigits(line.invoice_pcs * line.rate, 1, 1)}</div>
                        <div class="w-[10%] text-center"><button type="button" data-remove-manual-line="${index}" class="text-[var(--danger-color)] text-xs px-2 py-1 rounded-lg hover:text-[var(--h-danger-color)] transition-all duration-300 ease-in-out cursor-pointer"><i class="fas fa-trash"></i></button></div>
                    </div>`).join('') : '<div class="text-center bg-[var(--h-bg-color)] rounded-lg py-2 px-4">No Articles Yet</div>';
            }

            window.manualInvoiceArticleSearch = value => {
                document.querySelectorAll('#modalForm .card').forEach(card => {
                    const row = JSON.parse(card.dataset.json || '{}');
                    card.classList.toggle('hidden', !String(row.name || '').toLowerCase().includes(String(value || '').toLowerCase()));
                });
            };
            window.generateManualInvoiceArticlesModal = function () {
                const cards = availableArticles.map(article => ({ id: article.id, name: article.article_no, image: article.image === 'no_image_icon.png' ? '/images/no_image_icon.png' : `/storage/uploads/images/${article.image}`, details: { Category: article.category, Season: article.season, Size: article.size }, data: article, onclick: 'generateManualInvoiceQuantityModal(this)' }));
                createModal({ id: 'modalForm', class: 'h-[80%] w-full', cards: { name: 'Articles', count: 3, data: cards }, basicSearch: true, onBasicSearch: 'manualInvoiceArticleSearch(this.value)', info: `Selected: ${selectedLines.length}/500`, flex_col: true, calcBottom: [{ label: 'Total Quantity - Pcs', name: 'totalShipmentedQty', value: selectedLines.reduce((sum, line) => sum + line.invoice_pcs, 0), disabled: true }, { label: 'Total Amount - Rs.', name: 'totalShipmentAmount', value: formatNumbersWithDigits(selectedLines.reduce((sum, line) => sum + line.invoice_pcs * line.rate, 0), 1, 1), disabled: true }] });
                selectedLines.forEach(line => document.getElementById(line.article_id)?.insertAdjacentHTML('beforeend', `<div class="quantity-label absolute text-xs text-[var(--border-success)] top-2 right-2 rounded-md bg-[var(--secondary-bg-color)]/90 px-1.5 py-0.5">${line.invoice_pcs} Pcs</div>`));
            };
            window.generateManualInvoiceQuantityModal = function (elem) {
                const article = JSON.parse(elem.dataset.json).data;
                const limit = Number(article.orderable_quantity || 0);
                const existing = selectedLines.find(line => line.article_id === Number(article.id));
                const fields = [{ category: 'input', value: `${article.article_no} | ${article.season || '-'} | ${article.size || '-'} | ${article.category || '-'} | ${article.fabric_type || '-'} | ${formatMoney(article.sales_rate)} - Rs.`, disabled: true, full: true }];
                if (physicalQuantityEnabled) fields.push({ category: 'input', label: 'Invoiceable Quantity (Current Stock)', value: formatPcsAndPackets(article.current_stock, article.pcs_per_packet), disabled: true });
                fields.push({ category: 'input', label: 'Unit', value: `${formatNumbersDigitLess(article.pcs_per_packet || 0)} Pcs per Packet`, disabled: true }, { category: 'input', name: 'quantity', id: 'quantity', type: 'number', label: 'Quantity - Pcs.', max: physicalQuantityEnabled ? limit : '', required: true }, { category: 'input', name: 'quantity_packets', id: 'quantity_packets', type: 'number', label: 'Quantity - Pckts.', max: physicalQuantityEnabled && article.pcs_per_packet ? Math.floor(limit / article.pcs_per_packet) : '', required: true });
                createModal({ id: 'QuantityModalForm', name: 'Enter Quantity', class: 'h-auto', fields, fieldsGridCount: '2', bottomActions: [{ id: 'setQuantityBtn', text: 'Set Quantity', onclick: `setManualInvoiceQuantity(${article.id})` }] });
                initializeArticleQuantityPair(article.pcs_per_packet, physicalQuantityEnabled ? limit : 0, existing?.invoice_pcs || '');
                document.getElementById('quantity')?.addEventListener('input', () => syncArticleQuantityPair('pcs', article.pcs_per_packet, physicalQuantityEnabled ? limit : 0));
                document.getElementById('quantity_packets')?.addEventListener('input', () => syncArticleQuantityPair('packets', article.pcs_per_packet, physicalQuantityEnabled ? limit : 0));
                document.getElementById('quantity')?.focus();
            };
            window.setManualInvoiceQuantity = function (articleId) {
                const article = availableArticles.find(item => Number(item.id) === Number(articleId));
                const pcs = Number.parseInt(document.getElementById('quantity')?.value || '0', 10);
                if (!article || pcs <= 0) return;
                if (!syncArticleQuantityPair('pcs', article.pcs_per_packet, physicalQuantityEnabled ? Number(article.orderable_quantity || 0) : 0)) return;
                if (physicalQuantityEnabled && pcs > Number(article.orderable_quantity || 0)) return renderError('Quantity exceeds current stock.');
                const existing = selectedLines.find(line => line.article_id === Number(article.id));
                if (existing) existing.invoice_pcs = pcs;
                else selectedLines.push({ article_id: Number(article.id), article, description: descriptionFor(article), invoice_pcs: pcs, rate: Number(article.sales_rate || 0) });
                closeModal('QuantityModalForm');
                renderManualLines();
            };
            const selectArticlesBtn = document.getElementById('manualSelectArticlesBtn');
            if (selectArticlesBtn) { selectArticlesBtn.disabled = true; selectArticlesBtn.addEventListener('click', window.generateManualInvoiceArticlesModal); }
            const updateSelectArticlesState = () => { if (selectArticlesBtn) selectArticlesBtn.disabled = !customerValue?.value; };
            customerValue?.addEventListener('change', updateSelectArticlesState);
            document.getElementById('customer_id')?.addEventListener('input', () => window.setTimeout(updateSelectArticlesState, 0));
            linesContainer?.addEventListener('click', event => {
                const button = event.target.closest('[data-remove-manual-line]');
                if (!button) return;
                selectedLines.splice(Number(button.dataset.removeManualLine), 1);
                renderManualLines();
            });

            window.validateForNextStep = function validateForNextStep() {
                const customer = availableCustomers.find(item => Number(item.id) === Number(customerValue?.value));
                if (!customer || selectedLines.length === 0) {
                    renderError(!customer ? 'Please select a customer.' : 'Please add at least one article.');
                    return false;
                }
                const preview = document.getElementById('preview-container');
                const totalAmount = selectedLines.reduce((sum, line) => sum + line.invoice_pcs * line.rate, 0);
                preview.className = 'h-auto mx-auto relative flex flex-col';
                const data = { customer, date: document.getElementById('date')?.value, invoice_no: 'Assigned after save', order_no: null, carton_count: 0, discount: 0, netAmount: totalAmount, branch_branding: companyData, invoice_articles: selectedLines.map(line => ({ article: line.article, description: line.description, invoice_pcs: line.invoice_pcs })) };
                preview.innerHTML = [...buildA5InvoicePreviewPages(data, 'Customer', data.invoice_articles), ...buildA5InvoicePreviewPages(data, 'Office', data.invoice_articles)].join('');
                return true;
            };

            document.getElementById('printAndSaveBtn')?.addEventListener('click', event => {
                event.preventDefault();
                if (!window.validateForNextStep()) return;
                const form = document.getElementById('form');
                let input = form.querySelector('[name="printAfterSave"]');
                if (!input) { input = document.createElement('input'); input.type = 'hidden'; input.name = 'printAfterSave'; form.appendChild(input); }
                input.value = '1';
                form.requestSubmit();
            });
            renderManualLines();
            return;
        }

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
            const markup = window.DocumentPreview.render({
                preview: {
                    type: 'invoice',
                    size: 'A5',
                    document: 'Sales Invoice',
                    copyLabel,
                    data: {
                        ...previewData,
                        copy_label: copyLabel,
                        invoice_articles: invoiceRows,
                        branch_branding: previewData.branch_branding || companyData,
                    },
                },
            }, {
                companyData,
                companyLogoBase,
            });

            return [markup];
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
                return 'Assigned after save';
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
                        netAmount: netAmount || null,
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
                const articles = Array.isArray(previewData.invoice_articles)
                    ? previewData.invoice_articles
                    : [];

                return buildA5InvoicePreviewPages(previewData, copyLabel, articles).join('');
            }

            window.validateForNextStep = function validateForNextStep() {
                generateInvoice();

                document.getElementById("customers_array").value = JSON.stringify(selectedCustomersArray);
                return true;
            };

            function addListenerToPrintAndSaveBtn() {
                const printBtn = document.getElementById("printAndSaveBtn");
                if (!printBtn) return;

                printBtn.addEventListener("click", (e) => {
                    e.preventDefault();

                    closeAllDropdowns();

                    generateInvoice();

                    const form = document.getElementById("form");
                    if (!form) return;

                    // Tell Laravel that this is Save & Print
                    let printAfterSaveInput = form.querySelector(
                        'input[name="printAfterSave"]'
                    );

                    if (!printAfterSaveInput) {
                        printAfterSaveInput = document.createElement("input");
                        printAfterSaveInput.type = "hidden";
                        printAfterSaveInput.name = "printAfterSave";
                        form.appendChild(printAfterSaveInput);
                    }

                    printAfterSaveInput.value = "1";

                    // Shipment customers
                    if (invoiceType === "shipment") {
                        const customersInput = document.getElementById("customers_array");

                        if (customersInput) {
                            customersInput.value = JSON.stringify(selectedCustomersArray);
                        }
                    }

                    // Order articles
                    if (invoiceType === "order") {
                        updateInputArticlesInInvoice();
                    }

                    // IMPORTANT:
                    // Do NOT open print window here.
                    // Submit first so Laravel saves the invoice.
                    printAfterSave = 1
                    form.requestSubmit();
                });
            }

            addListenerToPrintAndSaveBtn();
        } else if (invoiceType === "order") {
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
                return 'Assigned after save';
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
                const articles = Array.isArray(previewData.invoice_articles)
                    ? previewData.invoice_articles
                    : [];

                return buildA5InvoicePreviewPages(previewData, copyLabel, articles).join('');
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

                    const form = document.getElementById("form");
                    if (!form) return;

                    // Tell Laravel that this is Save & Print
                    let printAfterSaveInput = form.querySelector(
                        'input[name="printAfterSave"]'
                    );

                    if (!printAfterSaveInput) {
                        printAfterSaveInput = document.createElement("input");
                        printAfterSaveInput.type = "hidden";
                        printAfterSaveInput.name = "printAfterSave";
                        form.appendChild(printAfterSaveInput);
                    }

                    printAfterSaveInput.value = "1";

                    // Shipment customers
                    if (invoiceType === "shipment") {
                        const customersInput = document.getElementById("customers_array");

                        if (customersInput) {
                            customersInput.value = JSON.stringify(selectedCustomersArray);
                        }
                    }

                    // Order articles
                    if (invoiceType === "order") {
                        updateInputArticlesInInvoice();
                    }

                    // IMPORTANT:
                    // Do NOT open print window here.
                    // Submit first so Laravel saves the invoice.
                    printAfterSave = 1
                    form.requestSubmit();
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
