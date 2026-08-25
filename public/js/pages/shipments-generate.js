(function () {
    let maxLimitOfArticles = 500;
    let limitOfArticles = 500;
    let selectedArticles = [];
    let totalShipmentQuantity = 0;
    let totalShipmentAmount = 0;
    let netAmount = 0;
    let articles = [];

    let lastShipment;
    let companyData;

    const generateShipmentBtn = document.getElementById('generateShipmentBtn');

    function isArticleAlreadySelected(articleId) {
        return selectedArticles.some(a => a.id == articleId);
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

    window.trackStateOfgenerateBtn = function trackStateOfgenerateBtn(value) {
        if (generateShipmentBtn) {
            generateShipmentBtn.disabled = value == '';
        }
    };

    let totalQuantityDOM;
    let totalAmountDOM;
    let cardData = [];

    window.basicSearch = function basicSearch(searchValue) {
        const modalData = {
            id: 'modalForm',
            cards: { data: cardData.filter(item => item.name.toLowerCase().includes(searchValue.toLowerCase())) },
        };
        renderCardsInModal(modalData);
    };

    if (generateShipmentBtn) {
        generateShipmentBtn.disabled = true;
        generateShipmentBtn.addEventListener('click', () => {
            generateArticlesModal();
        });
    }

    window.generateArticlesModal = function generateArticlesModal() {
        const data = [...(articles || [])].sort((left, right) => (
            articleSortValue(left).localeCompare(articleSortValue(right), undefined, {
                numeric: true,
                sensitivity: 'base',
            })
        ));

        if (data.length > 0) {
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
        } else {
            cardData = [];
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

        calculateNetAmount();
        calculateTotalShipmentQuantity();
        calculateTotalShipmentAmount();
        renderTotals();
        generateDescription();
        renderList();
        generateShipment();
        renderFinals();

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
                                ${selectedArticle.shipmentQuantity} Pcs
                            </div>
                        `;
                } else if (quantityLabelDom) {
                    quantityLabelDom.textContent = `${selectedArticle.shipmentQuantity} Pcs`;
                }
            });
        }
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
                        placeholder: 'Enter quantity in pcs.',
                        max: Number(data.orderable_quantity || 0),
                        required: true,
                        oninput: `syncArticleQuantityPair('pcs', ${Number(data.pcs_per_packet || 0)}, ${Number(data.orderable_quantity || 0)})`,
                    },
                    {
                        category: 'input',
                        name: 'quantity_packets',
                        id: 'quantity_packets',
                        type: 'number',
                        label: 'Quantity - Pckts.',
                        placeholder: 'Enter packets.',
                        min: 0,
                        max: Number(data.pcs_per_packet || 0) ? Math.floor(Number(data.orderable_quantity || 0) / Number(data.pcs_per_packet || 0)) : 0,
                        required: true,
                        oninput: `syncArticleQuantityPair('packets', ${Number(data.pcs_per_packet || 0)}, ${Number(data.orderable_quantity || 0)})`,
                    },
                ],
                fieldsGridCount: '2',
                bottomActions: [{ id: 'setQuantityBtn', text: 'Set Quantity', onclick: `setQuantity(${data.id})` }],
            };

            createModal(modalData);

            const quantityLabel = elem.querySelector('.quantity-label');
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
        } else if (typeof messageBox !== 'undefined') {
            messageBox.innerHTML = window.__shipmentsGenerate?.maxArticlesAlertHtml || '';
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
                            <div
                                class="quantity-label absolute text-xs text-[var(--border-success)] top-1 right-2 h-[1rem]">
                                ${quantity} Pcs
                            </div>
                        `;
                }

                cardData.shipmentQuantity = parseInt(quantity);

                if (alreadySelectedArticle.length > 0) {
                    alreadySelectedArticle[0].shipmentQuantity = parseInt(quantity);
                } else {
                    selectedArticles.push(cardData);
                }
            } else if (quantityLabel) {
                quantityLabel.remove();
                const index = selectedArticles.findIndex(c => c.id === cardData.id);
                deselectArticleAtIndex(index);
            }

            generateDescription();
            calculateTotalShipmentQuantity();
            calculateTotalShipmentAmount();
            calculateNetAmount();
            renderTotals();
            renderList();
        } else if (typeof messageBox !== 'undefined') {
            messageBox.innerHTML = window.__shipmentsGenerate?.maxArticlesAlertHtml || '';
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
        generateShipment();

        calculateTotalShipmentQuantity();
        calculateTotalShipmentAmount();
        calculateNetAmount();

        renderFinals();
        renderTotals();
    };

    const finalShipmentQuantity = document.getElementById('finalShipmentQuantity');
    const finalShipmentAmount = document.getElementById('finalShipmentAmount');
    const discountDOM = document.getElementById('discount');
    const finalNetAmount = document.getElementById('finalNetAmount');

    function calculateTotalShipmentQuantity() {
        totalShipmentQuantity = 0;

        selectedArticles.forEach(selectedArticle => {
            totalShipmentQuantity += selectedArticle.shipmentQuantity;
        });

        totalShipmentQuantity = formatNumbersWithDigits(totalShipmentQuantity);
    }

    function calculateTotalShipmentAmount() {
        totalShipmentAmount = 0;

        selectedArticles.forEach(selectedArticle => {
            totalShipmentAmount += selectedArticle.shipmentQuantity * selectedArticle.sales_rate;
        });
    }

    function generateDescription() {
        selectedArticles.forEach(selectedArticle => {
            selectedArticle.description = `${selectedArticle.size} | ${selectedArticle.category.replace(/_/g, ' ')} | ${selectedArticle.season}`;
        });
    }

    function calculateNetAmount() {
        const totalAmount = parseFloat(totalShipmentAmount);
        let discount = parseFloat(document.getElementById('discount').value || 0);
        if (Number.isNaN(discount)) discount = 0;
        discount = Math.max(0, Math.min(100, discount));
        if (discountDOM) discountDOM.value = discount;
        const discountAmount = totalAmount - totalAmount * (discount / 100);
        netAmount = discountAmount;
        netAmount = formatMoney(netAmount);
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
        totalQuantityDOM.value = totalShipmentQuantity;
        totalAmountDOM.value = totalShipmentAmount;
    }

    const orderListDOM = document.getElementById('shipment-list');

    function renderList() {
        if (!orderListDOM) return;
        if (selectedArticles.length > 0) {
            let clutter = '';
            sortedSelectedArticles().forEach((selectedArticle) => {
                const selectedIndex = selectedArticles.findIndex(article => article.id == selectedArticle.id);
                clutter += `
                        <div class="flex justify-between items-center border-t border-gray-600 py-3 px-4">
                            <div class="w-[10%]">${selectedArticle.article_no}</div>
                            <div class="w-1/6">${selectedArticle.shipmentQuantity} pcs</div>
                            <div class="grow capitalize">${selectedArticle.description}</div>
                            <div class="w-1/6">${formatMoney(selectedArticle.sales_rate)}</div>
                            <div class="w-1/5">${formatMoney(selectedArticle.sales_rate * selectedArticle.shipmentQuantity)}</div>
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
        updateInputShipmentedArticles();
        limitOfArticles = maxLimitOfArticles - selectedArticles.length;
        updateInfo();
    }

    function renderFinals() {
        if (!finalShipmentQuantity || !finalShipmentAmount || !finalNetAmount) return;
        finalShipmentQuantity.textContent = totalShipmentQuantity;
        finalShipmentAmount.textContent = totalShipmentAmount;
        finalNetAmount.value = netAmount;
    }

    function updateInputShipmentedArticles() {
        const inputShipmentedArticles = document.getElementById('articles');
        const finalArticlesArray = sortedSelectedArticles().map(article => {
            return {
                id: article.id,
                description: article.description,
                shipment_quantity: article.shipmentQuantity,
            };
        });
        if (inputShipmentedArticles) {
            inputShipmentedArticles.value = JSON.stringify(finalArticlesArray);
        }
    }

    let shipmentNo;
    let shipmentDate;
    const previewContainer = document.getElementById('preview-container');

    function generateShipmentNo() {
        return 'Assigned after save';
    }

    function getShipmentDate() {
        const dateDom = document.getElementById('date').value;
        const date = new Date(dateDom);
        const day = String(date.getDate()).padStart(2, '0');
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const year = date.getFullYear();
        const dayOfWeek = date.getDay();
        const weekDays = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

        return `${day}-${month}-${year}, ${weekDays[dayOfWeek]}`;
    }

    function getShipmentCityLabel() {
        const cityDom = document.getElementById('city');
        const selectedOption = cityDom?.options?.[cityDom.selectedIndex];
        return (selectedOption?.text || cityDom?.value || '').trim();
    }

    function shipmentDetailLine(article) {
        const description = String(article?.description ?? '').trim();
        const fabricType = String(article?.fabric_type ?? article?.article?.fabric_type ?? '').trim();
        const parts = [description, fabricType].filter((part, index, list) => (
            part && list.findIndex(item => item.toLowerCase() === part.toLowerCase()) === index
        ));

        return parts.join(' | ');
    }

    function totalShipmentPackets() {
        return selectedArticles.reduce((total, article) => {
            const pcsPerPacket = Number(article?.pcs_per_packet || 0);
            const qty = Number(article?.shipmentQuantity || 0);
            return total + (pcsPerPacket ? Math.floor(qty / pcsPerPacket) : 0);
        }, 0);
    }

    window.generateShipment = function generateShipment() {
        shipmentNo = generateShipmentNo();
        shipmentDate = getShipmentDate();

        if (!previewContainer) return;
        if (selectedArticles.length > 0) {
            previewContainer.className = 'h-auto mx-auto relative flex flex-col';
            previewContainer.innerHTML = window.DocumentPreview.render({
                preview: {
                    type: 'shipment',
                    size: 'A5',
                    document: 'Shipment',
                    data: {
                        shipment_no: shipmentNo,
                        date: document.getElementById('date')?.value,
                        city: getShipmentCityLabel(),
                        discount: discountDOM?.value || 0,
                        netAmount: parseFormattedNumber(finalNetAmount?.value ?? 0),
                        branch_branding: companyData,
                        articles: sortedSelectedArticles().map(article => ({
                            ...article,
                            shipment_pcs: Number(article.shipmentQuantity || 0),
                            description: shipmentDetailLine(article),
                        })),
                    },
                },
            }, {
                companyData,
                companyLogoBase: window.__shipmentsGenerate?.companyLogoBase,
            });
            previewContainer.insertAdjacentHTML('beforeend', `<input type="hidden" name="shipment_no" value="${shipmentNo}" />`);
        } else {
            previewContainer.className = 'w-[148mm] h-[210mm] mx-auto overflow-hidden relative';
            previewContainer.innerHTML =
                '<div id="preview" class="preview w-[148mm] h-[210mm] gos-a5-document gos-a5-invoice overflow-hidden flex flex-col"><h1 class="text-[var(--border-error)] font-medium text-center mt-5">No Preview avalaible.</h1></div>';
        }
    };

    window.getDataByDate = function getDataByDate(inputElem) {
        trackStateOfgenerateBtn(inputElem.value);
        $.ajax({
            url: window.__shipmentsGenerate?.shipmentsCreateUrl || '',
            method: 'GET',
            data: {
                date: inputElem.value,
            },
            success: function (response) {
                articles = response.articles || [];
                selectedArticles = [];
                totalShipmentQuantity = 0;
                totalShipmentAmount = 0;
                renderList();
                calculateTotalShipmentQuantity();
                calculateTotalShipmentAmount();
                renderTotals();

                const modal = document.getElementById('modalForm');
                if (modal) {
                    cardData = articles.map(item => ({
                        id: item.id,
                        name: item.article_no,
                        image: item.image == 'no_image_icon.png'
                            ? '/images/no_image_icon.png'
                            : `/storage/uploads/images/${item.image}`,
                        details: {
                            Category: item.category,
                            Season: item.season,
                            Size: item.size,
                        },
                        data: item,
                        onclick: 'generateQuantityModal(this)',
                    }));
                    renderCardsInModal({ id: 'modalForm', cards: { data: cardData } });
                    document.querySelectorAll('.card .quantity-label').forEach(label => label.remove());
                }
            },
            error: function () {
                appAlert('Error submitting form');
            },
        });
    };

    window.validateForNextStep = function validateForNextStep() {
        generateShipment();
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
            if (!form) return;
            let printAfterSave = form.querySelector('input[name="printAfterSave"]');
            if (!printAfterSave) {
                printAfterSave = document.createElement('input');
                printAfterSave.type = 'hidden';
                printAfterSave.name = 'printAfterSave';
                form.appendChild(printAfterSave);
            }
            printAfterSave.value = '1';
            form.requestSubmit();
        });
    }

    function initShipmentsGenerate(data) {
        lastShipment = data?.lastShipment || null;
        companyData = data?.companyData || null;
        renderList();
        addListenerToPrintAndSaveBtn();
    }

    window.initShipmentsGenerate = initShipmentsGenerate;

    function boot() {
        if (window.__shipmentsGenerate) {
            initShipmentsGenerate(window.__shipmentsGenerate);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
