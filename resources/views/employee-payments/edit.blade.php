@extends('app')
@section('title', 'Edit Customer Payment | ' . $client_company->name)
@section('content')
    @php
        $method_options = [
            'cash' => ['text' => 'Cash'],
            'cheque' => ['text' => 'Cheque'],
            'slip' => ['text' => 'Slip'],
            'adjustment' => ['text' => 'Adjustment'],
        ];
        $type_options = [
            'normal' => ['text' => 'Normal'],
            'payment_program' => ['text' => 'Payment Program'],
            'recovery' => ['text' => 'Recovery'],
        ]
    @endphp
    <!-- Progress Bar -->
    <div class="mb-5 max-w-3xl mx-auto">
        <x-search-header heading="Edit Customer Payment" link linkText="Show Payments" linkHref="{{ route('customer-payments.index') }}"/>
    </div>

    <div class="row max-w-3xl mx-auto flex gap-4">
        <!-- Form -->
        <form id="form" action="{{ route('customer-payments.update', ['customer_payment' => $customerPayment->id]) }}" method="post"
            class="bg-[var(--secondary-bg-color)] text-sm rounded-xl shadow-lg p-7 border border-[var(--h-bg-color)] pt-12 w-full mx-auto relative overflow-hidden">
            @csrf
            @method('PUT')
            <x-form-title-bar title="Edit Customer Payment" />

            <div class="step space-y-4 overflow-y-auto max-h-[65vh] p-1 my-scrollbar-2">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    {{-- customer --}}
                    <x-input
                        label="Customer"
                        value="{{ $customerPayment->customer->customer_name }}"
                        disabled
                    />

                    {{-- balance --}}
                    <x-input label="Balance" value="{{ $customerPayment->customer->balance }}" disabled />

                    {{-- date --}}
                    <x-input label="Date" name="date" id="date" type="date" required value="{{ $customerPayment->date->format('Y-m-d') }}" readonly onchange="trackDateState(this)"/>

                    {{-- type --}}
                    <x-select
                        label="Type"
                        name="type"
                        id="type"
                        :options="$type_options"
                        required
                        showDefault
                        onchange="trackTypeState(this)"
                    />

                    <div class="col-span-full">
                        <div id="details-inputs-container" class="grid grid-cols-1 md:grid-cols-2 gap-4 col-span-full">
                        </div>
                        {{-- method --}}
                        <x-select
                            label="Method"
                            name="method"
                            id="method"
                            :options="$method_options"
                            required
                            showDefault
                            onchange="trackMethodState(this)"
                        />

                        <hr class="border-gray-600 my-3">

                        <div id="details" class="grid grid-cols-1 md:grid-cols-3 gap-4">

                        </div>
                    </div>
                </div>
            </div>
            <div class="w-full flex justify-end mt-4">
                <button type="submit"
                    class="px-10 py-2 bg-[var(--bg-success)] border border-[var(--bg-success)] text-[var(--text-success)] font-medium text-nowrap rounded-lg hover:bg-[var(--h-bg-success)] hover:border-[var(--border-success)] hover:scale-90 transition-all duration-300 ease-in-out cursor-pointer">
                    <i class='fas fa-save mr-1'></i> Save
                </button>
            </div>
        </form>
    </div>

    <script>
        window.chequeNos = @json($cheque_nos ?? '');
        window.slipNos = @json($slip_nos ?? '');

        let customerPayment = @json($customerPayment);
        customerPayment.remarks = customerPayment.remarks || '';
        let methodSelectDom = document.getElementById('method');
        let typeSelectDom = document.getElementById('type');
        let dateDom = document.getElementById('date');
        let detailsDom = document.getElementById('details');

        selectedCustomerData = null;
        let selectedProgramData = {};

        let selectedCustomer;

        const today = new Date().toISOString().split('T')[0];

        function setOptionOnNthLi(triggerDom, index, key, value = '') {
            const li = triggerDom.closest(".selectParent")?.querySelectorAll('ul li')[index];
            if (li) li.dataset[key] = value;
        }

        function trackCustomerState() {
            setOptionOnNthLi(typeSelectDom, 2, 'option');
            methodSelectDom.value = '';
            typeSelectDom.value = '';

            if (customerPayment) {
                selectedCustomer = customerPayment.customer;
                dateDom.disabled = false;
                methodSelectDom.disabled = false;
                dateDom.min = selectedCustomer?.date.toString().split('T')[0];
                dateDom.max = today;
                selectedCustomerData = selectedCustomer;

                setOptionOnNthLi(typeSelectDom, 2, 'option', JSON.stringify(selectedCustomer?.payment_programs) ?? '');
            } else {
                dateDom.disabled = true;
                methodSelectDom.disabled = true;
                setOptionOnNthLi(typeSelectDom, 2, 'option');
            }

            methodSelectDom.querySelector("option[value='program']")?.remove();
        }


        window.addEventListener('DOMContentLoaded', () => {
            trackCustomerState()
            selectThisOption(document.querySelector(`li[data-for="type"][data-value="${customerPayment.type}"]`))
            selectThisOption(document.querySelector(`li[data-for="method"][data-value="${customerPayment.method}"]`))
        });

        const detailsInputsContainer = document.getElementById("details-inputs-container");

        function trackTypeState(elem, isNoModal) {
            methodSelectDom.value = '';
            detailsInputsContainer.classList.remove('mb-4');
            if (elem.value == 'payment_program') {
                methodSelectDom.closest(".selectParent").querySelector('ul').innerHTML += `
                    <li data-for="method" data-value="program" onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg transition hover:bg-[var(--h-bg-color)] text-nowrap overflow-x-auto scrollbar-hidden ">Program</li>
                `;
                detailsInputsContainer.innerHTML = "";

                let allProgramsArray = JSON.parse(typeSelectDom.closest(".selectParent")?.querySelector('ul li.selected').dataset.option);

                detailsInputsContainer.innerHTML += `
                    <div class="col-span-full">
                        {{-- payment_programs --}}
                        <x-select
                            label="Payment Programs"
                            name="program_id"
                            id="payment_programs"
                            required
                            onchange="trackProgramState(this)"
                        />
                    </div>
                `;
                detailsInputsContainer.classList.add('mb-4');

                const programSelectDom = document.getElementById('payment_programs');
                if (allProgramsArray.length > 0) {
                    programSelectDom.disabled = false;
                    programSelectDom.value = '-- Select payment program --';
                    programSelectDom.closest(".selectParent").querySelector('ul').innerHTML = `
                        <li data-for="payment_programs" data-value="" onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg transition hover:bg-[var(--h-bg-color)] text-nowrap overflow-x-auto scrollbar-hidden ">-- Select payment program --</li>
                    `;
                    allProgramsArray.forEach(program => {
                        programSelectDom.closest(".selectParent").querySelector('ul').innerHTML += `
                            <li data-for="payment_programs" data-value="${program.id}" data-option='${JSON.stringify(program)}' onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg transition hover:bg-[var(--h-bg-color)] text-nowrap overflow-x-auto scrollbar-hidden capitalize">${program.program_no ?? program.order_no} | ${formatNumbersWithDigits(program.balance, 1, 1)} | ${program.category}</li>
                        `;
                    });
                } else {
                    programSelectDom.disabled = false;
                    programSelectDom.closest(".selectParent").querySelector('ul').innerHTML = `
                        <li data-for="payment_programs" data-value="" onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg transition hover:bg-[var(--h-bg-color)] text-nowrap overflow-x-auto scrollbar-hidden ">-- No options avalaible --</li>
                    `;
                }
                selectThisOption(document.querySelector(`li[data-for="payment_programs"][data-value="${customerPayment.program_id}"]`))
            } else {
                detailsInputsContainer.innerHTML = "";
                methodSelectDom.closest(".selectParent").querySelector("ul li[data-value='program']")?.remove();
                methodSelectDom.value = '';
            }
            trackMethodState(methodSelectDom);
        }

        function trackMethodState(elem) {
            detailsDom.innerHTML = '';
            if (elem.value == 'cash') {
                detailsDom.innerHTML = `
                    {{-- amount --}}
                    <x-input label="Amount" type="amount" placeholder="Enter amount" name="amount" id="amount" value="${customerPayment.amount}" dataValidate="required|amount" oninput="validateInput(this)" required/>

                    {{-- remarks --}}
                    <x-input label="Remarks" placeholder="Remarks" name="remarks" id="remarks" value="${customerPayment.remarks}" dataValidate="friendly" oninput="validateInput(this)"/>
                `;
            } else if (elem.value == 'cheque') {
                detailsDom.innerHTML = `
                    {{-- bank --}}
                    <x-select label="Bank" name="bank_id" id="bank" :options="$banks_options" required showDefault />

                    {{-- amount --}}
                    <x-input label="Amount" type="amount" placeholder="Enter amount" name="amount" id="amount" value="${customerPayment.amount}" dataValidate="required|amount" oninput="validateInput(this)" required/>

                    {{-- cheque_date --}}
                    <x-input label="Cheque Date" type="date" name="cheque_date" id="cheque_date" value="${formatDate(customerPayment.cheque_date, false, true)}" required/>

                    {{-- cheque_no --}}
                    <x-input label="Cheque No" placeholder="Enter cheque no" name="cheque_no" id="cheque_no" value="${customerPayment.cheque_no}" required dataValidate="required|friendly|unique:chequeNo" oninput="validateInput(this)"/>

                    {{-- remarks --}}
                    <x-input label="Remarks" placeholder="Remarks" name="remarks" id="remarks" value="${customerPayment.remarks}" dataValidate="friendly" oninput="validateInput(this)"/>

                    {{-- clear_date --}}
                    <x-input label="Clear Date" type="date" name="clear_date" id="clear_date" value="${formatDate(customerPayment.clear_date, false, true)}"/>
                `;
                selectThisOption(document.querySelector(`li[data-for="bank"][data-value="${customerPayment.bank_id}"]`))
            } else if (elem.value == 'slip') {
                detailsDom.innerHTML = `
                    {{-- customer --}}
                    <x-input label="Customer" placeholder="Enter Customer" name="customer" id="customer" value="${selectedCustomer.customer_name}" disabled required/>

                    {{-- amount --}}
                    <x-input label="Amount" type="amount" placeholder="Enter amount" name="amount" id="amount" value="${customerPayment.amount}" dataValidate="required|amount" oninput="validateInput(this)" required/>

                    {{-- slip_date --}}
                    <x-input label="Slip Date" type="date" name="slip_date" id="slip_date" value="${formatDate(customerPayment.slip_date, false, true)}" required/>

                    {{-- slip_no --}}
                    <x-input label="Slip No" placeholder="Enter slip no" name="slip_no" id="slip_no" value="${customerPayment.slip_no}" required dataValidate="required|friendly|unique:slipNo" oninput="validateInput(this)"/>

                    {{-- remarks --}}
                    <x-input label="Remarks" placeholder="Remarks" name="remarks" id="remarks" dataValidate="friendly" value="${customerPayment.remarks}" oninput="validateInput(this)"/>

                    {{-- clear_date --}}
                    <x-input label="Clear Date" type="date" name="clear_date" id="clear_date" value="${formatDate(customerPayment.clear_date, false, true)}"/>
                `;
            } else if (elem.value == 'adjustment') {
                detailsDom.innerHTML = `
                    {{-- amount --}}
                    <x-input label="Amount" type="amount" placeholder="Enter amount" name="amount" id="amount" value="${customerPayment.amount}" dataValidate="required|amount" oninput="validateInput(this)" required/>

                    {{-- remarks --}}
                    <x-input label="Remarks" placeholder="Remarks" name="remarks" id="remarks" value="${customerPayment.remarks}" dataValidate="friendly" oninput="validateInput(this)"/>
                `;
            } else if (elem.value == 'program') {
                let programSelectDom = document.getElementById('payment_programs');
                selectedProgramData = JSON.parse(programSelectDom.closest(".selectParent")?.querySelector('ul li.selected').dataset.option);
                if (selectedProgramData.category != 'waiting') {
                    if (selectedProgramData.category != 'waiting') {
                        let beneficiary = '-';
                        if (selectedProgramData.category) {
                            if (selectedProgramData.category === 'supplier' && selectedProgramData.sub_category?.supplier_name) {
                                beneficiary = selectedProgramData.sub_category.supplier_name;
                            } else if (selectedProgramData.category === 'customer' && selectedProgramData.sub_category?.customer_name) {
                                beneficiary = selectedProgramData.sub_category.customer_name;
                            } else if (selectedProgramData.category === 'self_account' && selectedProgramData.sub_category?.account_title) {
                                beneficiary = selectedProgramData.sub_category.account_title;
                            } else if (selectedProgramData.category === 'waiting' && selectedProgramData.remarks) {
                                beneficiary = selectedProgramData.remarks;
                            }
                        }
                        selectedProgramData.beneficiary = beneficiary
                    }

                    detailsDom.innerHTML = `
                        {{-- category --}}
                        <x-input label="Category" value="${selectedProgramData.category}" disabled/>

                        {{-- beneficiary --}}
                        <x-input label="Beneficiary" value="${selectedProgramData.beneficiary}" disabled/>

                        {{-- program date --}}
                        <x-input label="Program Date" value="${selectedProgramData.date}" disabled/>

                        {{-- program amount --}}
                        <x-input label="Program Balance" type="number" value="${selectedProgramData.balance}" disabled/>

                        {{-- amount --}}
                        <x-input label="Amount" type="amount" placeholder="Enter amount" name="amount" id="amount" value="${customerPayment.amount}" dataValidate="required|amount" oninput="validateInput(this)" required/>

                        {{-- bank account --}}
                        <x-select label="Bank Accounts" name="bank_account_id" id="bank_accounts" required showDefault />

                        {{-- transaction id --}}
                        <x-input label="Transaction Id" name="transaction_id" id="transaction_id" placeholder="Enter Transaction Id" required value="${customerPayment.transaction_id}" dataValidate="required|alphanumeric" oninput="validateInput(this)"/>

                        {{-- remarks --}}
                        <x-input label="Remarks" placeholder="Remarks" name="remarks" id="remarks" value="${customerPayment.remarks}" dataValidate="friendly" oninput="validateInput(this)"/>
                    `;

                    let bankAccountData = selectedProgramData.sub_category.bank_accounts;

                    if (bankAccountData) {
                        let bankAccountsSelect = document.getElementById('bank_accounts');
                        bankAccountsSelect.disabled = false;
                        bankAccountsSelect.value = '-- Select Bank Account --';
                        bankAccountsSelect.closest(".selectParent").querySelector('ul').innerHTML = '';
                        if (bankAccountData.length > 0) {
                            bankAccountData.forEach(account => {
                                bankAccountsSelect.closest(".selectParent").querySelector('ul').innerHTML += `
                                    <li data-for="bank_accounts" data-value="${account.id}" onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg transition hover:bg-[var(--h-bg-color)] text-nowrap overflow-x-auto scrollbar-hidden ">${account.account_title} | ${account.bank.short_title}</li>
                                `;
                            });
                        } else {
                            bankAccountsSelect.closest(".selectParent").querySelector('ul').innerHTML += `
                                <li data-for="bank_accounts" data-value="${bankAccountData.id}" onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg transition hover:bg-[var(--h-bg-color)] text-nowrap overflow-x-auto scrollbar-hidden ">${bankAccountData.account_title} | ${bankAccountData.bank.short_title}</li>
                            `;
                        }
                    }
                    selectThisOption(document.querySelector(`li[data-for="bank_accounts"][data-value="${customerPayment.bank_account_id}"]`))
                } else {
                    detailsDom.innerHTML = '';
                }
            }

            formatAllAmountInputs();
        }

        function trackProgramState(elem) {
            let ProgramData = JSON.parse(elem.closest(".selectParent")?.querySelector('ul li.selected').dataset.option);

            if (ProgramData.category != 'waiting') {
                const desiredMethod = methodSelectDom.closest(".selectParent").querySelector('ul li[data-value="program"]');
                if (!desiredMethod) {
                    methodSelectDom.closest(".selectParent").querySelector('ul').innerHTML += `
                        <li data-for="method" data-value="program" onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg transition hover:bg-[var(--h-bg-color)] text-nowrap overflow-x-auto scrollbar-hidden ">Program</li>
                    `;
                }
                desiredMethod.dispatchEvent(new MouseEvent('mousedown', { bubbles: true }));
            } else {
                methodSelectDom.closest(".selectParent").querySelector('ul li[data-value="program"]')?.remove();
                detailsDom.innerHTML = '';
            }
            trackDateState(dateDom);
        }

        function trackDateState(elem) {
            let programSelectDom = document.getElementById('payment_programs');

            if (typeSelectDom.value == "Payment Program" && (!programSelectDom || programSelectDom.value == '')) {
                let totalPrograms = selectedCustomer.payment_programs;
                typeSelectDom.value = '';
                trackTypeState(typeSelectDom);

                methodSelectDom.value = '';
                detailsDom.innerHTML = '';
                trackMethodState(methodSelectDom);

                const filteredPrograms = totalPrograms.filter(program => {
                    return new Date(program.date) <= new Date(elem.value);
                });

                typeSelectDom.closest(".selectParent").querySelector('ul li[data-value="payment_program"]').dataset.option = JSON.stringify(filteredPrograms);
            } else {
                let programData = JSON.parse(programSelectDom.closest(".selectParent")?.querySelector('ul li.selected').dataset.option);
                if (date.value < programData?.date) {
                    dateDom.value = '';
                }
                date.min = programData?.date;
            }
        }

        function repeatThisRecord(button) {
            let formDom = document.getElementById('form');
            formDom.reset();
            const record = JSON.parse(button.getAttribute('data-record'));

            const desiredCustomer = customerSelectDom.closest(".selectParent").querySelector(`ul li[data-value="${record.customer.id}"]`);
            desiredCustomer?.dispatchEvent(new MouseEvent('mousedown', { bubbles: true }));

            // desiredType
            const desiredType = typeSelectDom.closest(".selectParent").querySelector(`ul li[data-value="${record.type}"]`);
            desiredType?.dispatchEvent(new MouseEvent('mousedown', { bubbles: true }));

            if (record.program_id) {
                // desired program
                let programSelectDom = document.getElementById('payment_programs');
                if (programSelectDom) {
                    // Find the li with the matching program_id and select it
                    let desiredProgram = programSelectDom.closest(".selectParent").querySelector(`ul li[data-value="${record.program_id}"]`);
                    desiredProgram?.dispatchEvent(new MouseEvent('mousedown', { bubbles: true }));
                }
            } else if(record.method) {
                const desiredMethod = methodSelectDom.closest(".selectParent").querySelector(`ul li[data-value="${record.method}"]`);
                desiredMethod?.dispatchEvent(new MouseEvent('mousedown', { bubbles: true }));

                // Helper to set value if element exists
                function setValueIfExists(id, value) {
                    const el = document.getElementById(id);
                    if (el) el.value = value;
                }

                setTimeout(() => {
                    if (record.method === 'cash') {
                        setValueIfExists('amount', record.amount);
                        setValueIfExists('remarks', record.remarks);
                    } else if (record.method === 'cheque') {
                        // Set custom select for bank
                        const bankSelectDom = document.getElementById('bank');
                        if (bankSelectDom && record.bank_id) {
                            const desiredBank = bankSelectDom.closest('.selectParent').querySelector(`ul li[data-value="${record.bank_id}"]`);
                            desiredBank?.dispatchEvent(new MouseEvent('mousedown', { bubbles: true }));
                        }
                        setValueIfExists('amount', record.amount);
                        setValueIfExists('cheque_date', record.cheque_date);
                        setValueIfExists('remarks', record.remarks);
                        setValueIfExists('clear_date', record.clear_date);
                    } else if (record.method === 'slip') {
                        setValueIfExists('amount', record.amount);
                        setValueIfExists('slip_date', record.slip_date);
                        setValueIfExists('remarks', record.remarks);
                        setValueIfExists('clear_date', record.clear_date);
                    } else if (record.method === 'adjustment') {
                        setValueIfExists('amount', record.amount);
                        setValueIfExists('remarks', record.remarks);
                    }
                }, 100);
            }

            dateDom.focus();
        }
    </script>
@endsection
