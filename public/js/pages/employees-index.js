(() => {
    function initEmployeesIndex() {
        const config = window.__employeesIndex || {};
        window.currentUserRole = config.currentUserRole || "";
        window.authLayout = config.authLayout || "table";
        const updateStatusUrl = config.updateStatusUrl || "";
        const companyData = config.companyData || {};

        window.createRow = function createRow(data) {
            return `
            <div id="${data.id}" oncontextmenu='${htmlAttr(data.oncontextmenu || "")}' onclick='${htmlAttr(data.onclick || "")}'
                class="item row relative group grid text- grid-cols-6 border-b border-[var(--h-bg-color)] items-center py-2 cursor-pointer hover:bg-[var(--h-secondary-bg-color)] transition-all fade-in ease-in-out"
                data-json='${jsonAttr(data)}'>

                <span class="text-left pl-5 capitalize">${data.name}</span>
                <span class="text-left pl-5">${data.urdu_title}</span>
                <span class="text-left pl-5 capitalize">${data.details["Category"]}</span>
                <span class="text-center capitalize">${data.details["Type"]}</span>
                <span class="text-right">${data.details["Balance"]}</span>
                <span class="text-right pr-5 capitalize ${
                    data.status === "active"
                        ? "text-[var(--border-success)]"
                        : "text-[var(--border-error)]"
                }">
                    ${data.status}
                </span>
            </div>`;
        };

        window.generateContextMenu = function generateContextMenu(e) {
            e.preventDefault();
            let item = e.target.closest(".item");
            let data = JSON.parse(item.dataset.json);

            let contextMenuData = {
                item: item,
                data: data,
                x: e.pageX,
                y: e.pageY,
                action: updateStatusUrl,
                actions: [
                    { id: "edit", text: "Edit", dataId: data.id },
                    { id: "emp-form-in-modal", text: "Show Form", onclick: `showEmployeeForm(${JSON.stringify(data)})` },
                ],
            };

            if (isDeveloperUser()) {
                contextMenuData.actions.push({
                    id: "delete-employee",
                    text: "Delete",
                    onclick: `submitResourceDelete('/employees/${data.id}')`,
                });
            }

            createContextMenu(contextMenuData);
        };

        window.generateModal = function generateModal(item) {
            let data = JSON.parse(item.dataset.json);

            let modalData = {
                id: "modalForm",
                uId: data.id,
                status: data.status,
                method: "POST",
                action: updateStatusUrl,
                class: "",
                closeAction: "closeModal()",
                image: data.image,
                name: data.name,
                details: {
                    Category: data.details.Category,
                    Type: data.details.Type,
                    "Phone Number": data.phone_number,
                    "Joining Date": data.joining_date,
                    "C.N.I.C No.": data.cnic_no,
                    Balance: data.details.Balance,
                    ...(data.salary > 0 && { Salary: formatNumbersWithDigits(data.salary, 1, 1) }),
                },
                profile: true,
                bottomActions: [
                    { id: "edit-in-modal", text: "Edit", dataId: data.id },
                    { id: "emp-form-in-modal", text: "Show Form", onclick: `showEmployeeForm(${JSON.stringify(data)})` },
                ],
            };

            if (isDeveloperUser()) {
                modalData.bottomActions.push({
                    id: "delete-employee",
                    text: "Delete",
                    onclick: `submitResourceDelete('/employees/${data.id}')`,
                });
            }

            createModal(modalData);
        };

        window.showEmployeeForm = function showEmployeeForm(data) {
            let formFieldsData = [
                { label: "Name", text: data.name },
                { label: "Category", text: data.details.Category },
                { label: "Type", text: data.details.Type },
                { label: "Joining Date", text: data.joining_date },
                { label: "Phone Number", text: data.phone_number },
                { label: "C.N.I.C No.", text: data.cnic_no },
            ];
            let modalData = {
                id: "modalForm",
                preview: { type: "form", data: { formFields: formFieldsData }, document: "Employee Form", size: "A5" },
                bottomActions: [{ id: "print", text: "Print Form", onclick: "printForm(this)" }],
            };

            createModal(modalData);
        };

        window.printForm = function printForm(elem) {
            closeAllDropdowns();

            if (elem.parentElement.tagName.toLowerCase() === "li") {
                elem.parentElement.parentElement.querySelector("#show-details").click();
                document.getElementById("modalForm").parentElement.classList.add("hidden");
            }

            const preview = document.getElementById("preview-container");

            let oldIframe = document.getElementById("printIframe");
            if (oldIframe) {
                oldIframe.remove();
            }

            let printIframe = document.createElement("iframe");
            printIframe.id = "printIframe";
            printIframe.style.position = "absolute";
            printIframe.style.width = "0px";
            printIframe.style.height = "0px";
            printIframe.style.border = "none";
            printIframe.style.display = "none";

            document.body.appendChild(printIframe);

            let printDocument = printIframe.contentDocument || printIframe.contentWindow.document;
            printDocument.open();

            const headContent = document.head.innerHTML;

            printDocument.write(`
                <html>
                    <head>
                        <title>Print Employee Form</title>
                        ${headContent}
                        <style>
                            @page {
                                size: A5 portrait;
                                margin: 0;
                            }

                            body {
                                padding: 0.08in 0.25in 0.08in 0.25in;
                                margin: 0;
                                width: 148mm;
                                height: 210mm;
                            }

                            .preview-container .banner {
                                margin-top: 0;
                            }

                            .preview-container .footer {
                                margin-top: 0;
                            }
                        </style>
                    </head>
                    <body>
                        <div class="preview-container">${preview.innerHTML}</div>
                    </body>
                </html>
            `);

            printDocument.close();

            printIframe.onload = () => {
                printDocument.querySelectorAll(".preview").forEach((p) => p.classList.remove("py-6"));

                printDocument.querySelectorAll("#banner").forEach((p) => p.classList.remove("mt-8"));

                printDocument.querySelectorAll(".footer").forEach((p) => p.classList.remove("mb-4"));

                printIframe.contentWindow.onafterprint = () => {};

                setTimeout(() => {
                    printIframe.contentWindow.focus();
                    printIframe.contentWindow.print();
                }, 1000);

                document.getElementById("modalForm").parentElement.remove();
            };
        };
    }

    window.initEmployeesIndex = initEmployeesIndex;

    function boot() {
        if (window.__employeesIndex) {
            initEmployeesIndex();
        }
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", boot);
    } else {
        boot();
    }
})();
