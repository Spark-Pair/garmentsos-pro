@extends('app')
@section('title', 'Show Bank Accounts | ' . $client_company->name)
@section('content')
    @php
        $searchFields = [
            "Account Title" => [
                "id" => "account_title",
                "type" => "text",
                "placeholder" => "Enter account title",
                "oninput" => "runDynamicFilter()",
                "dataFilterPath" => "account_title",
            ],
            "Category" => [
                "id" => "category",
                "type" => "select",
                "options" => [
                            'self' => ['text' => 'Self'],
                            'customer' => ['text' => 'Customer'],
                            'supplier' => ['text' => 'Supplier'],
                        ],
                "onchange" => "runDynamicFilter()",
                "dataFilterPath" => "category",
            ],
            "Name" => [
                "id" => "name",
                "type" => "text",
                "placeholder" => "Enter name",
                "oninput" => "runDynamicFilter()",
                "dataFilterPath" => "name",
            ],
            "Account No" => [
                "id" => "account_no",
                "type" => "text",
                "placeholder" => "Enter account no",
                "oninput" => "runDynamicFilter()",
                "dataFilterPath" => "account_no",
            ],
            "Bank" => [
                "id" => "bank",
                "type" => "select",
                "options" => $bank_options,
                "onchange" => "runDynamicFilter()",
                "dataFilterPath" => "bank",
            ],
            'Status' => [
                'id' => 'status',
                'type' => 'select',
                'options' => [
                    'active' => ['text' => 'Active'],
                    'in_active' => ['text' => 'In Active'],
                ],
                'dataFilterPath' => 'status',
            ],
            "Date Range" => [
                "id" => "date_range_start",
                "type" => "date",
                "id2" => "date_range_end",
                "type2" => "date",
                "oninput" => "runDynamicFilter()",
                "dataFilterPath" => "date",
            ]
        ];
    @endphp
    <div>
        <div class="w-[80%] mx-auto">
            <x-search-header heading="Bank Accounts" :search_fields=$searchFields/>
        </div>

        <!-- Main Content -->
        <section class="text-center mx-auto">
            <div
                class="show-box mx-auto w-[80%] h-[70vh] bg-[var(--secondary-bg-color)] border border-[var(--glass-border-color)]/20 rounded-xl shadow pt-8.5 relative">
                <x-form-title-bar printBtn title="Show Bank Accounts" changeLayoutBtn layout="{{ $authLayout }}" resetSortBtn />

                <div class="absolute bottom-0 right-0 flex items-center justify-between gap-2 w-fll z-50 p-3 w-full pointer-events-none">
                    <x-section-navigation-button direction="right" id="info" icon="fa-info" />
                    <x-section-navigation-button link="{{ route('bank-accounts.create') }}" title="Add New Account" icon="fa-plus" />
                </div>

                <div class="details h-full z-40">
                    <div class="container-parent h-full">
                        <div class="card_container px-3 h-full flex flex-col">
                            <div id="table-head" class="grid grid-cols-9 bg-[var(--h-bg-color)] rounded-lg text-center font-medium py-2 hidden mt-4">
                                <div class="cursor-pointer" onclick="sortByThis(this)">Date</div>
                                <div class="cursor-pointer col-span-2" onclick="sortByThis(this)">Account Title</div>
                                <div class="cursor-pointer col-span-2" onclick="sortByThis(this)">Name</div>
                                <div class="cursor-pointer" onclick="sortByThis(this)">Bank</div>
                                <div class="cursor-pointer" onclick="sortByThis(this)">Category</div>
                                <div class="cursor-pointer" onclick="sortByThis(this)">Balance</div>
                                <div class="cursor-pointer" onclick="sortByThis(this)">Status</div>
                            </div>
                            <p id="noItemsError" style="display: none" class="text-sm text-[var(--border-error)] mt-3">No items found</p>
                            <div class="overflow-y-auto grow my-scrollbar-2">
                                <div class="search_container grid grid-cols-1 sm:grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <script>
        let currentUserRole = '{{ Auth::user()->role }}';
        let authLayout = '{{ $authLayout }}';


        function createRow(data) {
            return `
            <div id="${data.id}" oncontextmenu='${data.oncontextmenu || ""}' onclick='${data.onclick || ""}'
                class="item row relative group grid grid-cols-9 border-b border-[var(--h-bg-color)] items-center text-center py-2 cursor-pointer hover:bg-[var(--h-secondary-bg-color)] transition-all fade-in ease-in-out"
                data-json='${JSON.stringify(data)}'>

                <span>${data.date}</span>
                <span class="col-span-2">${data.name}</span>
                <span class="capitalize col-span-2">${data.details["Name"]}</span>
                <span class="capitalize">${data.bank}</span>
                <span class="capitalize">${data.details["Category"]}</span>
                <span>${data.details["Balance"]}</span>
                <span class="capitalize">${data.status}</span>
            </div>`;
        }

        // const fetchedData = [];
        // let allDataArray = fetchedData.map(item => {
        //     return {
        //         id: item.id,
        //         uId: item.id,
        //         status: item.status,
        //         name: item.account_title,
        //         details: {
        //             'Name': item.sub_category?.customer_name ?? item.sub_category?.supplier_name ?? item.account_title,
        //             'Category': item.category,
        //             'Balance': formatNumbersWithDigits(item.balance, 1, 1) ?? 0,
        //         },
        //         accountNo: item.account_no ?? 0,
        //         bank: item.bank.title + " | " + item.bank.short_title,
        //         date: item.date,
        //         chqbkSerialStart: item.chqbk_serial_start ?? 0,
        //         chqbkSerialEnd: item.chqbk_serial_end ?? 0,
        //         available_cheques: item.available_cheques ?? [],
        //         oncontextmenu: "generateContextMenu(event)",
        //         onclick: "generateModal(this)",
        //         visible: true,
        //     };
        // });

        const activeAccounts = allDataArray.filter(account => account.status === 'active');

        let infoDom = document.getElementById('info').querySelector('span');
        infoDom.textContent = `Total Bank Account: ${allDataArray.length} | Active: ${activeAccounts.length}`;

        function generateContextMenu(e) {
            let item = e.target.closest('.item');
            let data = JSON.parse(item.dataset.json);

            let contextMenuData = {
                item: item,
                data: data,
                action: "{{ route('update-bank-account-status') }}",
                x: e.pageX,
                y: e.pageY,
            };

            if (data.details['Category'] === 'self') {
                contextMenuData.actions = [
                    {id: 'update-cheque-book-serial', text: 'Update Serial', onclick: `generateUpdateChequeBookSerialModel(${JSON.stringify(data)})`},
                ];
            }

            createContextMenu(contextMenuData);
        }

        function generateModal(item) {
            let data = JSON.parse(item.dataset.json);

            let modalData = {
                id: 'modalForm',
                uId: data.id,
                status: data.status,
                name: data.name,
                action: "{{ route('update-bank-account-status') }}",
                details: {
                    'Name': data.details['Name'],
                    'Category': data.details['Category'],
                    'Bank': data.bank,
                    'Date': data.date,
                    'Balance': data.details['Balance'],
                },
            }

            if (data.details['Category'] === 'self') {
                modalData.details['Account No'] = data.account_no;
                modalData.details['Cheque Book Serial'] = data.chqbkSerialStart + ' - ' + data.chqbkSerialEnd;
                modalData.bottomActions = [
                    {id: 'update-cheque-book-serial', text: 'Update Serial', onclick: `generateUpdateChequeBookSerialModel(${JSON.stringify(data)})`},
                ];
            }

            createModal(modalData);
        }

        function generateUpdateChequeBookSerialModel(data) {
            let modalData = {
                id: 'updateChequeBookSerialModelForm',
                class: 'h-auto',
                method: 'POST',
                action: '{{ url("bank-accounts") }}/' + data.id + '/update-serial',
                name: 'Update Serial',
                fields: [
                    {
                        category: 'input',
                        label: 'Account Title',
                        value: data.name,
                        disabled: true,
                    },
                    {
                        category: 'input',
                        type: 'hidden',
                        name: '_method',
                        value: 'PUT',
                    },
                    {
                        category: 'explicitHtml',
                        html: `
                            <!-- Cheque Book Serial Input -->
                            <div id="cheque_book_serial" class="form-group">
                                <label for="cheque_book_serial_start" class="block font-medium text-[var(--secondary-text)] mb-2">
                                    Cheque Book Serial (Start - End)
                                </label>

                                <div class="flex gap-4">
                                    <!-- Start Serial Input -->
                                    <input
                                        type="number"
                                        id="cheque_book_serial_start"
                                        name="cheque_book_serial[start]"
                                        placeholder="Start"
                                        class="w-full rounded-lg bg-[var(--h-bg-color)] border-gray-600 text-[var(--text-color)] px-3 py-2 border focus:ring-2 focus:ring-primary focus:border-transparent transition-all duration-300 ease-in-out"
                                    />

                                    <!-- End Serial Input -->
                                    <input
                                        type="number"
                                        id="cheque_book_serial_end"
                                        name="cheque_book_serial[end]"
                                        placeholder="End"
                                        class="w-full rounded-lg bg-[var(--h-bg-color)] border-gray-600 text-[var(--text-color)] px-3 py-2 border focus:ring-2 focus:ring-primary focus:border-transparent transition-all duration-300 ease-in-out"
                                    />
                                </div>

                                <!-- Error Message -->
                                <div id="cheque_book_serial_error" class="text-[var(--border-error)] text-xs mt-1 hidden"></div>
                            </div>
                        `,
                    },
                ],
                fieldsGridCount: '2',
                bottomActions: [
                    {id: 'update-serial-btn', text: 'Update Serial', type: 'submit'}
                ]
            }

            createModal(modalData);
        }
    </script>
@endsection
