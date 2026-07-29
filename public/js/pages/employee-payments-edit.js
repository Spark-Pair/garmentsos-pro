(() => {
    function initEmployeePaymentsEdit() {
        const config = window.__employeePaymentsEdit || {};
        const isDeveloper = config.isDeveloper === true;
        const templates = config.templates || {};

        window.chequeNos = config.chequeNos || '';
        window.slipNos = config.slipNos || '';

        let customerPayment = config.customerPayment || {};
        customerPayment.remarks = customerPayment.remarks || '';
        const methodSelectDom = document.getElementById('method');
        const typeSelectDom = document.getElementById('type');
        const dateDom = document.getElementById('date');
        const detailsDom = document.getElementById('details');

        selectedCustomerData = null;
        let selectedProgramData = {};
        let selectedCustomer;
        const today = localDateString();

        function renderTemplate(template, data) {
            let html = template || '';
            Object.keys(data || {}).forEach(key => {
                const value = data[key] ?? '';
                html = html.replaceAll(`__${key}__`, value);
            });
            return html;
        }

        function unlockDeveloperEditFields(scope = document) {
            if (!isDeveloper) return;

            scope.querySelectorAll('input[disabled], select[disabled], textarea[disabled]').forEach((input) => {
                input.disabled = false;
            });
            scope.querySelectorAll('input[readonly], textarea[readonly]').forEach((input) => {
                input.readOnly = false;
                input.removeAttribute('readonly');
            });
        }

        function setOptionOnNthLi(triggerDom, index, key, value = '') {
            const li = triggerDom.closest('.selectParent')?.querySelectorAll('ul li')[index];
            if (li) li.dataset[key] = value;
        }

        window.trackCustomerState = function trackCustomerState() {
            setOptionOnNthLi(typeSelectDom, 2, 'option');
            methodSelectDom.value = '';
            typeSelectDom.value = '';

            if (customerPayment) {
                selectedCustomer = customerPayment.customer;
                dateDom.disabled = false;
                methodSelectDom.disabled = false;
                dateDom.min = selectedCustomer?.date ? selectedCustomer.date.toString().split('T')[0] : '';
                dateDom.max = today;
                selectedCustomerData = selectedCustomer;

                setOptionOnNthLi(typeSelectDom, 2, 'option', JSON.stringify(selectedCustomer?.payment_programs) ?? '');
            } else {
                dateDom.disabled = true;
                methodSelectDom.disabled = true;
                setOptionOnNthLi(typeSelectDom, 2, 'option');
            }

            methodSelectDom.querySelector("option[value='program']")?.remove();
        };

        function safeSelect(liElem) {
            if (liElem) {
                selectThisOption(liElem);
            }
        }

        trackCustomerState();
        safeSelect(document.querySelector(`li[data-for="type"][data-value="${customerPayment.type}"]`));
        safeSelect(document.querySelector(`li[data-for="method"][data-value="${customerPayment.method}"]`));

        const detailsInputsContainer = document.getElementById('details-inputs-container');

        window.trackTypeState = function trackTypeState(elem) {
            methodSelectDom.value = '';
            detailsInputsContainer.classList.remove('mb-4');
            if (elem.value == 'payment_program') {
                methodSelectDom.closest('.selectParent').querySelector('ul').innerHTML += `
                    <li data-for="method" data-value="program" onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg transition hover:bg-[var(--h-bg-color)] text-nowrap overflow-x-auto scrollbar-hidden ">Program</li>
                `;
                detailsInputsContainer.innerHTML = config.programSelectHtml || '';
                detailsInputsContainer.classList.add('mb-4');

                let allProgramsArray = JSON.parse(
                    typeSelectDom.closest('.selectParent')?.querySelector('ul li.selected').dataset.option
                );

                const programSelectDom = document.getElementById('payment_programs');
                if (allProgramsArray.length > 0) {
                    programSelectDom.disabled = false;
                    programSelectDom.value = '-- Select payment program --';
                    programSelectDom.closest('.selectParent').querySelector('ul').innerHTML = `
                        <li data-for="payment_programs" data-value="" onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg transition hover:bg-[var(--h-bg-color)] text-nowrap overflow-x-auto scrollbar-hidden ">-- Select payment program --</li>
                    `;
                    allProgramsArray.forEach(program => {
                        programSelectDom.closest('.selectParent').querySelector('ul').innerHTML += `
                            <li data-for="payment_programs" data-value="${program.id}" data-option='${jsonAttr(program)}' onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg transition hover:bg-[var(--h-bg-color)] text-nowrap overflow-x-auto scrollbar-hidden capitalize">${program.program_no ?? program.order_no} | ${formatNumbersWithDigits(program.balance, 1, 1)} | ${program.category}</li>
                        `;
                    });
                } else {
                    programSelectDom.disabled = false;
                    programSelectDom.closest('.selectParent').querySelector('ul').innerHTML = `
                        <li data-for="payment_programs" data-value="" onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg transition hover:bg-[var(--h-bg-color)] text-nowrap overflow-x-auto scrollbar-hidden ">-- No options avalaible --</li>
                    `;
                }
                safeSelect(document.querySelector(`li[data-for="payment_programs"][data-value="${customerPayment.program_id}"]`));
            } else {
                detailsInputsContainer.innerHTML = '';
                methodSelectDom.closest('.selectParent').querySelector("ul li[data-value='program']")?.remove();
                methodSelectDom.value = '';
            }
            trackMethodState(methodSelectDom);
        };

        window.trackMethodState = function trackMethodState(elem) {
            detailsDom.innerHTML = '';
            if (elem.value == 'cash') {
                detailsDom.innerHTML = renderTemplate(templates.cash, {
                    AMOUNT: customerPayment.amount,
                    REMARKS: customerPayment.remarks,
                });
            } else if (elem.value == 'cheque') {
                detailsDom.innerHTML = renderTemplate(templates.cheque, {
                    AMOUNT: customerPayment.amount,
                    CHEQUE_DATE: formatDate(customerPayment.cheque_date, false, true),
                    CHEQUE_NO: customerPayment.cheque_no,
                    REMARKS: customerPayment.remarks,
                    CLEAR_DATE: formatDate(customerPayment.clear_date, false, true),
                });
                safeSelect(document.querySelector(`li[data-for="bank"][data-value="${customerPayment.bank_id}"]`));
            } else if (elem.value == 'slip') {
                detailsDom.innerHTML = renderTemplate(templates.slip, {
                    CUSTOMER_NAME: selectedCustomer.customer_name,
                    AMOUNT: customerPayment.amount,
                    SLIP_DATE: formatDate(customerPayment.slip_date, false, true),
                    SLIP_NO: customerPayment.slip_no,
                    REMARKS: customerPayment.remarks,
                    CLEAR_DATE: formatDate(customerPayment.clear_date, false, true),
                });
            } else if (elem.value == 'adjustment') {
                detailsDom.innerHTML = renderTemplate(templates.adjustment, {
                    AMOUNT: customerPayment.amount,
                    REMARKS: customerPayment.remarks,
                });
            } else if (elem.value == 'program') {
                let programSelectDom = document.getElementById('payment_programs');
                selectedProgramData = JSON.parse(
                    programSelectDom.closest('.selectParent')?.querySelector('ul li.selected').dataset.option
                );
                if (selectedProgramData.category != 'waiting') {
                    let beneficiary = '-';
                    if (selectedProgramData.category) {
                        if (selectedProgramData.category === 'supplier' && selectedProgramData.sub_category?.supplier_name) {
                            beneficiary = selectedProgramData.sub_category.supplier_name;
                        } else if (
                            selectedProgramData.category === 'customer' &&
                            selectedProgramData.sub_category?.customer_name
                        ) {
                            beneficiary = selectedProgramData.sub_category.customer_name;
                        } else if (
                            selectedProgramData.category === 'self_account' &&
                            selectedProgramData.sub_category?.account_title
                        ) {
                            beneficiary = selectedProgramData.sub_category.account_title;
                        } else if (selectedProgramData.category === 'waiting' && selectedProgramData.remarks) {
                            beneficiary = selectedProgramData.remarks;
                        }
                    }
                    selectedProgramData.beneficiary = beneficiary;

                    detailsDom.innerHTML = renderTemplate(templates.program, {
                        PROGRAM_CATEGORY: selectedProgramData.category,
                        BENEFICIARY: selectedProgramData.beneficiary,
                        PROGRAM_DATE: selectedProgramData.date,
                        PROGRAM_BALANCE: selectedProgramData.balance,
                        AMOUNT: customerPayment.amount,
                        TRANSACTION_ID: customerPayment.transaction_id,
                        REMARKS: customerPayment.remarks,
                    });

                    let bankAccountData = selectedProgramData.sub_category.bank_accounts;

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

            unlockDeveloperEditFields(detailsDom);
            formatAllAmountInputs();
        };

        window.trackProgramState = function trackProgramState(elem) {
            let ProgramData = JSON.parse(elem.closest('.selectParent')?.querySelector('ul li.selected').dataset.option);

            if (ProgramData.category != 'waiting') {
                const desiredMethod = methodSelectDom.closest('.selectParent').querySelector('ul li[data-value="program"]');
                if (!desiredMethod) {
                    methodSelectDom.closest('.selectParent').querySelector('ul').innerHTML += `
                        <li data-for="method" data-value="program" onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg transition hover:bg-[var(--h-bg-color)] text-nowrap overflow-x-auto scrollbar-hidden ">Program</li>
                    `;
                }
                desiredMethod.dispatchEvent(new MouseEvent('mousedown', { bubbles: true }));
            } else {
                methodSelectDom.closest('.selectParent').querySelector('ul li[data-value="program"]')?.remove();
                detailsDom.innerHTML = '';
            }
            trackDateState(dateDom);
        };

        window.trackDateState = function trackDateState(elem) {
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

                typeSelectDom.closest('.selectParent').querySelector('ul li[data-value="payment_program"]').dataset.option =
                    JSON.stringify(filteredPrograms);
            } else {
                let programData = JSON.parse(programSelectDom.closest('.selectParent')?.querySelector('ul li.selected').dataset.option);
                if (date.value < programData?.date) {
                    dateDom.value = '';
                }
                date.min = programData?.date;
            }
        };

        window.repeatThisRecord = function repeatThisRecord(button) {
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
                    let desiredProgram = programSelectDom
                        .closest('.selectParent')
                        .querySelector(`ul li[data-value="${record.program_id}"]`);
                    desiredProgram?.dispatchEvent(new MouseEvent('mousedown', { bubbles: true }));
                }
            } else if (record.method) {
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
                            const desiredBank = bankSelectDom.closest('.selectParent').querySelector(
                                `ul li[data-value="${record.bank_id}"]`
                            );
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
        };
    }

    window.initEmployeePaymentsEdit = initEmployeePaymentsEdit;

    function boot() {
        if (window.__employeePaymentsEdit) {
            initEmployeePaymentsEdit();
        }
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", boot);
    } else {
        boot();
    }
})();
