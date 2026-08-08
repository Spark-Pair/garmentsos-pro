(function () {
    let order = null;
    let maxLimitOfArticles = 500;
    let limitOfArticles = 500;
    let selectedArticles = [];
    let totalOrderedQuantity = 0;
    let totalOrderAmount = 0;
    let netAmount = 0;
    let articles = [];

    let customerData;
    const customerSelectDom = document.getElementById('customer_id');
    const generateOrderBtn = document.getElementById('generateOrderBtn');

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

    function canChangeCustomer() {
        return window.__ordersEdit?.canChangeCustomer === true;
    }

    function showOrderEditError(message) {
        if (typeof appAlert === 'function') {
            appAlert(message, 'error');
            return;
        }

        if (typeof messageBox === 'undefined') return;
        messageBox.innerHTML = `<div class="alert-message bg-[var(--bg-error)] text-[var(--text-error)] ps-2 pe-5 py-2 rounded-2xl flex items-center gap-2 fade-in leading-none tracking-wide"><i class="fas fa-circle-exclamation text-lg mr-1"></i><p>${message}</p></div>`;
        messageBoxAnimation();
    }

    function orderDateForRequest(inputElem = null) {
        const explicitValue = String(inputElem?.value || '').trim();
        if (explicitValue && !inputElem?.disabled && /^\d{4}-\d{2}-\d{2}$/.test(explicitValue)) {
            return explicitValue;
        }

        const orderDateValue = String(order?.date || '').trim();
        if (/^\d{4}-\d{2}-\d{2}$/.test(orderDateValue)) {
            return orderDateValue;
        }

        const dateInputValue = String(document.getElementById('date')?.value || '').trim();
        if (/^\d{4}-\d{2}-\d{2}$/.test(dateInputValue)) {
            return dateInputValue;
        }

        return orderDateValue || dateInputValue;
    }

    function mergeSelectedArticlesIntoModalData(availableArticles = []) {
        const merged = (availableArticles || [])
            .filter(article => Number(article?.orderable_quantity || 0) > 0);
        const existingIds = new Set(merged.map(article => String(article?.id ?? '')));

        selectedArticles.forEach(selectedArticle => {
            const selectedId = String(selectedArticle?.id ?? selectedArticle?.article_id ?? '');
            if (!selectedId || existingIds.has(selectedId)) return;

            const orderedPcs = Number(selectedArticle?.ordered_pcs || 0);
            const pcsPerPacket = Number(selectedArticle?.pcs_per_packet || 0);
            merged.push({
                ...selectedArticle,
                id: selectedArticle.id ?? selectedArticle.article_id,
                orderable_quantity: Math.max(Number(selectedArticle?.orderable_quantity || 0), orderedPcs),
                orderable_quantity_packets: pcsPerPacket ? Math.floor(Math.max(Number(selectedArticle?.orderable_quantity || 0), orderedPcs) / pcsPerPacket) : 0,
                current_stock: Math.max(Number(selectedArticle?.current_stock || 0), orderedPcs),
                current_stock_packets: pcsPerPacket ? Math.floor(Math.max(Number(selectedArticle?.current_stock || 0), orderedPcs) / pcsPerPacket) : 0,
            });
            existingIds.add(selectedId);
        });

        return merged;
    }

    function deliverToLine() {
        const deliverTo = String(document.getElementById('deliver_to')?.value ?? order?.deliver_to ?? '').trim();
        return deliverTo ? `Deliver To: ${deliverTo}` : '';
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

    function articlePacketCount(pcs, pcsPerPacket) {
        const quantity = Number(pcs || 0);
        const unit = Number(pcsPerPacket || 0);
        return unit > 0 ? Math.floor(quantity / unit) : 0;
    }

    function editableArticleQuantityState(data = {}) {
        const selectedArticle = selectedArticles.find(article => article.id == data.id);
        const selectedQuantity = Number(selectedArticle?.ordered_pcs || 0);
        const invoicedQuantity = Number(selectedArticle?.dispatched_pcs || data.dispatched_pcs || 0);
        const pcsPerPacket = Number(data.pcs_per_packet || selectedArticle?.pcs_per_packet || 0);
        const maxOrderQuantity = Math.max(
            Number(data.orderable_quantity || 0),
            selectedQuantity,
            invoicedQuantity
        );

        return {
            selectedArticle,
            selectedQuantity,
            invoicedQuantity,
            pcsPerPacket,
            maxOrderQuantity,
            maxOrderPackets: articlePacketCount(maxOrderQuantity, pcsPerPacket),
            stockPackets: articlePacketCount(data.current_stock, pcsPerPacket),
        };
    }

    function orderQuantitySummary() {
        const pcs = parseFormattedNumber(totalQuantityDOM?.value || totalOrderedQuantity || 0);
        const packets = selectedArticles.reduce((sum, article) => {
            const qty = parseFormattedNumber(article?.ordered_pcs || 0);
            const unit = parseFormattedNumber(article?.pcs_per_packet || 0);
            return sum + (unit ? Math.floor(qty / unit) : 0);
        }, 0);

        return `${formatNumbersDigitLess(packets)} | ${formatNumbersDigitLess(pcs)}`;
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
                            <div class="w-1/4 text-right grow font-semibold">${totalAmountDOM?.value || 0}</div>
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
                        <div class="w-1/4 text-right grow">${totalAmountDOM?.value || 0}</div>
                    </div>
                </div>
                <div id="order-total" class="tr flex justify-between w-full px-2 gap-2 text-sm">
                    <div class="total flex justify-between items-center border border-gray-600 rounded-lg py-2 px-4 w-full">
                        <div class="text-nowrap">Discount %</div>
                        <div class="w-1/4 text-right grow">${discountDOM?.value || 0}</div>
                    </div>
                    <div class="total flex justify-between items-center border border-gray-600 rounded-lg py-2 px-4 w-full">
                        <div class="text-nowrap">Net Amount</div>
                        <div class="w-1/4 text-right grow">${finalNetAmount?.value || 0}</div>
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

            try {
                customerData = JSON.parse(customerDataDom || '{}') || order.customer;
            } catch (_) {
                customerData = order?.customer || null;
            }
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
        applySelectedArticleLabels();
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

    window.generateArticlesModal = function generateArticlesModal() {
        const data = mergeSelectedArticlesIntoModalData(articles || []);

        cardData = data.map(item => {
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
        });

        const modalData = {
            id: 'modalForm',
            class: 'h-[80%] w-full',
            cards: { name: 'Articles', count: 3, data: cardData },
            basicSearch: true,
            onBasicSearch: 'basicSearch(this.value)',
            info: `Selected: ${selectedArticles.length}/${maxLimitOfArticles}`,
            flex_col: true,
            calcBottom: [
                { label: 'Total Quantity - Pcs', name: 'totalOrderQty', value: '0', disabled: true },
                { label: 'Total Amount - Rs.', name: 'totalOrderAmount', value: '0.0', disabled: true },
            ],
        };

        createModal(modalData);

        totalQuantityDOM = document.querySelector('#modalForm #totalOrderQty');
        totalAmountDOM = document.querySelector('#modalForm #totalOrderAmount');

        applySelectedArticleLabels();

        calculateTotalOrderedQuantity();
        calculateTotalOrderAmount();
        calculateNetAmount();
        renderTotals();
        generateDescription();
        renderList();
        generateOrder();
        renderFinals();
    };

    function applySelectedArticleLabels() {
        document.querySelectorAll('#modalForm .card .quantity-label').forEach(previousQuantityLabel => {
            previousQuantityLabel.remove();
        });

        selectedArticles.forEach(selectedArticle => {
            const card = document.getElementById(selectedArticle.id);
            if (!card) return;

            const invoicedPcs = Number(selectedArticle.dispatched_pcs || 0);
            const invoicedText = invoicedPcs > 0 ? ` | Inv ${formatNumbersDigitLess(invoicedPcs)}` : '';
            card.innerHTML += `
                <div class="quantity-label absolute text-xs text-[var(--border-success)] top-1 right-2 min-h-[1rem] rounded-md bg-[var(--secondary-bg-color)]/90 px-1.5 py-0.5">
                    ${formatNumbersDigitLess(selectedArticle.ordered_pcs)} Pcs${invoicedText}
                </div>
            `;
        });
    }

    window.generateQuantityModal = function generateQuantityModal(elem) {
        const data = JSON.parse(elem.dataset.json).data;
        const alreadySelected = isArticleAlreadySelected(data.id);
        const quantityState = editableArticleQuantityState(data);
        const maxOrderQuantity = quantityState.maxOrderQuantity;

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
                        value: `${formatNumbersDigitLess(maxOrderQuantity)} Pcs | ${formatNumbersWithDigits(quantityState.maxOrderPackets)} Pkts`,
                        disabled: true,
                        full: true,
                    },
                    {
                        category: 'input',
                        label: 'Invoiceable Quantity (Current Stock)',
                        value: `${formatNumbersDigitLess(data.current_stock)} Pcs | ${formatNumbersWithDigits(quantityState.stockPackets)} Pkts`,
                        disabled: true,
                        full: true,
                    },
                    {
                        category: 'input',
                        label: 'Unit',
                        value: `${formatNumbersDigitLess(data.pcs_per_packet)} Pcs per Packet`,
                        disabled: true,
                        full: true,
                    },{
                        category: 'input',
                        name: 'quantity',
                        id: 'quantity',
                        type: 'number',
                        label: 'Quantity - Pcs.',
                        placeholder: 'Enter quantity in pcs.',
                        max: maxOrderQuantity,
                        required: true,
                        oninput: `
                            if (+this.value > ${Number(maxOrderQuantity || 0)}) {
                                this.value = ${Number(maxOrderQuantity || 0)};
                            }
                            syncArticleQuantityPair('pcs', ${Number(data.pcs_per_packet || 0)}, ${Number(maxOrderQuantity || 0)});
                        `,
                    },
                    {
                        category: 'input',
                        name: 'quantity_packets',
                        id: 'quantity_packets',
                        type: 'number',
                        label: 'Quantity - Pckts.',
                        placeholder: 'Enter packets.',
                        min: 0,
                        max: Number(data.pcs_per_packet || 0)
                            ? Math.floor(Number(maxOrderQuantity || 0) / Number(data.pcs_per_packet || 0))
                            : 0,
                        required: true,
                        oninput: `
                            if (+this.value > ${Number(data.pcs_per_packet || 0)
                                ? Math.floor(Number(maxOrderQuantity || 0) / Number(data.pcs_per_packet || 0))
                                : 0}) {
                                this.value = ${Number(data.pcs_per_packet || 0)
                                    ? Math.floor(Number(maxOrderQuantity || 0) / Number(data.pcs_per_packet || 0))
                                    : 0};
                            }
                            syncArticleQuantityPair('packets', ${Number(data.pcs_per_packet || 0)}, ${Number(maxOrderQuantity || 0)});
                        `,
                    },
                ],
                fieldsGridCount: '2',
                bottomActions: [{ id: 'setQuantityBtn', text: 'Set Quantity', onclick: `setQuantity(${data.id})` }],
            };

            createModal(modalData);

            initializeArticleQuantityPair(data.pcs_per_packet, maxOrderQuantity, quantityState.selectedQuantity);

            document.getElementById('quantity').focus();
            ['quantity', 'quantity_packets'].forEach(inputId => {
                document.getElementById(inputId)?.addEventListener('keydown', e => {
                    if (e.key === 'Enter') {
                        document.getElementById('setQuantityBtn-in-modal')?.click();
                    }
                });
            });
        } else if (typeof messageBox !== 'undefined') {
            messageBox.innerHTML = window.__ordersEdit?.maxArticlesAlertHtml || '';
            messageBoxAnimation();
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
            const quantityState = editableArticleQuantityState(cardData);
            const invoicedQuantity = quantityState.invoicedQuantity;
            const maxOrderQuantity = quantityState.maxOrderQuantity;
            if (!syncArticleQuantityPair('pcs', cardData.pcs_per_packet, maxOrderQuantity)) {
                quantityInputDOM?.focus();
                return;
            }

            closeModal('QuantityModalForm');
            const quantity = Number(quantityInputDOM.value || 0);
            const quantityLabel = targetCard.querySelector('.quantity-label');

            if (invoicedQuantity > 0 && quantity < invoicedQuantity) {
                showOrderEditError(`This article already has invoice quantity ${invoicedQuantity} pcs. Edit invoice first, then reduce order quantity.`);
                return;
            }

            if (quantity > 0) {
                cardData.ordered_pcs = parseInt(quantity);

                if (alreadySelectedArticle.length > 0) {
                    alreadySelectedArticle[0].ordered_pcs = parseInt(quantity);
                } else {
                    selectedArticles.push(cardData);
                }
                applySelectedArticleLabels();
            } else if (quantityLabel) {
                if (invoicedQuantity > 0) {
                    showOrderEditError(`This article already has invoice quantity ${invoicedQuantity} pcs. Edit invoice first, then remove it from order.`);
                    return;
                }
                quantityLabel.remove();
                const index = selectedArticles.findIndex(c => c.id === cardData.id);
                deselectArticleAtIndex(index);
                applySelectedArticleLabels();
            }

            generateDescription();
            calculateTotalOrderedQuantity();
            calculateTotalOrderAmount();
            calculateNetAmount();
            renderTotals();
            renderList();
            renderFinals();
        } else if (typeof messageBox !== 'undefined') {
            messageBox.innerHTML = window.__ordersEdit?.maxArticlesAlertHtml || '';
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
            const selectedArticle = selectedArticles[index];
            const invoicedQuantity = Number(selectedArticle?.dispatched_pcs || 0);
            if (invoicedQuantity > 0) {
                showOrderEditError(`This article already has invoice quantity ${invoicedQuantity} pcs. Edit invoice first, then remove it from order.`);
                return false;
            }
            selectedArticles.splice(index, 1);
        }

        return true;
    }

    window.deselectThisArticle = function deselectThisArticle(index) {
        if (!deselectArticleAtIndex(index)) {
            return;
        }

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
            totalOrderedQuantity += selectedArticle.ordered_pcs;
        });

        totalOrderedQuantity = new Intl.NumberFormat('en-US').format(totalOrderedQuantity);
    }

    function calculateTotalOrderAmount() {
        totalOrderAmount = 0;

        selectedArticles.forEach(selectedArticle => {
            totalOrderAmount += selectedArticle.ordered_pcs * selectedArticle.sales_rate;
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
        const discount = document.getElementById('discount').value;
        const discountAmount = totalAmount - totalAmount * (discount / 100);
        netAmount = discountAmount;
        renderFinals();
    }

    if (discountDOM) {
        discountDOM.addEventListener('input', calculateNetAmount);
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
            selectedArticles.forEach((selectedArticle, index) => {
                clutter += `
                        <div class="flex justify-between items-center border-t border-gray-600 py-3 px-4">
                            <div class="w-[10%]">${selectedArticle.article_no}</div>
                            <div class="w-1/6">${selectedArticle.ordered_pcs} pcs</div>
                            <div class="grow capitalize">${selectedArticle.description}</div>
                            <div class="w-1/6">${formatNumbersWithDigits(selectedArticle.sales_rate, 1, 1)}</div>
                            <div class="w-1/5">${formatNumbersWithDigits(selectedArticle.sales_rate * selectedArticle.ordered_pcs, 1, 1)}</div>
                            <div class="w-[10%] text-center">
                                <button onclick="deselectThisArticle(${index})" type="button" class="text-[var(--danger-color)] text-xs px-2 py-1 rounded-lg hover:text-[var(--h-danger-color)] transition-all duration-300 ease-in-out cursor-pointer">
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
        const finalArticlesArray = selectedArticles.map(article => {
            return {
                id: article.id,
                description: article.description,
                ordered_pcs: article.ordered_pcs,
            };
        });
        if (inputOrderedArticles) {
            inputOrderedArticles.value = JSON.stringify(finalArticlesArray);
        }
    }

    let companyData;
    let orderDate;
    const previewDom = document.getElementById('preview');

    function getOrderDate() {
        const dateValue = document.getElementById('date')?.value || order?.date;
        const date = new Date(dateValue);
        if (Number.isNaN(date.getTime())) return order?.date || '';

        const day = String(date.getDate()).padStart(2, '0');
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const year = date.getFullYear();
        const dayOfWeek = date.getDay();
        const weekDays = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

        return `${day}-${month}-${year}, ${weekDays[dayOfWeek]}`;
    }

    function generateOrder() {
        orderDate = getOrderDate();

        if (!previewDom) return;
        if (selectedArticles.length > 0) {
            previewDom.innerHTML = `
                    <div id="order" class="order flex flex-col h-full">
                        <div id="banner" class="banner w-full flex justify-between items-center px-5">
                            <div class="left">
                                <div class="logo">
                                    <img src="${companyData.logo_url || `${window.__ordersEdit.companyLogoBase}/${companyData.logo}`}" alt="garmentsos-pro"
                                        class="w-[12rem]" />
                                    <div class='mt-1'>${companyData.phone_number || companyData.phone || ""}</div>
                                </div>
                            </div>
                            <div class="logo text-right">
                                <h1 class="text-2xl font-medium text-[var(--h-primary-color)]">Sales Order</h1>
                                <div class="mt-1 text-right">Order No.: ${order.order_no}</div>
                            </div>
                        </div>
                        <hr class="w-full my-3 border-gray-600">
                        <div id="header" class="header w-full flex justify-between px-5">
                            <div class="left w-50 space-y-1">
                                <div class="customer text-lg leading-none capitalize font-medium text-nowrap">M/s: ${customerData.customer_name}</div>
                                <div class="person text-md text-lg leading-none">${customerTitlePhoneLine(customerData)}</div>
                                <div class="address text-md leading-none">${customerData.address}, ${customerData.city.title}</div>
                                <div class="phone text-md leading-none">${deliverToLine()}</div>
                            </div>
                            <div class="right w-50 my-auto text-right text-sm text-gray-600 space-y-1.5">
                                <div class="date leading-none">Date: ${orderDate}</div>
                                <input type="hidden" name="order_no" value="${order.order_no}" />
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
                                            <div class="th text-sm font-medium">Amt.</div>
                                            <div class="th text-sm font-medium text-center">Dispatch</div>
                                        </div>
                                    </div>
                                    <div id="tbody" class="tbody w-full">
                                        ${selectedArticles
                                            .map((article, index) => {
                                                const hrClass = index === 0 ? 'mb-2.5' : 'my-2.5';
                                                const detailLine = orderDetailLine(article);
                                                const dispatched = orderDispatchText(article);
                                                return `
                                                    <div class="invoice-item-row">
                                                        <hr class="w-full ${hrClass} border-gray-600">
                                                        <div class="tr invoice-item-main grid grid-cols-9 justify-between w-full px-4 gap-0.5">
                                                            <div class="td text-sm font-semibold truncate">${String(index + 1).padStart(2, '0')}</div>
                                                            <div class="td invoice-article-cell text-sm font-semibold">
                                                                <div class="invoice-article-code">${article.article_no}</div>
                                                            </div>
                                                            <div class="td invoice-description-cell text-sm font-semibold">${detailLine}</div>
                                                            <div class="td text-sm font-semibold truncate">${article?.pcs_per_packet ?? 0}</div>
                                                            <div class="td text-sm font-semibold truncate">${article?.pcs_per_packet ? Math.floor(article.ordered_pcs / article.pcs_per_packet) : 0}</div>
                                                            <div class="td text-sm font-semibold truncate">${article.ordered_pcs}</div>
                                                            <div class="td text-sm font-semibold truncate">${formatNumbersDigitLess(article.sales_rate)}</div>
                                                            <div class="td text-sm font-semibold truncate">${formatNumbersDigitLess(parseFormattedNumber(article.sales_rate) * article.ordered_pcs)}</div>
                                                            <div class="td text-sm font-semibold text-center">${dispatched}</div>
                                                        </div>
                                                    </div>
                                                    `;
                                            })
                                            .join('')}
                                    </div>
                                </div>
                            </div>
                        </div>
                        <hr class="w-full my-3 border-gray-600">
                        ${orderDocumentTotalsHtml()}
                        <hr class="w-full my-3 border-gray-600">
                        <div class="tfooter flex w-full text-sm px-4 justify-between text-gray-600">
                            <P class="leading-none">Powered by SparkPair</P>
                            <p class="leading-none text-sm">&copy; ${new Date().getFullYear()} SparkPair | +92 316 5825495</p>
                        </div>
                    </div>
                `;
        } else {
            previewDom.innerHTML =
                '<h1 class="text-[var(--border-error)] font-medium text-center mt-5">No Preview avalaible.</h1>';
        }
    }

    window.getDataByDate = function getDataByDate(inputElem) {
        const dateValue = orderDateForRequest(inputElem);
        $.ajax({
            url: window.__ordersEdit?.ordersCreateUrl || '',
            method: 'GET',
            data: {
                date: dateValue,
                exclude_order_id: order?.id,
                include_customer_id: order?.customer?.id,
            },
            success: function (response) {
                articles = response.articles || [];
                cardData = [];
                if (canChangeCustomer()) {
                    populateOptions(response.customers_options || []);
                } else {
                    customerData = order?.customer || customerData;
                    renderList();
                    generateOrder();
                    renderFinals();
                }
                if (generateOrderBtn) generateOrderBtn.disabled = false;
            },
            error: function () {
                if (typeof messageBox !== 'undefined') {
                    messageBox.innerHTML = '<div class="bg-[var(--danger-color)]/10 border border-[var(--danger-color)] text-[var(--danger-color)] text-xs px-3 py-2 rounded-lg">Could not load order edit data. Please refresh and try again.</div>';
                    messageBoxAnimation();
                }
            },
        });
    };

    function populateOptions(customers_options) {
        if (!canChangeCustomer()) return;
        const customerSelectDomLocal = document.getElementById('customer_id');
        if (!customerSelectDomLocal) return;
        const selectedCustomerId = String(
            document.querySelector('.dbInput[data-for="customer_id"]')?.value
            || order?.customer?.id
            || ''
        );
        const hasSelectedCustomer = customers_options.some(customer => (
            String(customer?.data_option?.id ?? '') === selectedCustomerId
        ));

        if (!hasSelectedCustomer && order?.customer?.id) {
            customers_options.push({
                text: `${order.customer.customer_name || '-'} | ${order.customer.city?.title || '-'}`,
                data_option: order.customer,
            });
        }

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

        const selectedOption = dropdownUl.querySelector(`li[data-value="${CSS.escape(selectedCustomerId)}"]`)
            || dropdownUl.querySelector('li[data-value]:not([data-value=""])');
        if (selectedOption) {
            selectThisOption(selectedOption, { validate: false });
        }
    }

    window.validateForNextStep = function validateForNextStep() {
        generateOrder();
        return true;
    };

    window.reRenderSelectedState = function reRenderSelectedState() {
        applySelectedArticleLabels();
    };

    window.reRenderSelectedStateTotal = function reRenderSelectedStateTotal() {
        totalQuantityDOM = document.getElementById('totalOrderedQty');
        totalAmountDOM = document.getElementById('totalOrderAmount');
        renderTotals();
    };

    function initOrdersEdit(data) {
        order = data?.order || null;
        companyData = data?.companyData || null;
        customerData = order?.customer || null;

        if (order?.articles?.length) {
            selectedArticles = order.articles.map(item => ({
                ...item,
                ...(item.article || {}),
                id: item.article_id ?? item.id,
                ordered_pcs: Number(item.ordered_pcs || 0),
                dispatched_pcs: Number(item.dispatched_pcs || 0),
                sales_rate: parseFormattedNumber(item.article?.sales_rate ?? item.sales_rate),
                pcs_per_packet: Number(item.article?.pcs_per_packet ?? item.pcs_per_packet ?? 0),
                article_no: item.article?.article_no ?? item.article_no ?? '-',
            }));

            generateDescription();
            calculateTotalOrderedQuantity();
            calculateTotalOrderAmount();
            netAmount = orderDiscountDisabled() ? totalOrderAmount : Number(order.netAmount || 0);
            renderList();
            renderFinals();
            generateOrder();
        }

        getDataByDate();
        if (!selectedArticles.length) {
            renderList();
        }
    }

    window.initOrdersEdit = initOrdersEdit;

    function boot() {
        if (window.__ordersEdit) {
            initOrdersEdit(window.__ordersEdit);
        }
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", boot);
    } else {
        boot();
    }
})();
