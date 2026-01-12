@extends('app')
@section('title', 'Generate DR | ' . $client_company->name)
@section('content')
    @php
        $method_options = [
            'cash' => ['text' => 'Cash'],
            'cheque' => ['text' => 'Cheque'],
            'slip' => ['text' => 'Slip'],
            'online' => ['text' => 'Online'],
        ];
    @endphp
    <!-- Main Content -->
    <!-- Progress Bar -->
    <div class="mb-5 max-w-5xl mx-auto">
        <x-search-header heading="Generate DR" link linkText="Show DR" linkHref="{{ route('dr.index') }}"/>
        <x-progress-bar :steps="['Select Payment', 'Add Payment']" :currentStep="1" />
    </div>

    <!-- Form -->
    <form id="form" action="{{ route('dr.store') }}" method="post" enctype="multipart/form-data"
        class="bg-[var(--secondary-bg-color)] text-sm rounded-xl shadow-lg p-8 border border-[var(--glass-border-color)]/20 pt-14 max-w-5xl mx-auto  relative overflow-hidden">
        @csrf
        <x-form-title-bar title="Generate DR" />

        <!-- Step 1: Generate cargo list -->
        <div class="step1 space-y-4 ">
            <div class="flex items-end gap-4">
                <div class="grow">
                    <!-- customer -->
                    <x-select
                        label="Customer"
                        id="customer"
                        name="customer_id"
                        :options="$customer_options"
                        showDefault
                        onchange="trackCustomerState(this)"
                    />
                </div>

                <div class="w-1/4">
                    {{-- date --}}
                    <x-input label="Date" name="date" id="date" type="date" validateMax max="{{ today()->toDateString() }}" required/>
                </div>

                <button id="showPaymentBtn" type="button" class="bg-[var(--primary-color)] px-4 py-2 rounded-lg hover:bg-[var(--h-primary-color)] transition-all duration-300 ease-in-out text-nowrap cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed" disabled onclick="getPayments()">Show Payments</button>
            </div>
            <input type="hidden" name="returnPayments" id="selectedPaymentsArray">
            {{-- show-payment-table --}}
            <div id="show-payment-table" class="w-full text-left text-sm">
                <div class="flex justify-between items-center bg-[var(--h-bg-color)] rounded-lg py-2 px-4">
                    <div class="w-[8%]">S.No.</div>
                    <div class="w-1/6">Date</div>
                    <div class="w-[10%]">Method</div>
                    <div class="w-1/6">Reff. No.</div>
                    <div class="w-1/6">Amount</div>
                    <div class="w-1/6">Issued</div>
                    <div class="w-[10%] text-center">Select</div>
                </div>
                <div id="show-payments" class="h-[20rem] overflow-y-auto my-scrollbar-2">
                    <div class="text-center bg-[var(--h-bg-color)] rounded-lg py-2 px-4 mt-4">No Payments Added</div>
                </div>
            </div>

            <div class="w-full grid grid-cols-2 gap-4 text-sm mt-5 text-nowrap">
                <div class="flex justify-between items-center border border-gray-600 rounded-lg py-2 px-4 w-full">
                    <div class="grow">Total Selected Payments</div>
                    <div id="finalSelectedPayments">0</div>
                </div>
                <div class="flex justify-between items-center border border-gray-600 rounded-lg py-2 px-4 w-full">
                    <div class="grow">Total Selected Amount</div>
                    <div class="finalTotalSelectedAmount">0</div>
                </div>
            </div>
        </div>

        <!-- Step 2: view shipment -->
        <div class="step2 hidden space-y-4">
            <div class="flex items-end gap-4">
                <div class="grow">
                    <!-- method -->
                    <x-select
                        label="Method"
                        id="method"
                        required
                        showDefault
                        :options="$method_options"
                        onchange="trackMethodState(this)"
                    />
                </div>
            </div>
            <input type="hidden" name="newPayments" id="addedPaymentsArray">
            {{-- add-payment-table --}}
            <div id="add-payment-table" class="w-full text-left text-sm">
                <div class="grid grid-cols-5 bg-[var(--h-bg-color)] rounded-lg py-2 px-4">
                    <div>S.No.</div>
                    <div>Method</div>
                    <div>Reff. No.</div>
                    <div>Amount</div>
                    <div class="text-center">Action</div>
                </div>
                <div id="added-payments" class="h-[20rem] overflow-y-auto my-scrollbar-2">
                    <div class="text-center bg-[var(--h-bg-color)] rounded-lg py-2 px-4 mt-4">No Payments Added</div>
                </div>
            </div>

            <div class="w-full grid grid-cols-2 gap-4 text-sm mt-5 text-nowrap">
                <div class="flex justify-between items-center border border-gray-600 rounded-lg py-2 px-4 w-full">
                    <div class="grow">Total Selected Amount</div>
                    <div class="finalTotalSelectedAmount">0</div>
                </div>
                <div class="flex justify-between items-center border border-gray-600 rounded-lg py-2 px-4 w-full">
                    <div class="grow">Total Added Payment</div>
                    <div id="finalTotalAddedAmount">0</div>
                </div>
            </div>
        </div>
    </form>
    <script>
        let payments = [];
        let selectedPayments = [];
        let totalAddedAmount = 0;
        let totalSelectedAmount = 0;
        let addedPaymentsArray = [];

        function trackCustomerState(elem) {
            const showPaymentBtn = document.getElementById('showPaymentBtn');
            if (elem.value) {
                showPaymentBtn.disabled = false;
            } else {
                showPaymentBtn.disabled = true;
            }
        }

        function getPayments() {
            $.ajax({
                url: '/dr/get-payments',
                method: 'GET',
                data: {
                    customer_id: document.querySelector('input[data-for="customer"]').value,
                },
                success: function(response) {
                    if (response.status === 'success') {
                        payments = response.data;
                        renderList();
                    } else {
                        console.error('Failed to fetch payments');
                    }
                },
                error: function(xhr) {
                    // Handle any errors that occur during the request
                    console.error(xhr.responseText);
                }
            });
        }

        function renderList() {
            const showPaymentsDom = document.getElementById('show-payments');
            showPaymentsDom.innerHTML = '';

            if (payments.length > 0) {
                payments.forEach((payment, index) => {
                    showPaymentsDom.innerHTML += `
                        <div id="${payment.id}" class="flex justify-between items-center border-b border-gray-600 py-2 px-4 cursor-pointer" onclick="togglePaymentSelection(this)">
                            <div class="w-[8%]">${index + 1}.</div>
                            <div class="w-1/6">${formatDate(payment.date)}</div>
                            <div class="w-[10%] capitalize">${payment.method}</div>
                            <div class="w-1/6">${payment.cheque_no || payment.slip_no}</div>
                            <div class="w-1/6">${formatNumbersWithDigits(payment.amount, 1, 1)}</div>
                            <div class="w-1/6">${payment.is_return ? 'Return' : 'Not Issued'}</div>
                            <div class="w-[10%] grid place-items-center">
                                <input ${payment.checked ? 'checked' : ''} type="checkbox" class="row-checkbox hrink-0 w-3.5 h-3.5 appearance-none border border-gray-400 rounded-sm checked:bg-[var(--primary-color)] checked:border-transparent focus:outline-none transition duration-150 pointer-events-none cursor-pointer"/>
                            </div>
                        </div>
                    `;
                })
            } else {
                showPaymentsDom.innerHTML = '<div class="text-center bg-[var(--h-bg-color)] rounded-lg py-2 px-4 mt-4">No Payments Added</div>';
            }

            const finalSelectedPayments = document.getElementById('finalSelectedPayments');
            finalSelectedPayments.innerText = selectedPayments.length;
            const finalTotalSelectedAmount = document.querySelectorAll('.finalTotalSelectedAmount');
            totalSelectedAmount = payments.filter(p => p.checked).reduce((sum, p) => sum + parseFloat(p.amount), 0);
            finalTotalSelectedAmount.forEach(element => {
                element.innerText = formatNumbersWithDigits(totalSelectedAmount, 1, 1);
            });;
            document.getElementById('selectedPaymentsArray').value = JSON.stringify(selectedPayments);
        }

        function togglePaymentSelection(row) {
            const paymentId = row.id;
            const payment = payments.find(p => p.id == paymentId);
            if (payment) {
                payment.checked = !payment.checked;
                const checkbox = row.querySelector('input[type="checkbox"]');
                if (checkbox) {
                    checkbox.checked = payment.checked;
                }
            }

            if (selectedPayments.includes(paymentId)) {
                selectedPayments = selectedPayments.filter(id => id !== paymentId);
            } else {
                selectedPayments.push(paymentId);
            }

            renderList();
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
                        oninput: 'trackAmountState(this)',
                        onkeydown: "enterToAdd(event)"
                    },
                );
            } else if (elem.value == 'cheque') {
                fieldsData.push(
                    {
                        category: 'explicitHtml',
                        html: `
                            <x-select label="Bank" name="bank_id" id="bank_id" required :options="$bank_options" showDefault />
                        `,
                    },
                    {
                        category: 'input',
                        name: 'cheque_no',
                        label: 'Cheque No.',
                        data_validate: 'required|friendly',
                        required: true,
                        placeholder: 'Enter cheque no.',
                    },
                    {
                        category: 'input',
                        name: 'cheque_date',
                        label: 'Cheque Date',
                        type: 'date',
                        required: true,
                    },
                    {
                        category: 'input',
                        name: 'amount',
                        label: 'Amount',
                        type: 'amount',
                        data_validate: 'required|amount',
                        required: true,
                        placeholder: 'Enter amount',
                        oninput: 'trackAmountState(this)',
                        onkeydown: "enterToAdd(event)"
                    },
                );
            } else if (elem.value == 'slip') {
                fieldsData.push(
                    {
                        category: 'input',
                        name: 'slip_no',
                        label: 'Slip No.',
                        data_validate: 'required|friendly',
                        required: true,
                        placeholder: 'Enter slip no.',
                    },
                    {
                        category: 'input',
                        name: 'slip_date',
                        label: 'Slip Date',
                        type: 'date',
                        required: true,
                    },
                    {
                        category: 'input',
                        name: 'amount',
                        label: 'Amount',
                        type: 'amount',
                        data_validate: 'required|amount',
                        required: true,
                        placeholder: 'Enter amount',
                        oninput: 'trackAmountState(this)',
                        onkeydown: "enterToAdd(event)"
                    },
                );
            } else if (elem.value == 'online') {
                fieldsData.push(
                    {
                        category: 'explicitHtml',
                        html: `
                            <x-select label="Bank" name="bank_id" id="bank_id" required :options="$bank_options" showDefault />
                        `,
                    },
                    {
                        category: 'input',
                        name: 'transaction_id',
                        label: 'Transaction Id',
                        data_validate: 'required|friendly',
                        required: true,
                        placeholder: 'Enter transaction id',
                    },
                    {
                        category: 'input',
                        name: 'date',
                        label: 'Date',
                        type: 'date',
                        required: true,
                    },
                    {
                        category: 'input',
                        name: 'amount',
                        label: 'Amount',
                        type: 'amount',
                        data_validate: 'required|amount',
                        required: true,
                        placeholder: 'Enter amount',
                        oninput: 'trackAmountState(this)',
                        onkeydown: "enterToAdd(event)"
                    },
                );
            }

            if (elem.value != '') {
                fieldsData.push({
                    category: 'explicitHtml',
                    html: `
                        <x-input label="Remarks" name="remarks" id="remarks" placeholder="Enter remarks" dataValidate="friendly" oninput="trackAmountState(this)"/>
                    `,
                });

                const visibleIndexes = fieldsData
                .map((field, index) => field.type !== 'hidden' ? index : null)
                .filter(index => index !== null);

                if (visibleIndexes.length > 0) {
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
            }
        }

        function addPaymentDetails() {
            let detail = {};
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
                    } else {
                        detail[name] = value;
                    }
                } else {
                    const value = JSON.parse(input.value);
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
                let selectedMethod = document.getElementById('method').value;
                totalAddedAmount += detail.amount;
                detail['method'] = selectedMethod;
                addedPaymentsArray.push(detail);
                renderSecondList();
            }
            closeModal('modalForm');
        }

        function trackAmountState(elem) {
            let currentValue = elem.value.replace(/[^0-9.]/g, ''); // input ko format karke number return karega

            if (currentValue > (totalSelectedAmount - totalAddedAmount)) {
                elem.value = formatNumbersDigitLess(totalSelectedAmount - totalAddedAmount);
            }
        }

        function renderSecondList() {
            const addedPaymentsDom = document.getElementById('added-payments');
            addedPaymentsDom.innerHTML = '';

            if (addedPaymentsArray.length > 0) {
                addedPaymentsArray.forEach((payment, index) => {
                    let reff_no = payment.cheque_no || payment.slip_no || payment.transaction_id || '-';
                    addedPaymentsDom.innerHTML += `
                        <div class="grid grid-cols-5 border-b border-gray-600 py-2 px-4 cursor-pointer">
                            <div>${index + 1}.</div>
                            <div>${payment.method}</div>
                            <div>${reff_no}</div>
                            <div>${formatNumbersWithDigits(payment.amount, 1, 1)}</div>
                            <div class="text-center">
                                <button onclick="deselectThisPayment(${index})" type="button" class="text-[var(--danger-color)] text-xs px-2 py-1 rounded-lg hover:text-[var(--h-danger-color)] transition-all duration-300 ease-in-out cursor-pointer">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    `;
                })
            } else {
                addedPaymentsDom.innerHTML = '<div class="text-center bg-[var(--h-bg-color)] rounded-lg py-2 px-4 mt-4">No Payments Added</div>';
            }

            const finalTotalAddedAmount = document.getElementById('finalTotalAddedAmount');
            finalTotalAddedAmount.innerText = formatNumbersWithDigits(totalAddedAmount, 1, 1);
            document.getElementById('addedPaymentsArray').value = JSON.stringify(addedPaymentsArray);

            if (totalAddedAmount === totalSelectedAmount) {
                document.getElementById('method').disabled = true;
            } else {
                document.getElementById('method').disabled = false;
            }
        }

        function deselectThisPayment(index) {
            totalAddedAmount -= addedPaymentsArray[index].amount;
            addedPaymentsArray.splice(index, 1);
            renderSecondList();
        }

        function enterToAdd(event) {
            if (event.key == 'Enter') {
                addPaymentDetails();
            }
        }

        validateForNextStep = () => {
            return true;
        }

        function onSubmitFunction() {
            if (totalSelectedAmount <= 0) {
                messageBox.innerHTML = `
                    <x-alert type="error" :messages="'Please select at least one payment before submitting.'" />
                `;
                messageBoxAnimation();
                return false;
            }

            if (totalAddedAmount !== totalSelectedAmount) {
                messageBox.innerHTML = `
                    <x-alert type="error" :messages="'The total added amount must be equal to the total selected amount.'" />
                `;
                messageBoxAnimation();
                return false;
            }

            return true;
        }
    </script>
@endsection
