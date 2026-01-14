@extends('app')
@section('title', 'Show Articles | ' . $client_company->name)
@section('content')
    @php
        $searchFields = [
            "Article" => [
                "id" => "article",
                "type" => "text",
                "placeholder" => "Enter article no.",
                "oninput" => "runDynamicFilter()",
                "dataFilterPath" => "name",
            ],
            "Processed By" => [
                "id" => "processed_by",
                "type" => "text",
                "placeholder" => "Enter article no.",
                "oninput" => "runDynamicFilter()",
                "dataFilterPath" => "processed_by",
            ],
            "Category" => [
                "id" => "category",
                "type" => "select",
                "options" => app('article')->categories,
                "onchange" => "runDynamicFilter()",
                "dataFilterPath" => "category",
            ],
            "Season" => [
                "id" => "season",
                "type" => "select",
                "options" => app('article')->seasons,
                "onchange" => "runDynamicFilter()",
                "dataFilterPath" => "season",
            ],
            "Size" => [
                "id" => "size",
                "type" => "select",
                "options" => app('article')->sizes,
                "onchange" => "runDynamicFilter()",
                "dataFilterPath" => "size",
            ],
            "Date Range" => [
                "id" => "date_range_start",
                "type" => "date",
                "id2" => "date_range_end",
                "type2" => "date",
                "oninput" => "runDynamicFilter()",
                "dataFilterPath" => "ready_date",
            ]
        ];
    @endphp

    {{-- header --}}
    <div class="w-[80%] mx-auto">
        <x-search-header heading="Articles" :search_fields=$searchFields/>
    </div>

    <!-- Main Content -->
    <section class="text-center mx-auto ">
        <div
            class="show-box mx-auto w-[80%] h-[70vh] bg-[var(--secondary-bg-color)] border border-[var(--glass-border-color)]/20 rounded-xl shadow pt-8.5 relative">
            <x-form-title-bar printBtn title="Show Articles" changeLayoutBtn layout="{{ $authLayout }}" resetSortBtn  />

            @if (count($articles) > 0)
                <div class="absolute bottom-0 right-0 flex items-center justify-between gap-2 w-fll z-50 p-3 w-full pointer-events-none">
                    <x-section-navigation-button direction="right" id="info" icon="fa-info" />
                    <x-section-navigation-button link="{{ route('articles.create') }}" title="Add New Article" icon="fa-plus" />
                </div>

                <div class="details h-full z-40">
                    <div class="container-parent h-full">
                        <div class="card_container px-3 h-full flex flex-col">
                            <div id="table-head" class="grid grid-cols-6 bg-[var(--h-bg-color)] rounded-lg font-medium py-2 hidden mt-4">
                                <div class="text-center cursor-pointer" onclick="sortByThis(this)">Article No</div>
                                <div class="text-center cursor-pointer" onclick="sortByThis(this)">Category</div>
                                <div class="text-center cursor-pointer" onclick="sortByThis(this)">Season</div>
                                <div class="text-center cursor-pointer" onclick="sortByThis(this)">Size</div>
                                <div class="text-center cursor-pointer" onclick="sortByThis(this)">Sales Rate</div>
                                <div class="text-center cursor-pointer" onclick="sortByThis(this)">Processed By</div>
                            </div>
                            <p id="noItemsError" style="display: none" class="text-sm text-[var(--border-error)] mt-3">No items found</p>
                            <div class="overflow-y-auto grow my-scrollbar-2">
                                <div class="search_container grid grid-cols-1 sm:grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5 grow">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @else
                <div class="no-records-message w-full h-full flex flex-col items-center justify-center gap-2">
                    <h1 class="text-sm text-[var(--secondary-text)] capitalize">No Article Found</h1>
                    <a href="{{ route('articles.create') }}"
                        class="text-sm bg-[var(--primary-color)] text-[var(--text-color)] px-4 py-2 rounded-md hover:bg-[var(--h-primary-color)] hover:scale-105 hover:mb-2 transition-all duration-300 ease-in-out font-semibold">Add
                        New</a>
                </div>
            @endif
        </div>
    </section>

    <script>
        let currentUserRole = '{{ Auth::user()->role }}';
        let authLayout = '{{ $authLayout }}';

        function createRow(data) {
            return `
            <div id="${data.id}" oncontextmenu='${data.oncontextmenu || ""}' onclick='${data.onclick || ""}'
                class="item row relative group grid text- grid-cols-6 border-b border-[var(--h-bg-color)] items-center py-2 cursor-pointer hover:bg-[var(--h-secondary-bg-color)] transition-all fade-in ease-in-out"
                data-json='${JSON.stringify(data)}'>

                <span class="text-center">${data.name}</span>
                <span class="text-center">${data.details["Category"]}</span>
                <span class="text-center">${data.details["Season"]}</span>
                <span class="text-center">${data.details["Size"]}</span>
                <span class="text-center">${data.sales_rate}</span>
                <span class="text-center">${data.processed_by}</span>
            </div>`;
        }

        const fetchedData = @json($articles);
        let allDataArray = fetchedData.map(item => {
            return {
                id: item.id,
                image: item.image == 'no_image_icon.png' ? '/images/no_image_icon.png' : `/storage/uploads/images/${item.image}`,
                name: item.article_no,
                status: item.sales_rate == 0.00 ? 'no_rate' : 'transparent',
                category: item.category,
                season: item.season,
                size: item.size,
                details: {
                    'Category': item.category?.replaceAll('_', ' ')?.replace(/\b\w/g, item => item.toUpperCase()),
                    'Season': item.season?.replace(/\b\w/g, item => item.toUpperCase()),
                    'Size': item.size?.replaceAll('_', '-')?.replace(/\b\w/g, item => item.toUpperCase()),
                },
                sales_rate: item.sales_rate,
                processed_by: item.processed_by,
                fabric_type: item.fabric_type,
                quantity: item.quantity,
                current_stock: item.quantity - item.ordered_quantity,
                ordered_quantity: item.ordered_quantity,
                ready_date: item.date,
                rates_array: item.rates_array,
                oncontextmenu: "generateContextMenu(event)",
                onclick: "generateModal(this)",
                visible: true,
            };
        });

        let infoDom = document.getElementById('info').querySelector('span');
        infoDom.textContent = `Total Articles: ${allDataArray.length}`;

        function generateContextMenu(e) {
            e.preventDefault();
            let item = e.target.closest('.item');
            let data = JSON.parse(item.dataset.json);

            let contextMenuData = {
                item: item,
                data: data,
                x: e.pageX,
                y: e.pageY,
                actions: [
                    {id: 'Update Image', text: 'Update Image', onclick: `generateUpdateImageModal(${JSON.stringify(data)})`},
                    {id: 'edit', text: 'Edit Article'},
                ],
            };


            if (data.sales_rate == 0) {
                contextMenuData.actions.push({id: 'add-rates', text: 'Add Rates', onclick: `generateAddRatesModal(${JSON.stringify(data)})`});
            }

            createContextMenu(contextMenuData);
        }

        function generateModal(item) {
            let data = JSON.parse(item.dataset.json);

            let tableBody = [];

            tableBody = data.rates_array.map((item, index) => {
                return [
                    {data: index+1, class: 'w-1/5'},
                    {data: item.title, class: 'grow ml-5'},
                    {data: item.rate, class: 'w-1/4'},
                ]
            })

            let modalData = {
                id: 'modalForm',
                method: "POST",
                image: data.image,
                name: data.name,
                status: data.status,
                class: 'p-5 max-w-5xl h-[27rem]',
                details: {
                    'Category': data.details['Category'],
                    'Season': data.details['Season'],
                    'Size': data.details['Size'],
                    'Sales Rate': data.sales_rate,
                    'hr': '',
                    'Fabric Type': data.fabric_type,
                    'Quantity-Pcs.': data.quantity,
                    'Current Stock-Pcs.': data.current_stock,
                    'Ready Date': formatDate(data.ready_date),
                },
                table: {
                    name: 'Rates',
                    headers: [
                        { label: "#", class: "w-1/5" },
                        { label: "Title", class: "grow ml-5" },
                        { label: "Rate", class: "w-1/4" }
                    ],
                    body: tableBody,
                },
                bottomActions: [
                    {id: 'update-image', text: 'Update Image', onclick: `generateUpdateImageModal(${JSON.stringify(data)})`},
                ],
            }

            if (data.ordered_quantity == 0) {
                modalData.bottomActions.push({
                    id: 'edit',
                    text: 'Edit Article',
                    dataId: data.id
                });
            }

            if (data.sales_rate == 0) {
                modalData.bottomActions.push({id: 'add-rates', text: 'Add Rates', onclick: `generateAddRatesModal(${JSON.stringify(data)})`});
            }

            createModal(modalData);
        }

        let ratesArray = [];

        function enableDisableBtn(elem) {
            const formDom = elem.closest('form');

            const btnDom = formDom.querySelector('#addRate');
            const titleInpDom = formDom.querySelector('#title');
            const rateInpDom = formDom.querySelector('#rate');

            if (titleInpDom.value != '' && rateInpDom.value != '') {
                btnDom.disabled = false;
            } else {
                btnDom.disabled = true;
            }
        }

        function trackRateState(elem) {
            enableDisableBtn(elem);

            if (elem.dataset.listenerAdded === 'true') return;

            elem.dataset.listenerAdded = 'true'; // Mark as handled

            const formDom = elem.closest('form');
            const addBtn = formDom.querySelector('#addRate');

            elem.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    e.stopPropagation();
                    addRate(addBtn);
                }
            });
        }

        function deleteRate(elem) {
            elem.closest('.flex')
            const formDom = elem.closest('form');
            const titleInpDom = formDom.querySelector('#title');

            titleInpDom.focus();

            let rate = parseFloat(elem.parentElement.previousElementSibling.innerText);

            let title = elem.parentElement.previousElementSibling.previousElementSibling.innerText;

            ratesArray = ratesArray.filter(rate => rate.title !== title);

            renderRateList(elem.closest('#table-body'));
        }

        function renderRateList(tableBody) {
            if (ratesArray.length > 0) {
                tableBody.innerHTML = '';
                ratesArray.forEach((rate, index) => {
                    tableBody.innerHTML += `
                        <div class="flex justify-between items-center border-t border-gray-600 py-2 px-4">
                            <div class="w-1/5">${index + 1}</div>
                            <div class="grow ml-5">${rate.title}</div>
                            <div class="w-1/4">${formatNumbersWithDigits(rate.rate, 2, 2)}</div>
                            <div class="w-[10%] text-center">
                                <button onclick="deleteRate(this)" type="button" class="text-[var(--danger-color)] text-xs px-2 py-1 rounded-lg hover:text-[var(--h-danger-color)] transition-all duration-300 ease-in-out cursor-pointer">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    `;
                });
            } else {
                tableBody.innerHTML = `
                    <div class="flex justify-between items-center border-t border-gray-600 py-2 px-4">
                        <div class="grow text-center text-[var(--border-error)]">No Rates yet.</div>
                    </div>
                `;
            }
            renderCalcBottom(tableBody.closest('form').querySelector('#calc-bottom'));
            let formDom = tableBody.closest('form');
            let ratesArrayInpDom = formDom.querySelector('input[name=rates_array]');
            ratesArrayInpDom.value = JSON.stringify(ratesArray);
        }

        function renderCalcBottom(calcBottomElem) {
            let totalInpDom = calcBottomElem.querySelector('#total');
            let salesRateInpDom = calcBottomElem.querySelector('#sales_rate');
            let pcsPerPacketInpDom = calcBottomElem.querySelector('#pcs_per_packet');

            totalInpDom.value = ratesArray.reduce((sum, item) => sum + (parseFloat(item.rate) || 0), 0).toFixed(2);
            salesRateInpDom.value = ratesArray.reduce((sum, item) => sum + (parseFloat(item.rate) || 0), 0).toFixed(2);
        }

        function addRate(elem) {
            let rateObject = {};
            const formDom = elem.closest('form');
            const titleInpDom = formDom.querySelector('#title');
            const rateInpDom = formDom.querySelector('#rate');
            const tableBodyDom = formDom.querySelector('#table-body');
            rateObject.title = titleInpDom.value;
            rateObject.rate = rateInpDom.value;
            ratesArray.push(rateObject);
            titleInpDom.value = '';
            rateInpDom.value = '';
            titleInpDom.focus();
            renderRateList(tableBodyDom)
        }

        function generateAddRatesModal(item) {
            let modalData = {
                id: 'addRatesModalForm',
                method: "POST",
                action: "{{ route('add-rate') }}",
                class: 'max-w-3xl h-[37rem]',
                name: 'Add Rates',
                fields: [
                    {
                        category: 'input',
                        value: item.name + ' | ' + item.details.Category + ' | ' + item.details.Season + ' | ' + item.details.Size,
                        full: true,
                        disabled: true,
                    },
                    {
                        category: 'hr',
                    },
                    {
                        category: 'input',
                        type: 'hidden',
                        name: 'article_id',
                        value: item.id,
                    },
                    {
                        category: 'input',
                        type: 'hidden',
                        name: 'rates_array',
                        value: '[]',
                    },
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
                        category: 'input',
                        label: 'Rate',
                        id: 'rate',
                        type: 'number',
                        placeholder: 'Enter Rate',
                        oninput: 'trackRateState(this)',
                        btnId: 'addRate',
                        onclick: 'addRate(this)',
                    },
                ],
                fieldsGridCount: '2',
                table: {
                    name: 'Rates',
                    headers: [
                        { label: "#", class: "w-1/5" },
                        { label: "Title", class: "grow ml-5" },
                        { label: "Rate", class: "w-1/4" },
                        { label: "Action", class: "w-[10%]" },
                    ],
                    body: [],
                    scrollable: true,
                },
                calcBottom: [
                    {label: 'Total - Rs.', name: 'total', value: '0.00', disabled: true},
                    {label: 'Sales Rate - Rs.', name: 'sales_rate', value: '0.00'},
                    {label: 'Pcs / Packet', name: 'pcs_per_packet', value: '0'},
                ],
                bottomActions: [
                    {id: 'add', text: 'Add', type: 'submit'}
                ],
            }

            createModal(modalData);
        }

        function generateUpdateImageModal(item) {
            let modalData = {
                id: 'updateImageModalForm',
                method: "POST",
                action: "{{ route('update-image') }}",
                class: 'h-auto',
                name: 'Update Image',
                fields: [
                    {
                        category: 'input',
                        value: item.name + ' | ' + item.details.Category + ' | ' + item.details.Season + ' | ' + item.details.Size,
                        full: true,
                        disabled: true,
                    },
                    {
                        category: 'input',
                        type: 'hidden',
                        name: 'article_id',
                        value: item.id,
                    },
                ],
                fieldsGridCount: '2',
                imagePicker: {id: 'image_upload', name: 'image_upload', placeholder: item.image == "no_image_icon.png" ? 'images/no_image_icon.png' : `${item.image}`, uploadText: 'Upload article image'},
                bottomActions: [
                    {id: 'add', text: 'Add', type: 'submit'}
                ],
            }

            createModal(modalData);
        }
    </script>
@endsection
