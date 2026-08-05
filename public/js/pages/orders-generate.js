(function () {
    let maxLimitOfArticles = 500;
    let limitOfArticles = 500;
    let selectedArticles = [];
    let totalOrderedQuantity = 0;
    let totalOrderAmount = 0;
    let netAmount = 0;
    let articles = [];

    let lastOrder;
    let customerData;
    const customerSelectDom = document.getElementById('customer_id');
    const generateOrderBtn = document.getElementById('generateOrderBtn');
    const isCustomerRole = !!window.__ordersGenerate?.isCustomerRole;
    const discountInput = document.getElementById('discount');
    let defaultDiscountTouched = false;

    let totalQuantityDOM;
    let totalAmountDOM;

    function isArticleAlreadySelected(articleId) {
        return selectedArticles.some(a => a.id == articleId);
    }

    function customerTitlePhoneLine(customer = {}) {
        const title = String(customer?.urdu_title ?? '').trim();
        const phone = String(customer?.phone_number ?? '').trim();
        return [title, phone].filter(Boolean).join(' | ');
    }

    function deliverToLine() {
        const deliverTo = String(document.getElementById('deliver_to')?.value ?? '').trim();
        return `Deliver To: ${deliverTo || '-'}`;
    }

    function articleSortValue(article = {}) {
        return String(article?.article_no ?? article?.article?.article_no ?? '').trim();
    }

    function sortedSelectedArticles() {
        return [...selectedArticles].sort((left, right) => (
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

    function orderDiscountDisabled() {
        return truthySetting(companyData?.discount_disabled);
    }

    function orderDocumentTotalsHtml() {
        if (orderDiscountDisabled()) {
            const note = String(companyData?.document_note || '').trim();
            return `
                <div class="flex flex-col space-y-2">
                    ${note ? `
                        <div class="tr flex justify-between w-full px-2 gap-2 text-sm">
                            <div class="total flex justify-center items-center border border-gray-600 rounded-lg py-2 px-4 w-full text-center font-semibold">
                                ${previewText(note)}
                            </div>
                        </div>
                    ` : ''}
                    <div id="order-total" class="tr flex justify-between w-full px-2 gap-2 text-sm">
                        <div class="total flex justify-between items-center border border-gray-600 rounded-lg py-2 px-4 w-full">
                            <div class="text-nowrap">Total Quantity</div>
                            <div class="w-1/4 text-right grow">${orderQuantitySummary()}</div>
                        </div>
                        <div class="total flex justify-between items-center border border-gray-600 rounded-lg py-2 px-4 w-full">
                            <div class="text-nowrap font-semibold">Net Amount</div>
                            <div class="w-1/4 text-right grow font-semibold">${totalAmountDOM.value}</div>
                        </div>
                    </div>
                </div>
            `;
        }

        return `
            <div class="flex flex-col space-y-2">
                <div id="order-total" class="tr flex justify-between w-full px-2 gap-2 text-sm">
                    <div class="total flex justify-between items-center border border-gray-600 rounded-lg py-2 px-4 w-full">
                        <div class="text-nowrap">Total Quantity</div>
                        <div class="w-1/4 text-right grow">${orderQuantitySummary()}</div>
                    </div>
                    <div class="total flex justify-between items-center border border-gray-600 rounded-lg py-2 px-4 w-full">
                        <div class="text-nowrap">Total Amount</div>
                        <div class="w-1/4 text-right grow">${totalAmountDOM.value}</div>
                    </div>
                </div>
                <div id="order-total" class="tr flex justify-between w-full px-2 gap-2 text-sm">
                    <div class="total flex justify-between items-center border border-gray-600 rounded-lg py-2 px-4 w-full">
                        <div class="text-nowrap">Discount %</div>
                        <div class="w-1/4 text-right grow">${discountDOM?.value || 0}</div>
                    </div>
                    <div class="total flex justify-between items-center border border-gray-600 rounded-lg py-2 px-4 w-full">
                        <div class="text-nowrap">Net Amount</div>
                        <div class="w-1/4 text-right grow">${finalNetAmount.value}</div>
                    </div>
                </div>
            </div>
        `;
    }

    window.trackCustomerState = function trackCustomerState(elem) {
        if (elem.value != '') {
            const customerDataDom = elem.parentElement
                .querySelector('.optionsDropdown li.selected')
                ?.getAttribute('data-option');

            customerData = JSON.parse(customerDataDom || '{}');
            selectedArticles = [];
            totalOrderedQuantity = 0;
            totalOrderAmount = 0;
            netAmount = 0;
            renderList();
            generateOrder();
            renderFinals();

            if (generateOrderBtn) generateOrderBtn.disabled = false;
        } else if (generateOrderBtn) {
            generateOrderBtn.disabled = true;
        }
    };

    let cardData = [];

    window.basicSearch = function basicSearch(searchValue) {
        const modalData = {
            id: 'modalForm',
            cards: { data: cardData.filter(item => item.name.toLowerCase().includes(searchValue.toLowerCase())) },
        };
        renderCardsInModal(modalData);
    };

    if (generateOrderBtn) {
        generateOrderBtn.disabled = true;
        generateOrderBtn.addEventListener('click', () => {
            generateArticlesModal();
        });
    }

    document.getElementById('deliver_to')?.addEventListener('input', () => {
        generateOrder();
    });

    function safeDocumentNumberPreview(value, fallback = 'Will be generated on save') {
        const text = String(value ?? '').trim();
        return text && !text.includes('NaN') ? text : fallback;
    }

    function incrementDocumentNumber(value, offset = 0, fallback = 'Will be generated on save') {
        const text = safeDocumentNumberPreview(value, '');
        if (!text) return fallback;

        return text.replace(/(\d+)(?!.*\d)/, match => {
            const next = Number.parseInt(match, 10) + offset;
            return Number.isFinite(next) ? String(next).padStart(match.length, '0') : match;
        }) || fallback;
    }

    function applyDefaultOrderDiscount(value) {
        if (!discountInput || defaultDiscountTouched) return;
        const numeric = Number.parseInt(value ?? 0, 10);
        discountInput.value = Number.isFinite(numeric) ? Math.max(0, Math.min(100, numeric)) : 0;
        calculateNetAmount();
        renderFinals();
    }

    function orderDetailLine(article) {
        const description = String(article?.description ?? '').trim();
        const fabricType = String(article?.fabric_type ?? '').trim();
        const parts = [description, fabricType].filter((part, index, list) => (
            part && list.findIndex(item => item.toLowerCase() === part.toLowerCase()) === index
        ));

        return parts.join(' | ');
    }

    function orderDispatchText(article) {
        const dispatchedPcs = Number(article?.dispatched_pcs || 0);
        const pcsPerPacket = Number(article?.pcs_per_packet || 0);
        if (!dispatchedPcs) return '';

        const packets = pcsPerPacket ? Math.floor(dispatchedPcs / pcsPerPacket) : 0;
        return packets ? formatNumbersDigitLess(packets) : '';
    }

    function orderQuantitySummary() {
        const pcs = parseFormattedNumber(totalQuantityDOM?.value || totalOrderedQuantity || 0);
        const packets = selectedArticles.reduce((sum, article) => {
            const qty = parseFormattedNumber(article?.orderedQuantity || 0);
            const unit = parseFormattedNumber(article?.pcs_per_packet || 0);
            return sum + (unit ? Math.floor(qty / unit) : 0);
        }, 0);

        return `${formatNumbersDigitLess(packets)} | ${formatNumbersDigitLess(pcs)}`;
    }

    window.generateArticlesModal = function generateArticlesModal() {
        const data = [...(articles || [])].sort((left, right) => (
            articleSortValue(left).localeCompare(articleSortValue(right), undefined, {
                numeric: true,
                sensitivity: 'base',
            })
        ));

        if (data.length > 0 && cardData.length == 0) {
            cardData.push(
                ...data.map(item => {
                    return {
                        id: item.id,
                        name: item.article_no,
                        image:
                            item.image == 'no_image_icon.png'
                                ? '/images/no_image_icon.png'
                                : `/storage/uploads/images/${item.image}`,
                        details: {
                            Category: item.category,
                            Season: item.season,
                            Size: item.size,
                        },
                        data: item,
                        onclick: 'generateQuantityModal(this)',
                    };
                })
            );
        }

        const modalData = {
            id: 'modalForm',
            class: 'h-[80%] w-full',
            cards: { name: 'Articles', count: 3, data: cardData },
            basicSearch: true,
            onBasicSearch: 'basicSearch(this.value)',
            info: `Selected: ${selectedArticles.length}/${maxLimitOfArticles}`,
            flex_col: true,
            calcBottom: [
                { label: 'Total Quantity - Pcs', name: 'totalShipmentedQty', value: '0', disabled: true },
                { label: 'Total Amount - Rs.', name: 'totalShipmentAmount', value: '0.0', disabled: true },
            ],
        };

        createModal(modalData);

        totalQuantityDOM = document.querySelector('#modalForm #totalShipmentedQty');
        totalAmountDOM = document.querySelector('#modalForm #totalShipmentAmount');

        document.querySelectorAll('.card .quantity-label').forEach(previousQuantityLabel => {
            previousQuantityLabel.remove();
        });

        if (selectedArticles.length > 0) {
            selectedArticles.forEach(selectedArticle => {
                const card = document.getElementById(selectedArticle.id);
                const quantityLabelDom = card?.querySelector('.quantity-label');
                if (card && !quantityLabelDom) {
                    card.innerHTML += `
                            <div
                                class="quantity-label absolute text-xs text-[var(--border-success)] top-1 right-2 h-[1rem]">
                                ${selectedArticle.orderedQuantity} Pcs
                            </div>
                        `;
                } else if (quantityLabelDom) {
                    quantityLabelDom.textContent = `${selectedArticle.orderedQuantity} Pcs`;
                }
            });
        }

        calculateTotalOrderedQuantity();
        calculateTotalOrderAmount();
        calculateNetAmount();
        renderTotals();
        generateDescription();
        renderList();
        generateOrder();
        renderFinals();
    };

    window.generateQuantityModal = function generateQuantityModal(elem) {
        const data = JSON.parse(elem.dataset.json).data;
        const alreadySelected = isArticleAlreadySelected(data.id);

        if (limitOfArticles > 0 || alreadySelected) {
            const modalData = {
                id: 'QuantityModalForm',
                name: 'Enter Quantity',
                class: 'h-auto',
                fields: [
                    {
                        category: 'input',
                        value: `${data.article_no} | ${data.season} | ${data.size} | ${data.category} | ${data.fabric_type} | ${data.quantity} | ${formatMoney(data.sales_rate)} - Rs.`,
                        disabled: true,
                        full: true,
                    },
                    {
                        category: 'input',
                        label: 'Orderable Quantity',
                        value: `${formatNumbersDigitLess(data.orderable_quantity)} Pcs | ${formatNumbersWithDigits(data.orderable_quantity_packets)} Pkts`,
                        disabled: true,
                        full: true,
                    },
                    {
                        category: 'input',
                        label: 'Invoiceable Quantity (Current Stock)',
                        value: `${formatNumbersDigitLess(data.current_stock)} Pcs | ${formatNumbersWithDigits(data.current_stock_packets)} Pkts`,
                        disabled: true,
                        full: true,
                    },
                    {
                        category: 'input',
                        label: 'Unit',
                        value: `${formatNumbersDigitLess(data.pcs_per_packet)} Pcs per Packet`,
                        disabled: true,
                        full: true,
                    },
                    {
                        category: 'input',
                        name: 'quantity',
                        id: 'quantity',
                        type: 'number',
                        label: 'Quantity - Pcs.',
                        max: Number(data.orderable_quantity || 0),
                        required: true,
                        oninput: `
                            if (+this.value > ${Number(data.orderable_quantity || 0)}) {
                                this.value = ${Number(data.orderable_quantity || 0)};
                            }
                            syncArticleQuantityPair('pcs', ${Number(data.pcs_per_packet || 0)}, ${Number(data.orderable_quantity || 0)});
                        `,
                    },
                    {
                        category: 'input',
                        name: 'quantity_packets',
                        id: 'quantity_packets',
                        type: 'number',
                        label: 'Quantity - Pckts.',
                        max: Number(data.pcs_per_packet || 0)
                            ? Math.floor(Number(data.orderable_quantity || 0) / Number(data.pcs_per_packet || 0))
                            : 0,
                        required: true,
                        oninput: `
                            if (+this.value > ${Number(data.pcs_per_packet || 0)
                                ? Math.floor(Number(data.orderable_quantity || 0) / Number(data.pcs_per_packet || 0))
                                : 0}) {
                                this.value = ${Number(data.pcs_per_packet || 0)
                                    ? Math.floor(Number(data.orderable_quantity || 0) / Number(data.pcs_per_packet || 0))
                                    : 0};
                            }
                            syncArticleQuantityPair('packets', ${Number(data.pcs_per_packet || 0)}, ${Number(data.orderable_quantity || 0)});
                        `,
                    },
                ],
                fieldsGridCount: '2',
                bottomActions: [{ id: 'setQuantityBtn', text: 'Set Quantity', onclick: `setQuantity(${data.id})` }],
            };

            createModal(modalData);

            const quantityLabel = document.getElementById(data.id)?.querySelector('.quantity-label');

            if (quantityLabel) {
                initializeArticleQuantityPair(data.pcs_per_packet, data.orderable_quantity, parseInt(quantityLabel.textContent.replace(/\D/g, '')));
            }
            syncArticleQuantityPair('pcs', data.pcs_per_packet, data.orderable_quantity);

            document.getElementById('quantity').focus();
            ['quantity', 'quantity_packets'].forEach(inputId => {
                document.getElementById(inputId)?.addEventListener('keydown', e => {
                    if (e.key === 'Enter') {
                        document.getElementById('setQuantityBtn-in-modal')?.click();
                    }
                });
            });
        } else {
            if (typeof messageBox !== 'undefined') {
                messageBox.innerHTML = window.__ordersGenerate?.maxArticlesAlertHtml || '';
                messageBoxAnimation();
            }
        }
    };

    window.setQuantity = function setQuantity(cardId) {
        const targetCard = document.getElementById(cardId);
        if (!targetCard) return;
        const cardData = JSON.parse(targetCard.dataset.json).data;
        const alreadySelected = isArticleAlreadySelected(cardData.id);

        if (limitOfArticles > 0 || alreadySelected) {
            const alreadySelectedArticle = selectedArticles.filter(c => c.id == cardData.id);
            const quantityInputDOM = document.getElementById('quantity');
            if (!syncArticleQuantityPair('pcs', cardData.pcs_per_packet, cardData.orderable_quantity)) {
                quantityInputDOM?.focus();
                return;
            }

            closeModal('QuantityModalForm');
            const quantity = quantityInputDOM.value;
            const quantityLabel = targetCard.querySelector('.quantity-label');

            if (quantity > 0) {
                if (quantityLabel) {
                    quantityLabel.textContent = `${quantity} Pcs`;
                } else {
                    targetCard.innerHTML += `
                            <div class="quantity-label absolute text-xs text-[var(--border-success)] top-1 right-2 h-[1rem]">
                                ${quantity} Pcs
                            </div>
                        `;
                }

                cardData.orderedQuantity = parseInt(quantity);

                if (alreadySelectedArticle.length > 0) {
                    alreadySelectedArticle[0].orderedQuantity = parseInt(quantity);
                } else {
                    selectedArticles.push(cardData);
                }
            } else if (quantityLabel) {
                quantityLabel.remove();
                const index = selectedArticles.findIndex(c => c.id === cardData.id);
                deselectArticleAtIndex(index);
            }

            generateDescription();
            calculateTotalOrderedQuantity();
            calculateTotalOrderAmount();
            calculateNetAmount();
            renderTotals();
            renderList();
            renderFinals();
        } else if (typeof messageBox !== 'undefined') {
            messageBox.innerHTML = window.__ordersGenerate?.maxArticlesAlertHtml || '';
            messageBoxAnimation();
        }
    };

    function updateInfo() {
        const infoDom = document.querySelector('.modalFormInfo span');
        if (!infoDom) return;

        infoDom.textContent = `Selected ${selectedArticles.length}/${maxLimitOfArticles}`;
    }

    function deselectArticleAtIndex(index) {
        if (index !== -1) {
            selectedArticles.splice(index, 1);
        }
    }

    window.deselectThisArticle = function deselectThisArticle(index) {
        deselectArticleAtIndex(index);

        renderList();
        generateOrder();

        calculateTotalOrderedQuantity();
        calculateTotalOrderAmount();
        calculateNetAmount();

        renderFinals();
        renderTotals();
    };

    const finalOrderedQuantity = document.getElementById('finalOrderedQuantity');
    const finalOrderAmount = document.getElementById('finalOrderAmount');
    const discountDOM = document.getElementById('discount');
    const finalNetAmount = document.getElementById('finalNetAmount');

    function calculateTotalOrderedQuantity() {
        totalOrderedQuantity = 0;

        selectedArticles.forEach(selectedArticle => {
            totalOrderedQuantity += selectedArticle.orderedQuantity;
        });

        totalOrderedQuantity = new Intl.NumberFormat('en-US').format(totalOrderedQuantity);
    }

    function calculateTotalOrderAmount() {
        totalOrderAmount = 0;

        selectedArticles.forEach(selectedArticle => {
            totalOrderAmount += selectedArticle.orderedQuantity * selectedArticle.sales_rate;
        });
    }

    function generateDescription() {
        selectedArticles.forEach(selectedArticle => {
            selectedArticle.description = `${selectedArticle.size} | ${selectedArticle.category.replaceAll('_', ' ')} | ${selectedArticle.season}`;
        });
    }

    function calculateNetAmount() {
        const totalAmount = totalOrderAmount;
        if (orderDiscountDisabled()) {
            if (discountDOM) discountDOM.value = 0;
            netAmount = totalAmount;
            renderFinals();
            return;
        }
        let discount = parseInt(document.getElementById('discount').value || 0, 10);
        if (Number.isNaN(discount)) discount = 0;
        discount = Math.max(0, Math.min(100, discount));
        if (discountDOM) discountDOM.value = discount;
        const discountAmount = totalAmount - totalAmount * (discount / 100);
        netAmount = discountAmount;
        renderFinals();
    }

    if (discountDOM) {
        discountDOM.addEventListener('input', () => {
            defaultDiscountTouched = true;
            calculateNetAmount();
        });
        discountDOM.addEventListener('focus', e => {
            e.target.select();
        });
    }

    function renderTotals() {
        if (!totalQuantityDOM || !totalAmountDOM) return;
        totalQuantityDOM.value = totalOrderedQuantity;
        totalAmountDOM.value = formatNumbersWithDigits(totalOrderAmount, 1, 1);
    }

    const orderListDOM = document.getElementById('order-list');

    function renderList() {
        if (!orderListDOM) return;
        if (selectedArticles.length > 0) {
            let clutter = '';
            sortedSelectedArticles().forEach((selectedArticle) => {
                const selectedIndex = selectedArticles.findIndex(article => article.id == selectedArticle.id);
                clutter += `
                        <div class="flex justify-between items-center border-t border-gray-600 py-3 px-4">
                            <div class="w-[10%]">${selectedArticle.article_no}</div>
                            <div class="w-1/6">${selectedArticle.orderedQuantity} pcs</div>
                            <div class="grow capitalize">${selectedArticle.description}</div>
                            <div class="w-1/6">${formatNumbersWithDigits(selectedArticle.sales_rate, 1, 1)}</div>
                            <div class="w-1/5">${formatNumbersWithDigits(selectedArticle.sales_rate * selectedArticle.orderedQuantity, 1, 1)}</div>
                            <div class="w-[10%] text-center">
                                <button onclick="deselectThisArticle(${selectedIndex})" type="button" class="text-[var(--danger-color)] text-xs px-2 py-1 rounded-lg hover:text-[var(--h-danger-color)] transition-all duration-300 ease-in-out cursor-pointer">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    `;
            });

            orderListDOM.innerHTML = clutter;
        } else {
            orderListDOM.innerHTML =
                '<div class="text-center bg-[var(--h-bg-color)] rounded-lg py-2 px-4">No Articles Yet</div>';
        }
        updateInputOrderedArticles();
        limitOfArticles = maxLimitOfArticles - selectedArticles.length;
        updateInfo();
    }

    function renderFinals() {
        if (!finalOrderedQuantity || !finalOrderAmount || !finalNetAmount) return;
        finalOrderedQuantity.textContent = formatNumbersWithDigits(totalOrderedQuantity, 1, 1);
        finalOrderAmount.textContent = formatNumbersWithDigits(totalOrderAmount, 1, 1);
        finalNetAmount.value = formatNumbersWithDigits(netAmount, 1, 1);
    }

    function updateInputOrderedArticles() {
        const inputOrderedArticles = document.getElementById('articles');
        const finalArticlesArray = sortedSelectedArticles().map(article => {
            return {
                id: article.id,
                description: article.description,
                ordered_quantity: article.orderedQuantity,
            };
        });
        if (inputOrderedArticles) {
            inputOrderedArticles.value = JSON.stringify(finalArticlesArray);
        }
    }

    let companyData;
    let orderNo;
    let orderDate;
    const previewContainer = document.getElementById('preview-container');

    function chunkA5Rows(rows) {
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

    function generateOrderNo() {
        return safeDocumentNumberPreview(
            window.__ordersGenerate?.nextOrderNo || incrementDocumentNumber(lastOrder?.order_no, 1)
        );
    }

    function getOrderDate() {
        const dateValue = document.getElementById('date').value;
        const date = new Date(dateValue);
        const day = String(date.getDate()).padStart(2, '0');
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const year = date.getFullYear();
        const dayOfWeek = date.getDay();
        const weekDays = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

        return `${day}-${month}-${year}, ${weekDays[dayOfWeek]}`;
    }

    window.generateOrder = function generateOrder() {
        orderNo = generateOrderNo();
        orderDate = getOrderDate();

        if (!previewContainer) return;
        if (selectedArticles.length > 0) {
            const pages = chunkA5Rows(sortedSelectedArticles());
            let serial = 1;

            previewContainer.className = 'h-auto mx-auto relative flex flex-col';
            previewContainer.innerHTML = pages.map((pageArticles, pageIndex) => {
                const isLastPage = pageIndex === pages.length - 1;
                const bodyRows = pageArticles.map((article) => {
                    const detailLine = orderDetailLine(article);
                    const dispatched = orderDispatchText(article);
                    const currentSerial = String(serial++).padStart(2, '0');

                    return `
                        <div class="invoice-item-row">
                            <div class="tr invoice-item-main grid grid-cols-9 justify-between w-full px-4 gap-0.5">
                                <div class="td text-sm font-semibold truncate">${currentSerial}</div>
                                <div class="td invoice-article-cell text-sm font-semibold">
                                    <div class="invoice-article-code">${article.article_no}</div>
                                </div>
                                <div class="td invoice-description-cell text-sm font-semibold">${detailLine}</div>
                                <div class="td text-sm font-semibold truncate">${article?.pcs_per_packet ?? 0}</div>
                                <div class="td text-sm font-semibold truncate">${article?.pcs_per_packet ? Math.floor(article.orderedQuantity / article.pcs_per_packet) : 0}</div>
                                <div class="td text-sm font-semibold truncate">${article.orderedQuantity}</div>
                                <div class="td text-sm font-semibold truncate">${formatNumbersDigitLess(article.sales_rate)}</div>
                                <div class="td text-sm font-semibold truncate">${formatNumbersDigitLess(parseFormattedNumber(article.sales_rate) * article.orderedQuantity)}</div>
                                <div class="td text-sm font-semibold text-center">${dispatched}</div>
                            </div>
                        </div>
                    `;
                }).join('');

                return `
                    <div id="preview" class="preview w-[148mm] h-[210mm] gos-a5-document gos-a5-invoice overflow-hidden flex flex-col">
                        <div id="order" class="order flex flex-col h-full">
                        <div id="banner" class="banner w-full flex justify-between items-center px-5">
                            <div class="left">
                                <div class="logo">
                                    <img src="${companyData.logo_url || `${window.__ordersGenerate.companyLogoBase}/${companyData.logo}`}" alt="garmentsos-pro"
                                        class="w-[12rem]" />
                                    <div class='mt-1'>${companyData.phone_number || companyData.phone || ""}</div>
                                </div>
                            </div>
                            <div class="logo text-right">
                                <h1 class="text-2xl font-medium text-[var(--h-primary-color)]">Sales Order</h1>
                                <div class="document-number mt-1 text-right">Order No.: ${orderNo}</div>
                            </div>
                        </div>
                        <hr class="w-full my-3 border-gray-600">
                        <div id="header" class="header w-full flex justify-between px-5">
                            <div class="left grow min-w-0 pr-3 space-y-1">
                                <div class="customer text-lg leading-none capitalize font-medium text-nowrap">M/s: ${customerData.customer_name}</div>
                                <div class="person text-md text-lg leading-none">${customerTitlePhoneLine(customerData)}</div>
                                <div class="address text-md leading-none">${customerData.address}, ${customerData.city.title}</div>
                                <div class="phone text-md leading-none">${deliverToLine()}</div>
                            </div>
                            <div class="right shrink-0 min-w-[38%] my-auto text-right text-sm text-gray-600 space-y-1.5">
                                <div class="date leading-none">Date: ${orderDate}</div>
                                <input type="hidden" name="order_no" value="${orderNo}" />
                                <div class="preview-copy leading-none">Order Copy: Customer</div>
                            </div>
                        </div>
                        <hr class="w-full my-3 border-gray-600">
                        <div id="body" class="body w-[95%] grow mx-auto">
                            <div class="order-table w-full">
                                <div class="table w-full border border-gray-600 rounded-lg pb-2.5 overflow-hidden">
                                    <div class="thead w-full">
                                        <div class="tr grid grid-cols-9 w-full px-4 py-1.5 bg-[var(--primary-color)] text-white">
                                            <div class="th text-sm font-medium">S.#</div>
                                            <div class="th text-sm font-medium">Article</div>
                                            <div class="th text-sm font-medium">Description</div>
                                            <div class="th text-sm font-medium">Unit</div>
                                            <div class="th text-sm font-medium">Pkts</div>
                                            <div class="th text-sm font-medium">Pcs.</div>
                                            <div class="th text-sm font-medium">Rate</div>
                                            <div class="th text-sm font-medium">Amount</div>
                                            <div class="th text-sm font-medium text-center">Dispatch</div>
                                        </div>
                                    </div>
                                    <div id="tbody" class="tbody w-full">
                                        ${bodyRows}
                                    </div>
                                </div>
                            </div>
                        </div>
                        ${isLastPage ? `<hr class="w-full my-3 border-gray-600">${orderDocumentTotalsHtml()}` : ''}
                        <hr class="w-full my-3 border-gray-600">
                        <div class="tfooter flex w-full text-sm px-4 justify-between text-gray-600">
                            <P class="leading-none">Powered by SparkPair</P>
                            <p class="leading-none text-sm">Page ${pageIndex + 1} of ${pages.length}</p>
                            <p class="leading-none text-sm">Printed: ${printDateTime()}</p>
                            <p class="leading-none text-sm">&copy; ${new Date().getFullYear()} SparkPair | +92 316 5825495</p>
                        </div>
                    </div>
                    </div>
                `;
            }).join('');
        } else {
            previewContainer.className = 'w-[148mm] h-[210mm] mx-auto overflow-hidden relative';
            previewContainer.innerHTML =
                '<div id="preview" class="preview w-[148mm] h-[210mm] gos-a5-document gos-a5-invoice overflow-hidden flex flex-col"><h1 class="text-[var(--border-error)] font-medium text-center mt-5">No Preview avalaible.</h1></div>';
        }
    };

    window.getDataByDate = function getDataByDate(inputElem) {
        $.ajax({
            url: window.__ordersGenerate?.ordersCreateUrl || '',
            method: 'GET',
            data: {
                date: inputElem.value,
            },
            success: function (response) {
                populateOptions(response.customers_options);
                articles = response.articles || [];
                if (response.next_order_no) {
                    window.__ordersGenerate.nextOrderNo = response.next_order_no;
                }
                applyDefaultOrderDiscount(response.default_order_discount_percent);
            },
            error: function () {
                appAlert('Error submitting form');
            },
        });
    };

    function populateOptions(customers_options) {
        const customerSelectDomLocal = document.getElementById('customer_id');
        if (!customerSelectDomLocal) return;
        customerSelectDomLocal.disabled = false;
        customerSelectDomLocal.value = '-- Select Customer --';
        const dropdownUl = customerSelectDomLocal.parentElement.parentElement.parentElement.querySelector('ul');
        dropdownUl.innerHTML = '';
        let optionsHTML =
            '<li data-for="customer_id" data-value="" onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg transition hover:bg-[var(--h-bg-color)] text-nowrap overflow-x-auto scrollbar-hidden selected">-- Select Customer --</li>';
        customers_options.forEach(customer => {
            optionsHTML += `
                    <li data-for="customer_id" data-value="${customer.data_option.id}" onmousedown="selectThisOption(this)" data-option='${jsonAttr(customer.data_option)}' class="py-2 px-3 cursor-pointer rounded-lg transition hover:bg-[var(--h-bg-color)] text-nowrap overflow-x-auto scrollbar-hidden">${customer.text}</li>
                `;
        });
        dropdownUl.innerHTML = optionsHTML;
    }

    window.validateForNextStep = function validateForNextStep() {
        generateOrder();
        return true;
    };

    function addListenerToPrintAndSaveBtn() {
        const printAndSaveBtn = document.getElementById('printAndSaveBtn');
        if (!printAndSaveBtn) return;

        printAndSaveBtn.addEventListener('click', event => {
            event.preventDefault();
            closeAllDropdowns();

            if (typeof validateForNextStep === 'function' && validateForNextStep() === false) {
                return;
            }

            const form = document.getElementById('form');
            const preview = document.getElementById('preview-container');
            if (!form || !preview) return;

            const oldIframe = document.getElementById('printIframe');
            if (oldIframe) oldIframe.remove();

            const printIframe = document.createElement('iframe');
            printIframe.id = 'printIframe';
            printIframe.style.position = 'absolute';
            printIframe.style.width = '0px';
            printIframe.style.height = '0px';
            printIframe.style.border = 'none';
            printIframe.style.display = 'none';
            document.body.appendChild(printIframe);

            const printDocument = printIframe.contentDocument || printIframe.contentWindow.document;
            printDocument.open();
            printDocument.write(`
                <html>
                    <head>
                        <title>Print Order</title>
                        ${document.head.innerHTML}
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
                                    page-break-inside: avoid;
                                }

                                #preview-container .preview:last-child {
                                    break-after: auto;
                                    page-break-after: auto;
                                }
                            }
                        </style>
                    </head>
                    <body>
                        <div id="preview-container" class="preview-container">${preview.innerHTML}</div>
                    </body>
                </html>
            `);
            printDocument.close();

            printIframe.onload = () => {
                const orderCopy = printDocument.querySelector('#preview-container .preview-copy');
                if (orderCopy) orderCopy.textContent = 'Order Copy: Office';

                printIframe.contentWindow.onafterprint = () => form.submit();

                setTimeout(() => {
                    printIframe.contentWindow.focus();
                    printIframe.contentWindow.print();
                }, 1000);
            };
        });
    }

    window.reRenderSelectedState = function reRenderSelectedState() {
        const selectedIds = selectedArticles.map(card => card.id);

        document.querySelectorAll('.card_container .card').forEach(card => {
            const cardData = JSON.parse(card.getAttribute('data-json'));

            if (selectedIds.includes(cardData.id)) {
                const selectedCard = selectedArticles.find(item => item.id === cardData.id);

                card.innerHTML += `
                        <div class="quantity-label absolute text-xs text-[var(--border-success)] top-1 right-2 h-[1rem]">
                            ${selectedCard.orderedQuantity} Pcs
                        </div>
                    `;
            }
        });
    };

    window.reRenderSelectedStateTotal = function reRenderSelectedStateTotal() {
        totalQuantityDOM = document.getElementById('totalOrderedQty');
        totalAmountDOM = document.getElementById('totalOrderAmount');
        renderTotals();
    };

    function initOrdersGenerate(data) {
        lastOrder = data?.lastOrder || null;
        companyData = data?.companyData || null;
        applyDefaultOrderDiscount(data?.defaultOrderDiscountPercent);
        renderList();
        addListenerToPrintAndSaveBtn();
    }

    window.initOrdersGenerate = initOrdersGenerate;

    function boot() {
        if (window.__ordersGenerate) {
            initOrdersGenerate(window.__ordersGenerate);
        }
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", boot);
    } else {
        boot();
    }
})();
