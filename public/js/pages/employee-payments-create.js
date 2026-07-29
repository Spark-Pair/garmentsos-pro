(() => {
    function initEmployeePaymentsCreate() {
        const employeeSelectDom = document.getElementById("employee");
        const balanceDom = document.getElementById("balance");
        const dateSelectDom = document.getElementById("date");
        const methodSelectDom = document.getElementById("method");
        const amountInpDom = document.getElementById("amount");

        window.trackCategoryState = function trackCategoryState(elem) {
            if (elem.value !== "") {
                $.ajax({
                    url: "/get-employees-by-category",
                    type: "POST",
                    data: {
                        category: elem.value,
                    },
                    headers: {
                        "X-CSRF-TOKEN": $("meta[name=\"csrf-token\"]").attr("content"),
                    },
                    success: function (response) {
                        if (response.status == "success") {
                            let allEmployees = response.data;

                            let employeeUL = employeeSelectDom
                                .closest(".selectParent")
                                .querySelector("ul");
                            employeeUL.innerHTML = `
                                <li data-for="employee" data-value="" onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg transition hover:bg-[var(--h-bg-color)] text-nowrap overflow-x-auto scrollbar-hidden capitalize">-- Select ${elem.value} --</li>
                            `;

                            allEmployees.forEach((employee) => {
                                employeeUL.innerHTML += `
                                    <li data-for="employee" data-value="${employee.id}" data-option='${jsonAttr(employee)}' onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg transition hover:bg-[var(--h-bg-color)] text-nowrap overflow-x-auto scrollbar-hidden capitalize">${employee.employee_name} | ${employee.balance_formatted || formatNumbersWithDigits(
                                    employee.balance,
                                    1,
                                    1
                                )} | ${employee.type.title}</li>
                                `;
                            });

                            selectThisOption(employeeUL.querySelector("li"));
                            employeeSelectDom.disabled = false;

                            const editConfig = window.__employeePaymentEdit || null;
                            if (editConfig && elem.value === editConfig.category) {
                                const selectedEmployeeOption = employeeUL.querySelector(`li[data-value="${editConfig.employee_id}"]`);
                                if (selectedEmployeeOption) {
                                    selectThisOption(selectedEmployeeOption);
                                }
                                const selectedMethodOption = methodSelectDom
                                    ?.closest(".selectParent")
                                    ?.querySelector(`ul li[data-value="${editConfig.method}"]`);
                                if (selectedMethodOption) {
                                    selectThisOption(selectedMethodOption);
                                }
                            }
                        }
                    },
                    error: function (xhr, status, error) {
                        console.error(error);
                    },
                });
            }
        };

        window.trackEmployeeState = function trackEmployeeState(elem) {
            if (elem.value !== "") {
                let selectedEmployee = JSON.parse(
                    elem.closest(".selectParent").querySelector("ul li.selected").dataset.option || "{}"
                );
                balanceDom.value = selectedEmployee.balance_formatted || formatNumbersWithDigits(selectedEmployee.balance || 0, 1, 1);
                dateSelectDom.disabled = false;
                dateSelectDom.min = selectedEmployee.joining_date;
                methodSelectDom.disabled = false;
                amountInpDom.disabled = false;
            } else {
                balanceDom.value = "";
                dateSelectDom.disabled = true;
                methodSelectDom.disabled = true;
                amountInpDom.disabled = true;
            }
        };
    }

    window.initEmployeePaymentsCreate = initEmployeePaymentsCreate;

    function boot() {
        initEmployeePaymentsCreate();
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", boot);
    } else {
        boot();
    }
})();
