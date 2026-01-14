@extends('app')
@section('title', 'Show Customer Payments | ' . $client_company->name)
@section('content')
@php
    $searchFields = [
        "Date Range" => [
            "id" => "date_range_start",
            "type" => "date",
            "id2" => "date_range_end",
            "type2" => "date",
            "dataFilterPath" => "date",
        ],
        "Customer Name" => [
            "type" => "text",
            "id" => "customer_name",
            "placeholder" => "Enter customer name",
            "dataFilterPath" => "name",
        ],
        "City" => [
            "type" => "text",
            "id" => "city",
            "placeholder" => "Enter city",
            "dataFilterPath" => "data.customer.city.title",
        ],
        "Beneficiary" => [
            "type" => "text",
            "id" => "beneficiary",
            "placeholder" => "Enter beneficiary",
            "dataFilterPath" => "beneficiary",
        ],
        "Supplier Name" => [
            "type" => "text",
            "id" => "supplier_name",
            "placeholder" => "Enter supplier name",
            "dataFilterPath" => "supplier_name",
        ],
        "Method" => [
            "type" => "select",
            "id" => "method",
            "options" => [
                        'cash' => ['text' => 'Cash'],
                        'cheque' => ['text' => 'Cheque'],
                        'slip' => ['text' => 'Slip'],
                        'program' => ['text' => 'Program'],
                        'adjustment' => ['text' => 'Adjustment'],
                    ],
            "dataFilterPath" => "details.Method",
        ],
        "Category" => [
            "type" => "select",
            "id" => "category",
            "options" => [
                        'cash' => ['text' => 'Cash'],
                        'non-cash' => ['text' => 'Non Cash'],
                    ],
            "dataFilterPath" => "category",
        ],
        "Type" => [
            "type" => "select",
            "id" => "type",
            "options" => [
                        'normal' => ['text' => 'Normal'],
                        'payment_program' => ['text' => 'Payment Program'],
                        'recovery' => ['text' => 'Recovery'],
                    ],
            "dataFilterPath" => "details.Type",
        ],
        "Issued" => [
            "type" => "select",
            "id" => "issued",
            "options" => [
                        'Issued' => ['text' => 'Issued'],
                        'Return' => ['text' => 'Return'],
                        'DR' => ['text' => 'DR'],
                        'Not Issued' => ['text' => 'Not Issued'],
                    ],
            "dataFilterPath" => "issued",
        ],
        "Status" => [
            "type" => "select",
            "id" => "status",
            "options" => [
                        'Cleared' => ['text' => 'Cleared'],
                        'Pending' => ['text' => 'Pending'],
                    ],
            "dataFilterPath" => "status",
        ],
        "Reff. No." => [
            "id" => "reff_no",
            "type" => "text",
            "placeholder" => "Enter reff. no.",
            "dataFilterPath" => "reff_no",
        ],
        "Voucher No." => [
            "type" => "text",
            "id" => "voucher_no",
            "placeholder" => "Enter voucher no.",
            "dataFilterPath" => "voucher_no",
        ],
        "Amount" => [
            "type" => "text",
            "id" => "amount",
            "placeholder" => "Enter Amount",
            "dataFilterPath" => "details.Amount",
        ],
    ];
@endphp
    <div class="w-[80%] mx-auto">
        <x-search-header heading="Customer Payments" :search_fields=$searchFields/>
    </div>

    <!-- Main Content -->
    <section class="text-center mx-auto ">
        <div
            class="show-box mx-auto w-[80%] h-[70vh] bg-[var(--secondary-bg-color)] border border-[var(--glass-border-color)]/20 rounded-xl shadow pt-8.5 relative">
            <x-form-title-bar printBtn title="Show Customer Payments" changeLayoutBtn layout="{{ $authLayout }}" resetSortBtn />

            <div class="absolute bottom-14 right-0 flex items-center justify-between gap-2 w-fll z-50 p-3 w-full pointer-events-none">
                <x-section-navigation-button direction="right" id="info" icon="fa-info" />
                <x-section-navigation-button link="{{ route('customer-payments.create') }}" title="Add New Payment" icon="fa-plus" />
            </div>

            <div class="details h-full z-40">
                <div class="container-parent h-full">
                    <div class="card_container px-3 pb-3 h-full flex flex-col">
                        <div id="table-head" class="flex justify-between bg-[var(--h-bg-color)] rounded-lg font-medium py-2 hidden mt-4">
                            <div class="text-center w-1/10 cursor-pointer" onclick="sortByThis(this)">Date</div>
                            <div class="text-center w-1/7 cursor-pointer" onclick="sortByThis(this)">Customer</div>
                            <div class="text-center w-1/7 cursor-pointer" onclick="sortByThis(this)">Supplier Name</div>
                            <div class="text-center w-1/7 cursor-pointer" onclick="sortByThis(this)">Beneficiary</div>
                            <div class="text-center w-1/11 cursor-pointer" onclick="sortByThis(this)">Method</div>
                            <div class="text-center w-1/10 cursor-pointer" onclick="sortByThis(this)">Amount</div>
                            <div class="text-center w-1/10 cursor-pointer" onclick="sortByThis(this)">Reff. No.</div>
                            <div class="text-center w-1/10 cursor-pointer" onclick="sortByThis(this)">Clear Date</div>
                            <div class="text-center w-1/9 cursor-pointer" onclick="sortByThis(this)">Cleared Amount</div>
                            <div class="text-center w-1/10 cursor-pointer" onclick="sortByThis(this)">Voucher No.</div>
                            <div class="text-center w-1/10 cursor-pointer" onclick="sortByThis(this)">DR No.</div>
                        </div>
                        <p id="noItemsError" style="display: none" class="text-sm text-[var(--border-error)] mt-3">No items found</p>
                        <div class="overflow-y-auto grow my-scrollbar-2">
                            <div class="search_container grid grid-cols-1 sm:grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5 grow">
                            </div>
                        </div>
                        <div id="calc-bottom" class="flex w-full gap-4 text-sm bg-[var(--secondary-bg-color)] py-2 rounded-lg">
                            <div
                                class="total-Amount flex justify-between items-center border border-gray-600 rounded-lg py-2 px-4 w-full cursor-not-allowed">
                                <div>Total Amount - Rs.</div>
                                <div class="text-right">0.00</div>
                            </div>
                            <div
                                class="total-Payment flex justify-between items-center border border-gray-600 rounded-lg py-2 px-4 w-full cursor-not-allowed">
                                <div>Total Payment - Rs.</div>
                                <div class="text-right">0.00</div>
                            </div>
                            <div
                                class="balance flex justify-between items-center border border-gray-600 rounded-lg py-2 px-4 w-full cursor-not-allowed">
                                <div>Balance - Rs.</div>
                                <div class="text-right">0.00</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <script>
        let totalAmount = 0;
        let totalPayment = 0;
        let companyData = @json($client_company);
        let authLayout = '{{ $authLayout }}';

        function createRow(data) {
            return `
                <div id="${data.id}" oncontextmenu='${data.oncontextmenu || ""}' onclick='${data.onclick || ""}'
                    class="item row relative group flex justify-between border-b border-[var(--h-bg-color)] items-center py-2 cursor-pointer hover:bg-[var(--h-secondary-bg-color)] transition-all fade-in ease-in-out"
                    data-json='${JSON.stringify(data)}'>

                    <span class="text-center w-1/10">${data.details['Date']}</span>
                    <span class="text-center w-1/7">${data.name}</span>
                    <span class="text-center w-1/7">${data.supplier_name}</span>
                    <span class="text-center w-1/7">${data.beneficiary}</span>
                    <span class="text-center w-1/11 capitalize">${data.details["Method"]}</span>
                    <span class="text-center w-1/10">${data.details['Amount']}</span>
                    <span class="text-center w-1/10">${data.reff_no}</span>
                    <span class="text-center w-1/10">${data.clear_date ?? '-'}</span>
                    <span class="text-center w-1/9">${data.cleared_amount}</span>
                    <span class="text-center w-1/10">${data.voucher_no}</span>
                    <span class="text-center w-1/10">${data.d_r_no}</span>
                </div>
            `;
        }

        // const fetchedData = [];
        // let allDataArray = fetchedData.map(item => {
        //     totalAmount += parseFloat(item.amount);
        //     totalPayment += parseFloat(item.clear_amount);
        //     return {
        //         id: item.id,
        //         name: item.customer.customer_name + ' | ' + item.customer.city.short_title,
        //         details: {
        //             'Type': item.type.replace('_', ' '),
        //             'Method': item.method,
        //             'Date': formatDate(item.slip_date || item.cheque_date || item.date),
        //             'Amount': formatNumbersWithDigits(item.amount, 1, 1),
        //         },
        //         voucher_no: item.cheque?.voucher?.voucher_no || item.slip?.voucher?.voucher_no || item.voucher?.voucher_no || item.cheque?.cr?.c_r_no || item.slip?.cr?.c_r_no || '-',
        //         beneficiary: item.cheque?.supplier?.supplier_name || item.slip?.supplier?.supplier_name || item.bank_account?.account_title || item.cheque?.voucher?.supplier?.supplier_name || item.slip?.voucher?.supplier?.supplier_name ||'-',
        //         supplier_name: item.cheque?.supplier?.supplier_name || item.slip?.supplier?.supplier_name || item.cheque?.voucher?.supplier?.supplier_name || item.slip?.voucher?.supplier?.supplier_name ||item.bank_account?.sub_category?.supplier_name ||'-',
        //         reff_no: item.cheque_no || item.slip_no || item.transaction_id || item.reff_no || '-',
        //         data: item,
        //         date: item.slip_date || item.cheque_date || item.date,
        //         category: item.customer.category == 'cash' ? 'cash' : 'non-cash',
        //         clear_date: item.clear_date ? formatDate(item.clear_date) : (item.method == 'cheque' || item.method == 'slip') ? 'Pending' : '-',
        //         cleared_amount: item.clear_amount ? formatNumbersWithDigits(item.clear_amount, 1, 1) : (item.method == 'cheque' || item.method == 'slip') ? '0' : '-',
        //         ...((item.method == 'cheque' || item.method == 'slip') && { issued: item.issued }),
        //         ...((item.method == 'cheque' || item.method == 'slip') && (item.clear_date ? { clearStatus: 'Cleared'} : { clearStatus: 'Pending'} )),
        //         d_r_no: item.dr?.d_r_no || '-',
        //         oncontextmenu: "generateContextMenu(event)",
        //         onclick: "generateModal(this)",
        //         visible: true,
        //     };
        // });

        function generateClearModal(data) {
            let modalData = {
                id: 'clearModal',
                class: 'h-auto',
                name: 'Clear Payment',
                method: 'POST',
                action: `/customer-payments/${data.id}/clear`,
                fields: [
                    {
                        category: 'input',
                        label: 'Method',
                        value: data.customer.customer_name + ' | ' + data.customer.city.short_title + ' | ' + data.method.charAt(0).toUpperCase() + data.method.slice(1) + (data.method === 'cheque' ? ` | Cheque No. ${data.cheque_no}` : data.method === 'slip' ? ` | Slip No. ${data.slip_no}` : '') + ' | ' + formatNumbersWithDigits(data.amount, 1, 1) + ' - Rs.',
                        disabled: true,
                        full: true,
                    },
                    {
                        category: 'input',
                        name: 'clear_date',
                        label: 'Clear Date',
                        type: 'date',
                        min: (data.cheque_date || data.slip_date)?.split('T')[0],
                        max: new Date().toISOString().split('T')[0],
                        required: true,
                    },
                    {
                        category: 'explicitHtml',
                        html: `
                            <x-select
                                label="Method"
                                name="method_select"
                                id="method_select"
                                :options="[
                                    'online' => ['text' => 'Online'],
                                    'cash' => ['text' => 'Cash'],
                                ]"
                                required
                                showDefault
                                onchange="trackMethodState(this)"
                            />
                        `,
                    },
                    {
                        category: 'explicitHtml',
                        html: `
                            <x-select label="Bank Account" addBtnLink="/bank-accounts/create" name="bank_account_id" id="bank_account_id" :options="[]" required disabled showDefault />
                        `,
                    },
                    {
                        category: 'explicitHtml',
                        html: `
                            <x-input label="Amount" name="amount" id="amount" type="amount" placeholder="Enter amount" dataValidate="required|amount" oninput="validateInput(this)" required/>
                        `,
                    },
                    {
                        category: 'explicitHtml',
                        html: `
                            <x-input label="Reff. No." name="reff_no" id="reff_no" placeholder="Enter reff. no." required disabled/>
                        `,
                    },
                    {
                        category: 'input',
                        name: 'remarks',
                        label: 'Remarks',
                        type: 'text',
                        placeholder: 'Enter remarks',
                    },
                ],
                fieldsGridCount: '2',
                bottomActions: [
                    {id: 'clear', text: 'Clear', type: 'submit'},
                ],
            };
            createModal(modalData);

            let bankAccounts = data.bank_account ? [data.bank_account] : data.cheque?.voucher?.supplier?.bank_accounts ? data.cheque?.voucher?.supplier?.bank_accounts : data.slip?.voucher?.supplier?.bank_accounts ? data.slip?.voucher?.supplier?.bank_accounts : [];
            let form = document.querySelector('#clearModal');
            let bankAccountInpDom = form.querySelector('input[id="bank_account_id"]');
            let bankAccountDom = form.querySelector('ul[data-for="bank_account_id"]');

            bankAccountInpDom.disabled = true;
            bankAccountDom.innerHTML = `
                <li data-for="bank_account_id" data-value=" " onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg transition hover:bg-[var(--h-bg-color)] text-nowrap overflow-x-auto scrollbar-hidden"">-- Select bank account --</li>
            `;

            bankAccounts.forEach(bankAccount => {

                bankAccountDom.innerHTML += `
                    <li data-for="bank_account_id" data-value="${bankAccount.id}" onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg transition hover:bg-[var(--h-bg-color)] text-nowrap overflow-x-auto scrollbar-hidden">${bankAccount.account_title} | ${bankAccount.bank.short_title}</li>
                `;
            });
            // bankAccountDom.innerHTML = options;
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
                    {id: 'edit-payment', text: 'Edit Payment', dataId: data.id}
                ],
            };

            if (
                (data.data.method === 'cheque' || data.data.method === 'slip') &&
                (
                    (data.data.method === 'cheque' && new Date(data.data.cheque_date) <= new Date()) ||
                    (data.data.method === 'slip' && new Date(data.data.slip_date) <= new Date())
                )
            ) {
                if (data.data.clear_date == null && data.data.issued == 'Issued') {
                    contextMenuData.actions.push(
                        {id: 'clear', text: 'Clear', onclick: `generateClearModal(${JSON.stringify(data.data)})`},
                    );
                }
            }

            if (data.data.issued !== "Issued" && data.data.method !== "cash") {
                contextMenuData.actions.push(
                    {id: 'split-payment', text: 'Split Payment', onclick: `generateSplitPaymentModal(${JSON.stringify(data.data)})`},
                );
            }

            createContextMenu(contextMenuData);
        }

        function generateModal(item) {
            let data = JSON.parse(item.dataset.json);

            let modalData = {
                id: 'modalForm',
                class: 'h-auto',
                name: data.name,
                details: {
                    'Date': formatDate(data.data.date),
                    'Amount': data.details['Amount'],
                    'Type': data.details['Type'],
                    'Method': data.details['Method'],
                    'hr': true,
                    ...(data.data.cheque_no && { 'Cheque No': data.data.cheque_no }),
                    ...(data.data.slip_no && { 'Slip No': data.data.slip_no }),
                    ...(data.data.transition_id && { 'Transition Id': data.data.transition_id }),
                    ...(data.data.bank && { 'Bank': data.data.bank }),
                    ...(data.data.cheque_date && { 'Cheque Date': formatDate(data.data.cheque_date) }),
                    ...(data.data.slip_date && { 'Slip Date': formatDate(data.data.slip_date) }),
                    // ...(data.data.clear_date && { 'Clear Date': formatDate(data.data.clear_date) }),
                    ...(data.data.clear_amount && { 'Clear Amount': formatNumbersWithDigits(data.data.clear_amount, 1, 1) }),
                    ...((data.data.method == 'cheque' || data.data.method == 'slip') && (data.data.clear_date ? { 'Clear Date': formatDate(data.data.clear_date)} : { 'Clear Date': 'Pending'} )),
                    ...((data.data.method == 'cheque' || data.data.method == 'slip') && { 'Issued': data.issued }),
                    'Remarks': data.data.remarks || 'No Remarks',
                },
                bottomActions: [
                    {id: 'edit-payment', text: 'Edit Payment', dataId: data.id}
                ],
            }

            if (
                (data.data.method === 'cheque' || data.data.method === 'slip') &&
                (
                    (data.data.method === 'cheque' && new Date(data.data.cheque_date) <= new Date()) ||
                    (data.data.method === 'slip' && new Date(data.data.slip_date) <= new Date())
                )
            ) {
                if (data.data.clear_date == null && data.data.issued == 'Issued') {
                    modalData.bottomActions.push(
                        {id: 'clear', text: 'Clear', onclick: `generateClearModal(${JSON.stringify(data.data)})`},
                    );
                }
            }

            if (data.data.issued !== "Issued" && data.data.method !== "cash") {
                modalData.bottomActions.push(
                    {id: 'split-payment', text: 'Split Payment', onclick: `generateSplitPaymentModal(${JSON.stringify(data.data)})`},
                );
            }

            createModal(modalData);
        }

        function generateReffNos(rawReffNo, hasPipe, maxSuffix) {
            rawReffNo = rawReffNo.toString().replace('/', '|').trim();
            let base = rawReffNo.includes('|') ? rawReffNo.split('|')[0].trim() : rawReffNo;

            let current, next;

            if (hasPipe) {
                // Agar already pipe hai → current wahi rahega
                current = rawReffNo;
                next = `${base} | ${maxSuffix + 1}`;
            } else {
                // Agar pipe nahi hai → current update hoga | 1
                current = `${base} | 1`;
                next = `${base} | ${maxSuffix + 2}`; // kyunki 1 abhi assign ho gaya
            }

            return [current, next];
        }

        function generateSplitPaymentModal(data) {
            let rawReffNo =
                data.method === "cheque" ? data.cheque_no :
                data.method === "slip" ? data.slip_no :
                data.method === "program" ? data.transaction_id :
                data.reff_no;

            let [currentRef, newRef] = generateReffNos(rawReffNo, data.has_pipe, data.max_reff_suffix);

            let modalData = {
                id: 'splitModalForm',
                class: 'h-auto',
                method: 'POST',
                action: '{{ url("customer-payments") }}/' + data.id + '/split',
                name: 'Payment Split',
                fields: [
                    {
                        category: 'input',
                        label: 'Customer',
                        value: data.customer.customer_name + ' | ' + data.customer.city.short_title,
                        disabled: true,
                    },
                    {
                        category: 'input',
                        label: 'Method',
                        name: 'method',
                        value: data.method,
                        readonly: true,
                    },
                    {
                        category: 'input',
                        label: 'Amount',
                        value: formatNumbersWithDigits(data.amount, 1, 1),
                        disabled: true,
                    },
                    {
                        category: 'input',
                        label: 'Reff. No.',
                        name: 'reff_no',
                        value: currentRef,
                        readonly: true,
                    },
                    {
                        category: 'input',
                        label: 'New Reff. No.',
                        name: 'new_reff_no',
                        value: newRef,
                        readonly: true,
                    },
                    {
                        category: 'input',
                        label: 'Split Amount',
                        name: 'split_amount',
                        id: 'split_amount',
                        type: 'amount',
                        data_validate: "required|amount",
                        placeholder: 'Enter split amount',
                        oninput: `validateSplitAmount(this, ${data.amount - 1})`,
                        required: true,
                    },
                ],
                fieldsGridCount: '2',
                bottomActions: [
                    {id: 'split-payment-btn', text: 'Split Payment', type: 'submit'}
                ]
            }

            createModal(modalData);
        }

        function validateSplitAmount(input, maxAmount) {
            if (parseFloat(input.value) > parseFloat(maxAmount)) {
                input.value = maxAmount;
            }
        }

        function trackMethodState(select) {
            let form = select.closest('form');
            let bankAccountInpDom = form.querySelector('input[id="bank_account_id"]');
            let reffNoDom = form.querySelector('input[id="reff_no"]');

            if (select.value === 'online') {
                bankAccountInpDom.disabled = false;
                bankAccountInpDom.value = '';
                bankAccountInpDom.placeholder = '-- Select Bank Account --';
                reffNoDom.disabled = false;
            } else {
                bankAccountInpDom.disabled = true;
                bankAccountInpDom.value = '';
                bankAccountInpDom.placeholder = '-- No Options Available --';

                reffNoDom.disabled = true;
                reffNoDom.value = '';
            }
        }

        let totalAmountDom = document.querySelector('#calc-bottom >.total-Amount .text-right');
        let totalPaymentDom = document.querySelector('#calc-bottom >.total-Payment .text-right');
        let balanceDom = document.querySelector('#calc-bottom >.balance .text-right');
        let infoDom = document.getElementById('info').querySelector('span');

        function onFilter() {
            // ✅ Assuming visibleData already includes only visible items
            totalAmount = visibleData.reduce((sum, d) => sum + d.data.amount, 0);
            totalPayment = visibleData.reduce((sum, d) => sum + d.data.clear_amount, 0);

            infoDom.textContent = `Showing ${visibleData.length} of ${allDataArray.length} payments.`;

            totalAmountDom.innerText = formatNumbersWithDigits(totalAmount, 1, 1);
            totalPaymentDom.innerText = formatNumbersWithDigits(totalPayment, 1, 1);
            balanceDom.innerText = formatNumbersWithDigits(totalAmount - totalPayment, 1, 1);
        }
    </script>
@endsection
