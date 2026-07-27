(function () {
    let maxLimitOfArticles = 18;
    let limitOfArticles = 18;
    let shipment = null;
    let selectedArticles = [];
    let totalShipmentQuantity = 0;
    let totalShipmentAmount = 0;
    let netAmount = 0;
    let articles = [];

    let companyData;

    const generateShipmentBtn = document.getElementById('generateShipmentBtn');
    const finalShipmentQuantity = document.getElementById('finalShipmentQuantity');
    const finalShipmentAmount = document.getElementById('finalShipmentAmount');
    const discountDOM = document.getElementById('discount');
    const finalNetAmount = document.getElementById('finalNetAmount');

    let totalQuantityDOM;
    let totalAmountDOM;

    function isArticleAlreadySelected(articleId) {
        return selectedArticles.some(a => a.id == articleId);
    }

    window.generateArticlesModal = function generateArticlesModal() {
        const data = articles || [];
        let cardData = [];

        if (data.length > 0) {
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
        const selectedArticle = selectedArticles.find(article => article.id == data.id);
        const maxShipmentQuantity = Math.max(
            Number(data.orderable_quantity || 0),
            Number(selectedArticle?.shipmentQuantity || 0)
        );

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
                        value: `${formatNumbersDigitLess(maxShipmentQuantity)} Pcs | ${formatNumbersWithDigits(data.orderable_quantity_packets)} Pkts`,
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
                        max: maxShipmentQuantity,
                        required: true,
                        oninput: `syncArticleQuantityPair('pcs', ${Number(data.pcs_per_packet || 0)}, ${Number(maxShipmentQuantity || 0)})`,
                    },
                    {
                        category: 'input',
                        name: 'quantity_packets',
                        id: 'quantity_packets',
                        type: 'number',
                        label: 'Quantity - Pckts.',
                        placeholder: 'Enter packets.',
                        min: 0,
                        max: Number(data.pcs_per_packet || 0) ? Math.floor(Number(maxShipmentQuantity || 0) / Number(data.pcs_per_packet || 0)) : 0,
                        required: true,
                        oninput: `syncArticleQuantityPair('packets', ${Number(data.pcs_per_packet || 0)}, ${Number(maxShipmentQuantity || 0)})`,
                    },
                ],
                fieldsGridCount: '2',
                bottomActions: [{ id: 'setQuantityBtn', text: 'Set Quantity', onclick: `setQuantity(${data.id})` }],
            };

            createModal(modalData);

            const quantityLabel = elem.querySelector('.quantity-label');

            if (quantityLabel) {
                initializeArticleQuantityPair(data.pcs_per_packet, maxShipmentQuantity, parseInt(quantityLabel.textContent.replace(/\D/g, '')));
            }
            syncArticleQuantityPair('pcs', data.pcs_per_packet, maxShipmentQuantity);

            document.getElementById('quantity').focus();
            ['quantity', 'quantity_packets'].forEach(inputId => {
                document.getElementById(inputId)?.addEventListener('keydown', e => {
                    if (e.key === 'Enter') {
                        document.getElementById('setQuantityBtn-in-modal')?.click();
                    }
                });
            });
        } else if (typeof messageBox !== 'undefined') {
            messageBox.innerHTML = window.__shipmentsEdit?.maxArticlesAlertHtml || '';
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
            const selectedArticle = selectedArticles.find(article => article.id == cardData.id);
            const maxShipmentQuantity = Math.max(Number(cardData.orderable_quantity || 0), Number(selectedArticle?.shipmentQuantity || 0));
            if (!syncArticleQuantityPair('pcs', cardData.pcs_per_packet, maxShipmentQuantity)) {
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
            messageBox.innerHTML = window.__shipmentsEdit?.maxArticlesAlertHtml || '';
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
        const discount = document.getElementById('discount').value;
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
            selectedArticles.forEach((selectedArticle, index) => {
                clutter += `
                        <div class="flex justify-between items-center border-t border-gray-600 py-3 px-4">
                            <div class="w-[10%]">${selectedArticle.article_no}</div>
                            <div class="w-1/6">${selectedArticle.shipmentQuantity} pcs</div>
                            <div class="grow capitalize">${selectedArticle.description}</div>
                            <div class="w-1/6">${formatMoney(selectedArticle.sales_rate)}</div>
                            <div class="w-1/5">${formatMoney(selectedArticle.sales_rate * selectedArticle.shipmentQuantity)}</div>
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
        updateInputShipmentedArticles();
        limitOfArticles = maxLimitOfArticles - selectedArticles.length;
        updateInfo();
    }

    function renderFinals() {
        if (!finalShipmentQuantity || !finalShipmentAmount || !finalNetAmount) return;
        finalShipmentQuantity.textContent = totalShipmentQuantity;
        finalShipmentAmount.textContent = formatNumbersWithDigits(totalShipmentAmount, 1, 1);
        finalNetAmount.value = netAmount;
    }

    function updateInputShipmentedArticles() {
        const inputShipmentedArticles = document.getElementById('articles');
        const finalArticlesArray = selectedArticles.map(article => {
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
    const previewDom = document.getElementById('preview');

    function generateShipmentNo() {
        const shipmentNo = String(window.__shipmentsEdit?.shipmentNo ?? '').trim();
        return shipmentNo && !shipmentNo.includes('NaN') ? shipmentNo : 'Will be generated on save';
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

        if (!previewDom) return;
        if (selectedArticles.length > 0) {
            previewDom.innerHTML = `
                    <div id="shipment" class="shipment flex flex-col h-full">
                        <div id="banner" class="banner w-full flex justify-between items-center px-5">
                            <div class="left">
                                <div class="logo flex flex-col">
                                    <img src="${window.__shipmentsEdit.companyLogoBase}/${companyData.logo}" alt="garmentsos-pro"
                                        class="w-[12rem]" />
                                    <div class="mt-2 text-sm text-gray-600">${companyData.phone_number || ''}</div>
                                </div>
                            </div>
                            <div class="right">
                                <div class="logo text-right">
                                    <h1 class="text-2xl font-medium text-[var(--h-primary-color)]">Shipment</h1>
                                    <div class="mt-1 text-right">Shipment No.: ${shipmentNo}</div>
                                </div>
                            </div>
                        </div>
                        <hr class="w-full my-3 border-black">
                        <div id="header" class="header w-full flex justify-between px-5">
                            <div class="left w-50 space-y-1">
                                <div class="address text-md leading-none capitalize">City: ${getShipmentCityLabel()}</div>
                            </div>
                            <div class="right w-50 my-auto text-right text-sm text-black space-y-1.5">
                                <div class="date leading-none">Date: ${shipmentDate}</div>
                            </div>
                        </div>
                        <hr class="w-full my-3 border-black">
                        <div id="shipment-body" class="body w-full px-5 grow mx-auto">
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
                                            <div class="th text-sm font-medium">Amt.</div>
                                        </div>
                                    </div>
                                    <div id="tbody" class="tbody w-full">
                                        ${selectedArticles
                                            .map((article, index) => {
                                                const hrClass = index === 0 ? 'mb-2.5' : 'my-2.5';
                                                const packets = article.pcs_per_packet ? Math.floor(article.shipmentQuantity / article.pcs_per_packet) : 0;
                                                return `
                                                <div class="invoice-item-row">
                                                    <hr class="w-full ${hrClass} border-black">
                                                    <div class="tr invoice-item-main grid grid-cols-8 justify-between w-full px-4 gap-0.5">
                                                        <div class="td text-sm font-semibold truncate">${String(index + 1).padStart(2, '0')}</div>
                                                        <div class="td invoice-article-cell text-sm font-semibold">
                                                            <div class="invoice-article-code">${article.article_no}</div>
                                                        </div>
                                                        <div class="td invoice-description-cell text-sm font-semibold">${shipmentDetailLine(article)}</div>
                                                        <div class="td text-sm font-semibold truncate">${article.pcs_per_packet || 0}</div>
                                                        <div class="td text-sm font-semibold truncate">${formatNumbersDigitLess(packets)}</div>
                                                        <div class="td text-sm font-semibold truncate">${formatNumbersDigitLess(article.shipmentQuantity)}</div>
                                                        <div class="td text-sm font-semibold truncate">${formatNumbersDigitLess(article.sales_rate)}</div>
                                                        <div class="td text-sm font-semibold truncate">${formatNumbersDigitLess(parseFormattedNumber(article.sales_rate) * article.shipmentQuantity)}</div>
                                                    </div>
                                                </div>
                                            `;
                                            })
                                            .join('')}
                                    </div>
                                </div>
                            </div>
                        </div>
                        <hr class="w-full my-3 border-black">
                        <div class="grid grid-cols-2 gap-2 px-5">
                            <div class="total flex justify-between items-center border border-black rounded-lg py-2 px-4 w-full">
                                <div class="text-nowrap">Total Quantity</div>
                                <div class="w-1/4 text-right grow">${formatNumbersDigitLess(totalShipmentPackets())} | ${formatNumbersDigitLess(parseFormattedNumber(totalShipmentQuantity))}</div>
                            </div>
                            <div class="total flex justify-between items-center border border-black rounded-lg py-2 px-4 w-full">
                                <div class="text-nowrap">Gross Amount</div>
                                <div class="w-1/4 text-right grow">${formatNumbersWithDigits(totalShipmentAmount, 1, 1)}</div>
                            </div>
                            <div class="total flex justify-between items-center border border-black rounded-lg py-2 px-4 w-full">
                                <div class="text-nowrap">Discount ${discountDOM?.value || 0}%</div>
                                <div class="w-1/4 text-right grow">${formatNumbersWithDigits((totalShipmentAmount * Number(discountDOM?.value || 0)) / 100, 1, 1)}</div>
                            </div>
                            <div class="total flex justify-between items-center border border-black rounded-lg py-2 px-4 w-full">
                                <div class="text-nowrap">Net Amount</div>
                                <div class="w-1/4 text-right grow">${finalNetAmount.value}</div>
                            </div>
                        </div>
                        <hr class="w-full my-3 border-black">
                        <div class="footer flex w-full text-sm px-5 justify-between text-black">
                            <p class="leading-none">Powered by SparkPair</p>
                            <p class="leading-none text-sm">&copy; ${new Date().getFullYear()} SparkPair | +92 316 5825495</p>
                        </div>
                    </div>
                `;
        } else {
            previewDom.innerHTML =
                '<h1 class="text-[var(--border-error)] font-medium text-center mt-5">No Preview avalaible.</h1>';
        }
    };

    window.getDataByDate = function getDataByDate(inputElem) {
        $.ajax({
            url: window.__shipmentsEdit?.shipmentsCreateUrl || '',
            method: 'GET',
            data: {
                date: inputElem.value,
            },
            success: function (response) {
                articles = response.articles || [];
                selectedArticles.forEach(selected => {
                    if (!articles.some(article => article.id == selected.id)) {
                        articles.push({ ...selected });
                    }
                });

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

    function initShipmentsEdit(data) {
        shipment = data?.shipment || null;
        companyData = data?.companyData || null;
        selectedArticles = (shipment?.articles ?? []).map(a => ({
            ...a.article,
            shipmentQuantity: a.shipment_pcs,
            description: a.description,
        }));
        getDataByDate(document.getElementById('date'));
        calculateTotalShipmentQuantity();
        calculateTotalShipmentAmount();
        calculateNetAmount();
        renderList();

        if (generateShipmentBtn) {
            generateShipmentBtn.addEventListener('click', () => {
                generateArticlesModal();
            });
        }
    }

    window.initShipmentsEdit = initShipmentsEdit;

    function boot() {
        if (window.__shipmentsEdit) {
            initShipmentsEdit(window.__shipmentsEdit);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
