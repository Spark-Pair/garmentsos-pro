@extends('app')
@section('title', 'Generate Shipment | ' . $client_company->name)
@section('content')
    <!-- Main Content -->
    <!-- Progress Bar -->
    <div class="mb-5 max-w-4xl mx-auto">
        <x-search-header heading="Generate Shipment" link linkText="Show Shipments" linkHref="{{ route('shipments.index') }}"/>
        <x-progress-bar :steps="['Generate Shipment', 'Preview']" :currentStep="1" />
    </div>

    <!-- Form -->
    <form id="form" action="{{ route('shipments.store') }}" method="post" enctype="multipart/form-data"
        class="bg-[var(--secondary-bg-color)] text-sm rounded-xl shadow-lg p-8 border border-[var(--glass-border-color)]/20 pt-14 max-w-4xl mx-auto  relative overflow-hidden">
        @csrf
        <x-form-title-bar title="Generate Shipment" />

        <!-- Step 1: Generate shipment -->
        <div class="step1 space-y-4 ">
            <div class="flex justify-between items-end gap-4">
                {{-- shipment date --}}
                <div class="grow">
                    <x-input label="Date" name="date" id="date" type="date" onchange="getDataByDate(this)"
                        validateMax max='{{ now()->toDateString() }}' validateMin
                        min="2024-01-01" required />
                </div>
                <div class="w-1/3">
                    <x-select
                        label="City"
                        name="city"
                        id="city"
                        :options="[
                            'all' => ['text' => 'All'],
                            'karachi' => ['text' => 'Karachi'],
                            'lahore' => ['text' => 'Lahore'],
                        ]"
                        required
                        showDefault />
                </div>

                <button id="generateShipmentBtn" type="button"
                    class="bg-[var(--primary-color)] px-4 py-2 rounded-lg hover:bg-[var(--h-primary-color)] transition-all duration-300 ease-in-out text-nowrap cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed">Select Articles</button>
            </div>
            {{-- rate showing --}}
            <div id="shipment-table" class="w-full text-left text-sm">
                <div class="flex justify-between items-center bg-[var(--h-bg-color)] rounded-lg py-2 px-4 mb-4">
                    <div class="w-[10%]">#</div>
                    <div class="w-1/6">Qty.</div>
                    <div class="grow">Decs.</div>
                    <div class="w-1/6">Rate/Pc</div>
                    <div class="w-1/5">Amount</div>
                    <div class="w-[10%] text-center">Action</div>
                </div>
                <div id="shipment-list" class="h-[20rem] overflow-y-auto my-scrollbar-2">
                    <div class="text-center bg-[var(--h-bg-color)] rounded-lg py-3 px-4">No Rates Added</div>
                </div>
            </div>

            <div class="flex w-full grid grid-cols-1 md:grid-cols-2 gap-3 text-sm mt-5 text-nowrap">
                <div class="total-qty flex justify-between items-center border border-gray-600 rounded-lg py-2 px-4 w-full">
                    <div class="grow">Total Quantity - Pcs</div>
                    <div id="finalShipmentQuantity">0</div>
                </div>
                <div class="final flex justify-between items-center border border-gray-600 rounded-lg py-2 px-4 w-full">
                    <div class="grow">Total Amount - Rs.</div>
                    <div id="finalShipmentAmount">0.0</div>
                </div>
                <div
                    class="final flex justify-between items-center border border-gray-600 rounded-lg py-2 px-4 w-full">
                    <label for="discount" class="grow">Discount - %</label>
                    <input type="text" name="discount" id="discount" value="10"
                        class="text-right bg-transparent outline-none w-1/2 border-none" readonly />
                </div>
                <div class="final flex justify-between items-center border border-gray-600 rounded-lg py-2 px-4 w-full">
                    <div class="grow">Net Amount - Rs.</div>
                    <input type="text" name="netAmount" id="finalNetAmount" value="0.0" readonly
                        class="text-right bg-transparent outline-none w-1/2 border-none" />
                </div>
            </div>
            <input type="hidden" name="articles" id="articles" value="">
        </div>

        <!-- Step 2: view shipment -->
        <div class="step2 hidden space-y-4 text-black h-[35rem] overflow-y-auto my-scrollbar-2 bg-white rounded-md">
            <div id="preview-container" class="w-[208mm] h-[300mm] mx-auto overflow-hidden relative">
                <div id="preview" class="preview w-[208mm] h-[300mm] overflow-hidden flex flex-col">
                    <h1 class="text-[var(--border-error)] font-medium text-center mt-5">No Preview avalaible.</h1>
                </div>
            </div>
        </div>
    </form>

    <script>
        let maxLimitOfArticles = 500;
        let limitOfArticles = 500;
        let selectedArticles = [];
        let totalShipmentQuantity = 0;
        let totalShipmentAmount = 0;
        let netAmount = 0;
        let articles;

        const lastShipment = @json($last_shipment);
        const articleModalDom = document.getElementById("articleModal");
        const quantityModalDom = document.getElementById("quantityModal");
        const generateShipmentBtn = document.getElementById("generateShipmentBtn");
        generateShipmentBtn.disabled = true;

        function isArticleAlreadySelected(articleId) {
            return selectedArticles.some(a => a.id == articleId);
        }

        function trackStateOfgenerateBtn(value) {
            if (value != "") {
                generateShipmentBtn.disabled = false;
            } else {
                generateShipmentBtn.disabled = true;
            }
        }

        let totalQuantityDOM;
        let totalAmountDOM;

        let isModalOpened = false;
        let isQuantityModalOpened = false;
        let cardData = [];

        function basicSearch(searchValue) {
            let modalData = {
                id: 'modalForm',
                cards: {data: cardData.filter((item) => item.name.toLowerCase().includes(searchValue.toLowerCase()))},
            }
            renderCardsInModal(modalData);
        }

        generateShipmentBtn.addEventListener('click', () => {
            generateArticlesModal();
        })

        function generateArticlesModal() {
            let data = articles;

            if (data.length > 0) {
                cardData.push(...data.map(item => {
                    return {
                        id: item.id,
                        name: item.article_no,
                        image: item.image == 'no_image_icon.png' ? '/images/no_image_icon.png' : `/storage/uploads/images/${item.image}`,
                        details: {
                            "Category": item.category,
                            "Season": item.season,
                            "Size": item.size,
                        },
                        data: item,
                        onclick: 'generateQuantityModal(this)',
                    };
                }));
            }

            let modalData = {
                id: 'modalForm',
                class: 'h-[80%] w-full',
                cards: {name: 'Articles', count: 3, data: cardData},
                basicSearch: true,
                onBasicSearch: 'basicSearch(this.value)',
                info: `Selected: ${selectedArticles.length}/${maxLimitOfArticles}`,
                flex_col: true,
                calcBottom: [
                    {label: 'Total Quantity - Pcs', name: 'totalShipmentedQty', value: '0', disabled: true},
                    {label: 'Total Amount - Rs.', name: 'totalShipmentAmount', value: '0.0', disabled: true},
                ],
            }

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
                    let card = document.getElementById(selectedArticle.id);
                    let quantityLabelDom = card.querySelector('.quantity-label');
                    if (!quantityLabelDom) {
                        card.innerHTML += `
                            <div
                                class="quantity-label absolute text-xs text-[var(--border-success)] top-1 right-2 h-[1rem]">
                                ${selectedArticle.shipmentQuantity} Pcs
                            </div>
                        `;
                    } else {
                        quantityLabelDom.textContent = `${selectedArticle.shipmentQuantity} Pcs`;
                    }
                });
            }
        }

        function generateQuantityModal(elem) {
            let data = JSON.parse(elem.dataset.json).data;
            let alreadySelected = isArticleAlreadySelected(data.id);

            if (limitOfArticles > 0 || alreadySelected) {
                let modalData = {
                    id: 'QuantityModalForm',
                    name: 'Enter Quantity',
                    class: 'h-auto',
                    fields: [
                        {
                            category: 'input',
                            value: `${data.article_no} | ${data.season} | ${data.size} | ${data.category} | ${data.fabric_type} | ${data.quantity} | ${data.sales_rate} - Rs.`,
                            disabled: true,
                        },
                        {
                            category: 'input',
                            label: 'Current Stock - Pcs.',
                            value: formatNumbersDigitLess(data.quantity - data.ordered_quantity),
                            disabled: true,
                        },
                        {
                            category: 'input',
                            label: 'Current Stock - Pcs.',
                            value: formatNumbersDigitLess(data.physical_quantity),
                            disabled: true,
                        },
                        {
                            category: 'input',
                            name: 'quantity',
                            id: 'quantity',
                            type: 'number',
                            label: 'Quantity - Pcs.',
                            placeholder: 'Enter quantity in pcs.',
                            required: true,
                            oninput: "checkMax(this)",
                        },
                    ],
                    fieldsGridCount: '1',
                    bottomActions: [
                        {id: 'setQuantityBtn', text: 'Set Quantity', onclick: `setQuantity(${data.id})`},
                    ],
                }

                createModal(modalData);

                let physicalQuantity = 0;

                const physicalQuantityInpDom = document.getElementById('physical_quantity');
                const dateInpDom = document.getElementById('date');

                let quantityLabel = elem.querySelector('.quantity-label');

                if (quantityLabel) {
                    document.getElementById("quantity").value = parseInt(quantityLabel.textContent.replace(/\D/g, ""));
                }

                document.getElementById("quantity").focus();
                document.getElementById("quantity").addEventListener('keydown', (e) => {
                    if (e.key == 'Enter') {
                        document.getElementById("setQuantityBtn-in-modal").click();
                    }
                })

            } else {
                messageBox.innerHTML = `
                    <x-alert type="error" :messages="'You have reached the maximum allowed number of 500 articles.'" />
                `;
                messageBoxAnimation();
            }
        }

        function setQuantity(cardId) {
            let targetCard = document.getElementById(cardId);
            let cardData = JSON.parse(targetCard.dataset.json).data;
            let alreadySelected = isArticleAlreadySelected(cardData.id);

            if (limitOfArticles > 0 || alreadySelected) {

                closeModal('QuantityModalForm');
                let alreadySelectedArticle = selectedArticles.filter(c => c.id == cardData.id);
                let quantityInputDOM = document.getElementById("quantity");

                let quantity = quantityInputDOM.value;

                let quantityLabel = targetCard.querySelector('.quantity-label');

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

                } else {
                    if (quantityLabel) {
                        quantityLabel.remove();
                        const index = selectedArticles.findIndex(c => c.id === cardData.id);
                        deselectArticleAtIndex(index);
                    }
                }

                generateDescription();
                calculateTotalShipmentQuantity();
                calculateTotalShipmentAmount();
                calculateNetAmount();
                renderTotals();
                renderList();

            } else {
                messageBox.innerHTML = `
                    <x-alert type="error" :messages="'You have reached the maximum allowed number of 500 articles.'" />
                `;
                messageBoxAnimation();
            }
        }

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

        function deselectThisArticle(index) {
            deselectArticleAtIndex(index);

            renderList();
            generateShipment();

            calculateTotalShipmentQuantity();
            calculateTotalShipmentAmount();
            calculateNetAmount();

            renderFinals();
            renderTotals();
        }

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
                selectedArticle.description =
                    `${selectedArticle.size} | ${selectedArticle.category.replace(/_/g, ' ')} | ${selectedArticle.season}`;
            });
        }

        function calculateNetAmount() {
            let totalAmount = parseFloat(totalShipmentAmount);
            let discount = document.getElementById('discount').value;
            let discountAmount = totalAmount - (totalAmount * (discount / 100));
            netAmount = discountAmount;
            netAmount = new Intl.NumberFormat('en-US', {
                minimumFractionDigits: 1,
                maximumFractionDigits: 1
            }).format(netAmount);
            renderFinals();
        }

        discountDOM.addEventListener('input', calculateNetAmount);

        discountDOM.addEventListener('focus', (e) => {
            e.target.select();
        });

        function renderTotals() {
            totalQuantityDOM.value = totalShipmentQuantity;
            totalAmountDOM.value = totalShipmentAmount;
        }

        const orderListDOM = document.getElementById('shipment-list');

        function renderList() {
            if (selectedArticles.length > 0) {
                let clutter = "";
                selectedArticles.forEach((selectedArticle, index) => {
                    clutter += `
                        <div class="flex justify-between items-center border-t border-gray-600 py-3 px-4">
                            <div class="w-[10%]">${selectedArticle.article_no}</div>
                            <div class="w-1/6">${selectedArticle.shipmentQuantity} pcs</div>
                            <div class="grow capitalize">${selectedArticle.description}</div>
                            <div class="w-1/6">${selectedArticle.sales_rate}</div>
                            <div class="w-1/5">${selectedArticle.sales_rate * selectedArticle.shipmentQuantity}</div>
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
                    `<div class="text-center bg-[var(--h-bg-color)] rounded-lg py-2 px-4">No Articles Yet</div>`;
            }
            updateInputShipmentedArticles();
            limitOfArticles = maxLimitOfArticles - selectedArticles.length;
            updateInfo();
        }
        renderList();

        function renderFinals() {
            finalShipmentQuantity.textContent = totalShipmentQuantity;
            finalShipmentAmount.textContent = totalShipmentAmount;
            finalNetAmount.value = netAmount;
        }

        function updateInputShipmentedArticles() {
            let inputShipmentedArticles = document.getElementById('articles');
            let finalArticlesArray = selectedArticles.map(article => {
                return {
                    id: article.id,
                    description: article.description,
                    shipment_quantity: article.shipmentQuantity
                }
            });
            inputShipmentedArticles.value = JSON.stringify(finalArticlesArray);
        }

        let companyData = @json($client_company);
        let shipmentNo;
        let shipmentDate;
        const previewDom = document.getElementById('preview');

        function generateShipmentNo() {
            let lastShipmentNo = lastShipment.shipment_no
            const nextShipmentNo = String(parseInt(lastShipmentNo) + 1).padStart(4, '0');
            return nextShipmentNo;
        }

        function getShipmentDate() {
            const dateDom = document.getElementById('date').value;
            const date = new Date(dateDom);

            // Extract day, month, and year
            const day = String(date.getDate()).padStart(2, '0');
            const month = String(date.getMonth() + 1).padStart(2, '0'); // Months are 0-based
            const year = date.getFullYear();
            const dayOfWeek = date.getDay(); // 0 = Sunday, 1 = Monday, ..., 6 = Saturday

            // Array of weekday names
            const weekDays = ["Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"];

            // Return the formatted date
            return `${day}-${month}-${year}, ${weekDays[dayOfWeek]}`;
        }

        function generateShipment() {
            shipmentNo = generateShipmentNo();
            shipmentDate = getShipmentDate();

            if (selectedArticles.length > 0) {
                previewDom.innerHTML = `
                    <div id="shipment" class="shipment flex flex-col h-full">
                        <div id="shipment-banner" class="shipment-banner w-full flex justify-between items-center px-5">
                            <div class="left">
                                <div class="shipment-logo">
                                    <img src="{{ asset('images/${companyData.logo}') }}" alt="garmentsos-pro"
                                        class="w-[12rem]" />
                                </div>
                            </div>
                            <div class="right">
                                <div class="text-right">
                                    <h1 class="text-2xl font-medium text-[var(--primary-color)]">Shipment</h1>
                                    <div class='mt-1'>${ companyData.phone_number }</div>
                                </div>
                            </div>
                        </div>
                        <hr class="w-full my-3 border-gray-600">
                        <div id="shipment-header" class="shipment-header w-full flex justify-between px-5">
                            <div class="left w-50 my-auto text-sm text-gray-600 space-y-1.5">
                                <div class="shipment-date leading-none">Date: ${shipmentDate}</div>
                                <div class="shipment-number leading-none">Shipment No.: ${shipmentNo}</div>
                                <input type="hidden" name="shipment_no" value="${shipmentNo}" />
                            </div>
                            <div class="right w-50 my-auto text-right text-sm text-gray-600 space-y-1.5">
                                <div class="shipment-copy leading-none">Shipment Copy: Office</div>
                                <div class="shipment-copy leading-none">Document: Shipment</div>
                            </div>
                        </div>
                        <hr class="w-full my-3 border-gray-600">
                        <div id="shipment-body" class="shipment-body w-[95%] grow mx-auto">
                            <div class="shipment-table w-full">
                                <div class="table w-full border border-gray-600 rounded-lg pb-2.5 overflow-hidden">
                                    <div class="thead w-full">
                                        <div class="tr flex justify-between w-full px-4 py-1.5 bg-[var(--primary-color)] text-white">
                                            <div class="th text-sm font-medium w-[7%]">S.No</div>
                                            <div class="th text-sm font-medium w-[10%]">Article</div>
                                            <div class="th text-sm font-medium grow">Description</div>
                                            <div class="th text-sm font-medium w-[10%]">Pcs.</div>
                                            <div class="th text-sm font-medium w-[10%]">Packets</div>
                                            <div class="th text-sm font-medium w-[10%]">Rate</div>
                                            <div class="th text-sm font-medium w-[10%]">Amount</div>
                                        </div>
                                    </div>
                                    <div id="tbody" class="tbody w-full">
                                        ${selectedArticles.map((article, index) => {
                                            const hrClass = index === 0 ? "mb-2.5" : "my-2.5";
                                            return `
                                                <div>
                                                    <hr class="w-full ${hrClass} border-gray-600">
                                                    <div class="tr flex justify-between w-full px-4">
                                                        <div class="td text-sm font-semibold w-[7%]">${index + 1}.</div>
                                                        <div class="td text-sm font-semibold w-[10%]">${article.article_no}</div>
                                                        <div class="td text-sm font-semibold grow">${article.description}</div>
                                                        <div class="td text-sm font-semibold w-[10%]">${article.shipmentQuantity}</div>
                                                        <div class="td text-sm font-semibold w-[10%]">${article.pcs_per_packet ? Math.floor(article.shipmentQuantity / article.pcs_per_packet) : 0}</div>
                                                        <div class="td text-sm font-semibold w-[10%]">
                                                            ${new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(article.sales_rate)}
                                                        </div>
                                                        <div class="td text-sm font-semibold w-[10%]">
                                                            ${new Intl.NumberFormat('en-US', { minimumFractionDigits: 1, maximumFractionDigits: 1 }).format(parseInt(article.sales_rate) * article.shipmentQuantity)}
                                                        </div>
                                                    </div>
                                                </div>
                                            `;
                                        }).join('')}
                                    </div>
                                </div>
                            </div>
                        </div>
                        <hr class="w-full my-3 border-gray-600">
                        <div class="flex flex-col space-y-2">
                            <div id="shipment-total" class="tr flex justify-between w-full px-2 gap-2 text-sm">
                                <div class="total flex justify-between items-center border border-gray-600 rounded-lg py-2 px-4 w-full">
                                    <div class="text-nowrap">Total Quantity - Pcs</div>
                                    <div class="w-1/4 text-right grow">${totalQuantityDOM.value}</div>
                                </div>
                                <div class="total flex justify-between items-center border border-gray-600 rounded-lg py-2 px-4 w-full">
                                    <div class="text-nowrap">Total Amount</div>
                                    <div class="w-1/4 text-right grow">${totalAmountDOM.value}</div>
                                </div>
                            </div>
                            <div id="shipment-total" class="tr flex justify-between w-full px-2 gap-2 text-sm">
                                <div class="total flex justify-between items-center border border-gray-600 rounded-lg py-2 px-4 w-full">
                                    <div class="text-nowrap">Discount - %</div>
                                    <div class="w-1/4 text-right grow">${discountDOM.value}</div>
                                </div>
                                <div
                                    class="total flex justify-between items-center border border-gray-600 rounded-lg py-2 px-4 w-full">
                                    <div class="text-nowrap">Net Amount</div>
                                    <div class="w-1/4 text-right grow">${finalNetAmount.value}</div>
                                </div>
                            </div>
                        </div>
                        <hr class="w-full my-3 border-gray-600">
                        <div class="tfooter flex w-full text-sm px-4 justify-between text-gray-600">
                            <P class="leading-none">Powered by SparkPair</P>
                            <p class="leading-none text-sm">&copy; 2025 SparkPair | +92 316 5825495</p>
                        </div>
                    </div>
                `;
            } else {
                previewDom.innerHTML = `
                    <h1 class="text-[var(--border-error)] font-medium text-center mt-5">No Preview avalaible.</h1>
                `;
            }
        }

        let cardsDom;
        let cardsDataArray = [];

        function getDataByDate(inputElem) {
            trackStateOfgenerateBtn(inputElem.value);
            $.ajax({
                url: '{{ route("shipments.create") }}',
                method: 'GET',
                data: {
                    date: inputElem.value,
                },
                success: function(response) {
                    articles = response.articles;
                },
                error: function() {
                    alert('Error submitting form');
                }
            });
        }

        function validateForNextStep() {
            generateShipment()
            return true;
        }
    </script>
@endsection
