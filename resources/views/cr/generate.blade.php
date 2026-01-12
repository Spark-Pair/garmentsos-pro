@extends('app')
@section('title', 'Generate CR | ' . $client_company->name)
@section('content')
    @php
        $method_options = [
            'cheque' => ['text' => 'Cheque'],
            'slip' => ['text' => 'Slip'],
            'self_cheque' => ['text' => 'Self Cheque'],
            'program' => ['text' => 'Payment Program'],
        ];
    @endphp
    <!-- Main Content -->
    <!-- Progress Bar -->
    <div class="mb-5 max-w-5xl mx-auto">
        <x-search-header heading="Generate CR" link linkText="Show CR" linkHref="{{ route('cr.index') }}"/>
        <x-progress-bar :steps="['Select Payment', 'Add Payment']" :currentStep="1" />
    </div>

    <!-- Form -->
    <form id="form" action="{{ route('cr.store') }}" method="post" enctype="multipart/form-data"
        class="bg-[var(--secondary-bg-color)] text-sm rounded-xl shadow-lg p-8 border border-[var(--glass-border-color)]/20 pt-14 max-w-5xl mx-auto  relative overflow-hidden">
        @csrf
        <x-form-title-bar title="Generate CR" />

        <!-- Step 1: Generate cargo list -->
        <div class="step1 space-y-4 ">
            <div class="grid grid-cols-4 gap-4">
                <!-- voucher_no -->
                <x-input
                    label="Voucher No."
                    id="voucher_no"
                    name="voucher_no"
                    placeholder="Enter Voucher No."
                    required
                    onkeydown="trackVoucherState(event)"
                />
                <input type="hidden" name="voucher_id" id="voucher_id">

                {{-- cargo date --}}
                <x-input label="Date" name="date" id="date" type="date" validateMax max="{{ today()->toDateString() }}" required disabled/>

                <!-- supplier_name -->
                <x-input
                    label="Supplier Name"
                    id="supplier_name"
                    disabled
                    placeholder="Supplier Name"
                />

                {{-- c_r_no --}}
                <x-input label="CR No." name="c_r_no" id="c_r_no" required value="CR-"/>
            </div>
            <input type="hidden" name="returnPayments" id="selectedPaymentsArray">
            {{-- show-payment-table --}}
            <div id="show-payment-table" class="w-full text-left text-sm">
                <div class="flex justify-between items-center bg-[var(--h-bg-color)] rounded-lg py-2 px-4 mb-4">
                    <div class="w-[8%]">S.No.</div>
                    <div class="w-1/6">Date</div>
                    <div class="w-[10%]">Method</div>
                    <div class="w-1/6">Reff. No.</div>
                    <div class="w-1/6">Amount</div>
                    <div class="grow">Customer</div>
                    <div class="w-[10%] text-center">Select</div>
                </div>
                <div id="show-payment" class="h-[20rem] overflow-y-auto my-scrollbar-2">
                    <div class="text-center bg-[var(--h-bg-color)] rounded-lg py-3 px-4">No Payments Added</div>
                </div>
            </div>

            <div class="w-full grid grid-cols-2 gap-4 text-sm mt-5 text-nowrap">
                <div class="flex justify-between items-center border border-gray-600 rounded-lg py-2 px-4 w-full">
                    <div class="grow">Total Voucher Payment</div>
                    <div id="finalTotalPayment">0</div>
                </div>
                <div class="flex justify-between items-center border border-gray-600 rounded-lg py-2 px-4 w-full">
                    <div class="grow">Total Selected Payment</div>
                    <div id="finalTotalSelectedPayment">0</div>
                </div>
            </div>
        </div>

        <!-- Step 2: view shipment -->
        <div class="step2 hidden space-y-4">
            <div class="flex items-end gap-4">
                <!-- method -->
                <x-select
                    label="Method"
                    id="method"
                    :options="$method_options"
                    required
                    showDefault
                    onchange="trackMethodState(this)"
                />

                <div class="grow">
                    <!-- payment -->
                    <x-select
                        label="Payment"
                        id="payment"
                        :options="$payment_options"
                        required
                        showDefault
                        onchange="trackPaymentState(this)"
                    />
                </div>

                <!-- supplier_name -->
                <x-input
                    label="Amount"
                    id="amount"
                    name="amount"
                    disabled
                    placeholder="Enter Amount"
                    type="amount"
                    dataValidate="required|amount"
                    oninput="trackAmountState(this)"
                    onkeydown="enterToAdd(event)"
                />

                <button id="addPaymentBtn" type="button" class="bg-[var(--primary-color)] px-4 py-2 rounded-lg hover:bg-[var(--h-primary-color)] transition-all duration-300 ease-in-out text-nowrap cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed" onclick="addPayment()">Add Payment</button>
            </div>
            <input type="hidden" name="newPayments" id="addedPaymentsArray">
            {{-- add-payment-table --}}
            <div id="add-payment-table" class="w-full text-left text-sm">
                <div class="grid grid-cols-6 bg-[var(--h-bg-color)] rounded-lg py-2 px-4 mb-4">
                    <div>S.No.</div>
                    <div>Method</div>
                    <div class="col-span-2">Payment</div>
                    <div>Amount</div>
                    <div class="text-center">Action</div>
                </div>
                <div id="add-payment" class="h-[20rem] overflow-y-auto my-scrollbar-2">
                    <div class="text-center bg-[var(--h-bg-color)] rounded-lg py-3 px-4">No Payments Added</div>
                </div>
            </div>

            <div class="w-full grid grid-cols-2 gap-4 text-sm mt-5 text-nowrap">
                <div class="flex justify-between items-center border border-gray-600 rounded-lg py-2 px-4 w-full">
                    <div class="grow">Total Selected Payment</div>
                    <div id="finalTotalSelectedPayment">0</div>
                </div>
                <div class="flex justify-between items-center border border-gray-600 rounded-lg py-2 px-4 w-full">
                    <div class="grow">Total Added Payment</div>
                    <div id="finalTotalAddedPayment">0</div>
                </div>
            </div>
        </div>
    </form>

    <script>
        let voucher = {};
        let paymentsArray = [];
        let addedPaymentsArray = [];
        const voucherIdInpDom = document.getElementById('voucher_id');
        const selectedPaymentsArrayDom = document.getElementById('selectedPaymentsArray');
        const addedPaymentsArrayDom = document.getElementById('addedPaymentsArray');
        const dateDom = document.getElementById('date');
        const supplierNameDom = document.getElementById('supplier_name');
        const showPaymentListDOM = document.getElementById('show-payment');
        const addPaymentListDOM = document.getElementById('add-payment');
        const finalTotalPaymentDOM = document.getElementById('finalTotalPayment');
        const finalTotalSelectedPaymentDOM = document.querySelectorAll('#finalTotalSelectedPayment');
        const finalTotalAddedPaymentDOM = document.getElementById('finalTotalAddedPayment');
        const methodSelectDOM = document.getElementById('method');
        const amountDOM = document.getElementById('amount');
        let totalVoucherAmount = 0;
        let totalSelectedAmount = 0;
        let totalAddedAmount = 0;

        function trackVoucherState(e) {
            if (e.key == 'Enter') {
                $.ajax({
                    url: '/get-voucher-details',
                    type: 'POST',
                    data: {
                        voucher_no: e.target.value,
                    },
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function(response) {
                        voucher = response.data;
                        if (voucher) {
                            dateDom.disabled = false;
                            dateDom.min = voucher.date;
                            supplierNameDom.value = voucher.supplier_name;

                            paymentsArray = voucher.payments;

                            const messages = document.querySelectorAll('.alert-message');

                            messages.forEach((message) => {
                                if (message) {
                                    message.classList.add('fade-out');
                                    message.addEventListener('animationend', () => {
                                        message.style.display = 'none';
                                    });
                                }
                            });

                            voucherIdInpDom.value = voucher.id;
                        } else {
                            dateDom.value = '';
                            dateDom.disabled = true;
                            supplierNameDom.value = '';
                            paymentsArray = [];

                            messageBox.innerHTML = `
                                <x-alert type="error" :messages="'${response.message}'" />
                            `;
                            messageBoxAnimation()
                        }
                        renderSelectPaymentList()
                    },
                    error: function(xhr, status, error) {
                        console.error(error);
                    }
                });
            }
        }

        function renderSelectPaymentList() {
            totalVoucherAmount = 0;
            totalSelectedAmount = 0;
            if (paymentsArray.length > 0) {
                let clutter = "";
                paymentsArray.forEach((payment, index) => {
                    totalVoucherAmount += payment.amount;
                    totalSelectedAmount += payment.checked ? payment.amount : 0;
                    clutter += `
                        <div class="flex justify-between items-end border-t border-gray-600 py-3 px-4 cursor-pointer" onclick="selectThisPayment(this, ${index})">
                            <div class="w-[8%]">${index+1}</div>
                            <div class="w-1/6">${formatDate(payment.slip?.slip_date || payment.cheque?.cheque_date || payment.date)}</div>
                            <div class="w-[10%] capitalize">${payment.method}</div>
                            <div class="w-1/6">${payment.reff_no ?? '-'}</div>
                            <div class="w-1/6">${formatNumbersWithDigits(payment.amount, 1, 1) ?? '-'}</div>
                            <div class="grow">${payment.customer_name ?? '-'}</div>
                            <div class="w-[10%] grid place-items-center">
                                <input ${payment.checked ? 'checked' : ''} type="checkbox" class="row-checkbox hrink-0 w-3.5 h-3.5 appearance-none border border-gray-400 rounded-sm checked:bg-[var(--primary-color)] checked:border-transparent focus:outline-none transition duration-150 pointer-events-none cursor-pointer"/>
                            </div>
                        </div>
                    `;
                });

                showPaymentListDOM.innerHTML = clutter;
            } else {
                showPaymentListDOM.innerHTML =
                    `<div class="text-center bg-[var(--h-bg-color)] rounded-lg py-2 px-4">No Payments Yet</div>`;
            }
            finalTotalPaymentDOM.textContent = formatNumbersWithDigits(totalVoucherAmount, 1, 1);
            finalTotalSelectedPaymentDOM.forEach(elem => {
                elem.textContent = formatNumbersWithDigits(totalSelectedAmount, 1, 1);
            });
            selectedPaymentsArrayDom.value = JSON.stringify(paymentsArray.filter(p => p.checked == true));
        }

        function renderAddPaymentList() {
            totalAddedAmount = 0;
            if (addedPaymentsArray.length > 0) {
                let clutter = "";
                addedPaymentsArray.forEach((payment, index) => {
                    totalAddedAmount += parseInt(payment.amount);
                    clutter += `
                        <div class="grid grid-cols-6 border-t border-gray-600 py-3 px-4 cursor-pointer">
                            <div>${index+1}</div>
                            <div>${payment.method}</div>
                            <div class="col-span-2">${payment.payment}</div>
                            <div>${formatNumbersDigitLess(payment.amount)}</div>
                            <div class="text-center">
                                <button onclick="deleteThis(this, ${index})" type="button" class="text-[var(--danger-color)] text-xs px-2 py-1 rounded-lg hover:text-[var(--h-danger-color)] transition-all duration-300 ease-in-out cursor-pointer">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    `;
                });

                addPaymentListDOM.innerHTML = clutter;
            } else {
                addPaymentListDOM.innerHTML =
                    `<div class="text-center bg-[var(--h-bg-color)] rounded-lg py-2 px-4">No Payments Yet</div>`;
            }

            if (totalSelectedAmount != 0 && totalSelectedAmount === totalAddedAmount) {
                methodSelectDOM.disabled = true;
                methodSelectDOM.value = '';
                document.getElementById('payment').disabled = true;
                document.getElementById('payment').value = '';
                amountDOM.disabled = true;
                amountDOM.value = '';
                document.getElementById('amount-error').classList.add('hidden');
            } else {
                methodSelectDOM.disabled = false;
            }

            finalTotalAddedPaymentDOM.textContent = formatNumbersWithDigits(totalAddedAmount, 1, 1);
            addedPaymentsArrayDom.value = JSON.stringify(addedPaymentsArray);
        }
        renderSelectPaymentList();
        renderAddPaymentList();

        function selectThisPayment(elem, index) {
            let checkBox = elem.querySelector('.row-checkbox');
            checkBox.checked = !checkBox.checked;
            paymentsArray[index].checked = !paymentsArray[index].checked;

            renderSelectPaymentList();
        }

        function trackMethodState(elem) {
            amountDOM.value = '';
            amountDOM.disabled = true;
            document.getElementById('payment').value = '';
            document.getElementById('payment').disabled = true;

            if (elem.value != '') {
                $.ajax({
                    url: '/cr/create',
                    type: 'GET',
                    data: {
                        supplier: voucher.supplier_id,
                        method: elem.value,
                        max_date: dateDom.value,
                    },
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function(response) {
                        $('#payment').closest('.selectParent').html($(response).find('#payment').closest('.selectParent').html());
                        let allPaymentsDOM = document.querySelectorAll('ul[data-for="payment"] li');
                        allPaymentsDOM.forEach(paymentDOM => {
                            addedPaymentsArray.forEach(payment => {
                                if (payment.data_value === paymentDOM.dataset.value) {
                                    paymentDOM.remove();
                                }
                            })
                            if (JSON.parse(paymentDOM.dataset.option || '{}').amount > totalSelectedAmount) {
                                paymentDOM.remove();
                            };
                        })
                        if (document.querySelectorAll('ul[data-for="payment"] li').length <= 1) {
                            document.getElementById('payment').value = '';
                            document.getElementById('payment').disabled = true;
                            document.getElementById('payment').placeholder = '-- No options available --';
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error(error);
                    }
                });
            }
        }

        function trackPaymentState(elem) {
            amountDOM.value = '';
            amountDOM.disabled = true;
            if (elem.value != '') {
                if (methodSelectDOM.value === 'Self Cheque') {
                    amountDOM.disabled = false;
                } else {
                    const selectedPayment = JSON.parse(elem.parentElement.querySelector('ul[data-for="payment"] li.selected').dataset.option || '{}');
                    amount.value = selectedPayment.amount;
                }
            }
        }

        function addPayment() {
            let currentValue = amountDOM.value.replace(/[^0-9.]/g, ''); // input ko format karke number return karega
            
            if (currentValue > 0) {
                addedPaymentsArray.push({
                    'bank_account_id': JSON.parse(document.querySelector('ul[data-for="payment"] li.selected').dataset.option || '{}').id,
                    'data_value': document.querySelector('ul[data-for="payment"] li.selected').getAttribute('data-value'),
                    'method': methodSelectDOM.value,
                    'payment': document.getElementById('payment').value,
                    'amount': currentValue,
                })

                methodSelectDOM.value = '';
                document.getElementById('payment').value = '';
                currentValue = '';
                renderAddPaymentList();
            }
        }

        function deleteThis(elem, index) {
            addedPaymentsArray.splice(index, 1);
            renderAddPaymentList();
        }

        function trackAmountState(elem) {
            let currentValue = elem.value.replace(/[^0-9.]/g, ''); // input ko format karke number return karega

            if (currentValue > (totalSelectedAmount - totalAddedAmount)) {
                elem.value = formatNumbersDigitLess(totalSelectedAmount - totalAddedAmount);
            }
        }

        function enterToAdd(event) {
            if (event.key == 'Enter') {
                addPayment();
            }
        }

        function validateForNextStep() {
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
