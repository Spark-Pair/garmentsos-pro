(() => {
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

    function buildA5InvoicePreviewPages(previewData, copyLabel, articles, companyData, companyLogoBase) {
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

    function renderError(config, message) {
        if (typeof messageBox === "undefined") return;
        if (!config.errorAlertTemplate) return;

        messageBox.innerHTML = config.errorAlertTemplate.replace("__MESSAGE__", message);
        messageBoxAnimation();
    }

    function wireDeveloperCustomerSelect(config, customerDataRef, onChange) {
        if (!config.isDeveloper) return;

        const customerSelectDom = document.getElementById("customer_id");
        if (!customerSelectDom) return;

        customerSelectDom.addEventListener("change", () => {
            const selected = (config.customers || {})[customerSelectDom.value];
            const nextCustomer = selected?.data_option || null;
            if (nextCustomer) {
                onChange(nextCustomer);
            }
        });
    }

    // -----------------------------------------------------------------------
    // ORDER invoice edit flow
    // -----------------------------------------------------------------------

    function initOrderInvoiceEdit(config) {
        const csrfToken = config.csrfToken || "";
        const companyData = config.companyData || {};
        const companyLogoBase = config.companyLogoBase || "";
        const discountDisabled = Boolean(config.discountDisabled);

        function invoiceDiscountDisabled() {
            return discountDisabled;
        }

        // orderedArticles keeps every row that's currently on the invoice, in the
        // shape: { invoice_article_id, order_article_id, article_id, article, description,
        //          total_quantity_in_packets, ordered_quantity }
        // invoice_article_id is null for rows pulled fresh from an order (not yet saved).
        let orderedArticles = Array.isArray(config.articles) ? config.articles : [];
        let customerData = config.customer || null;
        let orderDeliverTo = config.deliverTo || '';
        let discount = Number(config.discount || 0);
        let totalQuantityPcs = 0;
        let totalAmount = 0;
        let netAmount = 0;

        const invoiceNoDom = document.getElementById("invoice_no");
        const dateDom = document.getElementById("date");
        const orderNoDom = document.getElementById("order_no");
        const orderNoValueDom = document.querySelector('.dbInput[data-for="order_no"]');
        const loadInvoiceOrderBtn = document.getElementById("loadInvoiceOrderBtn");

        const articleListDOM = document.getElementById("article-list");
        const totalQuantityInFormDom = document.getElementById("totalQuantityInForm");
        const totalAmountInFormDom = document.getElementById("totalAmountInForm");
        const dicountInFormDom = document.getElementById("dicountInForm");
        const netAmountInFormDom = document.getElementById("netAmountInForm");
        const previewDom = document.getElementById("preview-container");

        function renderCustomerDisplay() {
            const customerDisplayDom = document.getElementById("customer_display");
            if (!customerDisplayDom) return;

            if (!customerData) {
                customerDisplayDom.value = "";
                return;
            }

            const cityTitle = typeof customerData.city === 'string'
                ? customerData.city
                : (customerData.city?.title || '-');

            customerDisplayDom.value = `${customerData.customer_name || ''} | ${cityTitle}`;
        }
        renderCustomerDisplay();

        // Developer-only customer dropdown keeps customerData (and therefore the
        // preview / saved customer_id) in sync when changed.
        wireDeveloperCustomerSelect(config, customerData, (nextCustomer) => {
            customerData = nextCustomer;
            renderCustomerDisplay();
        });

        function selectedOrderNo() {
            return (orderNoValueDom?.value || orderNoDom?.value || "").trim();
        }

        // Non-developers see the order number field disabled and never load a
        // different order, so the "Load Articles" button + its listeners are
        // developer-only.
        if (config.isDeveloper && orderNoDom && loadInvoiceOrderBtn) {
            function trackStateOfOrderNo(value) {
                loadInvoiceOrderBtn.disabled = value === "";
            }

            orderNoDom.addEventListener("input", () => {
                trackStateOfOrderNo(selectedOrderNo());
            });
            orderNoValueDom?.addEventListener("change", () => trackStateOfOrderNo(selectedOrderNo()));

            orderNoDom.addEventListener("keydown", (e) => {
                if (e.key === "Enter") {
                    loadInvoiceOrderBtn.click();
                }
            });

            trackStateOfOrderNo(selectedOrderNo());

            loadInvoiceOrderBtn.addEventListener("click", function () {
                getOrderDetails();
            });
        }

        function normalizeOrderArticlesFromServer(articles) {
            // When freshly loaded from an order, `packets` IS the order's full
            // quantity for that article — so max_packets is simply that value;
            // the invoice can't request more packets than the order actually has.
            return (Array.isArray(articles) ? articles : []).map((line) => {
                const pcsPerPacket = Number(line.article?.pcs_per_packet || 1);
                const packets = Number(line.total_quantity_in_packets ?? line.packets ?? 0);

                return {
                    invoice_article_id: null,
                    order_article_id: line.id ?? null,
                    article_id: line.article_id ?? line.article?.id ?? null,
                    article: line.article,
                    description: line.description ?? line.article?.description ?? '',
                    total_quantity_in_packets: packets,
                    max_packets: Math.max(packets, 1),
                    ordered_quantity: packets * pcsPerPacket,
                };
            });
        }

        function getOrderDetails() {
            $.ajax({
                url: "/get-order-details",
                type: "POST",
                data: {
                    _token: csrfToken,
                    order_no: selectedOrderNo(),
                    allow_invoiced: 1,
                    current_invoice_id: config.invoiceId,
                },
                success: function (response) {
                    if (!response.error) {
                        orderedArticles = normalizeOrderArticlesFromServer(response.articles);
                        discount = Number(response.discount ?? 0);
                        customerData = response.customer;
                        orderDeliverTo = response.deliver_to || '';
                        renderCustomerDisplay();
                    } else {
                        renderError(config, response.error);
                    }
                    renderList();
                    renderCalcBottom();
                },
                error: function () {
                    renderError(config, "Could not load order articles. Please try again.");
                },
            });
        }

        function renderList() {
            // IMPORTANT: orderedArticles is re-assigned to its own sorted order here, so the
            // index used to build each row's remove button is the SAME index the row actually
            // lives at in orderedArticles. Sorting a copy (like older code did) while
            // deleting from the original unsorted array is what causes the wrong row to be
            // removed - keeping one single, consistently-ordered array fixes it.
            orderedArticles = sortArticleRows(orderedArticles);

            if (!articleListDOM) return;

            if (orderedArticles.length > 0) {
                totalAmount = 0;
                totalQuantityPcs = 0;

                let clutter = "";
                orderedArticles.forEach((selectedArticle, index) => {
                    if (selectedArticle.total_quantity_in_packets > 0) {
                        let totalQuantityInPackets = selectedArticle.total_quantity_in_packets;

                        totalQuantityPcs +=
                            totalQuantityInPackets * selectedArticle.article.pcs_per_packet;

                        let articleAmount =
                            selectedArticle.article.sales_rate *
                            selectedArticle.article.pcs_per_packet *
                            totalQuantityInPackets;

                        // Packets input can never go below 1, and can never exceed
                        // the order's quantity for this article.
                        const maxPackets = Number(
                            selectedArticle.max_packets ?? totalQuantityInPackets
                        );
                        const canRemove = orderedArticles.length > 1;

                        clutter += `
                            <div class="flex justify-between items-center border-t border-gray-600 py-3 px-4">
                                <div class="w-[5%]">${index + 1}.</div>
                                <div class="w-[11%]">${selectedArticle.article.article_no}</div>
                                <div class="w-[11%] pr-3">
                                    <input type="number" class="w-full border border-gray-600 bg-[var(--h-bg-color)] py-1 px-2 rounded-md focus:outline-none" value="${totalQuantityInPackets}" min="1" max="${maxPackets}" onclick='this.select()' oninput="packetEdited(this)" />
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
                                    <button onclick="removeArticle(${index})" type="button" ${canRemove ? "" : "disabled"} class="text-[var(--danger-color)] text-xs px-2 py-1 rounded-lg hover:text-[var(--h-danger-color)] transition-all duration-300 ease-in-out ${
                                        canRemove
                                            ? "cursor-pointer"
                                            : "cursor-not-allowed opacity-40"
                                    }">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        `;

                        totalAmount += articleAmount;

                        selectedArticle.total_quantity_in_packets = totalQuantityInPackets;
                        selectedArticle.ordered_quantity =
                            totalQuantityInPackets * selectedArticle.article.pcs_per_packet;
                    }
                });

                articleListDOM.innerHTML = clutter;
            } else {
                articleListDOM.innerHTML = `<div class="text-center bg-[var(--h-bg-color)] rounded-lg py-2 px-4">No Rates Added</div>`;
            }
        }
        renderList();

        window.removeArticle = function removeArticle(index) {
            const target = orderedArticles[index];
            if (!target) return;

            if (orderedArticles.length > 1) {
                orderedArticles.splice(index, 1);
                renderList();
                renderCalcBottom();
            }
        };

        function updateInputArticlesInInvoice() {
            const articlesInInvoiceInpDom = document.getElementById("articles_in_invoice");
            if (!articlesInInvoiceInpDom) return;

            let finalArticlesArray = sortArticleRows(orderedArticles).map((article) => {
                return {
                    invoice_article_id: article.invoice_article_id ?? null,
                    order_article_id: article.order_article_id ?? null,
                    id: article.article_id,
                    description: article.description,
                    invoice_quantity: article.ordered_quantity,
                };
            });
            articlesInInvoiceInpDom.value = JSON.stringify(finalArticlesArray);
        }

        function renderCalcBottom() {
            netAmount = invoiceDiscountDisabled() ? totalAmount : totalAmount - totalAmount * (discount / 100);
            if (totalQuantityInFormDom) totalQuantityInFormDom.textContent = formatNumbersDigitLess(totalQuantityPcs);
            if (totalAmountInFormDom) totalAmountInFormDom.textContent = formatNumbersWithDigits(totalAmount, 1, 1);
            if (dicountInFormDom) dicountInFormDom.textContent = invoiceDiscountDisabled() ? 0 : discount;
            if (netAmountInFormDom) netAmountInFormDom.value = formatNumbersWithDigits(netAmount, 1, 1);
        }
        renderCalcBottom();

        window.packetEdited = function packetEdited(elem) {
            elem.value = elem.value.replace(/\./g, "");

            // Packets input can never go below 1.
            const min = elem.min !== "" ? Math.max(parseInt(elem.min, 10), 1) : 1;
            const max = elem.max !== "" ? parseInt(elem.max, 10) : null;
            let value = parseInt(elem.value, 10);

            if (Number.isNaN(value)) {
                value = min;
            }
            if (max !== null && !Number.isNaN(max) && value > max) {
                value = max;
            }
            if (value < min) {
                value = min;
            }

            elem.value = value;

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
                currentArticle.total_quantity_in_packets = packetsValue;
                currentArticle.ordered_quantity = pcsCalculated;
            }

            renderCalcBottom();
        }

        function currentInvoiceNo() {
            return (invoiceNoDom?.value || config.invoiceNo || "").trim() || "Will be generated on save";
        }

        function buildOrderInvoicePreviewLikeModal(previewData, copyLabel = 'Customer') {
            const articles = Array.isArray(previewData.invoice_articles)
                ? previewData.invoice_articles
                : [];

            return buildA5InvoicePreviewPages(previewData, copyLabel, articles, companyData, companyLogoBase).join('');
        }

        function generateInvoice() {
            const invoiceNo = currentInvoiceNo();
            const invoiceDate = dateDom?.value || config.invoiceDate;

            if (!previewDom) return;

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
                        <h1 class="text-[var(--border-error)] font-medium text-center mt-5">No Preview available.</h1>
                    </div>
                `;
            }
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
                let printAfterSaveInput = form.querySelector('input[name="printAfterSave"]');

                if (!printAfterSaveInput) {
                    printAfterSaveInput = document.createElement("input");
                    printAfterSaveInput.type = "hidden";
                    printAfterSaveInput.name = "printAfterSave";
                    form.appendChild(printAfterSaveInput);
                }

                printAfterSaveInput.value = "1";

                updateInputArticlesInInvoice();

                // IMPORTANT:
                // Do NOT open print window here.
                // Submit first so Laravel saves the invoice.
                printAfterSave = 1;
                form.submit();
            });
        }

        addListenerToPrintAndSaveBtn();
    }

    // -----------------------------------------------------------------------
    // SHIPMENT invoice edit flow
    // -----------------------------------------------------------------------

    function initShipmentInvoiceEdit(config) {
        const companyData = config.companyData || {};
        const companyLogoBase = config.companyLogoBase || "";
        const discountDisabled = Boolean(config.discountDisabled);
        const discount = Number(config.discount || 0);

        const invoiceNoDom = document.getElementById("invoice_no");
        const dateDom = document.getElementById("date");
        const cartonCountDom = document.getElementById("carton_count");
        const articleListDOM = document.getElementById("article-list");
        const totalQuantityInFormDom = document.getElementById("totalQuantityInForm");
        const totalAmountInFormDom = document.getElementById("totalAmountInForm");
        const dicountInFormDom = document.getElementById("dicountInForm");
        const netAmountInFormDom = document.getElementById("netAmountInForm");
        const previewDom = document.getElementById("preview-container");

        // Base rate per article, taken from the shipment (per-carton figures),
        // NOT scaled by the current carton_count. All rendering below multiplies
        // by the live carton count so editing it recalculates everything.
        const shipmentArticles = Array.isArray(config.shipmentArticles) ? config.shipmentArticles : [];

        let customerData = config.customer || null;
        let totalQuantityPcs = 0;
        let totalAmount = 0;
        let netAmount = 0;

        function invoiceDiscountDisabled() {
            return discountDisabled;
        }

        function renderCustomerDisplay() {
            const customerDisplayDom = document.getElementById("customer_display");
            if (!customerDisplayDom) return;

            if (!customerData) {
                customerDisplayDom.value = "";
                return;
            }

            const cityTitle = typeof customerData.city === 'string'
                ? customerData.city
                : (customerData.city?.title || '-');

            customerDisplayDom.value = `${customerData.customer_name || ''} | ${cityTitle}`;
        }
        renderCustomerDisplay();

        wireDeveloperCustomerSelect(config, customerData, (nextCustomer) => {
            customerData = nextCustomer;
            renderCustomerDisplay();
        });

        function currentCartonCount() {
            const value = parseInt(cartonCountDom?.value, 10);
            return Number.isFinite(value) && value > 0 ? value : 1;
        }

        function renderList() {
            if (!articleListDOM) return;

            const cartonCount = currentCartonCount();

            if (shipmentArticles.length > 0) {
                totalAmount = 0;
                totalQuantityPcs = 0;

                let clutter = "";
                sortArticleRows(shipmentArticles).forEach((selectedArticle, index) => {
                    const shipmentPcs = Number(
                        selectedArticle.shipment_pcs ?? selectedArticle.quantity ?? 0
                    );
                    const invoicePcs = shipmentPcs * cartonCount;

                    if (invoicePcs <= 0) return;

                    totalQuantityPcs += invoicePcs;

                    const articleAmount = (selectedArticle.article?.sales_rate || 0) * invoicePcs;
                    totalAmount += articleAmount;

                    clutter += `
                        <div class="flex justify-between items-center border-t border-gray-600 py-3 px-4">
                            <div class="w-[5%]">${index + 1}.</div>
                            <div class="w-[11%]">${selectedArticle.article?.article_no ?? ''}</div>
                            <div class="w-[11%]">${formatNumbersDigitLess(shipmentPcs)}</div>
                            <div class="w-[10%]">${formatNumbersDigitLess(invoicePcs)}</div>
                            <div class="grow">${selectedArticle.description ?? ''}</div>
                            <div class="w-[8%]">${selectedArticle.article?.pcs_per_packet ?? ''}</div>
                            <div class="w-[12%] text-right">${formatNumbersWithDigits(
                                selectedArticle.article?.sales_rate ?? 0,
                                1,
                                1
                            )}</div>
                            <div class="w-[20%] text-right">${formatNumbersWithDigits(articleAmount, 1, 1)}</div>
                        </div>
                    `;
                });

                articleListDOM.innerHTML = clutter || `<div class="text-center bg-[var(--h-bg-color)] rounded-lg py-2 px-4">No Rates Added</div>`;
            } else {
                articleListDOM.innerHTML = `<div class="text-center bg-[var(--h-bg-color)] rounded-lg py-2 px-4">No Rates Added</div>`;
            }
        }

        function renderCalcBottom() {
            netAmount = invoiceDiscountDisabled() ? totalAmount : totalAmount - totalAmount * (discount / 100);
            if (totalQuantityInFormDom) totalQuantityInFormDom.textContent = formatNumbersDigitLess(totalQuantityPcs);
            if (totalAmountInFormDom) totalAmountInFormDom.textContent = formatNumbersWithDigits(totalAmount, 1, 1);
            if (dicountInFormDom) dicountInFormDom.textContent = invoiceDiscountDisabled() ? 0 : discount;
            if (netAmountInFormDom) netAmountInFormDom.value = formatNumbersWithDigits(netAmount, 1, 1);
        }

        cartonCountDom?.addEventListener("input", () => {
            let value = parseInt(cartonCountDom.value, 10);
            if (Number.isNaN(value) || value < 1) {
                value = 1;
            }
            cartonCountDom.value = value;

            renderList();
            renderCalcBottom();
        });

        renderList();
        renderCalcBottom();

        function currentInvoiceNo() {
            return (invoiceNoDom?.value || config.invoiceNo || "").trim() || "Will be generated on save";
        }

        function selectedShipmentNo() {
            const shipmentNoDom = document.getElementById("shipment_no");
            const shipmentNoValueDom = document.querySelector('.dbInput[data-for="shipment_no"]');
            return (shipmentNoValueDom?.value || shipmentNoDom?.value || config.shipmentNumber || "").trim();
        }

        function buildShipmentInvoicePreviewLikeModal(previewData, copyLabel = 'Customer') {
            const articles = Array.isArray(previewData.invoice_articles)
                ? previewData.invoice_articles
                : [];

            return buildA5InvoicePreviewPages(previewData, copyLabel, articles, companyData, companyLogoBase).join('');
        }

        function generateInvoice() {
            const invoiceNo = currentInvoiceNo();
            const invoiceDate = dateDom?.value || config.invoiceDate;
            const cartonCount = currentCartonCount();

            if (!previewDom) return;

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
                        invoice_pcs: (Number(article.shipment_pcs) || 0) * cartonCount,
                    })),
                };

                previewDom.className = "h-auto mx-auto relative flex flex-col";
                previewDom.innerHTML = [
                    buildShipmentInvoicePreviewLikeModal(previewData, 'Customer'),
                    buildShipmentInvoicePreviewLikeModal(previewData, 'Office'),
                ].join('');
            } else {
                previewDom.className = "w-[148mm] h-[210mm] mx-auto overflow-hidden relative";
                previewDom.innerHTML = `
                    <div id="preview" class="preview w-[148mm] h-[210mm] gos-a5-document gos-a5-invoice overflow-hidden flex flex-col">
                        <h1 class="text-[var(--border-error)] font-medium text-center mt-5">No Preview available.</h1>
                    </div>
                `;
            }
        }

        window.validateForNextStep = function validateForNextStep() {
            generateInvoice();
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

                let printAfterSaveInput = form.querySelector('input[name="printAfterSave"]');

                if (!printAfterSaveInput) {
                    printAfterSaveInput = document.createElement("input");
                    printAfterSaveInput.type = "hidden";
                    printAfterSaveInput.name = "printAfterSave";
                    form.appendChild(printAfterSaveInput);
                }

                printAfterSaveInput.value = "1";

                printAfterSave = 1;
                form.submit();
            });
        }

        addListenerToPrintAndSaveBtn();
    }

    // -----------------------------------------------------------------------
    // Boot
    // -----------------------------------------------------------------------

    function initInvoicesEdit() {
        const config = window.__invoicesEdit || {};
        const invoiceType = config.invoiceType || 'order';

        if (invoiceType === 'shipment') {
            initShipmentInvoiceEdit(config);
        } else {
            initOrderInvoiceEdit(config);
        }
    }

    window.initInvoicesEdit = initInvoicesEdit;

    function boot() {
        if (window.__invoicesEdit) {
            initInvoicesEdit();
        }
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", boot);
    } else {
        boot();
    }
})();