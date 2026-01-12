@extends('app')
@section('title', 'Add Customer Payment | ' . $client_company->name)
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
    <div class="mb-5 max-w-6xl mx-auto">
        <x-search-header heading="Add Customer Payment" link linkText="Show Payments" linkHref="{{ route('customer-payments.index') }}"/>
    </div>

    <div class="row max-w-6xl mx-auto flex gap-4">
        <!-- Form -->
        <form id="form" action="{{ route('customer-payments.store') }}" method="post"
            class="bg-[var(--secondary-bg-color)] text-sm rounded-xl shadow-lg p-7 border border-[var(--h-bg-color)] pt-12 w-[70%] mx-auto relative overflow-hidden">
            @csrf
            <x-form-title-bar title="Add Customer Payment" />

            <div class="step space-y-4 overflow-y-auto max-h-[65vh] p-1 my-scrollbar-2">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    {{-- customer --}}
                    <x-select
                        label="Customer"
                        name="customer_id"
                        id="customer_id"
                        :options="$customers_options"
                        required
                        showDefault
                        onchange="trackCustomerState()"
                    />

                    {{-- balance --}}
                    <x-input label="Balance" placeholder="Select customer first" name="balance" id="balance" disabled />

                    {{-- date --}}
                    <x-input label="Date" name="date" id="date" type="date" required disabled onchange="trackDateState(this)"/>

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

        <div class="bg-[var(--secondary-bg-color)] rounded-xl shadow-xl p-8 border border-[var(--glass-border-color)]/20 w-[35%] pt-12 relative overflow-hidden fade-in">
            <x-form-title-bar title="Last Record" />

            <!-- Step last record -->
            <div class="step1 space-y-4">
                @if ($lastRecord)
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        {{-- Customer --}}
                        <x-input label="Customer" name="last_customer" id="last_customer" type="text" disabled
                            value="{{ $lastRecord->customer->customer_name ?? '-' }}" />

                        <!-- date -->
                        <x-input label="Date" name="last_date" id="last_date" disabled
                            value="{{ $lastRecord->date->format('d-M-Y, D') ?? '-' }}" />

                        {{-- type --}}
                        <x-input label="Type" name="last_type" id="last_type" type="text" disabled capitalized
                            value="{{ str_replace('_', ' ', $lastRecord->type) ?? '-' }}" />

                        {{-- method --}}
                        <x-input label="Method" name="last_method" id="last_method" type="text" disabled capitalized
                            value="{{ $lastRecord->method ?? '-' }}" />

                        <!-- reff_no -->
                        <x-input label="Reff. No." name="last_reff_no" id="last_reff_no" disabled
                            value="{{ $lastRecord->slip_no ?? $lastRecord->cheque_no ?? $lastRecord->transaction_id ?? '-' }}" />

                        <!-- amount -->
                        <x-input label="Amount" name="last_amount" id="last_amount" disabled
                            value="{{ number_format($lastRecord->amount,) ?? '-' }}" />

                        {{-- remarks --}}
                        <x-input label="Remarks" name="last_remarks" id="last_remarks" type="text" disabled
                            value="{{ $lastRecord->remarks ?? 'No Remarks' }}" />

                        <div class="flex items-end">
                            <button type="button" data-record='@json($lastRecord)' onclick="repeatThisRecord(this)"
                                class="w-full px-6 py-2 bg-[var(--bg-warning)] border border-[var(--bg-warning)] text-[var(--text-warning)] font-medium text-nowrap rounded-lg hover:bg-[var(--h-bg-warning)] hover:border-[var(--border-warning)] hover:scale-90 transition-all duration-300 ease-in-out cursor-pointer">
                                <i class='fas fa-repeat mr-1'></i> Repeat
                            </button>
                        </div>
                    </div>
                @else
                    <div class="text-center text-gray-500">
                        <p>No last record found.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <script>
        let customerSelectDom = document.getElementById('customer_id');
        let methodSelectDom = document.getElementById('method');
        let typeSelectDom = document.getElementById('type');
        let dateDom = document.getElementById('date');
        let balanceDom = document.getElementById('balance');
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
            dateDom.value = '';
            balanceDom.value = '';
            methodSelectDom.value = '';
            typeSelectDom.value = '';

            if (customerSelectDom.value != '') {
                selectedCustomer = JSON.parse(customerSelectDom.closest(".selectParent")?.querySelector('ul li.selected')?.dataset.option || 'null');

                dateDom.disabled = false;
                methodSelectDom.disabled = false;
                dateDom.min = selectedCustomer?.date.toString().split('T')[0];
                dateDom.max = today;
                balanceDom.value = formatNumbersWithDigits(selectedCustomer?.balance || 0, 1, 1);
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
            const url = new URL(window.location.href);

            // Clean the URL after initial load (remove query params)
            if (url.searchParams.has('program_id') || url.searchParams.has('source')) {
                // reset url
                url.search = ''; // remove all query parameters
                window.history.replaceState({}, document.title, url.toString());

                // select customer
                for (const option of customerSelectDom.closest(".selectParent")?.querySelectorAll('ul li')) {
                    if (option.dataset.value && option.textContent.trim() !== '') {
                        customerSelectDom.value = option.textContent.trim();
                        customerSelectDom.closest(".selectParent")?.querySelector(`ul li[data-value="${option.dataset.value}"]`).dispatchEvent(new MouseEvent('mousedown', { bubbles: true }));
                        customerSelectDom.disabled = true;
                        break;
                    }
                }
                trackCustomerState();

                let typeInp = typeSelectDom.closest(".selectParent")?.querySelector('input[type="hidden"]');
                // select type
                for (const option of typeSelectDom.closest(".selectParent")?.querySelectorAll('ul li')) {
                    if (option.dataset.value.trim() === 'payment_program') {
                        option.dataset.option = JSON.stringify([selectedCustomer.payment_programs]);
                        typeSelectDom.value = option.textContent.trim();
                        typeSelectDom.closest(".selectParent")?.querySelector(`ul li.selected`).classList.remove('selected');
                        typeSelectDom.closest(".selectParent")?.querySelector(`ul li[data-value="${option.dataset.value}"]`).classList.add('selected');
                        typeSelectDom.closest(".selectParent")?.querySelector(`ul li[data-value="${option.dataset.value}"]`).dispatchEvent(new MouseEvent('mousedown', { bubbles: true }));
                        typeInp.value = 'payment_program';
                        typeSelectDom.disabled = true;
                        break;
                    }
                }
                trackTypeState(typeInp, true);

                let programSelectDom = document.getElementById('payment_programs');
                programSelectDom.closest(".selectParent").querySelectorAll('ul li')[1].classList.add('selected');
                let ProgramData = JSON.parse(programSelectDom.closest(".selectParent")?.querySelector('ul li.selected').dataset.option);
                // programSelectDom.value = programSelectDom.closest(".selectParent")?.querySelector('ul li.selected').textContent.trim();
                programSelectDom.closest(".selectParent")?.querySelector(`ul li.selected`).dispatchEvent(new MouseEvent('mousedown', { bubbles: true }));
                programSelectDom.disabled = true;

                if (ProgramData.category != 'waiting') {
                    programSelectDom.dispatchEvent(new Event('change'));
                    methodSelectDom.value = 'program'
                    methodSelectDom.closest(".selectParent")?.querySelector(`ul li.selected`).classList.remove('selected');
                    methodSelectDom.closest(".selectParent")?.querySelector(`ul li[data-value="program"]`).classList.add('selected');
                    methodSelectDom.closest(".selectParent")?.querySelector(`ul li[data-value="program"]`).dispatchEvent(new MouseEvent('mousedown', { bubbles: true }));
                    methodSelectDom.disabled = true;
                    // trackMethodState(methodSelectDom);
                } else {
                    methodSelectDom.querySelector("option[value='program']")?.remove();
                }
            }
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
                            <li data-for="payment_programs" data-value="${program.id}" data-option='${JSON.stringify(program)}' onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg transition hover:bg-[var(--h-bg-color)] text-nowrap overflow-x-auto scrollbar-hidden capitalize">${program.program_no ?? program.order_no} | ${formatNumbersWithDigits(program.balance, 1, 1)} | ${program.category.replaceAll('_', ' ')} | ${program.sub_category?.supplier_name ?? program.sub_category?.account_title ?? '-'}</li>
                        `;
                    });
                } else {
                    programSelectDom.disabled = false;
                    programSelectDom.closest(".selectParent").querySelector('ul').innerHTML = `
                        <li data-for="payment_programs" data-value="" onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg transition hover:bg-[var(--h-bg-color)] text-nowrap overflow-x-auto scrollbar-hidden ">-- No options avalaible --</li>
                    `;
                }
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
                    <x-input label="Amount" type="amount" placeholder="Enter amount" name="amount" id="amount" dataValidate="required|amount" oninput="validateInput(this)" required/>

                    {{-- remarks --}}
                    <x-input label="Remarks" placeholder="Remarks" name="remarks" id="remarks" dataValidate="friendly" oninput="validateInput(this)"/>
                `;
            } else if (elem.value == 'cheque') {
                detailsDom.innerHTML = `
                    {{-- bank --}}
                    <x-select label="Bank" name="bank_id" id="bank" :options="$banks_options" required showDefault />

                    {{-- amount --}}
                    <x-input label="Amount" type="amount" placeholder="Enter amount" name="amount" id="amount" dataValidate="required|amount" oninput="validateInput(this)" required/>

                    {{-- cheque_date --}}
                    <x-input label="Cheque Date" type="date" name="cheque_date" id="cheque_date" required/>

                    {{-- cheque_no --}}
                    <x-input label="Cheque No" placeholder="Enter cheque no" name="cheque_no" id="cheque_no" required dataValidate="required|friendly" oninput="validateInput(this)"/>

                    {{-- remarks --}}
                    <x-input label="Remarks" placeholder="Remarks" name="remarks" id="remarks" dataValidate="friendly" oninput="validateInput(this)"/>

                    {{-- clear_date --}}
                    <x-input label="Clear Date" type="date" name="clear_date" id="clear_date"/>
                `;
            } else if (elem.value == 'slip') {
                detailsDom.innerHTML = `
                    {{-- customer --}}
                    <x-input label="Customer" placeholder="Enter Customer" name="customer" id="customer" value="${selectedCustomer.customer_name}" disabled required/>

                    {{-- amount --}}
                    <x-input label="Amount" type="amount" placeholder="Enter amount" name="amount" id="amount" dataValidate="required|amount" oninput="validateInput(this)" required/>

                    {{-- slip_date --}}
                    <x-input label="Slip Date" type="date" name="slip_date" id="slip_date" required/>

                    {{-- slip_no --}}
                    <x-input label="Slip No" placeholder="Enter slip no" name="slip_no" id="slip_no" required dataValidate="required|friendly" oninput="validateInput(this)"/>

                    {{-- remarks --}}
                    <x-input label="Remarks" placeholder="Remarks" name="remarks" id="remarks" dataValidate="friendly" oninput="validateInput(this)"/>

                    {{-- clear_date --}}
                    <x-input label="Clear Date" type="date" name="clear_date" id="clear_date"/>
                `;
            } else if (elem.value == 'adjustment') {
                detailsDom.innerHTML = `
                    {{-- amount --}}
                    <x-input label="Amount" type="amount" placeholder="Enter amount" name="amount" id="amount" dataValidate="required|amount" oninput="validateInput(this)" required/>

                    {{-- remarks --}}
                    <x-input label="Remarks" placeholder="Remarks" name="remarks" id="remarks" dataValidate="friendly" oninput="validateInput(this)"/>
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
                        <x-input label="Amount" type="amount" placeholder="Enter amount" name="amount" id="amount" dataValidate="required|amount" oninput="validateInput(this)" required/>

                        {{-- bank account --}}
                        <x-select label="Bank Accounts" addBtnLink="/bank-accounts/create" name="bank_account_id" id="bank_accounts" required showDefault />

                        {{-- transaction id --}}
                        <x-input label="Transaction Id" name="transaction_id" id="transaction_id" placeholder="Enter Transaction Id" required dataValidate="required|alphanumeric" oninput="validateInput(this)"/>

                        {{-- remarks --}}
                        <x-input label="Remarks" placeholder="Remarks" name="remarks" id="remarks" dataValidate="friendly" oninput="validateInput(this)"/>
                    `;

                    let bankAccountData = selectedProgramData.sub_category.bank_accounts;

                    if (bankAccountData) {
                        let bankAccountsSelect = document.getElementById('bank_accounts');
                        bankAccountsSelect.disabled = false;
                        bankAccountsSelect.closest(".selectParent").querySelector('ul').innerHTML = '';
                        if (bankAccountData.length > 1) {
                            bankAccountsSelect.closest(".selectParent").querySelector('ul').innerHTML += `
                                <li data-for="bank_accounts" data-value="" onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg transition hover:bg-[var(--h-bg-color)] text-nowrap overflow-x-auto scrollbar-hidden ">-- Select Bank Account --</li>
                            `;
                        }

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
                        selectThisOption(bankAccountsSelect.closest(".selectParent").querySelector('ul li'));
                    }
                } else {
                    detailsDom.innerHTML = '';
                }
            }
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
                let programData = JSON.parse(programSelectDom?.closest(".selectParent")?.querySelector('ul li.selected').dataset.option || '{}');
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
