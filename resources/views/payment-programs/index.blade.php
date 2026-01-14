@extends('app')
@section('title', 'Show Payment Programs | ' . $client_company->name)
@section('content')
    @php
        $categories_options = [
            'self_account' => ['text' => 'Self Account'],
            'supplier' => ['text' => 'Supplier'],
            'waiting' => ['text' => 'Waiting'],
        ];

        $searchFields = [
            'Customer Name' => [
                'id' => 'customer_name',
                'type' => 'text',
                'placeholder' => 'Enter customer name',
                'oninput' => 'runDynamicFilter()',
                'dataFilterPath' => 'customer_name',
            ],
            'Category' => [
                'id' => 'category',
                'type' => 'select',
                'options' => [
                    'supplier' => ['text' => 'Supplier'],
                    'self_account' => ['text' => 'Self Account'],
                    'waiting' => ['text' => 'Waiting'],
                ],
                'onchange' => 'runDynamicFilter()',
                'dataFilterPath' => 'category',
            ],
            'Type' => [
                'id' => 'type',
                'type' => 'select',
                'options' => [
                    'order' => ['text' => 'Order'],
                    'program' => ['text' => 'Program'],
                ],
                'onchange' => 'runDynamicFilter()',
                'dataFilterPath' => 'type',
            ],
            'Beneficiary' => [
                'id' => 'beneficiary',
                'type' => 'text',
                'placeholder' => 'Enter beneficiary',
                'oninput' => 'runDynamicFilter()',
                'dataFilterPath' => 'beneficiary',
            ],
            'Status' => [
                'id' => 'status',
                'type' => 'select',
                'options' => [
                    'paid' => ['text' => 'Paid'],
                    'unpaid' => ['text' => 'Unpaid', 'selected' => true],
                    'overpaid' => ['text' => 'Overpaid'],
                ],
                'onchange' => 'runDynamicFilter()',
                'dataFilterPath' => 'status',
            ],
            'Date Range' => [
                'id' => 'date_range_start',
                'type' => 'date',
                'id2' => 'date_range_end',
                'type2' => 'date',
                'oninput' => 'runDynamicFilter()',
                'dataFilterPath' => 'date',
            ],
        ];
    @endphp
    <div class="w-[80%] mx-auto">
        <x-search-header heading="Payment Programs" :search_fields=$searchFields />
    </div>

    <!-- Main Content -->
    <section class="text-center mx-auto">
        <div
            class="show-box mx-auto w-[80%] h-[70vh] bg-[var(--secondary-bg-color)] border border-[var(--glass-border-color)]/20 rounded-xl shadow pt-8.5 relative">
            <x-form-title-bar printBtn layout="table" title="Show Payment Programs" resetSortBtn />

            @if (count($finalData) > 0)
                <div class="absolute bottom-3 right-3 flex items-center gap-2 w-fll z-50">
                    <x-section-navigation-button link="{{ route('payment-programs.create') }}" title="Add New Program"
                        icon="fa-plus" />
                </div>

                <div class="details h-full z-40">
                    <div class="container-parent h-full">
                        <div class="card_container px-3 h-full flex flex-col">
                            <div id="table-head" class="flex items-center bg-[var(--h-bg-color)] rounded-lg font-medium py-2 hidden mt-4">
                                <div class="w-[10%] cursor-pointer" onclick="sortByThis(this)">Date</div>
                                <div class="w-[8%] cursor-pointer" onclick="sortByThis(this)">O/P No.</div>
                                <div class="w-[19%] cursor-pointer" onclick="sortByThis(this)">Customer</div>
                                <div class="w-[9%] cursor-pointer" onclick="sortByThis(this)">Category</div>
                                <div class="w-[15%] cursor-pointer" onclick="sortByThis(this)">Beneficiary</div>
                                <div class="w-[10%] cursor-pointer" onclick="sortByThis(this)">Amount</div>
                                <div class="w-[10%] cursor-pointer" onclick="sortByThis(this)">Payment</div>
                                <div class="w-[10%] cursor-pointer" onclick="sortByThis(this)">Balance</div>
                                <div class="w-[10%] cursor-pointer" onclick="sortByThis(this)">Status</div>
                            </div>
                            <p id="noItemsError" style="display: none" class="text-sm text-[var(--border-error)] mt-3 cursor-pointer" onclick="sortByThis(this)">No items found</p>
                            <div class="overflow-y-auto grow my-scrollbar-2">
                                <div class="search_container grid grid-cols-1 sm:grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5 grow">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @else
                <div class="no-records-message w-full h-full flex flex-col items-center justify-center gap-2">
                    <h1 class="text-md text-[var(--secondary-text)] capitalize">No Payment Programs yet</h1>
                    <a href="{{ route('payment-programs.create') }}"
                        class="text-sm bg-[var(--primary-color)] text-[var(--text-color)] px-4 py-2 rounded-md hover:bg-[var(--h-primary-color)] hover:scale-105 hover:mb-2 transition-all duration-300 ease-in-out font-semibold">Add
                        New</a>
                </div>
            @endif
        </div>
    </section>

    <script>
        let authLayout = 'table';

        function createRow(data) {
            return `
            <div id="${data.id}" oncontextmenu='${data.oncontextmenu || ""}' onclick='${data.onclick || ""}'
                class="item row relative group flex items-center border-b border-[var(--h-bg-color)] items-center py-2 cursor-pointer hover:bg-[var(--h-secondary-bg-color)] transition-all fade-in ease-in-out"
                data-json='${JSON.stringify(data)}'>

                <span class="w-[10%]">${(data.date)}</span>
                <span class="w-[8%]">${data.o_p_no}</span>
                <span class="w-[19%] text-left">${data.customer_name}</span>
                <span class="w-[9%] capitalize">${data.category.replace(/_/g, ' ')}</span>
                <span class="w-[15%]">${data.beneficiary}</span>
                <span class="w-[10%]">${formatNumbersWithDigits(data.amount, 1, 1)}</span>
                <span class="w-[10%]">${formatNumbersWithDigits(data.payment, 1, 1)}</span>
                <span class="w-[10%]">${formatNumbersWithDigits(data.balance, 1, 1)}</span>
                <span class="w-[10%]">${data.status}</span>
            </div>`;
        }

        const fetchedData = @json($finalData);

        let allDataArray = fetchedData.map(item => {
            const category = item.category || item.payment_programs?.category;
            let beneficiary = null;

            if (item?.category) {
                if (item.category === 'supplier' && item.sub_category?.supplier_name) {
                    beneficiary = item.sub_category.supplier_name;
                } else if (item.category === 'customer' && item.sub_category?.customer_name) {
                    beneficiary = item.sub_category.customer_name;
                } else if (item.category === 'waiting' && item.remarks) {
                    beneficiary = item.remarks;
                } else if (item.category === 'self_account' && item.sub_category?.account_title) {
                    beneficiary = item.sub_category.account_title;
                }
            } else if (item?.payment_programs?.category) {
                const p = item.payment_programs;
                const sub = p.sub_category;
                if (p.category === 'supplier' && sub?.supplier_name) {
                    beneficiary = sub.supplier_name;
                } else if (p.category === 'customer' && sub?.customer_name) {
                    beneficiary = sub.customer_name;
                } else if (p.category === 'waiting' && p.remarks) {
                    beneficiary = p.remarks;
                } else if (p.category === 'self_account' && sub?.account_title) {
                    beneficiary = sub.account_title;
                }
            }

            return {
                id: item.id,
                date: formatDate(item.date, true),
                customer_name: item.customer?.customer_name + ' | ' + item.customer?.city?.title || '',
                o_p_no: item.order_no ? item.order_no + ' | O' : item.program_no ? item.program_no + ' | P' : '-',
                category: category,
                beneficiary: beneficiary || '-',
                amount: item.amount || item.netAmount,
                payment: item.payment || 0,
                balance: item.balance || 0,
                status: item.status || item.payment_programs.status || '-',
                type: item.order_no ? 'order' : item.program_no ? 'program' : '-',
                data: item,
                oncontextmenu: "generateContextMenu(event)",
                onclick: "generateModal(this)",
                visible: true,
            };
        });

        let subCategoryDom;
        let remarksInputDom;

        function getCategoryData(value) {
            if (value != "waiting") {
                subCategoryDom.parentElement.parentElement.classList.remove("hidden");
                remarksInputDom.parentElement.parentElement.classList.add("hidden");

                $.ajax({
                    url: "/get-category-data",
                    type: "POST",
                    data: {
                        _token: "{{ csrf_token() }}",
                        category: value,
                    },
                    success: function(response) {
                        let clutter = `
                            <option value='' selected>
                                -- No option avalaible --
                            </option>
                        `;
                        switch (value) {
                            case 'self_account':
                                if (response.length > 0) {
                                    clutter = '';
                                    clutter += `
                                        <option value='' selected>
                                            -- Select Self Account --
                                        </option>
                                    `;
                                    subCategoryDom.disabled = false;
                                } else {
                                    subCategoryDom.disabled = true;
                                    subCategoryFirstOptDom.textContent = '-- No options available --';
                                }

                                response.forEach(subCat => {
                                    clutter += `
                                        <option value='${subCat.id}'>
                                            ${subCat.account_title} | ${subCat.bank.short_title}
                                        </option>
                                    `;
                                });

                                subCategoryLabelDom.textContent = 'Self Account';
                                subCategoryFirstOptDom.textContent = '-- Select Self Account --';
                                break;

                            case 'supplier':
                                if (response.length > 0) {
                                    clutter = '';
                                    clutter += `
                                        <option value='' selected>
                                            -- Select Supplier --
                                        </option>
                                    `;
                                    subCategoryDom.disabled = false;
                                } else {
                                    subCategoryDom.disabled = true;
                                    subCategoryFirstOptDom.textContent = '-- No options available --';
                                }

                                response.forEach(subCat => {
                                    clutter += `
                                        <option value='${subCat.id}'>
                                            ${subCat.supplier_name} | Balance: ${formatNumbersWithDigits(subCat.balance, 1, 1)}
                                        </option>
                                    `;
                                });

                                subCategoryLabelDom.textContent = 'Supplier';
                                subCategoryFirstOptDom.textContent = '-- Select Supplier --';
                                break;

                            case 'customer':
                                clutter = '';
                                clutter += `
                                    <option value='' selected>
                                        -- Select Customer --
                                    </option>
                                `;

                                response.forEach(subCat => {
                                    if (subCat.id != customerSelectDom.value) {
                                        clutter += `
                                            <option value='${subCat.id}'>
                                                ${subCat.customer_name} | ${subCat.city} | Baalance: ${formatNumbersWithDigits(subCat.balance, 1, 1)}
                                            </option>
                                        `;
                                        subCategoryDom.disabled = false;
                                    }
                                });

                                subCategoryLabelDom.textContent = 'Customer';
                                subCategoryFirstOptDom.textContent = '-- Select Customer --';
                                break;

                            default:
                                break;
                        }

                        subCategoryDom.innerHTML = clutter;
                    }
                });
            } else {
                subCategoryDom.parentElement.parentElement.classList.add("hidden");
                remarksInputDom.parentElement.parentElement.classList.remove("hidden");
            }
        }

        function trackCategoryState(elem) {
            let form = elem.closest('form');
            subCategoryDom = form.querySelector('#sub_category');
            subCategoryLabelDom = subCategoryDom.parentElement.parentElement.querySelector('label');
            subCategoryFirstOptDom = subCategoryDom?.options?.[0];
            customerSelectDom = form.querySelector('#customer_id');
            remarksInputDom = form.querySelector('#remarks');

            if (elem.value != "") {
                subCategoryDom.disabled = false;
                getCategoryData(elem.value);
            } else {
                subCategoryDom.disabled = true;
            }
        }

        function generateUpdateProgramModal(item) {
            let modalData = {
                id: 'updateProgramModalForm',
                class: 'h-auto',
                method: 'POST',
                action: '{{ route("payment-programs.update-program") }}',
                name: 'Update Program',
                fields: [
                    {
                        category: 'input',
                        label: 'Date',
                        id: 'date',
                        value: formatDate(item.date),
                        disabled: true,
                    },
                    {
                        category: 'input',
                        label: 'Customer',
                        name: 'customer_id',
                        id: 'customer_id',
                        value: item.customer_name,
                        disabled: true,
                    },
                    {
                        category: 'select',
                        label: 'Category',
                        name: 'category',
                        id: 'category',
                        options: [@json($categories_options)],
                        onchange: 'trackCategoryState(this)'
                    },
                    {
                        category: 'select',
                        label: 'Disabled',
                        name: 'sub_category',
                        id: 'sub_category',
                        options: [],
                        disabled: true,
                    },
                    {
                        category: 'input',
                        type: 'hidden',
                        name: 'program_id',
                        value: item.id,
                        disabled: true,
                    },
                    {
                        category: 'input',
                        label: 'Remarks',
                        name: 'remarks',
                        id: 'remarks',
                        hidden: true,
                        placeholder: 'Enter remarks here',
                    },
                    {
                        category: 'input',
                        label: 'Amount',
                        type: 'amount',
                        data_validate: 'required|amount',
                        name: 'amount',
                        id: 'amount',
                        value: item.amount,
                        placeholder: 'Enter amount here',
                        full: true,
                    },
                ],
                fieldsGridCount: '2',
                bottomActions: [
                    {id: 'update', text: 'Update Program', type: 'submit'}
                ]
            }

            createModal(modalData);

            document.getElementById('updateProgramModalForm').addEventListener('submit', function(e) {
                e.preventDefault();
                formatAmountInput(this.querySelector('#amount'));
                this.submit();
            });
        }

        function printDetails(elem) {
            closeAllDropdowns();

            if (elem.parentElement.tagName.toLowerCase() === 'li') {
                elem.parentElement.parentElement.querySelector('#show-details').click();
                document.getElementById('modalForm').parentElement.classList.add('hidden');
            }

            const preview = document.getElementById('modelInner');

            // Remove existing iframe
            let oldIframe = document.getElementById('printIframe');
            if (oldIframe) oldIframe.remove();

            // Create iframe
            let printIframe = document.createElement('iframe');
            printIframe.id = "printIframe";
            printIframe.style.position = "absolute";
            printIframe.style.width = "0px";
            printIframe.style.height = "0px";
            printIframe.style.border = "none";
            printIframe.style.display = "none";
            document.body.appendChild(printIframe);

            let printDocument = printIframe.contentDocument || printIframe.contentWindow.document;
            printDocument.open();

            const headContent = document.head.innerHTML;

            printDocument.write(`
                <html>
                    <head>
                        <title>Print Program Details</title>
                        ${headContent}
                        <style>
                            @media print {
                                @page {
                                    size: A4;
                                    margin: 0.31in, 0.31in, 0.31in, 0.31in;
                                }

                                /* All text black */
                                body, body * {
                                    color: #000000 !important;
                                }

                                #table-head, #calc-bottom .final {
                                    background-color: transparent !important;
                                    border: 1px solid #6b7280 !important;
                                    page-break-inside: avoid;
                                }

                                /* Table row breaks */
                                #table-body > div {
                                    page-break-inside: avoid;
                                    page-break-after: auto;
                                }
                            }
                        </style>
                    </head>
                    <body>
                        ${preview.innerHTML}
                    </body>
                </html>
            `);

            printDocument.close();

            printIframe.onload = () => {
                setTimeout(() => {
                    printIframe.contentWindow.focus();
                    printIframe.contentWindow.print();
                }, 500);

                document.getElementById('modalForm').parentElement.remove();
            };
        }

        function goToAddPayment(program) {
            const url = new URL("{{ route('customer-payments.create') }}", window.location.origin);
            url.searchParams.set("program_id", program.payment_programs?.id ?? program.id);
            window.location.href = url.toString();
        }

        function goToMarkPaid(program) {
            const url = new URL("{{ route('payment-programs.mark-paid', ':id') }}", window.location.origin);
            url.pathname = url.pathname.replace(':id', program.id);
            window.location.href = url.toString();
        }

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
                    {id: 'print', text: 'Print Details', onclick: 'printDetails(this)'}
                ]
            };

            if (data.status != 'Paid' && data.status != 'Overpaid') {
                contextMenuData.actions.push(
                    {id: 'add-payment', text: 'Add Payment', onclick: `goToAddPayment(${JSON.stringify(data)})`},
                    {id: 'update-program', text: 'Update Program', onclick: `generateUpdateProgramModal(${JSON.stringify(data)})`},
                    {id: 'mark-paid', text: 'Mark as Paid', onclick: `goToMarkPaid(${JSON.stringify(data)})`},
                );
            }

            createContextMenu(contextMenuData);
        }

        function generateModal(item) {
            let data = JSON.parse(item.dataset.json);
            let tableBody = [];
            let totalAmount = 0;

            const sourceArray = Array.isArray(data.data.payments)
                ? data.data.payments
                : Array.isArray(data.data.payment_programs)
                ? data.data.payment_programs
                : [];

            tableBody = sourceArray.map((item, index) => {
                totalAmount += item.amount;
                return [
                    {data: index+1, class: 'w-[5%]'},
                    {data: formatDate(item.date), class: 'w-1/4'},
                    {data: (item.bank_account?.sub_category?.supplier_name ?? item.bank_account?.sub_category?.customer_name ?? 'Self Account'), class: 'w-1/3 capitalize'},
                    {data: (item.bank_account?.account_title ?? '-') + ' | ' + (item.bank_account?.bank?.short_title ?? '-'), class: 'w-1/3 capitalize'},
                    {data: formatNumbersWithDigits(item.amount, 1, 1), class: 'w-1/6'},
                    {data: item.transaction_id, class: 'w-[10%] capitalize'},
                ];
            });

            let modalData = {
                id: 'modalForm',
                class: 'max-w-4xl h-[37rem]',
                name: `Payment Details - ${data.customer_name}`,
                table: {
                    name: 'Details',
                    headers: [
                        { label: "#", class: "w-[5%]" },
                        { label: "Data", class: "w-1/4" },
                        { label: "Beneficiary", class: "w-1/3" },
                        { label: "Acc. Title", class: "w-1/3" },
                        { label: "Amount", class: "w-1/6" },
                        { label: "Reff. No.", class: "w-[10%]" },
                    ],
                    body: tableBody,
                    scrollable: true,
                },
                calcBottom: [
                    {label: 'Total Amount - Rs.', name: 'total', value: formatNumbersWithDigits(totalAmount, 1, 1), disabled: true},
                ],
                bottomActions: [
                    {id: 'print', text: 'Print Details', onclick: 'printDetails(this)'}
                ]
            }

            if (data.status != 'Paid' && data.status != 'Overpaid') {
                modalData.bottomActions.push(
                    {id: 'add-payment', text: 'Add Payment', onclick: `goToAddPayment(${JSON.stringify(data)})`},
                    {id: 'update-program', text: 'Update Program', onclick: `generateUpdateProgramModal(${JSON.stringify(data)})`},
                    {id: 'mark-paid', text: 'Mark as Paid', onclick: `goToMarkPaid(${JSON.stringify(data)})`},
                );
            }

            createModal(modalData);
        }
    </script>
@endsection
