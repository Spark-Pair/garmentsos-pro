(() => {
    function initDrGenerate() {
        const config = window.__drGenerate || {};
        let payments = [];
        let selectedPayments = [];
        let totalAddedAmount = 0;
        let totalSelectedAmount = 0;
        let addedPaymentsArray = [];

        window.trackCustomerState = function trackCustomerState(elem) {
            const showPaymentBtn = document.getElementById("showPaymentBtn");
            if (showPaymentBtn) {
                showPaymentBtn.disabled = !elem.value;
            }
        };

        window.getPayments = function getPayments() {
            $.ajax({
                url: "/dr/get-payments",
                method: "GET",
                    data: {
                        customer_id: document.querySelector('input[data-for="customer"]')?.value,
                        dr_id: config.editData?.id || null,
                    },
                success: function (response) {
                    if (response.status === "success") {
                        payments = response.data;
                        payments.forEach(payment => {
                            payment.checked = selectedPayments.includes(Number(payment.id));
                        });
                        renderList();
                    } else {
                        console.error("Failed to fetch payments");
                    }
                },
                error: function (xhr) {
                    console.error(xhr.responseText);
                },
            });
        };

        function renderList() {
            const showPaymentsDom = document.getElementById("show-payments");
            if (!showPaymentsDom) return;
            showPaymentsDom.innerHTML = "";

            if (payments.length > 0) {
                payments.forEach((payment, index) => {
                    showPaymentsDom.innerHTML += `
                        <div id="${payment.id}" class="flex justify-between items-center border-b border-gray-600 py-2 px-4 cursor-pointer" onclick="togglePaymentSelection(this)">
                            <div class="w-[8%]">${index + 1}.</div>
                            <div class="w-1/6">${formatDate(payment.date)}</div>
                            <div class="w-[10%] capitalize">${payment.method}</div>
                            <div class="w-1/6">${payment.cheque_no || payment.slip_no}</div>
                            <div class="w-1/6">${formatNumbersWithDigits(payment.amount, 1, 1)}</div>
                            <div class="w-1/6">${payment.is_return ? "Return" : "Not Issued"}</div>
                            <div class="w-[10%] grid place-items-center">
                                <input ${payment.checked ? "checked" : ""} type="checkbox" class="row-checkbox hrink-0 w-3.5 h-3.5 appearance-none border border-gray-400 rounded-sm checked:bg-[var(--primary-color)] checked:border-transparent focus:outline-none transition duration-150 pointer-events-none cursor-pointer"/>
                            </div>
                        </div>
                    `;
                });
            } else {
                showPaymentsDom.innerHTML =
                    '<div class="text-center bg-[var(--h-bg-color)] rounded-lg py-2 px-4 mt-4">No Payments Added</div>';
            }

            const finalSelectedPayments = document.getElementById("finalSelectedPayments");
            if (finalSelectedPayments) {
                finalSelectedPayments.innerText = selectedPayments.length;
            }

            const finalTotalSelectedAmount = document.querySelectorAll(".finalTotalSelectedAmount");
            totalSelectedAmount = payments
                .filter(p => p.checked)
                .reduce((sum, p) => sum + parseFormattedNumber(p.amount), 0);
            finalTotalSelectedAmount.forEach(element => {
                element.innerText = formatNumbersWithDigits(totalSelectedAmount, 1, 1);
            });

            const selectedPaymentsArray = document.getElementById("selectedPaymentsArray");
            if (selectedPaymentsArray) {
                selectedPaymentsArray.value = JSON.stringify(selectedPayments);
            }
        }

        window.togglePaymentSelection = function togglePaymentSelection(row) {
            const paymentId = row.id;
            const payment = payments.find(p => p.id == paymentId);
            if (payment) {
                payment.checked = !payment.checked;
                const checkbox = row.querySelector('input[type="checkbox"]');
                if (checkbox) {
                    checkbox.checked = payment.checked;
                }
            }

            const numericPaymentId = Number(paymentId);
            if (selectedPayments.includes(numericPaymentId)) {
                selectedPayments = selectedPayments.filter(id => Number(id) !== numericPaymentId);
            } else {
                selectedPayments.push(numericPaymentId);
            }

            renderList();
        };

        window.trackMethodState = function trackMethodState(elem) {
            const fieldsData = [];

            if (elem.value == "cash") {
                fieldsData.push({
                    category: "input",
                    name: "amount",
                    label: "Amount",
                    type: "amount",
                    data_validate: "required|amount",
                    required: true,
                    placeholder: "Enter amount",
                    oninput: "trackAmountState(this)",
                    onkeydown: "enterToAdd(event)",
                });
            } else if (elem.value == "cheque") {
                fieldsData.push(
                    {
                        category: "explicitHtml",
                        html: config.bankSelectHtml || "",
                    },
                    {
                        category: "input",
                        name: "cheque_no",
                        label: "Cheque No.",
                        data_validate: "required|friendly",
                        required: true,
                        placeholder: "Enter cheque no.",
                    },
                    {
                        category: "input",
                        name: "cheque_date",
                        label: "Cheque Date",
                        type: "date",
                        required: true,
                    },
                    {
                        category: "input",
                        name: "amount",
                        label: "Amount",
                        type: "amount",
                        data_validate: "required|amount",
                        required: true,
                        placeholder: "Enter amount",
                        oninput: "trackAmountState(this)",
                        onkeydown: "enterToAdd(event)",
                    }
                );
            } else if (elem.value == "slip") {
                fieldsData.push(
                    {
                        category: "input",
                        name: "slip_no",
                        label: "Slip No.",
                        data_validate: "required|friendly",
                        required: true,
                        placeholder: "Enter slip no.",
                    },
                    {
                        category: "input",
                        name: "slip_date",
                        label: "Slip Date",
                        type: "date",
                        required: true,
                    },
                    {
                        category: "input",
                        name: "amount",
                        label: "Amount",
                        type: "amount",
                        data_validate: "required|amount",
                        required: true,
                        placeholder: "Enter amount",
                        oninput: "trackAmountState(this)",
                        onkeydown: "enterToAdd(event)",
                    }
                );
            } else if (elem.value == "online") {
                fieldsData.push(
                    {
                        category: "explicitHtml",
                        html: config.bankSelectHtml || "",
                    },
                    {
                        category: "input",
                        name: "transaction_id",
                        label: "Transaction Id",
                        data_validate: "required|friendly",
                        required: true,
                        placeholder: "Enter transaction id",
                    },
                    {
                        category: "input",
                        name: "date",
                        label: "Date",
                        type: "date",
                        required: true,
                    },
                    {
                        category: "input",
                        name: "amount",
                        label: "Amount",
                        type: "amount",
                        data_validate: "required|amount",
                        required: true,
                        placeholder: "Enter amount",
                        oninput: "trackAmountState(this)",
                        onkeydown: "enterToAdd(event)",
                    }
                );
            }

            if (elem.value != "") {
                fieldsData.push({
                    category: "explicitHtml",
                    html: config.remarksInputHtml || "",
                });

                const visibleIndexes = fieldsData
                    .map((field, index) => (field.type !== "hidden" ? index : null))
                    .filter(index => index !== null);

                if (visibleIndexes.length > 0) {
                    const lastVisibleIndex = visibleIndexes[visibleIndexes.length - 1];
                    fieldsData[lastVisibleIndex].full = visibleIndexes.length % 2 === 1;
                }

                const modalData = {
                    id: "modalForm",
                    class: "h-auto",
                    name: "Payment Details",
                    fields: fieldsData,
                    fieldsGridCount: "2",
                    bottomActions: [
                        { id: "add-payment-details", text: "Add Payment", onclick: "addPaymentDetails()" },
                    ],
                    defaultListener: false,
                };

                createModal(modalData);
            }
        };

        window.addPaymentDetails = function addPaymentDetails() {
            let detail = {};
            const inputs = document.querySelectorAll("#modalForm input:not([disabled])");

            inputs.forEach(input => {
                const name = input.getAttribute("name");
                if (name != null) {
                    const value = input.value;

                    if (name == "amount") {
                        let amountValue = input.value.replace(/[^0-9.]/g, "");

                        if (amountValue.includes(".")) {
                            let [intPart, decPart] = amountValue.split(".");
                            decPart = decPart.slice(0, 2);
                            amountValue = decPart ? `${intPart}.${decPart}` : intPart;
                        }

                        detail[name] = parseInt(amountValue);
                    } else {
                        detail[name] = value;
                    }
                } else {
                    JSON.parse(input.value);
                }
            });

            const bankInput = document.querySelector('#modalForm input.dbInput[name="bank_id"]');
            if (bankInput) {
                detail[bankInput.getAttribute("name")] = bankInput.value;
            }

            if (isNaN(detail.amount) || detail.amount <= 0) {
                detail = {};
            }

            if (Object.keys(detail).length > 0) {
                const selectedMethod = document.getElementById("method")?.value;
                totalAddedAmount += detail.amount;
                detail.method = selectedMethod;
                addedPaymentsArray.push(detail);
                renderSecondList();
            }
            closeModal("modalForm");
        };

        window.trackAmountState = function trackAmountState(elem) {
            const currentValue = elem.value.replace(/[^0-9.]/g, "");
            if (currentValue > totalSelectedAmount - totalAddedAmount) {
                elem.value = formatNumbersDigitLess(totalSelectedAmount - totalAddedAmount);
            }
        };

        function renderSecondList() {
            const addedPaymentsDom = document.getElementById("added-payments");
            if (!addedPaymentsDom) return;
            addedPaymentsDom.innerHTML = "";

            if (addedPaymentsArray.length > 0) {
                addedPaymentsArray.forEach((payment, index) => {
                    const reff_no = payment.cheque_no || payment.slip_no || payment.transaction_id || "-";
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
                });
            } else {
                addedPaymentsDom.innerHTML =
                    '<div class="text-center bg-[var(--h-bg-color)] rounded-lg py-2 px-4 mt-4">No Payments Added</div>';
            }

            const finalTotalAddedAmount = document.getElementById("finalTotalAddedAmount");
            if (finalTotalAddedAmount) {
                finalTotalAddedAmount.innerText = formatNumbersWithDigits(totalAddedAmount, 1, 1);
            }

            const addedPaymentsInput = document.getElementById("addedPaymentsArray");
            if (addedPaymentsInput) {
                addedPaymentsInput.value = JSON.stringify(addedPaymentsArray);
            }

            const methodDom = document.getElementById("method");
            if (methodDom) {
                methodDom.disabled = totalAddedAmount === totalSelectedAmount;
            }
        }

        window.deselectThisPayment = function deselectThisPayment(index) {
            totalAddedAmount -= addedPaymentsArray[index].amount;
            addedPaymentsArray.splice(index, 1);
            renderSecondList();
        };

        window.enterToAdd = function enterToAdd(event) {
            if (event.key == "Enter") {
                addPaymentDetails();
            }
        };

        window.validateForNextStep = () => {
            return true;
        };

        window.onSubmitFunction = function onSubmitFunction() {
            if (config.editData) {
                return true;
            }

            if (totalSelectedAmount <= 0) {
                if (typeof messageBox !== "undefined") {
                    messageBox.innerHTML = config.selectPaymentAlertHtml || "";
                    messageBoxAnimation();
                }
                return false;
            }

            if (totalAddedAmount !== totalSelectedAmount) {
                if (typeof messageBox !== "undefined") {
                    messageBox.innerHTML = config.amountMismatchAlertHtml || "";
                    messageBoxAnimation();
                }
                return false;
            }

            return true;
        };

        if (config.editData) {
            const restoreEditState = function restoreEditState() {
            const customerInput = document.querySelector('input.dbInput[data-for="customer"]');
            if (customerInput) {
                customerInput.value = String(config.editData.customer_id || '');
                const selectedOption = document.querySelector(
                    `.optionsDropdown li[data-for="customer"][data-value="${CSS.escape(customerInput.value)}"]`
                );
                const customerSearch = document.querySelector('#customer');
                if (customerSearch) {
                    customerSearch.value = selectedOption
                        ? selectedOption.textContent.trim()
                        : (config.editData.customer_text || '');
                }
                if (selectedOption) {
                    selectedOption.classList.add('selected');
                }
            }
            const dateInput = document.getElementById('date');
            if (dateInput && config.editData.date) dateInput.value = config.editData.date;
            };

            // Select defaults are booted by a separate deferred script. Restore
            // edit values after that boot as well as during initial page load.
            restoreEditState();
            setTimeout(restoreEditState, 0);
            setTimeout(restoreEditState, 100);

            selectedPayments = (config.editData.return_payments || []).map(Number);
            const hasPreloadedReturns = Array.isArray(config.editData.return_payment_details);
            if (hasPreloadedReturns) {
                payments = config.editData.return_payment_details;
                payments.forEach(payment => {
                    payment.checked = selectedPayments.includes(Number(payment.id));
                });
                renderList();
            }
            addedPaymentsArray = config.editData.new_payment_details || [];
            totalAddedAmount = addedPaymentsArray.reduce((sum, payment) => sum + parseFormattedNumber(payment.amount), 0);
            renderSecondList();
            if (!hasPreloadedReturns) getPayments();
        }
    }

    window.initDrGenerate = initDrGenerate;

    function boot() {
        if (window.__drGenerate) {
            initDrGenerate();
        }
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", boot);
    } else {
        boot();
    }
})();
