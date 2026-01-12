@extends('app')
@section('title', 'Add Production | ' . $client_company->name)
@section('content')
@php
    $productionType = Auth::user()->production_type;
@endphp

    <div class="switch-btn-container flex absolute top-3 md:top-17 left-3 md:left-5 z-40">
        <div class="switch-btn relative flex border-3 border-[var(--secondary-bg-color)] bg-[var(--secondary-bg-color)] rounded-2xl overflow-hidden">
            <!-- Highlight rectangle -->
            <div id="highlight" class="absolute h-full rounded-xl bg-[var(--bg-color)] transition-all duration-300 ease-in-out z-0"></div>

            <!-- Buttons -->
            <button
                id="issueBtn"
                type="button"
                class="relative z-10 px-3.5 md:px-5 py-1.5 md:py-2 cursor-pointer rounded-xl transition-colors duration-300"
                onclick="setProductionType(this, 'issue')"
            >
                <div class="hidden md:block">Issue</div>
                <div class="block md:hidden"><i class="fas fa-cart-shopping text-xs"></i></div>
            </button>
            <button
                id="reciveBtn"
                type="button"
                class="relative z-10 px-3.5 md:px-5 py-1.5 md:py-2 cursor-pointer rounded-xl transition-colors duration-300"
                onclick="setProductionType(this, 'receive')"
            >
                <div class="hidden md:block">Receive</div>
                <div class="block md:hidden"><i class="fas fa-box-open text-xs"></i></div>
            </button>
        </div>
    </div>

    <script>
        let btnTypeGlobal = "issue";

        function setProductionType(btn, btnType) {
            // check if its already selected
            if (btnTypeGlobal == btnType) {
                return;
            }

            doHide = true;

            $.ajax({
                url: "/set-production-type",
                type: "POST",
                data: {
                    _token: "{{ csrf_token() }}",
                    production_type: btnType
                },
                success: function () {
                    location.reload();
                },
                error: function () {
                    alert("Failed to update production type.");
                    $(btn).prop("disabled", false);
                }
            });

            moveHighlight(btn, btnType);
        }

        function moveHighlight(btn, btnType) {
            const highlight = document.getElementById("highlight");
            const rect = btn.getBoundingClientRect();

            const parentRect = btn.parentElement.getBoundingClientRect();

            // Move and resize the highlight
            highlight.style.width = `${rect.width}px`;
            highlight.style.left = `${rect.left - parentRect.left - 3}px`;

            btnTypeGlobal = btnType;
        }

        // Initialize highlight on load
        window.onload = () => {
            @if($productionType == 'issue')
                const activeBtn = document.querySelector("#issueBtn");
                moveHighlight(activeBtn, "issue");
            @else
                const activeBtn = document.querySelector("#reciveBtn");
                moveHighlight(activeBtn, "receive");
            @endif
        };
    </script>

    @if ($productionType == 'issue')
        @php
            $units = app('defaults')->units;

            $units_options = [];

            foreach ($units as $unit) {
                $units_options[$unit] = [
                    'text' => $unit,
                ];
            }
        @endphp
        <!-- Main Content -->
        <div class="max-w-4xl mx-auto">
            <x-search-header heading="Issue Production" link linkText="Show Productions" linkHref="{{ route('productions.index') }}"/>
            <x-progress-bar :steps="['Master Information', 'Details']" :currentStep="1" />
        </div>

        <!-- Form -->
        <form id="form" action="{{ route('productions.store') }}" method="post"
            class="bg-[var(--secondary-bg-color)] text-sm rounded-xl shadow-lg p-8 border border-[var(--glass-border-color)]/20 pt-14 max-w-4xl mx-auto relative overflow-hidden">
            @csrf
            <x-form-title-bar title="Issue Production" />

            <div class="step1 space-y-4 ">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    {{-- article --}}
                    <x-input label="Article" id="article" placeholder='Select Article' class="cursor-pointer" addBtnLink="/articles/create" required />
                    <input type="hidden" name="article_id" id="article_id" value="" />

                    {{-- work --}}
                    <x-select
                        label="Work"
                        name="work_id"
                        id="work"
                        :options="[]"
                        showDefault
                        required
                        onchange="trackWorkState(this)"
                        disabled
                    />

                    {{-- worker --}}
                    <x-select
                        label="Worker"
                        name="worker_id"
                        id="worker"
                        :options="[]"
                        showDefault
                        required
                        onchange="trackWorkerState(this)"
                        disabled
                    />

                    {{-- balance --}}
                    <x-input label="Balance" id="balance" placeholder='Balance' disabled/>
                </div>
            </div>

            <div class="step2 space-y-4 hidden">
                <div id="secondStep" class="grid grid-cols-1 sm:grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="col-span-full text-center text-[var(--border-error)]">No Detailes yet.</div>
                </div>
            </div>
        </form>

        <script>
            let cardData = [];
            let ArticleModalData = {};
            let allWorks = Object.entries(@json($work_options));
            let allWorkers = Object.values(@json($worker_options));
            let allParts = Object.entries(@json(app('article')->parts));
            let Units = @json(app('defaults')->units);
            let allRates = @json($rates);
            let materialModalData = {};
            let materialsArray = [];
            let selectedPartsArray = [];
            const articleSelectInputDOM = document.getElementById("article");
            const articleIdInputDOM = document.getElementById("article_id");


            let tags = [];
            let selectedTagsArray = [];

            articleSelectInputDOM.addEventListener('keydown', (e) => {
                e.preventDefault();
            });

            articleSelectInputDOM.addEventListener('click', (e) => {
                generateArticlesModal();
            })

            function generateArticlesModal() {
                const data = @json($articles);

                const articlesIssuedOnCMT = data.filter(a => a.production.some(p => p.work.title === 'CMT'));
                const articles = data.filter(a => !articlesIssuedOnCMT.includes(a));

                if (data.length > 0) {

                    cardData.push(...articles.map(item => {
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
                            onclick: 'selectThisArticle(this)',
                        };
                    }));
                }

                ArticleModalData = {
                    id: 'modalForm',
                    basicSearch: true,
                    onBasicSearch: 'basicSearch(this.value)',
                    cards: {name: 'Articles', count: 3, data: cardData},
                }

                createModal(ArticleModalData);
            }

            function basicSearch(searchValue) {
                ArticleModalData.cards.data = cardData.filter((item) => item.name.toLowerCase().includes(searchValue.toLowerCase()));
                renderCardsInModal(ArticleModalData);
            }

            let selectedArticle = null;

            function selectThisArticle(articleElem) {
                selectedArticle = JSON.parse(articleElem.getAttribute('data-json')).data;

                articleIdInputDOM.value = selectedArticle.id;
                let value = `${selectedArticle.article_no} | ${selectedArticle.season} | ${selectedArticle.size} | ${selectedArticle.category} | ${formatNumbersDigitLess(selectedArticle.quantity)} (pcs) | Rs. ${formatNumbersWithDigits(selectedArticle.sales_rate, 1, 1)}`;
                articleSelectInputDOM.value = value;

                closeModal('modalForm');

                document.querySelector('input[name="work_name"]').disabled = false;

                const ul = document.querySelector('ul[data-for="work"]');

                ul.innerHTML = `
                    <li data-for="work" data-value="" onmousedown="selectThisOption(this)"
                        class="py-2 px-3 cursor-pointer rounded-lg hover:bg-[var(--h-bg-color)] selected">
                        -- Select Work --
                    </li>
                `;

                const afterCutting = ['Singer', 'Print | E', 'Embroidery | E', 'Dhaap', 'Wash', 'O/F Look', 'Kaj Button', 'Bartake', 'Stitching | E'];
                const categorySeasonKey = `${selectedArticle.category}_${selectedArticle.season}`;
                const partsRecievedFromCutting = selectedArticle.production.filter(p => p.work.title === 'Cutting').flatMap(p => p.parts);
                const partsRecievedFromSinger = [...new Set(selectedArticle.production.filter(p => p.work.title === 'Singer').flatMap(p => p.parts ?? []))];
                const allIssuedParts = selectedArticle.production.filter(p => p.receive_date === null).flatMap(p => p.parts);
                const existingWorks = [...new Set(selectedArticle.production.flatMap(p => p.work.title))];
                const parts = allParts.filter(([key]) => key === categorySeasonKey).flatMap(([_, value]) => value);

                allWorks.forEach(([workKey, workValue]) => {
                    const workTitle = workValue.text;
                    let shouldShowWork = false;

                    // --- Dynamic CMT ---
                    if (existingWorks.length === 1 && !afterCutting.includes('CMT | E')) {
                        afterCutting.push('CMT | E');
                    } else if (existingWorks.length !== 1 && afterCutting.includes('CMT | E')) {
                        afterCutting = afterCutting.filter(w => w !== 'CMT | E');
                    }

                    // --- Parts issued to current work ---
                    const currentWorkParts = selectedArticle.production
                        .filter(p => p.work.title === workTitle)
                        .flatMap(p => p.parts);

                    // --- Main Condition ---
                    if (afterCutting.includes(workTitle)) {
                        if (currentWorkParts.length < partsRecievedFromCutting.length) {
                            shouldShowWork = true;
                        }
                    }

                    // --- Add to UI ---
                    if (shouldShowWork) {
                        ul.innerHTML += `
                            <li data-for="work" data-value="${workKey}"
                                onmousedown="selectThisOption(this)"
                                class="py-2 px-3 cursor-pointer rounded-lg hover:bg-[var(--h-bg-color)]">
                                ${workTitle.split('|')[0].trim()}
                            </li>
                        `;
                    }
                });

                // agar koi work available nahi
                if (ul.children.length == 1) {
                    ul.innerHTML = ``;
                    document.querySelector('input[name="work_name"]').value = '';
                    document.querySelector('input[name="work_name"]').disabled = true;
                }

                const selectedLi = ul.querySelector('li.selected');
                if (selectedLi) {
                    selectedLi.dispatchEvent(new MouseEvent('mousedown', { bubbles: true }));
                }
            }

            function trackWorkState(elem) {
                if (elem.value != '') {
                    let correctWorkers = allWorkers.filter(worker => worker.data_option.type.id == elem.value);

                    if (correctWorkers.length > 0) {
                        document.querySelector('input[name="worker_name"]').disabled = false;
                        const ul = document.querySelector('ul[data-for="worker"]');

                        ul.innerHTML = `
                            <li data-for="worker" data-value="" onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg hover:bg-[var(--h-bg-color)] selected">-- Select Worker --</li>
                        `;

                        correctWorkers.forEach((worker) => {
                            ul.innerHTML += `
                                <li data-for="worker" data-value="${worker.data_option.id}" data-option='${JSON.stringify(worker.data_option)}' onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg hover:bg-[var(--h-bg-color)]">${worker.text}</li>
                            `;
                        })

                        const selectedLi = ul.querySelector('li.selected');
                        if (selectedLi) {
                            selectedLi.dispatchEvent(new MouseEvent('mousedown', { bubbles: true }));
                        }
                    } else {
                        document.querySelector('input[name="worker_name"]').disabled = true;
                        document.querySelector('input[name="worker_name"]').value = '';
                    }
                    generateSecondStep(elem.closest('.selectParent').querySelector('li.selected').textContent.trim());

                    let filteredRates = allRates.filter(rate => rate.type.id == elem.value && rate.categories.includes(selectedArticle.category) && rate.seasons.includes(selectedArticle.season) && rate.sizes.includes(selectedArticle.size));
                    let selectRateNameDom = document.querySelector('input[name="select_rate_name"]');

                    if (selectRateNameDom) {
                        if (filteredRates.length > 0) {
                            selectRateNameDom.value = '-- Select Rates --';
                            selectRateNameDom.disabled = false;
                            let ratesUL = document.querySelector('ul[data-for="select_rate"]');
                            ratesUL.innerHTML = `
                                <li data-for="select_rate" data-value="" onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg hover:bg-[var(--h-bg-color)] selected">-- Select Rates --</li>
                            `;

                            filteredRates.forEach((rate) => {
                                ratesUL.innerHTML += `
                                    <li data-for="select_rate" data-value="${rate.id}" onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg hover:bg-[var(--h-bg-color)]">${rate.title} | ${rate.rate}</li>
                                `;
                            })

                            ratesUL.innerHTML += `
                                <li data-for="select_rate" data-value="0" onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg hover:bg-[var(--h-bg-color)]">Other</li>
                            `;

                        } else {
                            selectRateNameDom.value = '';
                            selectRateNameDom.disabled = true;
                        }
                    }
                }
            }

            function trackWorkerState(elem) {
                const selectParent = elem.closest('.selectParent');
                const selectedWorkerData = JSON.parse(selectParent.querySelector('li.selected').dataset.option || '{}');
                document.getElementById('balance').value = formatNumbersWithDigits(selectedWorkerData?.balance || 0, 1, );
                tags = selectedWorkerData.taags || [];
                elem.value !== '' && gotoStep(2);
                selectedTagsArray = [];
            }

            function generateMaterialsModal(animate = 'animate') {
                let tableBody = [];

                tableBody = materialsArray.map((item, index) => {
                    return [
                        {data: index+1, class: 'w-[10%]'},
                        {data: item.title, class: 'w-[25%]'},
                        {data: item.unit, class: 'w-[25%]'},
                        {data: item.quantity, class: 'w-[15%]'},
                        {rawHTML: `
                            <div class="w-[10%] text-center">
                                <button onclick="deleteMaterial(this)" type="button" class="text-[var(--danger-color)] text-xs px-2 py-1 rounded-lg hover:text-[var(--h-danger-color)] transition-all duration-300 ease-in-out cursor-pointer">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        `},
                    ]
                })

                materialModalData = {
                    id: 'addMaterialsModalForm',
                    class: 'max-w-4xl h-[37rem]',
                    name: 'Add Materials',
                    fields: [
                        {
                            category: 'input',
                            label: 'Title',
                            id: 'title',
                            placeholder: 'Enter Title',
                            oninput: 'enableDisableBtn(this)',
                            grow: true,
                            focus: true,
                        },
                        {
                            category: 'explicitHtml',
                            html: `
                                <x-select
                                    label="Units"
                                    name="unit"
                                    id="unit"
                                    :options="$units_options"
                                    showDefault
                                    required
                                />
                            `,
                        },
                        {
                            category: 'input',
                            label: 'Quantity',
                            id: 'quantity',
                            type: 'number',
                            placeholder: 'Enter Quantity',
                            oninput: 'trackQuantityState(this)',
                            btnId: 'addMaterial',
                            onclick: 'addthis(this)',
                        },
                    ],
                    fieldsGridCount: '3',
                    table: {
                        name: 'Rates',
                        headers: [
                            { label: "#", class: "w-[10%]" },
                            { label: "Title", class: "w-[25%]" },
                            { label: "Unit", class: "w-[25%]" },
                            { label: "Quantity", class: "w-[15%]" },
                            { label: "Action", class: "w-[10%]" },
                        ],
                        body: tableBody,
                        scrollable: true,
                    },
                }

                createModal(materialModalData, animate);
            }

            function enableDisableBtn(elem) {
                const formDom = elem.closest('form');

                const btnDom = formDom.querySelector('#addMaterial');
                const titleInpDom = formDom.querySelector('#title');
                const unitSelectDom = formDom.querySelector('#unit');
                const quantityInpDom = formDom.querySelector('#quantity');

                if (titleInpDom.value != '' && unitSelectDom.value != '' && quantityInpDom.value != '') {
                    btnDom.disabled = false;
                } else {
                    btnDom.disabled = true;
                }
            }

            function trackQuantityState(elem) {
                enableDisableBtn(elem);

                if (elem.dataset.listenerAdded === 'true') return;

                elem.dataset.listenerAdded = 'true'; // Mark as handled

                const formDom = elem.closest('form');
                const addBtn = formDom.querySelector('#addMaterial');

                elem.addEventListener('keydown', function (e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        e.stopPropagation();
                        addMaterial(addBtn);
                    }
                });
            }

            function deleteMaterial(elem) {
                elem.closest('.flex')
                const formDom = elem.closest('form');
                const titleInpDom = formDom.querySelector('#title');

                titleInpDom.focus();

                let quantity = parseFloat(elem.parentElement.previousElementSibling.innerText);

                let title = elem.parentElement.previousElementSibling.previousElementSibling.previousElementSibling.innerText;

                materialsArray = materialsArray.filter(quantity => quantity.title !== title);

                renderMaterialList(elem.closest('#table-body'));
            }

            function renderMaterialList(tableBody) {
                if (materialsArray.length > 0) {
                    tableBody.innerHTML = '';
                    materialsArray.forEach((material, index) => {
                        tableBody.innerHTML += `
                            <div class="flex justify-between items-center border-t border-gray-600 py-2 px-4">
                                <div class="w-[10%]">${index + 1}</div>
                                <div class="w-[25%]">${material.title}</div>
                                <div class="w-[25%]">${material.unit}</div>
                                <div class="w-[15%]">${formatNumbersWithDigits(material.quantity)}</div>
                                <div class="w-[10%] text-center">
                                    <button onclick="deleteMaterial(this)" type="button" class="text-[var(--danger-color)] text-xs px-2 py-1 rounded-lg hover:text-[var(--h-danger-color)] transition-all duration-300 ease-in-out cursor-pointer">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        `;
                    });
                } else {
                    tableBody.innerHTML = `
                        <div class="flex justify-between items-center border-t border-gray-600 py-2 px-4">
                            <div class="grow text-center text-[var(--border-error)]">No Materials yet.</div>
                        </div>
                    `;
                }
            }

            function addthis(elem) {
                let materialObject = {};
                const formDom = elem.closest('form');
                const titleInpDom = formDom.querySelector('#title');
                const unitSelectDom = formDom.querySelector('#unit');
                const quantityInpDom = formDom.querySelector('#quantity');
                const tableBodyDom = formDom.querySelector('#table-body');
                materialObject.title = titleInpDom.value;
                materialObject.unit = unitSelectDom.value;
                materialObject.quantity = quantityInpDom.value;
                materialsArray.push(materialObject);
                titleInpDom.value = '';
                unitSelectDom.value = '';
                quantityInpDom.value = '';
                titleInpDom.focus();
                document.getElementById('materials').value = `${materialsArray.length} Material${materialsArray > 1 ? 's' : ''} Selected`;
                document.querySelector('input[name="materials"]').value = JSON.stringify(materialsArray);
                renderMaterialList(tableBodyDom)
            }

            function generateQuantityModal(item) {
                let quantityModalData = {
                    id: 'quantityModalForm',
                    name: 'Enter Quantity',
                    class: 'h-auto',
                    fields: [
                        {
                            category: 'input',
                            label: 'Unit',
                            value: item.unit,
                            disabled: true,
                        },
                        {
                            category: 'input',
                            id: 'tag',
                            name: 'tag',
                            type: 'hidden',
                            value: item.tag,
                        },
                        {
                            category: 'input',
                            label: 'Avalaible Quantity',
                            value: item.available_quantity,
                            disabled: true,
                        },
                        {
                            category: 'explicitHtml',
                            html: `
                                <x-input label="Quantity" name="quantity" id="quantity" type="number" placeholder="Enter quantity" required oninput="validateInput(this)"/>
                            `,
                            focus: 'quantity',
                        },
                    ],
                    fieldsGridCount: '1',
                    bottomActions: [
                        {id: 'add', text: 'Add', onclick: 'selectWithQuantity(this)'},
                    ],
                }

                createModal(quantityModalData)

                document.querySelector('input[name="quantity"]').value = item.selected_quantity || '';
                document.querySelector('input[name="quantity"]').dataset.validate = `max:${item.available_quantity + (item.selected_quantity || 0)}`;
            }

            function selectWithQuantity(elem) {
                const inputs = elem.closest('form').querySelectorAll('input:not([disabled])');
                let detail = {};

                inputs.forEach(input => {
                    const name = input.getAttribute('name');
                    if (name != null && name != '_token') {
                        const value = input.value;

                        if (name == "quantity") {
                            detail[name] = parseInt(value);
                        } else {
                            detail[name] = value;
                        }
                    }
                });

                let existingTag = selectedTagsArray.find(tag => tag.tag == detail.tag);

                if (detail.quantity > 0) {
                    existingTag ? existingTag.quantity = detail.quantity : selectedTagsArray.push(detail);
                } else if (existingTag) {
                    selectedTagsArray = selectedTagsArray.filter(tag => tag.tag !== detail.tag);
                }
                tags.find(tag => tag.tag === detail.tag).selected_quantity = detail.quantity;
                tags.find(tag => tag.tag === detail.tag).available_quantity -= tags.find(tag => tag.tag === detail.tag).selected_quantity;
                document.querySelector('input[name="tags"]').value = JSON.stringify(selectedTagsArray);
                closeModal('quantityModalForm');
                closeModal('tagModalForm', 'notAnimate');
                generateSelectTagModal('notAnimate');
                document.getElementById('tags').value = selectedTagsArray.length > 0 ? `${selectedTagsArray.length} Selected` : '';
            }

            function generateSecondStep(work) {
                let secondStepHTML = '';
                if (work == 'Singer' || work == 'Stitching' || work == 'CMT' || work == 'Kaj Button' || work == 'Cropping' || work == 'Packing') {
                    secondStepHTML += `
                        {{-- article --}}
                        <x-input label="Article" name="article" id="article" disabled value="${selectedArticle.article_no} | ${selectedArticle.season} | ${selectedArticle.size} | ${selectedArticle.category} | ${formatNumbersDigitLess(selectedArticle.quantity)} (pcs) | Rs. ${formatNumbersWithDigits(selectedArticle.sales_rate, 1, 1)}" />

                        {{-- materials  --}}
                        <x-input label="Materials" id="materials" placeholder="Select Materials" class="cursor-pointer" required onclick="generateMaterialsModal()" autoComplete="off" />
                        <input type="hidden" name="materials" value="" />

                        ${!selectedArticle.quantity > 0 ? `
                            {{-- quantity --}}
                            <x-input label="Quantity" name="article_quantity" id="article_quantity" type="number" placeholder="Enter Quantity" required oninput="calculateAmount()" />
                        ` : `
                            {{-- quantity --}}
                            <x-input label="Quantity" name="article_quantity" id="article_quantity" type="number" value="${selectedArticle.quantity}" disabled />
                        `}

                        ${selectedArticle.category != '1_pc' ? `
                            {{-- parts --}}
                            <x-input label="Parts" name="partsInView" id="parts" withCheckbox :checkBoxes="[]" required />
                            <input type="hidden" name="parts" id="dbParts" value="[]" />
                        ` : `` }

                        {{-- issue_date --}}
                        <x-input label="Issue Date" name="issue_date" id="issue_date" required type="date" validateMin min="2024-01-01" validateMax max="{{ now()->toDateString() }}" />
                    `;
                } else if (work == 'Print' || work == 'Embroidery' || work == 'Dhaap' || work == 'Wash' || work == 'O/F Look' || work == 'Bartake' || work == 'Press') {
                    secondStepHTML += `
                        {{-- article --}}
                        <x-input label="Article" name="article" id="article" disabled value="${selectedArticle.article_no} | ${selectedArticle.season} | ${selectedArticle.size} | ${selectedArticle.category} | ${formatNumbersDigitLess(selectedArticle.quantity)} (pcs) | Rs. ${formatNumbersWithDigits(selectedArticle.sales_rate, 1, 1)}" />

                        ${!selectedArticle.quantity > 0 ? `
                            {{-- quantity --}}
                            <x-input label="Quantity" name="article_quantity" id="article_quantity" type="number" placeholder="Enter Quantity" required oninput="calculateAmount()" />
                        ` : `
                            {{-- quantity --}}
                            <x-input label="Quantity" name="article_quantity" id="article_quantity" type="number" value="${selectedArticle.quantity}" disabled />
                        `}

                        ${selectedArticle.category != '1_pc' ? `
                            {{-- parts --}}
                            <x-input label="Parts" id="parts" withCheckbox :checkBoxes="[]" required />
                            <input type="hidden" name="parts" id="dbParts" value="[]" />
                        ` : `` }

                        {{-- issue_date --}}
                        <x-input label="Issue Date" name="issue_date" id="issue_date" required type="date" validateMin min="2024-01-01" validateMax max="{{ now()->toDateString() }}" />
                    `;
                }
                document.getElementById('secondStep').innerHTML = secondStepHTML;

                let partKey = selectedArticle.category + '_' + selectedArticle.season;
                let partsClutter = '';
                const checkboxes_container = document.querySelector('.checkboxes_container');

                // Cutting se sab parts
                const partsRecivedFromCutting = selectedArticle.production
                    .filter(p => p.work.title === "Cutting")
                    .flatMap(p => p.parts);

                // Abhi tak jo parts kisi bhi work ke liye issue hue aur receive nahi hue
                const allIssuedParts = selectedArticle.production
                    .filter(p => p.receive_date === null)
                    .flatMap(p => p.parts);

                // Jo work abhi select hua hai (current work)
                const currentWorkParts = selectedArticle.production
                    .filter(p => p.work.title === work) // <-- yahan selectedWorkTitle pass karein
                    .flatMap(p => p.parts);

                // Available parts = cutting se aaye - issued - current work ke already issued
                const availableParts = partsRecivedFromCutting.filter(p =>
                    !currentWorkParts.includes(p)
                );

                // Checkbox rendering
                availableParts.forEach((part) => {
                    partsClutter += `
                        <label class="flex items-center gap-2 cursor-pointer rounded-md border border-[var(--h-bg-color)] bg-[var(--h-bg-color)] px-2 py-[0.1875rem] shadow-sm transition hover:shadow-md hover:border-primary">
                            <input
                                type="checkbox"
                                onchange="toggleThisCheckbox(this)"
                                data-checkbox="${part}"
                                class="checkbox appearance-none bg-[var(--secondary-bg-color)] w-4 h-4 border border-gray-600 rounded-sm checked:bg-[var(--primary-color)] transition"
                            />
                            <span class="text-sm font-medium text-[var(--secondary-text)]">
                                ${part}
                            </span>
                        </label>
                    `;
                });

                if (checkboxes_container) {
                    checkboxes_container.innerHTML = partsClutter;
                }
            }

            function toggleThisCheckbox(checkbox) {
                const dbPartsInput = document.getElementById('dbParts');

                const checkboxValue = checkbox.dataset.checkbox;
                if (checkbox.checked) {
                    if (!selectedPartsArray.includes(checkboxValue)) {
                        selectedPartsArray.push(checkboxValue);
                    }
                } else {
                    selectedPartsArray = selectedPartsArray.filter(part => part !== checkboxValue);
                }

                dbPartsInput.value = JSON.stringify(selectedPartsArray);
            }

            function validateForNextStep() {
                return true;
            }
        </script>
    @else
        <!-- Main Content -->
        <div class="max-w-4xl mx-auto">
            <x-search-header heading="Add Production" link linkText="Show Productions" linkHref="{{ route('productions.index') }}"/>
            <x-progress-bar :steps="['Master Information', 'Details']" :currentStep="1" />
        </div>

        <!-- Form -->
        <form id="form" action="{{ route('productions.store') }}" method="post"
            class="bg-[var(--secondary-bg-color)] text-sm rounded-xl shadow-lg p-8 border border-[var(--glass-border-color)]/20 pt-14 max-w-4xl mx-auto relative overflow-hidden">
            @csrf
            <x-form-title-bar title="Add Production" />

            <div class="step1 space-y-4 ">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    {{-- ticket --}}
                    <x-select
                        label="Ticket"
                        id="ticket"
                        :options="$ticket_options"
                        showDefault
                        required
                        onchange="trackTicketState(this)"
                    />

                    {{-- article --}}
                    <x-input label="Article" id="article" placeholder='Select Article' class="cursor-pointer" addBtnLink="/articles/create" required />
                    <input type="hidden" name="article_id" id="article_id" value="" />

                    {{-- work --}}
                    <x-select
                        label="Work"
                        name="work_id"
                        id="work"
                        :options="[]"
                        showDefault
                        required
                        onchange="trackWorkState(this)"
                        disabled
                    />

                    {{-- worker --}}
                    <x-select
                        label="Worker"
                        name="worker_id"
                        id="worker"
                        :options="[]"
                        showDefault
                        required
                        onchange="trackWorkerState(this)"
                        disabled
                    />

                    {{-- balance --}}
                    <x-input label="Balance" id="balance" placeholder='Balance' disabled />
                </div>
            </div>

            <div class="step2 space-y-4 hidden">
                <div id="secondStep" class="grid grid-cols-1 sm:grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="col-span-full text-center text-[var(--border-error)]">No Detailes yet.</div>
                </div>
            </div>
        </form>

        <script>
            let cardData = [];
            let ArticleModalData = {};
            let allWorks = Object.entries(@json($work_options));
            let allWorkers = Object.values(@json($worker_options));
            let allParts = Object.entries(@json(app('article')->parts));
            let allRates = @json($rates);
            let materialModalData = {};
            const articleSelectInputDOM = document.getElementById("article");
            const articleIdInputDOM = document.getElementById("article_id");


            let tags = [];
            let selectedTagsArray = [];

            articleSelectInputDOM.addEventListener('keydown', (e) => {
                e.preventDefault(); // kuch bhi type nahi hoga
            });

            articleSelectInputDOM.addEventListener('click', () => {
                generateArticlesModal();
            })

            function generateArticlesModal() {
                const data = @json($articles);

                const articles = data.filter(a => {
                    // saare parts (category + season se)
                    const partKey = `${a.category}_${a.season}`;
                    const allPartsForArticle = allParts
                        .filter(([key]) => key === partKey)
                        .flatMap(([_, value]) => value);

                    // Cutting ke parts
                    const cuttingParts = a.production
                        .filter(p => p.work.title === "Cutting")
                        .flatMap(p => p.parts);

                    // Filter condition
                    return cuttingParts.length !== allPartsForArticle.length;
                });

                if (data.length > 0) {
                    cardData.push(...articles.map(item => {
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
                            onclick: 'selectThisArticle(this)',
                        };
                    }));
                }

                ArticleModalData = {
                    id: 'modalForm',
                    basicSearch: true,
                    onBasicSearch: 'basicSearch(this.value)',
                    cards: {name: 'Articles', count: 3, data: cardData},
                }

                createModal(ArticleModalData);
                trackWorkState(document.getElementById('work'));
            }

            function basicSearch(searchValue) {
                ArticleModalData.cards.data = cardData.filter((item) => item.name.toLowerCase().includes(searchValue.toLowerCase()));
                renderCardsInModal(ArticleModalData);
            }

            let selectedArticle = null;

            function selectThisArticle(articleElem) {
                document.getElementById('secondStep').innerHTML = '';
                if (articleElem.dataset?.json) {
                    selectedArticle = JSON.parse(articleElem.getAttribute('data-json')).data;
                } else {
                    selectedArticle = articleElem;
                }

                articleIdInputDOM.value = selectedArticle.id;
                let value = `${selectedArticle.article_no} | ${selectedArticle.season} | ${selectedArticle.size} | ${selectedArticle.category} | ${formatNumbersDigitLess(selectedArticle.quantity)} (pcs) | Rs. ${formatNumbersWithDigits(selectedArticle.sales_rate, 1, 1)}`;
                articleSelectInputDOM.value = value;

                if (articleElem.dataset?.json) {
                    closeModal('modalForm');
                    document.querySelector('input[name="work_name"]').disabled = false;
                } else {
                    articleSelectInputDOM.disabled = true;
                }

                const ul = document.querySelector('ul[data-for="work"]');

                ul.innerHTML = `
                    <li data-for="work" data-value="" onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg hover:bg-[var(--h-bg-color)] selected">-- Select Work --</li>
                `;

                allWorks.forEach(([workKey, workValue]) => {
                    let shouldShowWork = false;

                    // Step 1a: Check if this work exists in production
                    const productionItems = selectedArticle.production.filter(
                        p => p.work.title === workValue.text
                    );

                    const cuttingNotStarted = selectedArticle.production.every(
                        p => p.work.title !== "Cutting"
                    );

                    // Step 1b: Decide whether to show this work
                    const missingParts = () => {
                        if (productionItems.length === 0)  {return [];}
                        const categorySeasonKey = `${selectedArticle.category}_${selectedArticle.season}`;
                        const parts = allParts
                            .filter(([key]) => key === categorySeasonKey)
                            .flatMap(([_, value]) => value);

                        const existingParts = productionItems
                            .flatMap(p => p.parts)
                            .filter(p => parts.includes(p));

                        return parts.filter(p => !existingParts.includes(p));
                    };

                    shouldShowWork = !articleElem.dataset?.json ? true : workValue.text === "Cutting";

                    if (shouldShowWork) {
                        ul.innerHTML += `
                            <li data-for="work" data-value="${workKey}"
                                onmousedown="selectThisOption(this)"
                                class="py-2 px-3 cursor-pointer rounded-lg hover:bg-[var(--h-bg-color)]">
                                ${workValue.text}
                            </li>
                        `;
                    }
                });

                if (ul.children.length == 1) {
                    ul.innerHTML = ``;
                    document.querySelector('input[name="work_name"]').value = '';
                    document.querySelector('input[name="work_name"]').disabled = true;
                }

                const selectedLi = ul.querySelector('li.selected');
                if (selectedLi) {
                    selectedLi.dispatchEvent(new MouseEvent('mousedown', { bubbles: true }));
                }
            }

            function trackWorkState(elem) {
                if (elem.value != '') {
                    let correctWorkers = allWorkers.filter(worker => worker.data_option.type.id == elem.value);

                    if (correctWorkers.length > 0) {
                        document.querySelector('input[name="worker_name"]').disabled = false;
                        const ul = document.querySelector('ul[data-for="worker"]');

                        ul.innerHTML = `
                            <li data-for="worker" data-value="" onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg hover:bg-[var(--h-bg-color)] selected">-- Select Worker --</li>
                        `;

                        correctWorkers.forEach((worker) => {
                            if (selectedArticle.production.find(p => p.work.id == elem.value && p.receive_date == null && p.worker_id == worker.data_option.id)) {
                                ul.innerHTML += `
                                    <li data-for="worker" data-value="${worker.data_option.id}" data-option='${JSON.stringify(worker.data_option)}' onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg hover:bg-[var(--h-bg-color)] selected">${worker.text}</li>
                                `;
                            } else {
                                if (worker.data_option.type.title == 'Cutting') {
                                    ul.innerHTML += `
                                        <li data-for="worker" data-value="${worker.data_option.id}" data-option='${JSON.stringify(worker.data_option)}' onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg hover:bg-[var(--h-bg-color)]">${worker.text}</li>
                                    `;
                                }
                            }
                        })

                        const selectedLi = ul.querySelector('li.selected');
                        if (selectedLi) {
                            selectedLi.dispatchEvent(new MouseEvent('mousedown', { bubbles: true }));
                        }
                    } else {
                        document.querySelector('input[name="worker_name"]').disabled = true;
                        document.querySelector('input[name="worker_name"]').value = '';
                    }
                    generateSecondStep(elem.closest('.selectParent').querySelector('li.selected')?.textContent.trim());

                    let filteredRates = allRates.filter(rate => rate.type.id == elem.value && rate.categories.includes(selectedArticle.category) && rate.seasons.includes(selectedArticle.season) && rate.sizes.includes(selectedArticle.size));
                    let selectRateNameDom = document.querySelector('input[name="select_rate_name"]');

                    if (selectRateNameDom) {
                        let ratesUL = document.querySelector('ul[data-for="select_rate"]');
                        selectRateNameDom.value = '-- Select Rates --';
                        selectRateNameDom.disabled = false;
                        ratesUL.innerHTML = `
                            <li data-for="select_rate" data-value="" onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg hover:bg-[var(--h-bg-color)] selected">-- Select Rates --</li>
                        `;
                        if (filteredRates.length > 0) {
                            filteredRates.forEach((rate) => {
                                ratesUL.innerHTML += `
                                    <li data-for="select_rate" data-value="${rate.id}" onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg hover:bg-[var(--h-bg-color)]">${rate.title} | ${rate.rate}</li>
                                `;
                            })
                        }
                        ratesUL.innerHTML += `
                            <li data-for="select_rate" data-value="0" onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg hover:bg-[var(--h-bg-color)]">Other</li>
                        `;
                    }

                    if (document.querySelector('input[data-for="ticket"]').value != '') {
                        document.querySelector('input[name="worker_name"]').disabled = true;
                    }
                }
            }

            function trackWorkerState(elem) {
                const selectParent = elem.closest('.selectParent');
                const selectedWorkerData = JSON.parse(selectParent.querySelector('li.selected').dataset.option || '{}');
                document.getElementById('balance').value = formatNumbersWithDigits(selectedWorkerData?.balance || 0, 1, 1);
                tags = selectedWorkerData.taags || [];
                elem.value !== '' && gotoStep(2);
                selectedTagsArray = [];
            }

            function generateSelectTagModal(animate = 'animate') {
                let data = tags;

                let cardData = [];

                if (data.length > 0) {
                    cardData.push(...data.map(item => {
                        return {
                            id: item.tag,
                            name: item.tag,
                            details: {
                                'Supplier': item.supplier_name,
                                'Available Quantity': item.available_quantity,
                                'Selected Quantity': item.selected_quantity || 0,
                            },
                            data: item,
                            onclick: `generateQuantityModal(${JSON.stringify(item)})`,
                        };
                    }));
                }

                materialModalData = {
                    id: 'tagModalForm',
                    cards: {name: 'Tags', count: 3, data: cardData},
                }

                createModal(materialModalData, animate);
            }

            function generateQuantityModal(item) {
                let quantityModalData = {
                    id: 'quantityModalForm',
                    name: 'Enter Quantity',
                    class: 'h-auto',
                    fields: [
                        {
                            category: 'input',
                            label: 'Unit',
                            value: item.unit,
                            disabled: true,
                        },
                        {
                            category: 'input',
                            id: 'tag',
                            name: 'tag',
                            type: 'hidden',
                            value: item.tag,
                        },
                        {
                            category: 'input',
                            label: 'Avalaible Quantity',
                            value: item.available_quantity,
                            disabled: true,
                        },
                        {
                            category: 'explicitHtml',
                            html: `
                                <x-input label="Quantity" name="quantity" id="quantity" type="number" placeholder="Enter quantity" required oninput="validateInput(this)"/>
                            `,
                            focus: 'quantity',
                        },
                    ],
                    fieldsGridCount: '1',
                    bottomActions: [
                        {id: 'add', text: 'Add', onclick: 'selectWithQuantity(this)'},
                    ],
                }

                createModal(quantityModalData)

                document.querySelector('input[name="quantity"]').value = item.selected_quantity || '';
                document.querySelector('input[name="quantity"]').dataset.validate = `max:${item.available_quantity + (item.selected_quantity || 0)}`;
            }

            function selectWithQuantity(elem) {
                const inputs = elem.closest('form').querySelectorAll('input:not([disabled])');
                let detail = {};

                inputs.forEach(input => {
                    const name = input.getAttribute('name');
                    if (name != null && name != '_token') {
                        const value = input.value;

                        if (name == "quantity") {
                            detail[name] = parseInt(value);
                        } else {
                            detail[name] = value;
                        }
                    }
                });

                let existingTag = selectedTagsArray.find(tag => tag.tag == detail.tag);

                if (detail.quantity > 0) {
                    existingTag ? existingTag.quantity = detail.quantity : selectedTagsArray.push(detail);
                } else if (existingTag) {
                    selectedTagsArray = selectedTagsArray.filter(tag => tag.tag !== detail.tag);
                }
                tags.find(tag => tag.tag === detail.tag).selected_quantity = detail.quantity;
                tags.find(tag => tag.tag === detail.tag).available_quantity -= tags.find(tag => tag.tag === detail.tag).selected_quantity;
                document.querySelector('input[name="tags"]').value = JSON.stringify(selectedTagsArray);
                closeModal('quantityModalForm');
                closeModal('tagModalForm', 'notAnimate');
                generateSelectTagModal('notAnimate');
                document.getElementById('tags').value = selectedTagsArray.length > 0 ? `${selectedTagsArray.length} Selected` : '';
            }

            function generateSecondStep(work) {
                let secondStepHTML = '';
                let minDate = new Date(); // aaj ki date

                if (!new Date(selectedArticle.production.find(p => p.work.title == work && p.receive_date == null)?.issue_date) < minDate) {
                    minDate = selectedArticle.production.find(p => p.work.title == work && p.receive_date == null)?.issue_date;
                } else {
                    minDate = minDate.setDate(minDate.getDate() - 15);
                }

                if (work == 'Cutting') {
                    secondStepHTML += `
                        {{-- article --}}
                        <x-input label="Article" name="article" id="article" disabled value="${selectedArticle.article_no} | ${selectedArticle.season} | ${selectedArticle.size} | ${selectedArticle.category} | ${formatNumbersDigitLess(selectedArticle.quantity)} (pcs) | Rs. ${formatNumbersWithDigits(selectedArticle.sales_rate, 1, 1)}" />

                        {{-- tags  --}}
                        <x-input label="Tags" id="tags" placeholder="Select Tags" class="cursor-pointer" required onclick="generateSelectTagModal()" autoComplete="off" />
                        <input type="hidden" name="tags" value="" />

                        ${!selectedArticle.quantity > 0 ? `
                            {{-- quantity --}}
                            <x-input label="Quantity" name="article_quantity" id="article_quantity" type="number" placeholder="Enter Quantity" required oninput="calculateAmount()" />
                        ` : `
                            {{-- quantity --}}
                            <x-input label="Quantity" name="article_quantity" id="article_quantity" type="number" value="${selectedArticle.quantity}" disabled />
                        `}

                        {{-- select_rate --}}
                        <x-select
                            label="Select Rate"
                            id="select_rate"
                            :options="[]"
                            showDefault
                            onchange="trackSelectRateState(this)"
                        />

                        <div id="titleContainer" class="col-span-full hidden">
                            {{-- title --}}
                            <x-input label="Title" name="title" id="title" placeholder="Enter Title"/>
                        </div>

                        {{-- rate --}}
                        <x-input label="Rate" name="rate" id="rate" readonly placeholder="Rate" oninput="calculateAmount()" dataValidate="numeric" />

                        {{-- amount --}}
                        <x-input label="Amount" name="amount" id="amount" readonly placeholder="Amount" dataValidate="required|amount" oninput="validateInput(this)" />

                        {{-- receive_date --}}
                        <x-input label="Receving Date" name="receive_date" id="receive_date" required type="date" validateMin min="2024-01-01" validateMax max="{{ now()->toDateString() }}" />

                        {{-- parts --}}
                        <x-input label="Parts" id="parts" withCheckbox :checkBoxes="[]" required />
                        <input type="hidden" name="parts" id="dbParts" value="[]" />
                    `;
                } else {
                    secondStepHTML += `
                        {{-- article --}}
                        <x-input label="Article" name="article" id="article" disabled value="${selectedArticle.article_no} | ${selectedArticle.season} | ${selectedArticle.size} | ${selectedArticle.category} | ${formatNumbersDigitLess(selectedArticle.quantity)} (pcs) | Rs. ${formatNumbersWithDigits(selectedArticle.sales_rate, 1, 1)}" />

                        {{-- quantity --}}
                        <x-input label="Quantity" name="article_quantity" id="article_quantity" type="number" value="${selectedArticle.quantity}" disabled />

                        {{-- title --}}
                        <x-input label="Title" name="title" id="title" placeholder="Enter Title"/>

                        {{-- rate --}}
                        <x-input label="Rate" name="rate" id="rate" placeholder="Rate" oninput="calculateAmount()" dataValidate="numeric" />

                        {{-- amount --}}
                        <x-input label="Amount" name="amount" id="amount" readonly placeholder="Amount" dataValidate="required|amount" oninput="validateInput(this)" />

                        {{-- receive_date --}}
                        <x-input label="Receving Date" name="receive_date" id="receive_date" required type="date" validateMin min="${minDate}" validateMax max="{{ now()->toDateString() }}" />

                        {{-- parts --}}
                        <x-input label="Parts" id="parts" withCheckbox :checkBoxes="[]" required />
                        <input type="hidden" name="parts" id="dbParts" value="[]" />
                    `;
                }
                document.getElementById('secondStep').innerHTML = secondStepHTML;
                let selectedTicket = JSON.parse(document.getElementById('ticket')?.closest('.selectParent').querySelector('li.selected')?.dataset.option || '{}');
                const checkboxes_container = document.querySelector('.checkboxes_container');
                let parts = [];
                let partsClutter = '';

                if (selectedTicket && Object.keys(selectedTicket).length > 0) {
                    parts = selectedTicket.parts;
                } else {
                    const partKey = selectedArticle.category + '_' + selectedArticle.season;

                    const allparts = allParts
                        .filter(([key]) => key === partKey)
                        .flatMap(([_, value]) => value);

                    const existingParts = selectedArticle.production
                        .flatMap(p => p.parts)
                        .filter(p => allparts.includes(p));


                    if (work == 'Cutting') {
                        parts = allparts.filter(p => !existingParts.includes(p));
                    } else {
                        const showingParts = selectedArticle.production
                        .filter(p => p.work.title == work)
                        .flatMap(p => p.parts)
                        .filter(p => allparts.includes(p));

                        const existingParts = selectedArticle.production
                        .filter(p => p.work.title == work)
                        .filter(p => p.receive_date !== null)
                        .flatMap(p => p.parts)
                        .filter(p => allparts.includes(p));

                        parts = showingParts.filter(p => !existingParts.includes(p));
                    }
                }

                parts.forEach((part) => {
                    if (part) {
                        partsClutter += `
                            <label class="flex items-center gap-2 cursor-pointer rounded-md border border-[var(--h-bg-color)] bg-[var(--h-bg-color)] px-2 py-[0.1875rem] shadow-sm transition hover:shadow-md hover:border-primary">
                                <input
                                    type="checkbox"
                                    onchange="toggleThisCheckbox(this)"
                                    data-checkbox="${part}"
                                    class="checkbox appearance-none bg-[var(--secondary-bg-color)] w-4 h-4 border border-gray-600 rounded-sm checked:bg-[var(--primary-color)] transition"
                                />
                                <span class="text-sm font-medium text-[var(--secondary-text)] capitalize">
                                    ${part}
                                </span>
                            </label>
                        `;
                    }
                });

                if (checkboxes_container) {
                    checkboxes_container.innerHTML = partsClutter;
                }
            }

            function trackSelectRateState(elem) {
                const rateInput = document.getElementById('rate');
                const titleInput = document.getElementById('title');
                const titleContainer = document.getElementById('titleContainer');

                if (elem.value !== '' && elem.value !== '0') {
                    titleContainer.classList.add('hidden');
                    rateInput.readOnly = true;
                    const selectedText = elem.closest('.selectParent').querySelector('li.selected').textContent;
                    rateInput.value = selectedText.split('|')[1].trim();
                    titleInput.value = selectedText.split('|')[0].trim();
                    calculateAmount();
                } else if (elem.value === '0') {
                    titleContainer.classList.remove('hidden');
                    titleInput.value = '';
                    rateInput.value = '';
                    rateInput.readOnly = false;
                } else {
                    titleInput.value = '';
                    rateInput.value = '';
                    titleContainer.classList.add('hidden');
                    rateInput.readOnly = true;
                }
            }

            function calculateAmount() {
                validateInput(document.getElementById('article_quantity'));
                let quantity = parseInt(document.getElementById('article_quantity').value);
                let rate = parseInt(document.getElementById('rate').value);
                document.getElementById('amount').value = rate * quantity;
            }

            function trackTicketState(elem) {
                if (elem.value != '') {
                    let selectedTicket = JSON.parse(elem.parentElement.querySelector('li.selected').dataset.option);
                    selectThisArticle(selectedTicket.article);
                    selectThisOption(document.querySelector(`li[data-value="${selectedTicket.work_id}"]`));
                    selectThisOption(document.querySelector(`li[data-value="${selectedTicket.worker_id}"]`));
                }
            }

            function validateForNextStep() {
                return true;
            }

            let selectedPartsArray = [];

            function toggleThisCheckbox(checkbox) {
                const dbPartsInput = document.getElementById('dbParts');

                const checkboxValue = checkbox.dataset.checkbox;
                if (checkbox.checked) {
                    if (!selectedPartsArray.includes(checkboxValue)) {
                        selectedPartsArray.push(checkboxValue);
                    }
                } else {
                    selectedPartsArray = selectedPartsArray.filter(part => part !== checkboxValue);
                }

                dbPartsInput.value = JSON.stringify(selectedPartsArray);
            }
        </script>
    @endif
@endsection
