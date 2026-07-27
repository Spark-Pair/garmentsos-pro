(() => {
    function initSalesReturnReturn() {
        const config = window.__salesReturnReturn || {};
        const csrfToken = config.csrfToken || "";
        const detailsUrl = config.detailsUrl || "";

        let returnableLines = [];
        let selectedReturns = [];
        let returnModalSearch = "";

        const articleListDOM = document.getElementById("article-list");
        const dateInput = document.getElementById("date");
        const returnsDataInput = document.getElementById("returns_data");
        const selectedLinesDOM = document.getElementById("selectedLinesInForm");
        const totalQuantityDOM = document.getElementById("totalQuantityInForm");
        const totalAmountDOM = document.getElementById("totalAmountInForm");
        const selectArticlesBtn = document.getElementById("selectReturnArticlesBtn");
        const form = document.getElementById("form");
        const typeInput = document.getElementById("salesReturnType");

        function currentTypeLabel() {
            return typeInput?.value === "adjustment" ? "Adjustment" : "Return";
        }

        window.setSalesReturnType = function setSalesReturnType(button, type) {
            if (!typeInput || !["return", "adjustment"].includes(type)) return;

            typeInput.value = type;
            const highlight = document.getElementById("returnTypeHighlight");
            if (highlight && button?.parentElement) {
                const rect = button.getBoundingClientRect();
                const parentRect = button.parentElement.getBoundingClientRect();
                highlight.style.width = `${rect.width}px`;
                highlight.style.left = `${rect.left - parentRect.left - 3}px`;
            }

            selectedReturns = [];
            renderReturnLinesModalBody();
            renderList();
            renderCalcBottom();
        };

        function money(value) {
            return typeof formatNumbersWithDigits === "function"
                ? formatNumbersWithDigits(value || 0, 1, 1)
                : Number(value || 0).toFixed(1);
        }

        function numberLess(value) {
            return typeof formatNumbersDigitLess === "function"
                ? formatNumbersDigitLess(value || 0)
                : String(value || 0);
        }

        function lineAmount(line, quantity) {
            const rate = parseFloat(line.sales_rate || 0);
            const discount = parseFloat(line.discount || 0);
            return quantity * rate * (1 - discount / 100);
        }

        function normalizeResponse(response) {
            return Array.isArray(response) ? response : Object.values(response || {});
        }

        function getSelected(key) {
            return selectedReturns.find(item => item.key === key);
        }

        function getEligibleLines() {
            const selectedDate = dateInput.value;

            if (!selectedDate) {
                return returnableLines;
            }

            return returnableLines.filter(line => !line.invoice_date || line.invoice_date <= selectedDate);
        }

        function removeReturnsAfterSelectedDate() {
            const eligibleKeys = new Set(getEligibleLines().map(line => line.key));
            selectedReturns = selectedReturns.filter(item => eligibleKeys.has(item.key));
        }

        function upsertSelected(line, quantity) {
            const existingIndex = selectedReturns.findIndex(item => item.key === line.key);

            if (quantity > 0) {
                const payload = {
                    key: line.key,
                    invoice_id: line.invoice_id,
                    invoice_no: line.invoice_no,
                    invoice_date: line.invoice_date,
                    article_id: line.article_id,
                    article_no: line.article_no,
                    description: line.description,
                    remaining_quantity: line.remaining_quantity,
                    sales_rate: line.sales_rate,
                    discount: line.discount,
                    quantity,
                    amount: lineAmount(line, quantity),
                };

                if (existingIndex >= 0) {
                    selectedReturns[existingIndex] = payload;
                } else {
                    selectedReturns.push(payload);
                }
            } else if (existingIndex >= 0) {
                selectedReturns.splice(existingIndex, 1);
            }
        }

        function resetReturns(message = "Select a customer to load invoices") {
            returnableLines = [];
            selectedReturns = [];
            returnModalSearch = "";
            dateInput.value = "";
            dateInput.min = "";
            dateInput.disabled = true;
            selectArticlesBtn.disabled = true;
            returnsDataInput.value = "";
            articleListDOM.innerHTML = `<div class="text-center bg-[var(--h-bg-color)] rounded-lg py-3 px-4">${message}</div>`;
            renderCalcBottom();
        }

        window.onCustomerSelect = function onCustomerSelect(selectElement) {
            const selectedCustomerId = selectElement.value;

            if (!selectedCustomerId) {
                resetReturns();
                return;
            }

            resetReturns("Loading invoices...");

            $.ajax({
                url: detailsUrl,
                type: "POST",
                data: {
                    customer_id: selectedCustomerId,
                    getReturnLines: true,
                    _token: csrfToken,
                },
                success: function (response) {
                    returnableLines = normalizeResponse(response);
                    selectedReturns = [];
                    returnModalSearch = "";

                    if (returnableLines.length === 0) {
                        resetReturns("No returnable invoice articles found");
                        return;
                    }

                    dateInput.disabled = false;
                    dateInput.value = localDateString();
                    selectArticlesBtn.disabled = false;
                    removeReturnsAfterSelectedDate();
                    renderList();
                    renderCalcBottom();
                    openReturnLinesModal();
                },
                error: function (xhr) {
                    console.error("Error fetching sales return details:", xhr);
                    resetReturns("Unable to load invoices");
                },
            });
        };

        window.openReturnLinesModal = function openReturnLinesModal() {
            if (!returnableLines.length) return;

            const eligibleLines = getEligibleLines();
            const visibleLines = filterReturnModalLines(eligibleLines);

            const modalData = {
                id: "ReturnArticlesModal",
                name: `Select ${currentTypeLabel()} Articles`,
                class: "h-[85vh] max-h-[46rem] max-w-6xl",
                info: `Selected: ${selectedReturns.length} lines / ${numberLess(getTotalQuantity())} PCs`,
                basicSearch: true,
                onBasicSearch: "filterReturnLinesModal(this.value)",
                fieldsGridCount: "1",
                fields: [
                    {
                        category: "explicitHtml",
                        full: true,
                        html: `
                            <div id="return-lines-modal-table" class="w-full h-[calc(85vh-11rem)] max-h-[36rem] flex flex-col text-left text-sm">
                                <div id="table-head" class="flex justify-between items-center bg-[var(--h-bg-color)] rounded-lg py-2 px-4 mb-3 select-none">
                                    <div class="w-[4%] cursor-pointer" onclick="sortByThis(this)">#</div>
                                    <div class="w-[10%] cursor-pointer" onclick="sortByThis(this)">Invoice</div>
                                    <div class="w-[10%] cursor-pointer" onclick="sortByThis(this)">Date</div>
                                    <div class="w-[11%] cursor-pointer" onclick="sortByThis(this)">Article</div>
                                    <div class="grow cursor-pointer" onclick="sortByThis(this)">Desc.</div>
                                    <div class="w-[7%] text-right cursor-pointer" onclick="sortByThis(this)">Unit</div>
                                    <div class="w-[9%] text-right cursor-pointer" onclick="sortByThis(this)">Max Pcs</div>
                                    <div class="w-[12%] text-right">${currentTypeLabel()} Pcs</div>
                                    <div class="w-[9%] text-right cursor-pointer" onclick="sortByThis(this)">Rate</div>
                                    <div class="w-[7%] text-right cursor-pointer" onclick="sortByThis(this)">Amount</div>
                                    <div class="w-[9%] text-right cursor-pointer" onclick="sortByThis(this)">Selected</div>
                                </div>
                                <div id="return-lines-modal-body" class="search_container flex-1 min-h-0 overflow-y-auto my-scrollbar-2">
                                    ${returnLinesModalRows(visibleLines)}
                                </div>
                            </div>
                        `,
                    },
                ],
                bottomActions: [
                    { id: "doneReturnArticles", text: "Done", onclick: "closeReturnLinesModal()" },
                ],
            };

            createModal(modalData);
        };

        function filterReturnModalLines(lines = getEligibleLines()) {
            const search = returnModalSearch.trim().toLowerCase();

            if (!search) {
                return lines;
            }

            return lines.filter(line => {
                    const selected = getSelected(line.key);
                    return [
                        line.invoice_no,
                        line.invoice_date,
                        line.article_no,
                        line.description,
                        line.pcs_per_packet,
                        line.remaining_quantity,
                        line.sales_rate,
                        selected?.quantity,
                    ].some(value => String(value ?? "").toLowerCase().includes(search));
                });
        }

        function renderReturnLinesModalBody() {
            const body = document.getElementById("return-lines-modal-body");
            if (!body) return;

            body.innerHTML = returnLinesModalRows(filterReturnModalLines());
        }

        window.filterReturnLinesModal = function filterReturnLinesModal(value) {
            returnModalSearch = value || "";
            renderReturnLinesModalBody();
        };

        function returnLinesModalRows(lines) {
            if (!lines.length) {
                return `
                    <div class="flex justify-between items-center border-t border-gray-600 py-3 px-4">
                        <div class="grow text-center text-[var(--border-error)]">No invoice articles available for selected date.</div>
                    </div>
                `;
            }

            return lines.map((line, index) => {
                const selected = getSelected(line.key);
                const quantity = selected?.quantity || "";
                const amount = selected ? selected.amount : 0;

                return `
                    <div class="item return-modal-line flex justify-between items-center border-t border-gray-600 py-3 px-4"
                        data-key="${htmlAttr(line.key)}">
                        <div class="w-[4%]">${index + 1}.</div>
                        <div class="w-[10%]">${line.invoice_no || "-"}</div>
                        <div class="w-[10%]">${line.invoice_date || "-"}</div>
                        <div class="w-[11%]">${line.article_no || "-"}</div>
                        <div class="grow pr-3">${line.description || "-"}</div>
                        <div class="w-[7%] text-right">${numberLess(line.pcs_per_packet)}</div>
                        <div class="w-[9%] text-right">${numberLess(line.remaining_quantity)}</div>
                        <div class="w-[12%] text-right">
                            <input type="number"
                                min="0"
                                max="${line.remaining_quantity}"
                                value="${quantity}"
                                data-key="${htmlAttr(line.key)}"
                                oninput="onReturnModalQuantityInput(this)"
                                onclick="this.select()"
                                class="return-modal-quantity w-[5.5rem] text-right border border-gray-600 bg-[var(--h-bg-color)] py-1 px-2 rounded-md focus:outline-none">
                        </div>
                        <div class="w-[9%] text-right">${money(line.sales_rate)}</div>
                        <div class="return-modal-amount w-[7%] text-right">${money(amount)}</div>
                        <div class="w-[9%] text-right">
                            <span class="return-selected-label text-xs text-[var(--border-success)]">${quantity ? `${quantity} PCs` : "-"}</span>
                        </div>
                    </div>
                `;
            }).join("");
        }

        window.onReturnModalQuantityInput = function onReturnModalQuantityInput(input) {
            const line = returnableLines.find(item => item.key === input.dataset.key);
            if (!line) return;

            const quantity = normalizeQuantity(input, line.remaining_quantity);
            upsertSelected(line, quantity);

            const row = input.closest(".return-modal-line");
            row.querySelector(".return-modal-amount").textContent = money(lineAmount(line, quantity));
            row.querySelector(".return-selected-label").textContent = quantity ? `${quantity} PCs` : "-";

            updateModalInfo();
            updateDateMinimum();
            renderList();
            renderCalcBottom();
        };

        dateInput?.addEventListener("change", function () {
            removeReturnsAfterSelectedDate();
            updateDateMinimum();
            renderReturnLinesModalBody();
            renderList();
            renderCalcBottom();
        });

        function updateModalInfo() {
            const infoDom = document.querySelector(".ReturnArticlesModalInfo .main-text");
            if (infoDom) {
                infoDom.textContent = `Selected: ${selectedReturns.length} lines / ${numberLess(getTotalQuantity())} PCs`;
            }
        }

        window.closeReturnLinesModal = function closeReturnLinesModal() {
            closeModal("ReturnArticlesModal");
            renderList();
            renderCalcBottom();
        };

        function normalizeQuantity(input, max) {
            input.value = input.value.replace(/[^\d]/g, "");

            let quantity = parseInt(input.value || "0", 10);
            const maxQuantity = parseInt(max || 0, 10);

            if (quantity > maxQuantity) quantity = maxQuantity;
            if (quantity < 0 || Number.isNaN(quantity)) quantity = 0;

            input.value = quantity || "";
            return quantity;
        }

        function renderList() {
            if (!selectedReturns.length) {
                articleListDOM.innerHTML = `<div class="text-center bg-[var(--h-bg-color)] rounded-lg py-3 px-4">${returnableLines.length ? "No articles selected" : "Select a customer to load invoices"}</div>`;
                return;
            }

            articleListDOM.innerHTML = selectedReturns.map((line, index) => `
                <div class="return-line flex justify-between items-center text-center border-t border-gray-600 py-3 px-2"
                    data-key="${htmlAttr(line.key)}">
                    <div class="w-[3%]">${index + 1}.</div>
                    <div class="w-[9%]">${line.invoice_no || "-"}</div>
                    <div class="w-[11%]">${line.invoice_date || "-"}</div>
                    <div class="w-[12%]">${line.article_no || "-"}</div>
                    <div class="grow">${line.description || "-"}</div>
                    <div class="w-[8%] ">${numberLess(line.remaining_quantity)}</div>
                    <div class="w-[11%] ">${numberLess(line.quantity)}</div>
                    <div class="w-[9%] ">${money(line.sales_rate)}</div>
                    <div class="w-[5%] ">${line.discount || 0}%</div>
                    <div class="line-amount w-[10%] ">${money(line.amount)}</div>
                    <div class="w-[3%] text-center">
                        <button type="button" onclick="removeReturnLine(${index})"
                            class="text-[var(--danger-color)] text-xs px-2 py-1 rounded-lg hover:text-[var(--h-danger-color)] transition-all duration-300 ease-in-out cursor-pointer">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            `).join("");
        }

        window.removeReturnLine = function removeReturnLine(index) {
            if (index >= 0) {
                selectedReturns.splice(index, 1);
            }

            updateDateMinimum();
            renderReturnLinesModalBody();
            renderList();
            renderCalcBottom();
        };

        function updateDateMinimum() {
            dateInput.min = "";
        }

        function getTotalQuantity() {
            return selectedReturns.reduce((sum, item) => sum + item.quantity, 0);
        }

        function setReturnsDataInput() {
            returnsDataInput.value = JSON.stringify(
                selectedReturns.map(item => ({
                    invoice_id: item.invoice_id,
                    article_id: item.article_id,
                    quantity: item.quantity,
                }))
            );
        }

        function renderCalcBottom() {
            const totalAmount = selectedReturns.reduce((sum, item) => sum + item.amount, 0);

            selectedLinesDOM.textContent = numberLess(selectedReturns.length);
            totalQuantityDOM.textContent = numberLess(getTotalQuantity());
            totalAmountDOM.textContent = money(totalAmount);
            setReturnsDataInput();
        }

        form?.addEventListener("submit", function (event) {
            setReturnsDataInput();

            if (selectedReturns.length === 0) {
                event.preventDefault();
                appAlert(`Please select at least one invoice article for ${currentTypeLabel().toLowerCase()}.`);
            }
        });

        resetReturns();
        const initialType = typeInput?.value === "adjustment" ? "adjustment" : "return";
        window.setSalesReturnType(
            document.getElementById(initialType === "adjustment" ? "adjustmentTypeBtn" : "returnTypeBtn"),
            initialType
        );
    }

    window.initSalesReturnReturn = initSalesReturnReturn;

    function boot() {
        if (window.__salesReturnReturn) {
            initSalesReturnReturn();
        }
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", boot);
    } else {
        boot();
    }
})();
