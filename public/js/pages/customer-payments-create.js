(() => {
function initCustomerPaymentsCreate() {
    const config = window.__customerPaymentsCreate || {};
    window.__cpBanksOptions = config.banksOptions || {};
    let customerSelectDom = document.getElementById('customer_id');
    let methodSelectDom = document.getElementById('method');
    let typeSelectDom = document.getElementById('type');
    let dateDom = document.getElementById('date');
    let balanceDom = document.getElementById('balance');
    let detailsDom = document.getElementById('details');

    function lockProgramFlowSelect(selectDom) {
        if (!selectDom) return;

        selectDom.disabled = true;
        selectDom.closest('.selectParent')?.classList.add('pointer-events-none', 'opacity-70');
    }

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
        addBtnLink = '',
        className = '',
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
            <div class="form-group relative grow ${className}">
                ${label ? `
                    <span class="flex items-center justify-between mb-2">
                        <label for="${name}" class="block font-medium text-[var(--secondary-text)]">
                            ${label}${!required && !disabled ? ' (optional)' : ''}
                        </label>
                        ${addBtnLink ? `<a class="text-lg px-2 leading-none" href="${addBtnLink}">+</a>` : ''}
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

    function formatProgramBalance(balance) {
        const normalized = typeof balance === 'string' ? balance.replace(/,/g, '') : balance;
        const numeric = Number(normalized);
        if (!Number.isFinite(numeric)) {
            return formatNumbersWithDigits(0, 1, 1);
        }
        return formatNumbersWithDigits(numeric, 1, 1);
    }

    function normalizePrograms(programs) {
        if (Array.isArray(programs)) return programs;
        if (programs && typeof programs === 'object') return [programs];
        return [];
    }

    function programMethodAvailable(program) {
        return Boolean(program?.program_method_available || program?.has_sub_category || program?.sub_category?.id);
    }

    function setOptionOnNthLi(triggerDom, index, key, value = '') {
        const li = triggerDom.closest('.selectParent')?.querySelectorAll('ul li')[index];
        if (li) li.dataset[key] = value;
    }

    function setOptionForValue(triggerDom, value, key, dataValue = '') {
        const li = triggerDom.closest('.selectParent')?.querySelector(`ul li[data-value="${value}"]`);
        if (li) li.dataset[key] = dataValue;
    }

    function selectByValue(forId, value) {
        const scope = getSelectScope(document.getElementById(forId) || document);
        const li = scope.querySelector(`.optionsDropdown li[data-for="${forId}"][data-value="${value}"]`);
        if (li) {
            selectThisOption(li);
            return true;
        }
        const dbInput = scope.querySelector(`.dbInput[data-for="${forId}"]`);
        const searchInput = scope.querySelector(`#${forId}`);
        if (dbInput) dbInput.value = value;
        if (searchInput && !searchInput.value) {
            searchInput.value = String(value).replaceAll('_', ' ');
        }
        return false;
    }

    function getSelectedLi(forId, scope = document) {
        return scope.querySelector(`.optionsDropdown li[data-for="${forId}"].selected`)
            || scope.querySelector(`.optionsDropdown li[data-for="${forId}"][data-value="${forId === 'type' ? 'payment_program' : ''}"]`);
    }

    window.trackCustomerState = function() {
        setOptionOnNthLi(typeSelectDom, 2, 'option');
        setOptionForValue(typeSelectDom, 'payment_program', 'option', '');
        dateDom.value = '';
        balanceDom.value = '';
        methodSelectDom.value = '';
        typeSelectDom.value = '';

        if (customerSelectDom.value != '') {
            selectedCustomer = JSON.parse(customerSelectDom.closest('.selectParent')?.querySelector('ul li.selected')?.dataset.option || 'null');

            dateDom.disabled = false;
            methodSelectDom.disabled = false;
            dateDom.min = selectedCustomer?.date ? selectedCustomer.date.toString().split('T')[0] : '';
            dateDom.max = today;
            balanceDom.value = formatNumbersWithDigits(selectedCustomer?.balance || 0, 1, 1);
            selectedCustomerData = selectedCustomer;
            const programs = normalizePrograms(selectedCustomer?.payment_programs);
            const serializedPrograms = JSON.stringify(programs) ?? '';
            setOptionOnNthLi(typeSelectDom, 2, 'option', serializedPrograms);
            setOptionForValue(typeSelectDom, 'payment_program', 'option', serializedPrograms);
        } else {
            dateDom.disabled = true;
            methodSelectDom.disabled = true;
            setOptionOnNthLi(typeSelectDom, 2, 'option');
            setOptionForValue(typeSelectDom, 'payment_program', 'option', '');
        }

        methodSelectDom.querySelector("option[value='program']")?.remove();
    }

    const url = new URL(window.location.href);
    const programIdParam = url.searchParams.get('program_id') || String(config.programFromParam?.id ?? '');
    const isProgramFlow = Boolean(config.programFromParam)
        || url.searchParams.has('program_id')
        || url.searchParams.has('source');

    const detailsInputsContainer = document.getElementById('details-inputs-container');

    window.trackTypeState = function(elem, isNoModal, options = {}) {
        methodSelectDom.value = '';
        detailsInputsContainer.classList.remove('mb-4');
        if (elem.value == 'payment_program') {
            methodSelectDom.closest('.selectParent').querySelector('ul li[data-value="program"]')?.remove();
            methodSelectDom.disabled = true;
            detailsInputsContainer.innerHTML = "";

            const typeScope = typeSelectDom.closest('.selectParent') || document;
            const selectedTypeLi = typeScope.querySelector('ul li.selected') || typeScope.querySelector('ul li[data-value="payment_program"]');
            let allProgramsArray = JSON.parse(selectedTypeLi?.dataset?.option || '[]');
            allProgramsArray = normalizePrograms(allProgramsArray);

            detailsInputsContainer.innerHTML += buildSelect({
                label: 'Payment Programs',
                name: 'program_id',
                id: 'payment_programs',
                required: true,
                onchange: 'trackProgramState(this)',
                className: 'col-span-full',
            });
            detailsInputsContainer.classList.add('mb-4');

            const programSelectDom = document.getElementById('payment_programs');
            if (!allProgramsArray.length && config.programFromParam) {
                allProgramsArray = normalizePrograms(config.programFromParam);
            }

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
                            <li data-for="payment_programs" data-value="${program.id}" data-option='${jsonAttr(program)}' onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg transition hover:bg-[var(--h-bg-color)] text-nowrap overflow-x-auto scrollbar-hidden capitalize">${program.program_no ?? program.order_no} | ${formatProgramBalance(program.balance)} | ${categoryText} | ${beneficiary} | ${formatDate(program.date)}</li>
                        `;
                });
                if (options.autoSelectProgram !== false && programIdParam) {
                    const desired = programSelectDom.closest('.selectParent').querySelector(`ul li[data-value="${programIdParam}"]`);
                    desired?.dispatchEvent(new MouseEvent('mousedown', { bubbles: true }));
                } else if (options.autoSelectProgram !== false && allProgramsArray.length === 1) {
                    const only = programSelectDom.closest('.selectParent').querySelector(`ul li[data-for="payment_programs"][data-value="${allProgramsArray[0].id}"]`);
                    only?.dispatchEvent(new MouseEvent('mousedown', { bubbles: true }));
                }
            } else {
                programSelectDom.disabled = true;
                programSelectDom.value = '-- No options available --';
                programSelectDom.closest('.selectParent').querySelector('ul').innerHTML = `
                    <li data-for="payment_programs" data-value="" onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg transition hover:bg-[var(--h-bg-color)] text-nowrap overflow-x-auto scrollbar-hidden ">-- No options avalaible --</li>
                `;
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
                buildInput({ label: 'Amount', name: 'amount', id: 'amount', type: 'amount', placeholder: 'Enter amount', dataValidate: 'required|amount', oninput: 'validateInput(this)', required: true }),
                buildInput({ label: 'Remarks', name: 'remarks', id: 'remarks', placeholder: 'Remarks', dataValidate: 'friendly', oninput: 'validateInput(this)' }),
            ].join('');
        } else if (elem.value == 'cheque') {
            const bankOptions = Object.entries(window.__cpBanksOptions || {}).map(([value, opt]) => ({
                value,
                text: opt.text,
                data_option: opt.data_option,
            }));
            detailsDom.innerHTML = [
                buildSelect({ label: 'Bank', name: 'bank_id', id: 'bank', options: bankOptions, showDefault: true, required: true }),
                buildInput({ label: 'Amount', name: 'amount', id: 'amount', type: 'amount', placeholder: 'Enter amount', dataValidate: 'required|amount', oninput: 'validateInput(this)', required: true }),
                buildInput({ label: 'Cheque Date', name: 'cheque_date', id: 'cheque_date', type: 'date', required: true }),
                buildInput({ label: 'Cheque No', name: 'cheque_no', id: 'cheque_no', placeholder: 'Enter cheque no', dataValidate: 'required|friendly', oninput: 'validateInput(this)', required: true }),
                buildInput({ label: 'Remarks', name: 'remarks', id: 'remarks', placeholder: 'Remarks', dataValidate: 'friendly', oninput: 'validateInput(this)' }),
                buildInput({ label: 'Clear Date', name: 'clear_date', id: 'clear_date', type: 'date' }),
            ].join('');
        } else if (elem.value == 'slip') {
            detailsDom.innerHTML = [
                buildInput({ label: 'Customer', name: 'customer', id: 'customer', placeholder: 'Enter Customer', value: selectedCustomer?.customer_name || '', disabled: true, required: true }),
                buildInput({ label: 'Amount', name: 'amount', id: 'amount', type: 'amount', placeholder: 'Enter amount', dataValidate: 'required|amount', oninput: 'validateInput(this)', required: true }),
                buildInput({ label: 'Slip Date', name: 'slip_date', id: 'slip_date', type: 'date', required: true }),
                buildInput({ label: 'Slip No', name: 'slip_no', id: 'slip_no', placeholder: 'Enter slip no', dataValidate: 'required|friendly', oninput: 'validateInput(this)', required: true }),
                buildInput({ label: 'Remarks', name: 'remarks', id: 'remarks', placeholder: 'Remarks', dataValidate: 'friendly', oninput: 'validateInput(this)' }),
                buildInput({ label: 'Clear Date', name: 'clear_date', id: 'clear_date', type: 'date' }),
            ].join('');
        } else if (elem.value == 'adjustment') {
            detailsDom.innerHTML = [
                buildInput({ label: 'Amount', name: 'amount', id: 'amount', type: 'amount', placeholder: 'Enter amount', dataValidate: 'required|amount', oninput: 'validateInput(this)', required: true }),
                buildInput({ label: 'Remarks', name: 'remarks', id: 'remarks', placeholder: 'Remarks', dataValidate: 'friendly', oninput: 'validateInput(this)' }),
            ].join('');
        } else if (elem.value == 'program') {
            let programSelectDom = document.getElementById('payment_programs');
            const selectedProgramLi = programSelectDom?.closest('.selectParent')?.querySelector('ul li.selected');
            if (!selectedProgramLi || !selectedProgramLi.dataset.option) {
                detailsDom.innerHTML = '';
                return;
            }
            selectedProgramData = JSON.parse(selectedProgramLi.dataset.option || '{}');
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

                detailsDom.innerHTML = [
                    buildInput({ label: 'Category', name: 'category', id: 'category', value: selectedProgramData.category, disabled: true }),
                    buildInput({ label: 'Beneficiary', name: 'beneficiary', id: 'beneficiary', value: selectedProgramData.beneficiary, disabled: true }),
                    buildInput({ label: 'Program Date', name: 'program_date', id: 'program_date', value: selectedProgramData.date, disabled: true }),
                    buildInput({ label: 'Program Balance', name: 'program_balance', id: 'program_balance', type: 'number', value: selectedProgramData.balance, disabled: true }),
                    buildInput({ label: 'Amount', name: 'amount', id: 'amount', type: 'amount', placeholder: 'Enter amount', dataValidate: 'required|amount', oninput: 'validateInput(this)', required: true }),
                    buildSelect({ label: 'Bank Accounts', name: 'bank_account_id', id: 'bank_accounts', required: true, showDefault: true, addBtnLink: '/bank-accounts/create' }),
                    buildInput({ label: 'Transaction Id', name: 'transaction_id', id: 'transaction_id', placeholder: 'Enter Transaction Id', dataValidate: 'required|friendly', oninput: 'validateInput(this)', required: true }),
                    buildInput({ label: 'Remarks', name: 'remarks', id: 'remarks', placeholder: 'Remarks', dataValidate: 'friendly', oninput: 'validateInput(this)' }),
                ].join('');

                let bankAccountData = selectedProgramData.sub_category.bank_accounts;

                if (bankAccountData) {
                    let bankAccountsSelect = document.getElementById('bank_accounts');
                    bankAccountsSelect.disabled = false;
                    bankAccountsSelect.closest('.selectParent').querySelector('ul').innerHTML = '';
                    if (Array.isArray(bankAccountData) && bankAccountData.length > 1) {
                        bankAccountsSelect.closest('.selectParent').querySelector('ul').innerHTML += `
                            <li data-for="bank_accounts" data-value="" onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg transition hover:bg-[var(--h-bg-color)] text-nowrap overflow-x-auto scrollbar-hidden ">-- Select Bank Account --</li>
                        `;
                    }

                    if (Array.isArray(bankAccountData) && bankAccountData.length > 0) {
                        bankAccountData.forEach(account => {
                        const title = account.account_title ?? account.title ?? '-';
                        const bankTitle = account.bank?.short_title ?? account.bank?.title ?? '-';
                        bankAccountsSelect.closest('.selectParent').querySelector('ul').innerHTML += `
                                <li data-for="bank_accounts" data-value="${account.id}" onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg transition hover:bg-[var(--h-bg-color)] text-nowrap overflow-x-auto scrollbar-hidden ">${title} | ${bankTitle}</li>
                            `;
                        });
                    } else if (!Array.isArray(bankAccountData) && bankAccountData?.id) {
                        const title = bankAccountData.account_title ?? bankAccountData.title ?? '-';
                        const bankTitle = bankAccountData.bank?.short_title ?? bankAccountData.bank?.title ?? '-';
                        bankAccountsSelect.closest('.selectParent').querySelector('ul').innerHTML += `
                            <li data-for="bank_accounts" data-value="${bankAccountData.id}" onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg transition hover:bg-[var(--h-bg-color)] text-nowrap overflow-x-auto scrollbar-hidden ">${title} | ${bankTitle}</li>
                        `;
                    } else {
                        bankAccountsSelect.closest('.selectParent').querySelector('ul').innerHTML += `
                            <li data-for="bank_accounts" data-value="" onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg transition hover:bg-[var(--h-bg-color)] text-nowrap overflow-x-auto scrollbar-hidden ">-- No options available --</li>
                        `;
                        bankAccountsSelect.disabled = true;
                    }
                    selectThisOption(bankAccountsSelect.closest('.selectParent').querySelector('ul li'));
                }
            } else {
                detailsDom.innerHTML = '';
            }
        }
    }

    window.trackProgramState = function(elem, options = {}) {
        const selectedLi = elem.closest('.selectParent')?.querySelector('ul li.selected');
        if (!selectedLi || !selectedLi.dataset?.option) {
            methodSelectDom.closest('.selectParent').querySelector('ul li[data-value="program"]')?.remove();
            methodSelectDom.value = '';
            methodSelectDom.disabled = true;
            detailsDom.innerHTML = '';
            return;
        }
        let ProgramData = JSON.parse(selectedLi.dataset.option);
        const canUseProgramMethod = programMethodAvailable(ProgramData);
        methodSelectDom.disabled = canUseProgramMethod;

        if (canUseProgramMethod) {
            let desiredMethod = methodSelectDom.closest('.selectParent').querySelector('ul li[data-value="program"]');
            if (!desiredMethod) {
                methodSelectDom.closest('.selectParent').querySelector('ul').innerHTML += `
                    <li data-for="method" data-value="program" onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg transition hover:bg-[var(--h-bg-color)] text-nowrap overflow-x-auto scrollbar-hidden ">Program</li>
                `;
                desiredMethod = methodSelectDom.closest('.selectParent').querySelector('ul li[data-value="program"]');
            }
            if (options.autoSelectMethod !== false) {
                desiredMethod?.dispatchEvent(new MouseEvent('mousedown', { bubbles: true }));
            }
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

    function selectOptionInProgramFlow(option) {
        const selectParent = option?.closest('.selectParent');
        const forId = option?.dataset?.for;
        if (!selectParent || !forId) return null;

        const form = selectParent.closest('form') || document;
        const visibleInput = form.querySelector(`#${forId}`);
        const hiddenInput = form.querySelector(`.dbInput[data-for="${forId}"]`);
        if (!visibleInput || !hiddenInput) return null;

        visibleInput.value = option.textContent.trim();
        hiddenInput.value = option.dataset.value || '';
        selectParent.querySelectorAll(`ul li[data-for="${forId}"]`).forEach(item => item.classList.remove('selected'));
        option.classList.add('selected');

        return { visibleInput, hiddenInput };
    }

    function initFromProgramParam() {
        if (!isProgramFlow) return;

        const customerOptions = customerSelectDom.closest('.selectParent')?.querySelectorAll('ul li') || [];
        let matchedCustomerOption = null;

        if (programIdParam) {
            matchedCustomerOption = Array.from(customerOptions).find(option => {
                if (!option.dataset.value || option.textContent.trim() === '') return false;

                try {
                    return normalizePrograms(JSON.parse(option.dataset.option || 'null')?.payment_programs)
                        .some(program => String(program.id) === String(programIdParam));
                } catch (error) {
                    return false;
                }
            });
        }

        if (!matchedCustomerOption && config.programCustomerId) {
            matchedCustomerOption = Array.from(customerOptions)
                .find(option => String(option.dataset.value) === String(config.programCustomerId));
        }

        const customerSelection = selectOptionInProgramFlow(matchedCustomerOption);
        if (!customerSelection) return;
        trackCustomerState();
        customerSelectDom.disabled = true;

        const typeOption = typeSelectDom.closest('.selectParent')
            ?.querySelector('ul li[data-value="payment_program"]');
        if (!typeOption || !selectedCustomer) return;

        let programPool = normalizePrograms(selectedCustomer.payment_programs);
        if (programIdParam) {
            programPool = programPool.filter(program => String(program.id) === String(programIdParam));
        }
        if (!programPool.length && config.programFromParam) {
            programPool = normalizePrograms(config.programFromParam);
        }
        if (!programPool.length) return;

        typeOption.dataset.option = JSON.stringify(programPool);
        const typeSelection = selectOptionInProgramFlow(typeOption);
        if (!typeSelection) return;
        trackTypeState(typeSelection.hiddenInput, true, { autoSelectProgram: false });
        lockProgramFlowSelect(typeSelectDom);

        const programSelectDom = document.getElementById('payment_programs');
        const programOption = programSelectDom?.closest('.selectParent')
            ?.querySelector(`ul li[data-value="${programIdParam || programPool[0].id}"]`);
        const programSelection = selectOptionInProgramFlow(programOption);
        if (!programSelection) return;
        trackProgramState(programSelection.hiddenInput, { autoSelectMethod: false });
        lockProgramFlowSelect(programSelectDom);

        const selectedProgram = JSON.parse(programOption.dataset.option || '{}');
        if (!programMethodAvailable(selectedProgram)) {
            methodSelectDom.disabled = false;
            return;
        }

        const methodOption = methodSelectDom.closest('.selectParent')?.querySelector('ul li[data-value="program"]');
        const methodSelection = selectOptionInProgramFlow(methodOption);
        if (!methodSelection) return;
        trackMethodState(methodSelection.hiddenInput);
        methodSelectDom.disabled = true;

        url.search = '';
        window.history.replaceState({}, document.title, url.toString());
    }

    initFromProgramParam();

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

window.initCustomerPaymentsCreate = initCustomerPaymentsCreate;

function boot() {
    if (window.__customerPaymentsCreate) initCustomerPaymentsCreate();
}

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", boot);
} else {
    boot();
}
})();
