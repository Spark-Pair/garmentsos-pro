@extends('app')
@section('title', 'Edit Voucher | ' . $client_company->name)
@section('content')

@php
    $voucherType = Auth::user()->voucher_type;

    $steps = [
        $voucherType == 'supplier' ? 'Select Supplier' : 'Select Date',
        'Enter Payment',
        'Preview',
    ];

    $method_options = [
        'cash' => ['text' => 'Cash'],
        'cheque' => ['text' => 'Cheque'],
        'slip' => ['text' => 'Slip'],
    ];

    if ($voucherType == 'supplier') {
        // Insert 'program' at 3rd position (index 3)
        $method_options = array_slice($method_options, 0, 3, true)
            + ['program' => ['text' => 'Payment Program']]
            + array_slice($method_options, 3, null, true);
        $method_options = array_slice($method_options, 0, 3, true)
            + ['purchase_return' => ['text' => 'Purchase Return']]
            + array_slice($method_options, 3, null, true);
    }

    // Add remaining methods
    $method_options += [
        'self_cheque' => ['text' => 'Self Cheque'],
        'atm' => ['text' => 'ATM'],
        'adjustment' => ['text' => 'Adjustment'],
    ];
@endphp

    <!-- Progress Bar -->
    <div class="mb-5 max-w-4xl mx-auto">
        <x-search-header heading="Edit Voucher" link linkText="Show Vouchers"
            linkHref="{{ route('vouchers.index') }}" />
        <x-progress-bar :steps="$steps" :currentStep="1" />
    </div>

    <!-- Form -->
    <form id="form" action="{{ route('vouchers.update', ['voucher' => $voucher->id]) }}" method="post"
        class="bg-[var(--secondary-bg-color)] text-sm rounded-xl shadow-lg p-8 border border-[var(--glass-border-color)]/20 pt-14 max-w-4xl mx-auto  relative overflow-hidden">
        @csrf
        @method('PUT')
        <x-form-title-bar title="Edit Voucher" />

        <div class="step1 space-y-4 ">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @if ($voucherType == 'supplier')
                    {{-- supplier --}}
                    <div class="col-span-full">
                        <x-input label="Supplier" name="supplier_id" id="supplier_id" disabled value="{{ $voucher->supplier?->supplier_name }}" />
                    </div>

                    {{-- balance --}}
                    <x-input label="Balance" placeholder="Select supplier first" name="balance" id="balance" disabled />

                    {{-- date --}}
                    <x-input label="Date" name="date" id="date" type="date" disabled value="{{ ($voucher->date)->format('Y-m-d') }}" />
                @else
                    <div class="col-span-full">
                        {{-- date --}}
                        <x-input label="Date" name="date" id="date" type="date" disabled value="{{ ($voucher->date)->format('Y-m-d') }}" />
                    </div>
                @endif
            </div>
        </div>

        <div class="step2 space-y-4 hidden">
            <div class="flex flex-col space-y-4 gap-4">
                {{-- method --}}
                <x-select label="Method" id="method" :options="$method_options" required showDefault
                    onchange="trackMethodState(this)" withButton btnId="enterDetailsBtn" btnText="Enter Details"
                    btnOnclick="trackMethodState(this.previousElementSibling)" />
            </div>
            {{-- payment showing --}}
            <div id="payment-table" class="w-full text-left text-sm">
                <div class="flex justify-between items-center bg-[var(--h-bg-color)] rounded-lg py-2 px-4 mb-4">
                    <div class="w-[5%]">S.No</div>
                    @if ($voucherType == 'self_account')
                        <div class="w-1/3">Account Title</div>
                    @endif
                    <div class="w-1/5">Method</div>
                    <div class="w-1/3">Customer/Self Acc.</div>
                    <div class="w-1/5">Reff. No.</div>
                    <div class="w-1/6">Remarks</div>
                    <div class="w-[15%]">Amount</div>
                    <div class="w-[8%] text-center">Action</div>
                </div>
                <div id="payment-list" class="h-[20rem] overflow-y-auto my-scrollbar-2">
                    <div class="text-center bg-[var(--h-bg-color)] rounded-lg py-2 px-4">No Payment Added</div>
                </div>
                <input type="hidden" name="payment_details_array" id="payment_details_array">
            </div>

            <div class="flex w-full text-sm mt-5 text-nowrap">
                <div
                    class="total-payment flex justify-between items-center border border-gray-600 rounded-lg py-2 px-4 w-full">
                    <div class="grow">Total Payment - Rs.</div>
                    <div id="finalTotalPayment">0</div>
                </div>
            </div>
        </div>

        <div class="step3 hidden space-y-4 text-black h-[35rem] overflow-y-auto my-scrollbar-2 bg-white rounded-md">
            <div id="preview-container" class="w-[210mm] h-[297mm] mx-auto overflow-hidden relative">
                <div id="preview" class="preview flex flex-col h-full">
                    <h1 class="text-[var(--border-error)] font-medium text-center mt-5">No Preview avalaible.</h1>
                </div>
            </div>
        </div>
    </form>

    <script>
        let supplierSelectDom = document.getElementById('supplier_id');
        let methodSelectDom = document.getElementById('method');
        let dateDom = document.getElementById('date');
        let balanceDom = document.getElementById('balance');
        let paymentDetailsDom = document.getElementById('paymentDetails');
        let finalTotalPaymentDom = document.getElementById('finalTotalPayment');
        let paymentListDom = document.getElementById('payment-list');
        const paymentDetailsArrayDom = document.getElementById("payment_details_array");

        let voucher = @json($voucher);

        let companyData = @json($client_company);
        const previewDom = document.getElementById('preview');

        selectedSupplierData = null;
        let totalPayment = 0;

        let paymentDetailsArray = [];
        let allPayments = [];

        let selectedSupplier;

        const today = new Date().toISOString().split('T')[0];

        function trackSupplierState() {
            balanceDom.value = '';
            methodSelectDom.value = '';

            if (supplierSelectDom.value != '') {
                selectedSupplier = voucher.supplier;
                methodSelectDom.disabled = false;
                balanceDom.value = formatNumbersWithDigits(selectedSupplier.balance, 1, 1);
                selectedSupplierData = selectedSupplier;
            } else {
                methodSelectDom.disabled = true;
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            @if ($voucherType == 'supplier')
                trackSupplierState();
            @endif
            trackDateState(dateDom);

            paymentDetailsArray = voucher.payments;
            allPayments = voucher.payments;
            totalPayment = paymentDetailsArray.reduce((sum, item) => sum + parseFloat(item.amount || 0), 0);

            renderList();
        });

        function trackDateState(elem) {
            paymentDetailsArray = [];
            methodSelectDom.value = '';
            renderList();

            if (elem.value != '') {
                gotoStep(2);
            }
        }

        const enterDetailsBtn = document.getElementById("enterDetailsBtn");
        enterDetailsBtn.disabled = true;

        function trackChequeState(elem) {
            let selectedCheque = JSON.parse(elem.closest('.selectParent').querySelector('ul[data-for="cheque_id"] li.selected').dataset.option || '{}');
            let amountInpDom = elem.closest('form').querySelector('input[name="amount"]');

            selectedDom.value = JSON.stringify(selectedCheque);
            amountInpDom.value = selectedCheque.amount;
        }

        function trackSlipState(elem) {
            let selectedSlip = JSON.parse(elem.closest('.selectParent').querySelector('ul[data-for="slip_id"] li.selected').dataset.option || '{}');
            let amountInpDom = elem.closest('form').querySelector('input[name="amount"]');

            selectedDom.value = JSON.stringify(selectedSlip);
            amountInpDom.value = selectedSlip.amount;
        }

        let selectedDom;
        let availableChequesArray = [];

        function setSelectedAccount(elem) {
            let hiddenAccountInSelfAccount = elem.closest('form').querySelector(`ul[data-for="self_account_id"]`);
            hiddenAccountInSelfAccount?.querySelectorAll('li').forEach(li => {
                if (li.style.display == 'none') {
                    li.style.display = 'block';
                }
            })

            let selectedOption = elem.nextElementSibling.querySelector('li.selected');
            let selectedAccount = JSON.parse(selectedOption.getAttribute('data-option')) || ''
            elem.closest('form').querySelector('input[name="selected"]').value = JSON.stringify(selectedAccount);

            availableChequesArray = selectedAccount.available_cheques;

            if (elem.closest('form').querySelector('input[name="cheque_no"]')) {
                fetchChequeNumbers();
            }

            const amountInput = elem.closest('form').querySelector('input[name="amount"]');

            const matchingPayments = paymentDetailsArray.filter(item =>
                item.bank_account_id == selectedAccount.id
            );

            const totalAmount = matchingPayments.reduce((sum, item) => {
                return sum + parseFloat(item.amount || 0);
            }, 0);

            amountInput.dataset.validate += `|max:${selectedAccount.balance - totalAmount}`;

            let selectedAccountInSelfAccount = elem.closest('form').querySelector(`ul[data-for="self_account_id"] li[data-value="${selectedAccount.id}"]`);

            if (selectedAccountInSelfAccount) {
                selectedAccountInSelfAccount.style.display = 'none';
            }
        }

        function trackExpenseSelect(elem) {
            let selectedOption = elem.nextElementSibling.querySelector('li.selected');
            let selectedExpense = JSON.parse(selectedOption.getAttribute('data-option')) || ''
            elem.closest('form').querySelector('input[name="selected"]').value = JSON.stringify(selectedExpense);
            elem.closest('form').querySelector('input[name="reff_no"]').value = selectedExpense.reff_no;
        }

        function updateSelectedAccount(elem) {
            let hiddenAccountInSelfAccount = elem.closest('form').querySelector(`ul[data-for="bank_account_id"]`);
            hiddenAccountInSelfAccount.querySelectorAll('li').forEach(li => {
                if (li.style.display == 'none') {
                    li.style.display = 'block';
                }
            })

            let selectedOption = elem.nextElementSibling.querySelector('li.selected');
            let selectedAccount = JSON.parse(selectedOption.getAttribute('data-option')) || ''

            let selectedAccountInBankAccount = elem.closest('form').querySelector(`ul[data-for="bank_account_id"] li[data-value="${selectedAccount.id}"]`);

            if (selectedAccountInBankAccount) {
                selectedAccountInBankAccount.style.display = 'none';
            }
        }

        function fetchChequeNumbers() {
            const chequeNoSelect = document.querySelector("#cheque_no");
            const chequeNoDropdown = document.querySelector("ul.optionsDropdown[data-for='cheque_no']");

            const usedChequeNumbers = paymentDetailsArray.map(p => String(p.cheque_no));
            const filteredCheques = availableChequesArray.filter(chequeNo => !usedChequeNumbers.includes(String(chequeNo)));

            let clutter = `
                <li data-for="cheque_no" data-value="" onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg transition hover:bg-[var(--h-bg-color)] selected">
                    -- Select Cheque Number --
                </li>
                ${filteredCheques.map(chequeNo => `
                    <li data-for="cheque_no" data-value="${chequeNo}" onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg transition hover:bg-[var(--h-bg-color)] text-nowrap overflow-x-auto scrollbar-hidden">
                        ${chequeNo}
                    </li>
                `).join('')}
            `;

            chequeNoDropdown.innerHTML = clutter;
            chequeNoSelect.disabled = false;
        }

        function trackMethodState(elem) {
            let fieldsData = [];

            if (elem.value == 'cash') {
                fieldsData.push(
                    {
                        category: 'input',
                        name: 'amount',
                        label: 'Amount',
                        type: 'amount',
                        data_validate: 'required|amount',
                        required: true,
                        placeholder: 'Enter amount',
                    },
                    @if($voucherType == 'self_account')
                    {
                        category: 'explicitHtml',
                        html: `
                            <x-select class="" label="Self Account" name="self_account_id" id="self_account_id" :options="$self_accounts_options" required showDefault />
                        `,
                    },
                    @endif
                );
            } else if (elem.value == 'cheque') {
                fieldsData.push(
                    {
                        category: 'explicitHtml',
                        html: `
                            <x-select label="Cheque" name="cheque_id" id="cheque_id" required :options="$cheques_options" showDefault onchange="trackChequeState(this)" />
                        `,
                        full: true,
                    },
                    @if($voucherType == 'self_account')
                    {
                        category: 'explicitHtml',
                        html: `
                            <x-select class="" label="Self Account" name="self_account_id" id="self_account_id" :options="$self_accounts_options" required showDefault />
                        `,
                    },
                    @endif
                    {
                        category: 'input',
                        name: 'amount',
                        label: 'Amount',
                        type: 'amount',
                        data_validate: 'required|amount',
                        required: true,
                        placeholder: 'Enter amount',
                        readonly: true,
                    },
                    {
                        category: 'input',
                        id: 'selected',
                        name: 'selected',
                        type: 'hidden',
                    }
                );
            } else if (elem.value == 'slip') {
                fieldsData.push(
                    {
                        category: 'explicitHtml',
                        html: `
                            <x-select label="Slip" name="slip_id" id="slip_id" required :options="$slips_options" showDefault onchange="trackSlipState(this)" />
                        `,
                        full: true,
                    },
                    @if($voucherType == 'self_account')
                    {
                        category: 'explicitHtml',
                        html: `
                            <x-select class="" label="Self Account" name="self_account_id" id="self_account_id" :options="$self_accounts_options" required showDefault />
                        `,
                    },
                    @endif
                    {
                        category: 'input',
                        name: 'amount',
                        label: 'Amount',
                        type: 'amount',
                        data_validate: 'required|amount',
                        required: true,
                        placeholder: 'Enter amount',
                        readonly: true,
                    },
                    {
                        category: 'input',
                        id: 'selected',
                        name: 'selected',
                        type: 'hidden',
                    }
                );
            } else if (elem.value == 'program') {
                fieldsData.push(
                    {
                        category: 'explicitHtml',
                        html: `
                            <x-select label="Program" name="program_id" id="program_id" required :options="[]" showDefault />
                        `,
                        full: true,
                    },
                    {
                        category: 'input',
                        name: 'amount',
                        id: 'amount',
                        label: 'Amount',
                        type: 'amount',
                        data_validate: 'required|amount',
                        required: true,
                        placeholder: 'Enter amount',
                        readonly: true,
                    },
                    {
                        category: 'input',
                        name: 'selected',
                        id: 'selected',
                        type: 'hidden',
                    },
                    {
                        category: 'input',
                        name: 'payment_id',
                        id: 'payment_id',
                        type: 'hidden',
                    },
                );
            } else if (elem.value == 'self_cheque') {
                fieldsData.push(
                    {
                        category: 'explicitHtml',
                        html: `
                            <x-select class="" label="Self Account" name="bank_account_id" id="bank_account_id" :options="$self_accounts_options" required onchange="setSelectedAccount(this)" showDefault />
                        `,
                    },
                    {
                        category: 'explicitHtml',
                        html: `
                            <x-select class="" label="Cheque No." name="cheque_no" id="cheque_no" required showDefault />
                        `,
                    },
                    {
                        category: 'explicitHtml',
                        html: `
                            <x-input label="Amount" name="amount" id="amount" type="amount" placeholder="Enter amount" required dataValidate="required|amount" oninput="validateInput(this)"/>
                        `,
                    },
                    @if($voucherType == 'self_account')
                    {
                        category: 'explicitHtml',
                        html: `
                            <x-select class="" label="Self Account" name="self_account_id" id="self_account_id" :options="$self_accounts_options" onchange="updateSelectedAccount(this)" required showDefault />
                        `,
                    },
                    {
                        category: 'explicitHtml',
                        html: `
                            <x-input label="Cheque Date" name="cheque_date" id="cheque_date" type="date" placeholder="Enter cheque date" required/>
                        `,
                    },
                    @endif
                    {
                        category: 'input',
                        name: 'selected',
                        type: 'hidden',
                    }
                );
            } else if (elem.value == 'atm') {
                fieldsData.push(
                    {
                        category: 'explicitHtml',
                        html: `
                            <x-select class="" label="Self Account" name="bank_account_id" id="bank_account_id" :options="$self_accounts_options" required onchange="setSelectedAccount(this)" showDefault />
                        `,
                    },
                    {
                        category: 'input',
                        name: 'reff_no',
                        label: 'Reff. No.',
                        type: 'number',
                        required: true,
                        placeholder: 'Enter reff no.',
                    },
                    {
                        category: 'explicitHtml',
                        html: `
                            <x-input label="Amount" name="amount" id="amount" type="amount" placeholder="Enter amount" required dataValidate="required|amount" oninput="validateInput(this)"/>
                        `,
                    },
                    @if($voucherType == 'self_account')
                    {
                        category: 'explicitHtml',
                        html: `
                            <x-select class="" label="Self Account" name="self_account_id" id="self_account_id" :options="$self_accounts_options" onchange="updateSelectedAccount(this)" required showDefault />
                        `,
                    },
                    @endif
                    {
                        category: 'input',
                        name: 'selected',
                        type: 'hidden',
                    }
                );
            } else if (elem.value == 'purchase_return') {
                fieldsData.push(
                    {
                        category: 'explicitHtml',
                        html: `
                            <x-select class="" label="Expense" name="expense_id" id="expense_id" required showDefault onchange="trackExpenseSelect(this)" />
                        `,
                    },
                    {
                        category: 'input',
                        name: 'amount',
                        label: 'Amount',
                        type: 'amount',
                        data_validate: 'required|amount',
                        required: true,
                        placeholder: 'Enter amount',
                    },
                    {
                        category: 'input',
                        name: 'selected',
                        type: 'hidden',
                    },
                    {
                        category: 'input',
                        name: 'reff_no',
                        type: 'hidden',
                    }
                );
            } else if (elem.value == 'adjustment') {
                fieldsData.push(
                    {
                        category: 'input',
                        name: 'amount',
                        label: 'Amount',
                        type: 'amount',
                        data_validate: 'required|amount',
                        required: true,
                        placeholder: 'Enter amount',
                    },
                    @if($voucherType == 'self_account')
                    {
                        category: 'explicitHtml',
                        html: `
                            <x-select class="" label="Self Account" name="self_account_id" id="self_account_id" :options="$self_accounts_options" required showDefault />
                        `,
                    },
                    @endif
                );
            }

            if (elem.value != '') {
                fieldsData.push({
                    category: 'explicitHtml',
                    html: `
                        <x-input label="Remarks" name="remarks" id="remarks" placeholder="Enter remarks" dataValidate="friendly" oninput="validateInput(this)"/>
                    `,
                });

                const visibleIndexes = fieldsData
                .map((field, index) => field.type !== 'hidden' ? index : null)
                .filter(index => index !== null);

                if (visibleIndexes.length > 0 && elem.value != 'program' && elem.value != 'cheque' && elem.value != 'slip') {
                    const lastVisibleIndex = visibleIndexes[visibleIndexes.length - 1];
                    fieldsData[lastVisibleIndex].full = visibleIndexes.length % 2 === 1;
                }

                let modalData = {
                    id: 'modalForm',
                    class: 'h-auto',
                    name: 'Payment Details',
                    fields: fieldsData,
                    fieldsGridCount: '2',
                    bottomActions: [
                        {id: 'add-payment-details', text: 'Add Payment', onclick: 'addPaymentDetails()'},
                    ],
                    defaultListener: false,
                }

                createModal(modalData);

                let amountInpDom = document.getElementById('amount');
                selectedDom = document.getElementById('selected');

                let allSelfAccounts = @json($self_accounts);

                const filteredAccounts = allSelfAccounts.filter(account => {
                    return new Date(account.date) <= new Date(dateDom.value);
                });

                if (elem.value == 'program') {
                    let paymentSelectDom = document.querySelector(`ul[data-for="program_id"]`);

                    let allPayments = selectedSupplier.payments;

                    const filteredPayments = allPayments.filter(payment => {
                        console.log(payment);

                        return formatDate(payment.date, false, true) <= formatDate(dateDom.value, false, true);
                    });

                    paymentSelectDom.innerHTML = `
                        <li data-for="program_id" data-value="" onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg hover:bg-[var(--h-bg-color)] selected">-- Select program --</li>
                    `;

                    filteredPayments.forEach(payment => {
                        paymentSelectDom.innerHTML += `
                            <li data-for="program_id" data-value="${payment.program_id}" data-option='${JSON.stringify(payment)}' onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg hover:bg-[var(--h-bg-color)]">${formatNumbersWithDigits(payment.amount, 1, 1)} | ${payment.program.customer.customer_name} | ${payment.program.customer.city.title} | ${payment.transaction_id} | ${formatDate(payment.date)}</li>
                        `;
                    })

                    if (filteredPayments.length > 0) {
                        document.querySelector('input[name="program_id_name"]').disabled = false;
                        document.querySelector('input[name="program_id_name"]').placeholder = '-- Select program --';
                    }

                    document.querySelector('input[name="program_id"]').addEventListener('change', () => {
                        let selectedOption = paymentSelectDom.querySelector('li.selected');
                        let selectedPayment = JSON.parse(selectedOption.getAttribute('data-option')) || '';

                        selectedDom.value = JSON.stringify(selectedPayment);
                        document.getElementById('amount').value = selectedPayment.amount;
                        document.getElementById('payment_id').value = selectedPayment.id;
                    })
                }

                if (elem.value == 'purchase_return') {
                    selectedDom = document.querySelector('input[name="selected"]');
                    let expenseSelectDom = document.querySelector(`ul[data-for="expense_id"]`);

                    let allExpenses = selectedSupplier.expenses;

                    const filteredExpenses = allExpenses.filter(expense => {
                        return new Date(expense.date) <= new Date(dateDom.value);
                    });

                    expenseSelectDom.innerHTML = `
                        <li data-for="expense_id" data-value="" onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg hover:bg-[var(--h-bg-color)] selected">-- Select expense --</li>
                    `;

                    filteredExpenses.forEach(expense => {
                        expenseSelectDom.innerHTML += `
                            <li data-for="expense_id" data-value="${expense.id}" data-option='${JSON.stringify(expense)}' onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg hover:bg-[var(--h-bg-color)]">${formatNumbersWithDigits(expense.amount, 1, 1)} | ${expense.reff_no}</li>
                        `;
                    })

                    if (filteredExpenses.length > 0) {
                        document.querySelector('input[name="expense_id_name"]').disabled = false;
                        document.querySelector('input[name="expense_id_name"]').placeholder = '-- Select program --';
                    }

                    document.querySelector('input[name="expense_id"]').addEventListener('change', () => {
                        let selectedOption = expenseSelectDom.querySelector('li.selected');
                        let selectedExpense = JSON.parse(selectedOption.getAttribute('data-option')) || '';

                        selectedDom.value = JSON.stringify(selectedExpense);
                        document.querySelector('input[name="amount"]').max = selectedExpense.amount;
                    })
                }

                if (elem.value === 'slip' || elem.value === 'cheque' || elem.value === 'program') {
                    const type = elem.value; // 'slip' or 'cheque'
                    const key = type + '_id'; // slip_id or cheque_id
                    const inputName = key + '_name'; // slip_id_name or cheque_id_name

                    // Step 1: Get all slip_id / cheque_id from paymentDetailsArray
                    const usedIds = paymentDetailsArray
                        .map(item => item[key])
                        .filter(id => id !== undefined && id !== null);

                    // Step 2: Hide the corresponding <li> elements
                    usedIds.forEach(id => {
                        const listItem = document.querySelector(`ul[data-for="${key}"] li[data-value="${id}"]`);
                        if (listItem) {
                            listItem.style.display = 'none';
                        }
                    });

                    // Step 3: Check remaining visible <li> items
                    const allListItems = document.querySelectorAll(`ul[data-for="${key}"] li`);
                    const visibleListItems = Array.from(allListItems).filter(li => li.style.display !== 'none');

                    // Step 4: If only one visible <li> and its data-value is '', disable input and update placeholder
                    if (
                        visibleListItems.length === 1 &&
                        visibleListItems[0].getAttribute('data-value') === ''
                    ) {
                        const input = document.querySelector(`input[name="${inputName}"]`);
                        if (input) {
                            input.placeholder = '-- No options available --';
                            input.disabled = true;
                        }
                    }
                }
            }
        }

        function addPaymentDetails() {
            let detail = {};
            let allDetail = {};
            const inputs = document.querySelectorAll('#modalForm input:not([disabled])');

            inputs.forEach(input => {
                const name = input.getAttribute('name');
                if (name != null) {
                    const value = input.value;

                    if (name == "amount") {
                        let amountValue = input.value.replace(/[^0-9.]/g, ''); // only digits & dot

                        if (amountValue.includes('.')) {
                            let [intPart, decPart] = amountValue.split('.');
                            decPart = decPart.slice(0, 2); // max 2 decimals
                            amountValue = decPart ? `${intPart}.${decPart}` : intPart;
                        }

                        detail[name] = parseInt(amountValue);
                        allDetail[name] = parseInt(amountValue);
                    } else {
                        detail[name] = value;
                        allDetail[name] = value;
                    }
                } else {
                    const value = JSON.parse(input.value);

                    allDetail[name ?? 'selected'] = value;
                }
            });

            const selectBankAccount = document.querySelector("#modalForm select");
            if (selectBankAccount) {
                detail[selectBankAccount.getAttribute('name')] = selectBankAccount.value;
            }

            if (isNaN(detail.amount) || detail.amount <= 0) {
                detail = {};
            }

            if (Object.keys(detail).length > 0) {
                let selectedMethod = methodSelectDom.value;
                if (selectedMethod == 'Payment Program') {
                    selectedMethod = 'program';
                }
                if (selectedMethod == 'Purchase Return') {
                    selectedMethod = 'p. return';
                }
                totalPayment += detail.amount;
                detail['method'] = selectedMethod;
                // allDetail['method'] = selectedMethod;
                paymentDetailsArray.push(detail);
                // allPayments.push(allDetail);
                renderList();
            }
            closeModal('modalForm');
        }

        function renderList() {
            if (paymentDetailsArray.length > 0) {
                let clutter = "";
                paymentDetailsArray.forEach((paymentDetail, index) => {
                    let selected = paymentDetail.selected ? JSON.parse(paymentDetail.selected) : null;

                    clutter += `
                        <div class="flex justify-between items-center border-t border-gray-600 py-3 px-4">
                            <div class="w-[7%]">${index+1}</div>
                            @if ($voucherType == 'self_account')
                                <div class="w-1/3 capitalize">${paymentDetail.self_account_id_name ?? paymentDetail.self_account.account_title}</div>
                            @endif
                            <div class="w-1/5 capitalize">${paymentDetail.method}</div>
                            <div class="w-1/3 capitalize">${selected?.customer ? `${selected.customer.customer_name} | ${selected.customer.city?.title ?? '-'}` : paymentDetail?.cheque ? `${paymentDetail.cheque.customer?.customer_name ?? '-'} | ${paymentDetail.cheque.customer?.city?.title ?? '-'}` : paymentDetail?.slip ? `${paymentDetail.slip.customer?.customer_name ?? '-'} | ${paymentDetail.slip.customer?.city?.title ?? '-'}` : paymentDetail?.bank_account?.account_title ?? paymentDetail?.bank_account_id_name ?? '-'}</div>
                            <div class="w-1/5 capitalize">${selected?.slip_no ?? selected?.cheque_no ?? selected?.reff_no ?? selected?.transaction_id ?? paymentDetail?.cheque?.cheque_no ?? paymentDetail.cheque_no ?? paymentDetail.reff_no ?? paymentDetail?.slip?.slip_no ?? paymentDetail.slip_no ?? paymentDetail.transaction_id ?? '-'}</div>
                            <div class="w-1/6 capitalize">${selected?.remarks ?? (paymentDetail.remarks !== '' && paymentDetail.remarks) !== null ? paymentDetail.remarks : '-' ?? '-'}</div>
                            <div class="w-[15%]">${formatNumbersWithDigits(paymentDetail.amount, 1, 1)}</div>
                            <div class="w-[10%] text-center">
                                <button onclick="deselectThisPayment(${index})" type="button" class="text-[var(--danger-color)] text-xs px-2 py-1 rounded-lg hover:text-[var(--h-danger-color)] transition-all duration-300 ease-in-out cursor-pointer">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    `;
                });

                paymentListDom.innerHTML = clutter;

                paymentDetailsArrayDom.value = JSON.stringify(paymentDetailsArray);
            } else {
                paymentListDom.innerHTML =
                    `<div class="text-center bg-[var(--h-bg-color)] rounded-lg py-2 px-4">No Payment Yet</div>`;
            }
            finalTotalPaymentDom.textContent = formatNumbersWithDigits(totalPayment, 1, 1);
        }

        function deselectThisPayment(index) {
            totalPayment -= paymentDetailsArray[index].amount;
            paymentDetailsArray.splice(index, 1);
            renderList();
        }

        function generateVoucherPreview() {
            let voucherNo = voucher.voucher_no;
            const dateInpDom = document.getElementById("date");

            if (allPayments.length > 0) {
                previewDom.innerHTML = `
                    <div id="preview-document" class="preview-document flex flex-col h-full">
                        <div id="preview-banner" class="preview-banner w-full flex justify-between items-center mt-8 pl-5 pr-8">
                            <div class="left">
                                <div class="company-logo">
                                    <img src="{{ asset('images/${companyData.logo}') }}" alt="garmentsos-pro"
                                        class="w-[12rem]" />
                                </div>
                            </div>
                            <div class="right">
                                <div>
                                    <h1 class="text-2xl font-medium text-[var(--primary-color)] pr-2">Voucher</h1>
                                    <div class='mt-1'>${ companyData.phone_number }</div>
                                </div>
                            </div>
                        </div>
                        <hr class="w-full my-3 border-gray-600">
                        <div id="preview-header" class="preview-header w-full flex justify-between px-5">
                            <div class="left my-auto pr-3 text-sm text-gray-600 space-y-1.5">
                                <div class="voucher-date leading-none">Date: ${formatDate(dateInpDom.value)}</div>
                                <div class="voucher-number leading-none">Voucher No.: ${voucherNo}</div>
                                <input type="hidden" name="voucher_no" value="${voucherNo}" />
                            </div>
                            @if ($voucherType == 'supplier')
                                <div class="center my-auto">
                                    <div class="supplier-name capitalize font-semibold text-md">Supplier Name: ${selectedSupplier.supplier_name}</div>
                                </div>
                            @endif
                            <div class="right my-auto pr-3 text-sm text-gray-600 space-y-1.5">
                                <div class="preview-copy leading-none">Voucher Copy: Supplier</div>
                                <div class="preview-doc leading-none">Document: Voucher</div>
                            </div>
                        </div>
                        <hr class="w-full my-3 border-gray-600">
                        <div id="preview-body" class="preview-body w-[95%] grow mx-auto">
                            <div class="preview-table w-full">
                                <div class="table w-full border border-gray-600 rounded-lg pb-2.5 overflow-hidden">
                                    <div class="thead w-full">
                                        <div class="tr flex justify-between w-full px-4 py-1.5 bg-[var(--primary-color)] text-white">
                                            <div class="th text-sm font-medium w-[7%]">S.No</div>
                                            <div class="th text-sm font-medium w-[11%]">Method</div>
                                            <div class="th text-sm font-medium w-1/5">Customer</div>
                                            <div class="th text-sm font-medium w-1/4">Account</div>
                                            <div class="th text-sm font-medium w-[14%]">Date</div>
                                            <div class="th text-sm font-medium w-[14%]">Reff. No.</div>
                                            <div class="th text-sm font-medium w-[10%]">Amount</div>
                                        </div>
                                    </div>
                                    <div id="tbody" class="tbody w-full">
                                        ${paymentDetailsArray.map((payment, index) => {

                                            let selected = JSON.parse(payment.selected || '{}');

                                            const hrClass = index === 0 ? "mb-2.5" : "my-2.5";
                                            return `
                                                    <div>
                                                        <hr class="w-full ${hrClass} border-gray-600">
                                                        <div class="tr flex justify-between w-full px-4">
                                                            <div class="td text-sm font-semibold w-[7%]">${index + 1}.</div>
                                                            <div class="td text-sm font-semibold w-[11%] capitalize">${payment.method ?? '-'}</div>
                                                            <div class="td text-sm font-semibold w-1/5">${payment.program?.customer?.customer_name ? payment.program?.customer?.customer_name : selected.customer?.customer_name ? selected.customer?.customer_name : payment.cheque ? payment.cheque.customer.customer_name : payment.slip ? payment.slip.customer.customer_name : '-'}</div>
                                                            <div class="td text-sm font-semibold w-1/4">${payment.bank_account ? payment.bank_account.account_title + ' | ' + payment.bank_account.bank.short_title : (selected?.bank_account?.account_title ?? '-') + ' | ' + (selected?.bank_account?.bank.short_title ?? '-')}</div>
                                                            <div class="td text-sm font-semibold w-[14%]">${formatDate(dateInpDom.value, true) ?? '-'}</div>
                                                            <div class="td text-sm font-semibold w-[14%]">${selected?.cheque_no ?? selected?.slip_no ?? selected?.transaction_id ?? selected?.reff_no ?? payment.cheque_no ?? payment.reff_no ?? payment.transaction_id ?? payment.cheque?.cheque_no ?? payment.slip?.slip_no ?? '-'}</div>
                                                            <div class="td text-sm font-semibold w-[10%]">${formatNumbersWithDigits(payment.amount, 1, 1) ?? '-'}</div>
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
                            <div id="total" class="tr flex justify-between w-full px-2 gap-2 text-sm">
                                @if ($voucherType == 'supplier')
                                    <div class="total flex justify-between items-center border border-gray-600 rounded-lg py-2 px-4 w-full">
                                        <div class="text-nowrap">Previous Balance - Rs</div>
                                        <div class="w-1/4 text-right grow">${formatNumbersWithDigits(selectedSupplier.balance, 1, 1)}</div>
                                    </div>
                                @endif
                                <div class="total flex justify-between items-center border border-gray-600 rounded-lg py-2 px-4 w-full">
                                    <div class="text-nowrap">Total Payment - Rs</div>
                                    <div class="w-1/4 text-right grow">${formatNumbersWithDigits(totalPayment, 1, 1)}</div>
                                </div>
                                @if ($voucherType == 'supplier')
                                    <div class="total flex justify-between items-center border border-gray-600 rounded-lg py-2 px-4 w-full">
                                        <div class="text-nowrap">Current Balance - Rs</div>
                                        <div class="w-1/4 text-right grow">${formatNumbersWithDigits(selectedSupplier.balance - totalPayment, 1, 1)}</div>
                                    </div>
                                @endif
                            </div>
                        </div>
                        <hr class="w-full my-3 border-gray-600">
                        <div class="tfooter flex w-full text-sm px-4 justify-between mb-4 text-gray-600">
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

        function validateForNextStep() {
            generateVoucherPreview();
            return true;
        }
    </script>
@endsection
