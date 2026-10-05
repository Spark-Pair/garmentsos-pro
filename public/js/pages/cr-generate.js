(() => {
    function initCrGenerate() {
        const config = window.__crGenerate || {};
        let voucher = {};
        let paymentsArray = [];
        let addedPaymentsArray = [];
        const voucherIdInpDom = document.getElementById("voucher_id");
        const selectedPaymentsArrayDom = document.getElementById("selectedPaymentsArray");
        const addedPaymentsArrayDom = document.getElementById("addedPaymentsArray");
        const dateDom = document.getElementById("date");
        const supplierNameDom = document.getElementById("supplier_name");
        const showPaymentListDOM = document.getElementById("show-payment");
        const addPaymentListDOM = document.getElementById("add-payment");
        const finalTotalPaymentDOM = document.getElementById("finalTotalPayment");
        const finalTotalSelectedPaymentDOM = document.querySelectorAll("#finalTotalSelectedPayment");
        const finalTotalAddedPaymentDOM = document.getElementById("finalTotalAddedPayment");
        const methodSelectDOM = document.getElementById("method");
        const amountDOM = document.getElementById("amount");
        let totalVoucherAmount = 0;
        let totalSelectedAmount = 0;
        let totalAddedAmount = 0;

        window.trackVoucherState = function trackVoucherState(e) {
            if (e.key == "Enter") {
                $.ajax({
                    url: "/get-voucher-details",
                    type: "POST",
                    data: {
                        voucher_no: e.target.value,
                        edit_payment_ids: (config.editData?.return_payments || [])
                            .map(payment => payment.id ?? payment.payment_id)
                            .filter(Boolean),
                    },
                    headers: {
                        "X-CSRF-TOKEN": $("meta[name=\"csrf-token\"]").attr("content"),
                    },
                    success: function (response) {
                        voucher = response.data;
                        if (voucher) {
                            dateDom.disabled = false;
                            dateDom.min = voucher.date;
                            supplierNameDom.value = voucher.supplier_name;

                            paymentsArray = voucher.payments;
                            if (config.editData) {
                                const returnedIds = (config.editData.return_payments || [])
                                    .flatMap(payment => [payment.id, payment.payment_id])
                                    .filter(Boolean)
                                    .map(Number);
                                paymentsArray.forEach(payment => {
                                    payment.checked = returnedIds.includes(Number(payment.id))
                                        || returnedIds.includes(Number(payment.payment_id));
                                });
                                addedPaymentsArray = (config.editData.new_payments || []).map(payment => ({
                                    ...payment,
                                    method: String(payment.method || '').replace(/\s*\|\s*CR$/i, ''),
                                    data_value: payment.data_value ?? payment.payment_id ?? payment.id,
                                    amount: payment.amount ?? 0,
                                }));
                            }

                            const messages = document.querySelectorAll(".alert-message");
                            messages.forEach(message => {
                                if (message) {
                                    message.classList.add("fade-out");
                                    message.addEventListener("animationend", () => {
                                        message.style.display = "none";
                                    });
                                }
                            });

                            voucherIdInpDom.value = voucher.id;
                        } else {
                            dateDom.value = "";
                            dateDom.disabled = true;
                            supplierNameDom.value = "";
                            paymentsArray = [];

                            if (typeof messageBox !== "undefined") {
                                const template = config.voucherErrorAlertTemplate || "";
                                messageBox.innerHTML = template.replace("__MESSAGE__", response.message || "");
                                messageBoxAnimation();
                            }
                        }
                            renderSelectPaymentList();
                            renderAddPaymentList();
                    },
                    error: function (xhr, status, error) {
                        console.error(error);
                    },
                });
            }
        };

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
                            <div class="w-[8%]">${index + 1}</div>
                            <div class="w-1/6">${formatDate(payment.slip?.slip_date || payment.cheque?.cheque_date || payment.date)}</div>
                            <div class="w-[10%] capitalize">${payment.method}</div>
                            <div class="w-1/6">${payment.reff_no ?? "-"}</div>
                            <div class="w-1/6">${formatNumbersWithDigits(payment.amount, 1, 1) ?? "-"}</div>
                            <div class="grow">${payment.customer_name ?? "-"}</div>
                            <div class="w-[10%] grid place-items-center">
                                <input ${payment.checked ? "checked" : ""} type="checkbox" class="row-checkbox hrink-0 w-3.5 h-3.5 appearance-none border border-gray-400 rounded-sm checked:bg-[var(--primary-color)] checked:border-transparent focus:outline-none transition duration-150 pointer-events-none cursor-pointer"/>
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
                    totalAddedAmount += parseFormattedNumber(payment.amount);
                    clutter += `
                        <div class="grid grid-cols-6 border-t border-gray-600 py-3 px-4 cursor-pointer">
                            <div>${index + 1}</div>
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
                methodSelectDOM.value = "";
                document.getElementById("payment").disabled = true;
                document.getElementById("payment").value = "";
                amountDOM.disabled = true;
                amountDOM.value = "";
                document.getElementById("amount-error").classList.add("hidden");
            } else {
                methodSelectDOM.disabled = false;
            }

            finalTotalAddedPaymentDOM.textContent = formatNumbersWithDigits(totalAddedAmount, 1, 1);
            addedPaymentsArrayDom.value = JSON.stringify(addedPaymentsArray);
        }

        renderSelectPaymentList();
        renderAddPaymentList();

        window.selectThisPayment = function selectThisPayment(elem, index) {
            const checkBox = elem.querySelector(".row-checkbox");
            checkBox.checked = !checkBox.checked;
            paymentsArray[index].checked = !paymentsArray[index].checked;
            renderSelectPaymentList();
        };

        window.trackMethodState = function trackMethodState(elem) {
            amountDOM.value = "";
            amountDOM.disabled = true;
            document.getElementById("payment").value = "";
            document.getElementById("payment").disabled = true;

            if (elem.value != "") {
                $.ajax({
                    url: "/cr/create",
                    type: "GET",
                    data: {
                        supplier: voucher.supplier_id,
                        method: elem.value,
                        max_date: dateDom.value,
                    },
                    headers: {
                        "X-CSRF-TOKEN": $("meta[name=\"csrf-token\"]").attr("content"),
                    },
                    success: function (response) {
                        $("#payment")
                            .closest(".selectParent")
                            .html($(response).find("#payment").closest(".selectParent").html());
                        const allPaymentsDOM = document.querySelectorAll('ul[data-for="payment"] li');
                        allPaymentsDOM.forEach(paymentDOM => {
                            addedPaymentsArray.forEach(payment => {
                                if (payment.data_value === paymentDOM.dataset.value) {
                                    paymentDOM.remove();
                                }
                            });
                            if (JSON.parse(paymentDOM.dataset.option || "{}").amount > totalSelectedAmount) {
                                paymentDOM.remove();
                            }
                        });
                        if (document.querySelectorAll('ul[data-for="payment"] li').length < 1) {
                            document.getElementById("payment").value = "";
                            document.getElementById("payment").disabled = true;
                            document.getElementById("payment").placeholder = "-- No options available --";
                        }
                    },
                    error: function (xhr, status, error) {
                        console.error(error);
                    },
                });
            }
        };

        window.trackPaymentState = function trackPaymentState(elem) {
            amountDOM.value = "";
            amountDOM.disabled = true;
            if (elem.value != "") {
                if (methodSelectDOM.value === "Self Cheque") {
                    amountDOM.disabled = false;
                } else {
                    const selectedPayment = JSON.parse(
                        elem.parentElement.querySelector('ul[data-for="payment"] li.selected').dataset.option || "{}"
                    );
                    amount.value = selectedPayment.amount;
                }
            }
        };

        window.addPayment = function addPayment() {
            let currentValue = amountDOM.value.replace(/[^0-9.]/g, "");
            if (currentValue > 0) {
                const selectedOption = JSON.parse(
                    document.querySelector('ul[data-for="payment"] li.selected').dataset.option || "{}"
                );
                const selectedBankAccountId =
                    methodSelectDOM.value === "Self Cheque"
                        ? selectedOption.id ?? null
                        : selectedOption.bank_account_id ?? null;

                addedPaymentsArray.push({
                    bank_account_id: selectedBankAccountId,
                    data_value: document
                        .querySelector('ul[data-for="payment"] li.selected')
                        .getAttribute("data-value"),
                    method: methodSelectDOM.value,
                    payment: document.getElementById("payment").value,
                    amount: currentValue,
                });

                methodSelectDOM.value = "";
                document.getElementById("payment").value = "";
                currentValue = "";
                renderAddPaymentList();
            }
        };

        window.deleteThis = function deleteThis(elem, index) {
            addedPaymentsArray.splice(index, 1);
            renderAddPaymentList();
        };

        window.trackAmountState = function trackAmountState(elem) {
            const currentValue = elem.value.replace(/[^0-9.]/g, "");
            if (currentValue > totalSelectedAmount - totalAddedAmount) {
                elem.value = formatNumbersDigitLess(totalSelectedAmount - totalAddedAmount);
            }
        };

        window.enterToAdd = function enterToAdd(event) {
            if (event.key == "Enter") {
                addPayment();
            }
        };

        window.validateForNextStep = function validateForNextStep() {
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

        if (config.editData && voucherIdInpDom?.value) {
            trackVoucherState({
                key: "Enter",
                target: document.getElementById("voucher_no"),
            });
        }
    }

    window.initCrGenerate = initCrGenerate;

    function boot() {
        if (window.__crGenerate) {
            initCrGenerate();
        }
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", boot);
    } else {
        boot();
    }
})();
