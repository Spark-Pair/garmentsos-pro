(() => {
    function initArticlesIndex() {
        const config = window.__articlesIndex || {};
        window.currentUserRole = config.currentUserRole || "";
        window.authLayout = config.authLayout || "table";

        window.createRow = function createRow(data) {
            return `
            <div id="${data.id}" oncontextmenu='${htmlAttr(data.oncontextmenu || "")}' onclick='${htmlAttr(data.onclick || "")}'
                class="item row relative group grid text- grid-cols-6 border-b border-[var(--h-bg-color)] items-center py-2 cursor-pointer hover:bg-[var(--h-secondary-bg-color)] transition-all fade-in ease-in-out"
                data-json='${jsonAttr(data)}'>

                <span class="text-center">${data.name}</span>
                <span class="text-center">${data.details["Category"]}</span>
                <span class="text-center">${data.details["Season"]}</span>
                <span class="text-center">${data.details["Size"]}</span>
                <span class="text-center">${formatMoney(data.sales_rate)}</span>
                <span class="text-center">${data.processed_by}</span>
            </div>`;
        };

        window.generateContextMenu = function generateContextMenu(e) {
            e.preventDefault();
            const item = e.target.closest(".item");
            const data = JSON.parse(item.dataset.json);

            const contextMenuData = {
                item: item,
                data: data,
                x: e.pageX,
                y: e.pageY,
                actions: [
                    {
                        id: "Update Image",
                        text: "Update Image",
                        onclick: `generateUpdateImageModal(${JSON.stringify(data)})`,
                    },
                    { id: "edit", text: "Edit" },
                ],
            };

            if (data.sales_rate == 0) {
                contextMenuData.actions.push({
                    id: "add-rates",
                    text: "Add Rates",
                    onclick: `generateAddRatesModal(${JSON.stringify(data)})`,
                });
            }

            if (config.currentUserRole === "developer") {
                contextMenuData.actions.push({
                    id: "delete",
                    text: "Delete",
                    onclick: `submitDeveloperArticleDelete(${data.id})`,
                });
            }

            createContextMenu(contextMenuData);
        };

        window.generateModal = function generateModal(item) {
            const data = JSON.parse(item.dataset.json);
            const tableBody = data.rates_array.map((rateItem, index) => {
                return [
                    { data: index + 1, class: "w-1/5" },
                    { data: rateItem.title, class: "grow ml-5" },
                    { data: rateItem.rate, class: "w-1/4" },
                ];
            });

            const modalData = {
                id: "modalForm",
                method: "POST",
                image: data.image,
                name: data.name,
                status: data.status,
                class: "p-5 max-w-5xl h-[27rem]",
                details: {
                    Category: data.details["Category"],
                    Season: data.details["Season"],
                    Size: data.details["Size"],
                    "Sales Rate": formatMoney(data.sales_rate),
                    hr: "",
                    "Fabric Type": data.fabric_type,
                    "Quantity-Pcs.": data.quantity,
                    "Current Stock-Pcs.": data.current_stock,
                    "Ready Date": formatDate(data.ready_date),
                },
                table: {
                    name: "Rates",
                    headers: [
                        { label: "#", class: "w-1/5" },
                        { label: "Title", class: "grow ml-5" },
                        { label: "Rate", class: "w-1/4" },
                    ],
                    body: tableBody,
                },
                bottomActions: [
                    {
                        id: "update-image",
                        text: "Update Image",
                        onclick: `generateUpdateImageModal(${JSON.stringify(data)})`,
                    },
                ],
            };

            if (data.ordered_quantity == 0 || config.currentUserRole === "developer") {
                modalData.bottomActions.push({
                    id: "edit",
                    text: "Edit",
                    dataId: data.id,
                });
            }

            if (data.sales_rate == 0) {
                modalData.bottomActions.push({
                    id: "add-rates",
                    text: "Add Rates",
                    onclick: `generateAddRatesModal(${JSON.stringify(data)})`,
                });
            }

            createModal(modalData);
        };

        let ratesArray = [];

        window.enableDisableBtn = function enableDisableBtn(elem) {
            const formDom = elem.closest("form");
            const btnDom = formDom.querySelector("#addRate");
            const titleInpDom = formDom.querySelector("#title");
            const rateInpDom = formDom.querySelector("#rate");

            if (titleInpDom.value != "" && rateInpDom.value != "") {
                btnDom.disabled = false;
            } else {
                btnDom.disabled = true;
            }
        };

        window.trackRateState = function trackRateState(elem) {
            enableDisableBtn(elem);

            if (elem.dataset.listenerAdded === "true") return;
            elem.dataset.listenerAdded = "true";

            const formDom = elem.closest("form");
            const addBtn = formDom.querySelector("#addRate");

            elem.addEventListener("keydown", function (e) {
                if (e.key === "Enter") {
                    e.preventDefault();
                    e.stopPropagation();
                    addRate(addBtn);
                }
            });
        };

        window.deleteRate = function deleteRate(elem) {
            const formDom = elem.closest("form");
            const titleInpDom = formDom.querySelector("#title");
            titleInpDom.focus();

            const title = elem.parentElement.previousElementSibling.previousElementSibling.innerText;
            ratesArray = ratesArray.filter(rate => rate.title !== title);
            renderRateList(elem.closest("#table-body"));
        };

        function renderRateList(tableBody) {
            if (ratesArray.length > 0) {
                tableBody.innerHTML = "";
                ratesArray.forEach((rate, index) => {
                    tableBody.innerHTML += `
                        <div class="flex justify-between items-center border-t border-gray-600 py-2 px-4">
                            <div class="w-1/5">${index + 1}</div>
                            <div class="grow ml-5">${rate.title}</div>
                            <div class="w-1/4">${formatNumbersWithDigits(rate.rate, 2, 2)}</div>
                            <div class="w-[10%] text-center">
                                <button onclick="deleteRate(this)" type="button" class="text-[var(--danger-color)] text-xs px-2 py-1 rounded-lg hover:text-[var(--h-danger-color)] transition-all duration-300 ease-in-out cursor-pointer">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    `;
                });
            } else {
                tableBody.innerHTML = `
                    <div class="flex justify-between items-center border-t border-gray-600 py-2 px-4">
                        <div class="grow text-center text-[var(--border-error)]">No Rates yet.</div>
                    </div>
                `;
            }
            renderCalcBottom(tableBody.closest("form").querySelector("#calc-bottom"));
            const formDom = tableBody.closest("form");
            const ratesArrayInpDom = formDom.querySelector("input[name=rates_array]");
            ratesArrayInpDom.value = JSON.stringify(ratesArray);
        }

        function renderCalcBottom(calcBottomElem) {
            const totalInpDom = calcBottomElem.querySelector("#total");
            const salesRateInpDom = calcBottomElem.querySelector("#sales_rate");

            totalInpDom.value = ratesArray
                .reduce((sum, item) => sum + (parseFloat(item.rate) || 0), 0)
                .toFixed(2);
            salesRateInpDom.value = ratesArray
                .reduce((sum, item) => sum + (parseFloat(item.rate) || 0), 0)
                .toFixed(2);
        }

        window.addRate = function addRate(elem) {
            const rateObject = {};
            const formDom = elem.closest("form");
            const titleInpDom = formDom.querySelector("#title");
            const rateInpDom = formDom.querySelector("#rate");
            const tableBodyDom = formDom.querySelector("#table-body");
            rateObject.title = titleInpDom.value;
            rateObject.rate = rateInpDom.value;
            ratesArray.push(rateObject);
            titleInpDom.value = "";
            rateInpDom.value = "";
            titleInpDom.focus();
            renderRateList(tableBodyDom);
        };

        window.generateAddRatesModal = function generateAddRatesModal(item) {
            const modalData = {
                id: "addRatesModalForm",
                method: "POST",
                action: config.addRateUrl,
                class: "max-w-3xl h-[37rem]",
                name: "Add Rates",
                fields: [
                    {
                        category: "input",
                        value:
                            item.name +
                            " | " +
                            item.details.Category +
                            " | " +
                            item.details.Season +
                            " | " +
                            item.details.Size,
                        full: true,
                        disabled: true,
                    },
                    {
                        category: "hr",
                    },
                    {
                        category: "input",
                        type: "hidden",
                        name: "article_id",
                        value: item.id,
                    },
                    {
                        category: "input",
                        type: "hidden",
                        name: "rates_array",
                        value: "[]",
                    },
                    {
                        category: "input",
                        label: "Title",
                        id: "title",
                        placeholder: "Enter Title",
                        oninput: "enableDisableBtn(this)",
                        grow: true,
                        focus: true,
                    },
                    {
                        category: "input",
                        label: "Rate",
                        id: "rate",
                        type: "number",
                        placeholder: "Enter Rate",
                        oninput: "trackRateState(this)",
                        btnId: "addRate",
                        onclick: "addRate(this)",
                    },
                ],
                fieldsGridCount: "2",
                table: {
                    name: "Rates",
                    headers: [
                        { label: "#", class: "w-1/5" },
                        { label: "Title", class: "grow ml-5" },
                        { label: "Rate", class: "w-1/4" },
                        { label: "Action", class: "w-[10%]" },
                    ],
                    body: [],
                    scrollable: true,
                },
                calcBottom: [
                    { label: "Total - Rs.", name: "total", value: "0.0", disabled: true },
                    { label: "Sales Rate - Rs.", name: "sales_rate", value: "0.0" },
                    { label: "Pcs / Packet", name: "pcs_per_packet", value: "0" },
                ],
                bottomActions: [{ id: "add", text: "Add", type: "submit" }],
            };

            createModal(modalData);
        };

        window.generateUpdateImageModal = function generateUpdateImageModal(item) {
            const modalData = {
                id: "updateImageModalForm",
                method: "POST",
                action: config.updateImageUrl,
                class: "h-auto",
                name: "Update Image",
                fields: [
                    {
                        category: "input",
                        value:
                            item.name +
                            " | " +
                            item.details.Category +
                            " | " +
                            item.details.Season +
                            " | " +
                            item.details.Size,
                        full: true,
                        disabled: true,
                    },
                    {
                        category: "input",
                        type: "hidden",
                        name: "article_id",
                        value: item.id,
                    },
                ],
                fieldsGridCount: "2",
                imagePicker: {
                    id: "image_upload",
                    name: "image_upload",
                    placeholder:
                        item.image == "no_image_icon.png" ? "images/no_image_icon.png" : `${item.image}`,
                    uploadText: "Upload article image",
                },
                bottomActions: [{ id: "add", text: "Add", type: "submit" }],
            };

            createModal(modalData);
        };

        window.submitDeveloperArticleDelete = function submitDeveloperArticleDelete(articleId) {
            const token = document.querySelector('meta[name="csrf-token"]')?.content || "";
            const form = document.createElement("form");
            form.method = "POST";
            form.action = `/articles/${articleId}`;
            form.className = "hidden";
            form.innerHTML = `
                <input type="hidden" name="_token" value="${token}">
                <input type="hidden" name="_method" value="DELETE">
            `;
            document.body.appendChild(form);
            form.submit();
        };
    }

    window.initArticlesIndex = initArticlesIndex;

    function boot() {
        if (window.__articlesIndex) {
            initArticlesIndex();
        }
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", boot);
    } else {
        boot();
    }
})();
