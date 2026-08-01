(() => {
function initCustomerPaymentsEdit() {
    const config = window.__customerPaymentsEdit || {};
    const isDeveloper = config.isDeveloper === true;
    let customerPayment = config.customerPayment;
    window.__cpBanksOptions = config.banksOptions || {};
    customerPayment.remarks = customerPayment.remarks || '';
    let methodSelectDom = document.getElementById('method');
    let typeSelectDom = document.getElementById('type');
    let dateDom = document.getElementById('date');
    let detailsDom = document.getElementById('details');

    selectedCustomerData = null;
    let selectedProgramData = {};
    let selectedCustomer;
    const today = localDateString();

    function buildInput({
        label = '',
        name = '',
        id = '',
        type = 'text',
        placeholder = '',
        value = '',
        required = false,
        disabled = false,
        readonly = false,
        dataValidate = '',
        oninput = '',
    } = {}) {
        const requiredAttr = required ? 'required' : '';
        const disabledAttr = disabled ? 'disabled' : '';
        const readonlyAttr = readonly ? 'readonly' : '';
        const dataValidateAttr = dataValidate ? `data-validate="${dataValidate}"` : '';
        const oninputAttr = oninput ? `oninput="${oninput}"` : '';
        const valueAttr = value !== '' && value !== null && typeof value !== 'undefined' ? `value="${value}"` : '';

        return `
            <div class="form-group relative">
                ${label ? `
                    <span class="flex items-center justify-between mb-2">
                        <label for="${name}" class="block font-medium text-[var(--secondary-text)]">
                            ${label}${!required && !readonly && !disabled ? ' (optional)' : ''}
                        </label>
                    </span>
                ` : ''}
                <div class="relative flex gap-4">
                    <input
                        id="${id}"
                        type="${type}"
                        name="${name}"
                        placeholder="${placeholder}"
                        ${valueAttr}
                        ${requiredAttr}
                        ${readonlyAttr}
                        ${disabledAttr}
                        ${dataValidateAttr}
                        ${oninputAttr}
                        class="w-full rounded-lg bg-[var(--h-bg-color)] border border-gray-600 text-[var(--text-color)] px-3 ${type === 'date' ? 'py-[7px]' : 'py-2'} focus:ring-1 focus:ring-primary focus:border-transparent transition-all duration-300 ease-in-out disabled:bg-transparent disabled:opacity-70 placeholder:capitalize"
                    />
                </div>
                <div id="${name}-error" class="text-[var(--border-error)] text-xs mt-1 hidden transition-all duration-300 ease-in-out"></div>
            </div>
        `;
    }

    function buildSelect({
        label = '',
        name = '',
        id = '',
        options = [],
        showDefault = false,
        required = false,
        disabled = false,
        onchange = '',
    } = {}) {
        const requiredAttr = required ? 'required' : '';
        const disabledAttr = disabled ? 'disabled' : '';
        const onchangeAttr = onchange ? `onchange="${onchange}"` : '';
        const defaultOption = showDefault
            ? `<li data-for="${id}" data-value="" onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg transition hover:bg-[var(--h-bg-color)]">-- Select ${label} --</li>`
            : '';

        const optionsHtml = options.map(opt => {
            const dataOption = opt.data_option ? `data-option='${jsonAttr(opt.data_option)}'` : '';
            return `<li data-for="${id}" data-value="${opt.value}" ${dataOption} onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg transition hover:bg-[var(--h-bg-color)] text-nowrap overflow-x-auto scrollbar-hidden">${opt.text}</li>`;
        }).join('');

        return `
            <div class="form-group relative grow">
                ${label ? `
                    <span class="flex items-center justify-between mb-2">
                        <label for="${name}" class="block font-medium text-[var(--secondary-text)]">
                            ${label}${!required && !disabled ? ' (optional)' : ''}
                        </label>
                    </span>
                ` : ''}
                <div class="selectParent flex gap-4">
                    <input
                        id="${id}"
                        name="${id}_name"
                        autocomplete="off"
                        ${disabledAttr}
                        placeholder="-- Select ${label} --"
                        onfocus="selectClicked(this)"
                        class="w-full rounded-lg bg-[var(--h-bg-color)] border border-gray-600 text-[var(--text-color)] px-3 py-2 focus:ring-1 focus:ring-primary focus:border-transparent transition-all duration-300 ease-in-out disabled:bg-transparent disabled:opacity-70 placeholder:capitalize"
                    />
                    <input
                        type="hidden"
                        class="dbInput"
                        data-for="${id}"
                        name="${name}"
                        value=""
                        ${onchangeAttr}
                        ${requiredAttr}
                    >
                    <div class="dropDownParent flex flex-col gap-2 fixed z-50 mt-2 w-full rounded-xl bg-[var(--secondary-bg-color)] border-gray-600 text-[var(--text-color)] p-1.5 border appearance-none focus:ring-2 focus:ring-primary focus:border-transparent max-h-[13rem]">
                        <input
                            data-for="${id}"
                            oninput="searchSelect(this)"
                            onblur="validateSelectInput(this)"
                            autocomplete="off"
                            placeholder="-- Select ${label} --"
                            onkeydown="selectKeyDown(event, this)"
                            class="w-full rounded-lg bg-[var(--h-bg-color)] border border-gray-600 text-[var(--text-color)] px-3 py-2 focus:ring-1 focus:ring-primary focus:border-transparent transition-all duration-300 ease-in-out placeholder:capitalize"
                        />
                        <ul class="optionsDropdown overflow-auto my-scrollbar-2 space-y-0.5 grow" data-for="${id}">
                            ${defaultOption}
                            ${optionsHtml}
                        </ul>
                    </div>
                </div>
                <div id="${name}-error" class="text-[var(--border-error)] text-xs mt-1 hidden transition-all duration-300 ease-in-out"></div>
            </div>
        `;
    }

    function setOptionOnNthLi(triggerDom, index, key, value = '') {
        const li = triggerDom.closest('.selectParent')?.querySelectorAll('ul li')[index];
        if (li) li.dataset[key] = value;
    }

    function setOptionForValue(triggerDom, value, key, dataValue = '') {
        const li = triggerDom.closest('.selectParent')?.querySelector(`ul li[data-value="${value}"]`);
        if (li) li.dataset[key] = dataValue;
    }

    window.trackCustomerState = function() {
        setOptionOnNthLi(typeSelectDom, 2, 'option');
        setOptionForValue(typeSelectDom, 'payment_program', 'option', '');
        methodSelectDom.value = '';
        typeSelectDom.value = '';

        if (customerPayment) {
            selectedCustomer = customerPayment.customer;
            dateDom.disabled = false;
            methodSelectDom.disabled = false;
            dateDom.min = selectedCustomer?.date ? selectedCustomer.date.toString().split('T')[0] : '';
            dateDom.max = today;
            selectedCustomerData = selectedCustomer;

            const programData = JSON.stringify(selectedCustomer?.payment_programs) ?? '';
            setOptionOnNthLi(typeSelectDom, 2, 'option', programData);
            setOptionForValue(typeSelectDom, 'payment_program', 'option', programData);
        } else {
            dateDom.disabled = true;
            methodSelectDom.disabled = true;
            setOptionOnNthLi(typeSelectDom, 2, 'option');
            setOptionForValue(typeSelectDom, 'payment_program', 'option', '');
        }

        methodSelectDom.querySelector("option[value='program']")?.remove();
    }

    function safeSelect(liElem) {
        if (liElem) {
            selectThisOption(liElem);
        }
    }

    function formatProgramBalance(balance) {
        const normalized = typeof balance === 'string' ? balance.replace(/,/g, '') : balance;
        const numeric = Number(normalized);
        if (!Number.isFinite(numeric)) {
            return formatNumbersWithDigits(0, 1, 1);
        }
        return formatNumbersWithDigits(numeric, 1, 1);
    }

    function programMethodAvailable(program) {
        return Boolean(program?.program_method_available || program?.has_sub_category || program?.sub_category?.id);
    }

    function applyEditDefaults() {
        if (!customerPayment) return;

        safeSelect(document.querySelector(`li[data-for="type"][data-value="${customerPayment.type}"]`));

        setTimeout(() => {
            if (customerPayment.type === 'payment_program') {
                safeSelect(document.querySelector(`li[data-for="payment_programs"][data-value="${customerPayment.program_id}"]`));
                const methodValue = customerPayment.method || 'program';
                safeSelect(document.querySelector(`li[data-for="method"][data-value="${methodValue}"]`));
            } else {
                safeSelect(document.querySelector(`li[data-for="method"][data-value="${customerPayment.method}"]`));
            }
        }, 0);
    }

    trackCustomerState();
    applyEditDefaults();

    const detailsInputsContainer = document.getElementById('details-inputs-container');

    window.trackTypeState = function(elem, isNoModal) {
        methodSelectDom.value = '';
        detailsInputsContainer.classList.remove('mb-4');
        if (elem.value == 'payment_program') {
            methodSelectDom.closest('.selectParent').querySelector('ul li[data-value="program"]')?.remove();
            methodSelectDom.disabled = true;
            detailsInputsContainer.innerHTML = "";

            const typeScope = typeSelectDom.closest('.selectParent') || document;
            const selectedTypeLi = typeScope.querySelector('ul li.selected') || typeScope.querySelector('ul li[data-value="payment_program"]');
            let allProgramsArray = JSON.parse(selectedTypeLi?.dataset?.option || '[]');
            allProgramsArray = Array.isArray(allProgramsArray) ? allProgramsArray : (allProgramsArray ? [allProgramsArray] : []);

            detailsInputsContainer.innerHTML += buildSelect({
                label: 'Payment Programs',
                name: 'program_id',
                id: 'payment_programs',
                required: true,
                onchange: 'trackProgramState(this)',
            });
            detailsInputsContainer.classList.add('mb-4');

            const programSelectDom = document.getElementById('payment_programs');
            if (allProgramsArray.length > 0) {
                programSelectDom.disabled = false;
                programSelectDom.value = '-- Select payment program --';
                programSelectDom.closest('.selectParent').querySelector('ul').innerHTML = `
                    <li data-for="payment_programs" data-value="" onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg transition hover:bg-[var(--h-bg-color)] text-nowrap overflow-x-auto scrollbar-hidden ">-- Select payment program --</li>
                `;
                allProgramsArray.forEach(program => {
                        const categoryText = program.category ? program.category.replaceAll('_', ' ') : '-';
                        const beneficiary = program.sub_category?.supplier_name ?? program.sub_category?.account_title ?? program.sub_category?.customer_name ?? '-';
                        programSelectDom.closest('.selectParent').querySelector('ul').innerHTML += `
                            <li data-for="payment_programs" data-value="${program.id}" data-option='${jsonAttr(program)}' onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg transition hover:bg-[var(--h-bg-color)] text-nowrap overflow-x-auto scrollbar-hidden capitalize">${program.program_no ?? program.order_no} | ${formatProgramBalance(program.balance)} | ${categoryText} | ${beneficiary}</li>
                        `;
                });
            } else {
                programSelectDom.disabled = false;
                programSelectDom.closest('.selectParent').querySelector('ul').innerHTML = `
                    <li data-for="payment_programs" data-value="" onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg transition hover:bg-[var(--h-bg-color)] text-nowrap overflow-x-auto scrollbar-hidden ">-- No options avalaible --</li>
                `;
            }
            if (customerPayment?.program_id) {
                safeSelect(document.querySelector(`li[data-for="payment_programs"][data-value="${customerPayment.program_id}"]`));
            } else if (allProgramsArray.length === 1) {
                safeSelect(document.querySelector(`li[data-for="payment_programs"][data-value="${allProgramsArray[0].id}"]`));
            }
        } else {
            detailsInputsContainer.innerHTML = "";
            methodSelectDom.closest('.selectParent').querySelector("ul li[data-value='program']")?.remove();
            methodSelectDom.value = '';
            methodSelectDom.disabled = false;
        }
        trackMethodState(methodSelectDom);
    }

    window.trackMethodState = function(elem) {
        detailsDom.innerHTML = '';
        if (elem.value == 'cash') {
            detailsDom.innerHTML = [
                buildInput({ label: 'Amount', name: 'amount', id: 'amount', type: 'amount', placeholder: 'Enter amount', value: customerPayment.amount, dataValidate: 'required|amount', oninput: 'validateInput(this)', required: true }),
                buildInput({ label: 'Remarks', name: 'remarks', id: 'remarks', placeholder: 'Remarks', value: customerPayment.remarks, dataValidate: 'friendly', oninput: 'validateInput(this)' }),
            ].join('');
        } else if (elem.value == 'cheque') {
            const bankOptions = Object.entries(window.__cpBanksOptions || {}).map(([value, opt]) => ({
                value,
                text: opt.text,
                data_option: opt.data_option,
            }));
            detailsDom.innerHTML = [
                buildSelect({ label: 'Bank', name: 'bank_id', id: 'bank', options: bankOptions, showDefault: true, required: true }),
                buildInput({ label: 'Amount', name: 'amount', id: 'amount', type: 'amount', placeholder: 'Enter amount', value: customerPayment.amount, dataValidate: 'required|amount', oninput: 'validateInput(this)', required: true }),
                buildInput({ label: 'Cheque Date', name: 'cheque_date', id: 'cheque_date', type: 'date', value: formatDate(customerPayment.cheque_date, false, true), required: true }),
                buildInput({ label: 'Cheque No', name: 'cheque_no', id: 'cheque_no', placeholder: 'Enter cheque no', value: customerPayment.cheque_no, dataValidate: 'required|friendly', oninput: 'validateInput(this)', required: true }),
                buildInput({ label: 'Remarks', name: 'remarks', id: 'remarks', placeholder: 'Remarks', value: customerPayment.remarks, dataValidate: 'friendly', oninput: 'validateInput(this)' }),
                buildInput({ label: 'Clear Date', name: 'clear_date', id: 'clear_date', type: 'date', value: formatDate(customerPayment.clear_date, false, true) }),
            ].join('');
            safeSelect(document.querySelector(`li[data-for="bank"][data-value="${customerPayment.bank_id}"]`));
        } else if (elem.value == 'slip') {
            detailsDom.innerHTML = [
                buildInput({ label: 'Customer', name: 'customer', id: 'customer', placeholder: 'Enter Customer', value: selectedCustomer?.customer_name || '', disabled: !isDeveloper, required: true }),
                buildInput({ label: 'Amount', name: 'amount', id: 'amount', type: 'amount', placeholder: 'Enter amount', value: customerPayment.amount, dataValidate: 'required|amount', oninput: 'validateInput(this)', required: true }),
                buildInput({ label: 'Slip Date', name: 'slip_date', id: 'slip_date', type: 'date', value: formatDate(customerPayment.slip_date, false, true), required: true }),
                buildInput({ label: 'Slip No', name: 'slip_no', id: 'slip_no', placeholder: 'Enter slip no', value: customerPayment.slip_no, dataValidate: 'required|friendly', oninput: 'validateInput(this)', required: true }),
                buildInput({ label: 'Remarks', name: 'remarks', id: 'remarks', placeholder: 'Remarks', value: customerPayment.remarks, dataValidate: 'friendly', oninput: 'validateInput(this)' }),
                buildInput({ label: 'Clear Date', name: 'clear_date', id: 'clear_date', type: 'date', value: formatDate(customerPayment.clear_date, false, true) }),
            ].join('');
        } else if (elem.value == 'adjustment') {
            detailsDom.innerHTML = [
                buildInput({ label: 'Amount', name: 'amount', id: 'amount', type: 'amount', placeholder: 'Enter amount', value: customerPayment.amount, dataValidate: 'required|amount', oninput: 'validateInput(this)', required: true }),
                buildInput({ label: 'Remarks', name: 'remarks', id: 'remarks', placeholder: 'Remarks', value: customerPayment.remarks, dataValidate: 'friendly', oninput: 'validateInput(this)' }),
            ].join('');
        } else if (elem.value == 'program') {
            let programSelectDom = document.getElementById('payment_programs');
            const selectedProgramLi = programSelectDom?.closest('.selectParent')?.querySelector('ul li.selected');
            selectedProgramData = JSON.parse(selectedProgramLi?.dataset?.option || '{}');
            if (programMethodAvailable(selectedProgramData)) {
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

                detailsDom.innerHTML = [
                    buildInput({ label: 'Category', name: 'category', id: 'category', value: selectedProgramData.category, disabled: !isDeveloper }),
                    buildInput({ label: 'Beneficiary', name: 'beneficiary', id: 'beneficiary', value: selectedProgramData.beneficiary, disabled: !isDeveloper }),
                    buildInput({ label: 'Program Date', name: 'program_date', id: 'program_date', value: selectedProgramData.date, disabled: !isDeveloper }),
                    buildInput({ label: 'Program Balance', name: 'program_balance', id: 'program_balance', type: 'number', value: selectedProgramData.balance, disabled: !isDeveloper }),
                    buildInput({ label: 'Amount', name: 'amount', id: 'amount', type: 'amount', placeholder: 'Enter amount', value: customerPayment.amount, dataValidate: 'required|amount', oninput: 'validateInput(this)', required: true }),
                    buildSelect({ label: 'Bank Accounts', name: 'bank_account_id', id: 'bank_accounts', required: true, showDefault: true }),
                    buildInput({ label: 'Transaction Id', name: 'transaction_id', id: 'transaction_id', placeholder: 'Enter Transaction Id', value: customerPayment.transaction_id, dataValidate: 'required|friendly', oninput: 'validateInput(this)', required: true }),
                    buildInput({ label: 'Remarks', name: 'remarks', id: 'remarks', placeholder: 'Remarks', value: customerPayment.remarks, dataValidate: 'friendly', oninput: 'validateInput(this)' }),
                ].join('');

                let bankAccountData = selectedProgramData.sub_category?.bank_accounts;

                if (bankAccountData) {
                    let bankAccountsSelect = document.getElementById('bank_accounts');
                    bankAccountsSelect.disabled = false;
                    bankAccountsSelect.value = '-- Select Bank Account --';
                    bankAccountsSelect.closest('.selectParent').querySelector('ul').innerHTML = '';
                    if (bankAccountData.length > 0) {
                        bankAccountData.forEach(account => {
                            bankAccountsSelect.closest('.selectParent').querySelector('ul').innerHTML += `
                                <li data-for="bank_accounts" data-value="${account.id}" onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg transition hover:bg-[var(--h-bg-color)] text-nowrap overflow-x-auto scrollbar-hidden ">${account.account_title} | ${account.bank.short_title}</li>
                            `;
                        });
                    } else {
                        bankAccountsSelect.closest('.selectParent').querySelector('ul').innerHTML += `
                            <li data-for="bank_accounts" data-value="${bankAccountData.id}" onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg transition hover:bg-[var(--h-bg-color)] text-nowrap overflow-x-auto scrollbar-hidden ">${bankAccountData.account_title} | ${bankAccountData.bank.short_title}</li>
                        `;
                    }
                }
                safeSelect(document.querySelector(`li[data-for="bank_accounts"][data-value="${customerPayment.bank_account_id}"]`));
            } else {
                detailsDom.innerHTML = '';
            }
        }

        formatAllAmountInputs();
    }

    window.trackProgramState = function(elem) {
        const selectedLi = elem.closest('.selectParent')?.querySelector('ul li.selected');
        if (!selectedLi || !selectedLi.dataset.option) {
            detailsDom.innerHTML = '';
            return;
        }
        let ProgramData = JSON.parse(selectedLi.dataset.option);

        const canUseProgramMethod = programMethodAvailable(ProgramData);
        methodSelectDom.disabled = canUseProgramMethod;

        if (canUseProgramMethod) {
            const desiredMethod = methodSelectDom.closest('.selectParent').querySelector('ul li[data-value="program"]');
            if (!desiredMethod) {
                methodSelectDom.closest('.selectParent').querySelector('ul').innerHTML += `
                    <li data-for="method" data-value="program" onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg transition hover:bg-[var(--h-bg-color)] text-nowrap overflow-x-auto scrollbar-hidden ">Program</li>
                `;
            }
            methodSelectDom.closest('.selectParent')
                .querySelector('ul li[data-value="program"]')
                ?.dispatchEvent(new MouseEvent('mousedown', { bubbles: true }));
        } else {
            methodSelectDom.closest('.selectParent').querySelector('ul li[data-value="program"]')?.remove();
            const methodHiddenInput = methodSelectDom.closest('.selectParent')?.querySelector('.dbInput[data-for="method"]');
            if ((methodHiddenInput?.value || methodSelectDom.value).toLowerCase() === 'program') {
                methodSelectDom.value = '';
                if (methodHiddenInput) methodHiddenInput.value = '';
            }
            detailsDom.innerHTML = '';
        }
        trackDateState(dateDom);
    }

    window.trackDateState = function(elem) {
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

            const paymentProgramLi = typeSelectDom.closest('.selectParent').querySelector('ul li[data-value="payment_program"]');
            if (paymentProgramLi) {
                paymentProgramLi.dataset.option = JSON.stringify(filteredPrograms);
            }
            setOptionForValue(typeSelectDom, 'payment_program', 'option', JSON.stringify(filteredPrograms));
        } else {
            let programData = JSON.parse(programSelectDom?.closest('.selectParent')?.querySelector('ul li.selected')?.dataset?.option || '{}');
            if (date.value < programData?.date) {
                dateDom.value = '';
            }
            date.min = programData?.date;
        }
    }

    window.repeatThisRecord = function(button) {
        let formDom = document.getElementById('form');
        formDom.reset();
        const record = JSON.parse(button.getAttribute('data-record'));

        const desiredCustomer = customerSelectDom.closest('.selectParent').querySelector(`ul li[data-value="${record.customer.id}"]`);
        desiredCustomer?.dispatchEvent(new MouseEvent('mousedown', { bubbles: true }));

        const desiredType = typeSelectDom.closest('.selectParent').querySelector(`ul li[data-value="${record.type}"]`);
        desiredType?.dispatchEvent(new MouseEvent('mousedown', { bubbles: true }));

        if (record.program_id) {
            let programSelectDom = document.getElementById('payment_programs');
            if (programSelectDom) {
                let desiredProgram = programSelectDom.closest('.selectParent').querySelector(`ul li[data-value="${record.program_id}"]`);
                desiredProgram?.dispatchEvent(new MouseEvent('mousedown', { bubbles: true }));
            }
        } else if(record.method) {
            const desiredMethod = methodSelectDom.closest('.selectParent').querySelector(`ul li[data-value="${record.method}"]`);
            desiredMethod?.dispatchEvent(new MouseEvent('mousedown', { bubbles: true }));

            function setValueIfExists(id, value) {
                const el = document.getElementById(id);
                if (el) el.value = value;
            }

            setTimeout(() => {
                if (record.method === 'cash') {
                    setValueIfExists('amount', record.amount);
                    setValueIfExists('remarks', record.remarks);
                } else if (record.method === 'cheque') {
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
}

window.initCustomerPaymentsEdit = initCustomerPaymentsEdit;

function boot() {
    if (window.__customerPaymentsEdit) initCustomerPaymentsEdit();
}

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", boot);
} else {
    boot();
}
})();
