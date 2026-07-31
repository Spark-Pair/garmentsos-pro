(() => {
    function initProductionsAdd() {
        const config = window.__productionsAdd || {};
        const productionType = config.productionType || "issue";
        const csrfToken = config.csrfToken || "";
        const templates = config.templates || {};
        const unitsOptionsHtml = templates.unitsSelect || config.unitsOptionsHtml || "";
        const todayDate = config.todayDate || "";
        const articles = config.articles || [];
        const workOptions = config.workOptions || {};
        const workerOptions = config.workerOptions || {};
        const partsByCategorySeason = config.partsByCategorySeason || {};
        const units = config.units || [];
        const rates = config.rates || [];
        const tickets = config.tickets || [];
        const inventoryItems = config.inventoryItems || [];
        const printTicket = config.printTicket || null;
        if (config.companyData) {
            window.companyData = config.companyData;
        }

        function renderTemplate(template, replacements) {
            if (!template) return "";
            let output = template;
            Object.entries(replacements || {}).forEach(([key, value]) => {
                output = output.split(key).join(value ?? "");
            });
            return output;
        }

        let btnTypeGlobal = "issue";

        function moveHighlight(btn, btnType) {
            const highlight = document.getElementById("highlight");
            if (!highlight || !btn || !btn.parentElement) return;

            const rect = btn.getBoundingClientRect();
            const parentRect = btn.parentElement.getBoundingClientRect();

            highlight.style.width = `${rect.width}px`;
            highlight.style.left = `${rect.left - parentRect.left - 3}px`;

            btnTypeGlobal = btnType;
        }

        window.setProductionType = function setProductionType(btn, btnType) {
            if (btnTypeGlobal === btnType) {
                return;
            }

            doHide = true;

            $.ajax({
                url: "/set-production-type",
                type: "POST",
                data: {
                    _token: csrfToken,
                    production_type: btnType,
                },
                success: function () {
                    location.reload();
                },
                error: function () {
                    appAlert("Failed to update production type.");
                    $(btn).prop("disabled", false);
                },
            });

            moveHighlight(btn, btnType);
        };

        const initialBtn =
            productionType === "issue"
                ? document.querySelector("#issueBtn")
                : document.querySelector("#receiveBtn");
        moveHighlight(initialBtn, productionType === "issue" ? "issue" : "receive");

        if (printTicket && typeof window.showProductionTicket === "function") {
            setTimeout(() => window.showProductionTicket(printTicket, true), 300);
        }

        if (productionType === "issue") {
            let cardData = [];
            let ArticleModalData = {};
            let allWorks = Object.entries(workOptions);
            let allWorkers = Object.values(workerOptions);
            let allParts = Object.entries(partsByCategorySeason);
            let Units = units;
            let allRates = rates;
            if (config.isInventoryEnabled) {
                const inventoryOptions = inventoryItems.reduce((options, item) => {
                    options[item.id] = {
                        text: `${item.name}${item.stock_quantity !== undefined ? ` | ${formatNumbersWithDigits(item.stock_quantity)}` : ""} ${item.unit || ""}`.trim(),
                        data_option: item,
                    };
                    return options;
                }, {});
            }
            let materialModalData = {};
            let materialsArray = [];
            let selectedPartsArray = [];
            let tagCardData = [];
            const articleSelectInputDOM = document.getElementById("article");
            const articleIdInputDOM = document.getElementById("article_id");

            let tags = [];
            let selectedTagsArray = [];

            if (!articleSelectInputDOM || !articleIdInputDOM) return;

            articleSelectInputDOM.addEventListener("keydown", (e) => {
                e.preventDefault();
            });

            articleSelectInputDOM.addEventListener("click", () => {
                generateArticlesModal();
            });

            function generateArticlesModal() {
                const articlesIssuedOnCMT = articles.filter((a) =>
                    a.production.some((p) => p.work.title === "CMT")
                );
                const filteredArticles = articles.filter((a) => !articlesIssuedOnCMT.includes(a));

                cardData =
                    articles.length > 0
                        ? filteredArticles.map((item) => {
                            return {
                                id: item.id,
                                name: item.article_no,
                                image:
                                    item.image === "no_image_icon.png"
                                        ? "/images/no_image_icon.png"
                                        : `/storage/uploads/images/${item.image}`,
                                details: {
                                    Category: item.category,
                                    Season: item.season,
                                    Size: item.size,
                                },
                                data: item,
                                onclick: "selectThisArticle(this)",
                            };
                        })
                        : [];

                ArticleModalData = {
                    id: "modalForm",
                    basicSearch: true,
                    onBasicSearch: "basicSearch(this.value)",
                    cards: { name: "Articles", count: 3, data: cardData },
                };

                createModal(ArticleModalData);
            }

            window.basicSearch = function basicSearch(searchValue) {
                ArticleModalData.cards.data = cardData.filter((item) =>
                    item.name.toLowerCase().includes(searchValue.toLowerCase())
                );
                renderCardsInModal(ArticleModalData);
            };

            let selectedArticle = null;

            window.selectThisArticle = function selectThisArticle(articleElem) {
                selectedArticle = JSON.parse(articleElem.getAttribute("data-json")).data;

                articleIdInputDOM.value = selectedArticle.id;
                let value = `${selectedArticle.article_no} | ${selectedArticle.season} | ${selectedArticle.size} | ${selectedArticle.category} | ${formatNumbersDigitLess(
                    selectedArticle.quantity
                )} (pcs) | Rs. ${formatNumbersWithDigits(selectedArticle.sales_rate, 1, 1)}`;
                articleSelectInputDOM.value = value;

                closeModal("modalForm");

                document.querySelector('input[name="work_name"]').disabled = false;

                const ul = document.querySelector('ul[data-for="work"]');

                ul.innerHTML = `
                    <li data-for="work" data-value="" onmousedown="selectThisOption(this)"
                        class="py-2 px-3 cursor-pointer rounded-lg hover:bg-[var(--h-bg-color)] selected">
                        -- Select Work --
                    </li>
                `;

                let afterCutting = [
                    "Singer",
                    "Print | E",
                    "Embroidery | E",
                    "Dhaap",
                    "Wash",
                    "O/F Look",
                    "Kaj Button",
                    "Bartake",
                    "Stitching | E",
                ];
                const categorySeasonKey = `${selectedArticle.category}_${selectedArticle.season}`;
                const partsRecievedFromCutting = selectedArticle.production
                    .filter((p) => p.work.title === "Cutting")
                    .flatMap((p) => p.parts);
                const partsRecievedFromSinger = [
                    ...new Set(
                        selectedArticle.production
                            .filter((p) => p.work.title === "Singer")
                            .flatMap((p) => p.parts ?? [])
                    ),
                ];
                const allIssuedParts = selectedArticle.production
                    .filter((p) => p.receive_date === null)
                    .flatMap((p) => p.parts);
                const existingWorks = [...new Set(selectedArticle.production.flatMap((p) => p.work.title))];
                const parts = allParts
                    .filter(([key]) => key === categorySeasonKey)
                    .flatMap(([_, value]) => value);

                allWorks.forEach(([workKey, workValue]) => {
                    const workTitle = workValue.text;
                    let shouldShowWork = false;

                    if (existingWorks.length === 1 && !afterCutting.includes("CMT | E")) {
                        afterCutting.push("CMT | E");
                    } else if (existingWorks.length !== 1 && afterCutting.includes("CMT | E")) {
                        afterCutting = afterCutting.filter((w) => w !== "CMT | E");
                    }

                    const currentWorkParts = selectedArticle.production
                        .filter((p) => p.work.title === workTitle)
                        .flatMap((p) => p.parts);

                    if (afterCutting.includes(workTitle)) {
                        if (currentWorkParts.length < partsRecievedFromCutting.length) {
                            shouldShowWork = true;
                        }
                    }

                    if (shouldShowWork) {
                        ul.innerHTML += `
                            <li data-for="work" data-value="${workKey}"
                                onmousedown="selectThisOption(this)"
                                class="py-2 px-3 cursor-pointer rounded-lg hover:bg-[var(--h-bg-color)]">
                                ${workTitle.split("|")[0].trim()}
                            </li>
                        `;
                    }
                });

                if (ul.children.length == 1) {
                    ul.innerHTML = ``;
                    document.querySelector('input[name="work_name"]').value = "";
                    document.querySelector('input[name="work_name"]').disabled = true;
                }

                const selectedLi = ul.querySelector("li.selected");
                if (selectedLi) {
                    selectedLi.dispatchEvent(new MouseEvent("mousedown", { bubbles: true }));
                }
            };

            window.trackWorkState = function trackWorkState(elem) {
                if (elem.value !== "") {
                    let correctWorkers = allWorkers.filter(
                        (worker) => worker.data_option.type.id == elem.value
                    );

                    if (correctWorkers.length > 0) {
                        document.querySelector('input[name="worker_name"]').disabled = false;
                        const ul = document.querySelector('ul[data-for="worker"]');

                        ul.innerHTML = `
                            <li data-for="worker" data-value="" onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg hover:bg-[var(--h-bg-color)] selected">-- Select Worker --</li>
                        `;

                        correctWorkers.forEach((worker) => {
                            ul.innerHTML += `
                                <li data-for="worker" data-value="${worker.data_option.id}" data-option='${jsonAttr(worker.data_option)}' onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg hover:bg-[var(--h-bg-color)]">${worker.text}</li>
                            `;
                        });

                        const selectedLi = ul.querySelector("li.selected");
                        if (selectedLi) {
                            selectedLi.dispatchEvent(new MouseEvent("mousedown", { bubbles: true }));
                        }
                    } else {
                        document.querySelector('input[name="worker_name"]').disabled = true;
                        document.querySelector('input[name="worker_name"]').value = "";
                    }
                    generateSecondStep(
                        elem.closest(".selectParent").querySelector("li.selected").textContent.trim()
                    );

                    let filteredRates = allRates.filter(
                        (rate) =>
                            rate.type.id == elem.value &&
                            rate.categories.includes(selectedArticle.category) &&
                            rate.seasons.includes(selectedArticle.season) &&
                            rate.sizes.includes(selectedArticle.size)
                    );
                    let selectRateNameDom = document.querySelector('input[name="select_rate_name"]');

                    if (selectRateNameDom) {
                        if (filteredRates.length > 0) {
                            selectRateNameDom.value = "-- Select Rates --";
                            selectRateNameDom.disabled = false;
                            let ratesUL = document.querySelector('ul[data-for="select_rate"]');
                            ratesUL.innerHTML = `
                                <li data-for="select_rate" data-value="" onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg hover:bg-[var(--h-bg-color)] selected">-- Select Rates --</li>
                            `;

                            filteredRates.forEach((rate) => {
                                ratesUL.innerHTML += `
                                    <li data-for="select_rate" data-value="${rate.id}" onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg hover:bg-[var(--h-bg-color)]">${rate.title} | ${formatMoney(rate.rate)}</li>
                                `;
                            });

                            ratesUL.innerHTML += `
                                <li data-for="select_rate" data-value="0" onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg hover:bg-[var(--h-bg-color)]">Other</li>
                            `;
                        } else {
                            selectRateNameDom.value = "";
                            selectRateNameDom.disabled = true;
                        }
                    }
                }
            };

            window.trackWorkerState = function trackWorkerState(elem) {
                const selectParent = elem.closest(".selectParent");
                const selectedWorkerData = JSON.parse(
                    selectParent.querySelector("li.selected").dataset.option || "{}"
                );
                document.getElementById("balance").value = formatNumbersWithDigits(
                    selectedWorkerData?.balance || 0,
                    1
                );
                tags = selectedWorkerData.taags || [];
                elem.value !== "" && gotoStep(2);
                selectedTagsArray = [];
            };

            function generateMaterialsModal(animate = "animate") {
                let tableBody = [];

                tableBody = materialsArray.map((item, index) => {
                    return [
                        { data: index + 1, class: "w-[8%]" },
                        { data: item.title, class: "w-[28%]" },
                        { data: item.unit, class: "w-[18%]" },
                        { data: item.quantity, class: "w-[15%]" },
                        { data: item.inventory_item_id ? "Inventory" : "Manual", class: "w-[18%]" },
                        {
                            rawHTML: `
                            <div class="w-[10%] text-center">
                                <button onclick="deleteMaterial(this)" data-index="${index}" type="button" class="text-[var(--danger-color)] text-xs px-2 py-1 rounded-lg hover:text-[var(--h-danger-color)] transition-all duration-300 ease-in-out cursor-pointer">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        `,
                        },
                    ];
                });

                materialModalData = {
                    id: "addMaterialsModalForm",
                    class: "max-w-4xl h-[37rem]",
                    name: "Add Materials",
                    fields: [
                        ...(config.isInventoryEnabled
                            ? [{
                                category: "select",
                                label: "Inventory Item",
                                id: "inventory_item_id",
                                name: "inventory_item_id",
                                options: [inventoryOptions],
                                onchange: "trackInventoryMaterialState(this)",
                            }]
                            : []),

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
                            category: "explicitHtml",
                            html: unitsOptionsHtml,
                        },
                        {
                            category: "input",
                            label: "Quantity",
                            id: "quantity",
                            type: "number",
                            placeholder: "Enter Quantity",
                            oninput: "trackQuantityState(this)",
                            btnId: "addMaterial",
                            onclick: "addthis(this)",
                        },
                    ],
                    fieldsGridCount: config.isInventoryEnabled ? '4' : '3',
                    table: {
                        name: "Rates",
                        headers: [
                            { label: "#", class: "w-[8%]" },
                            { label: "Title", class: "w-[28%]" },
                            { label: "Unit", class: "w-[18%]" },
                            { label: "Quantity", class: "w-[15%]" },
                            { label: "Source", class: "w-[18%]" },
                            { label: "Action", class: "w-[10%]" },
                        ],
                        body: tableBody,
                        scrollable: true,
                    },
                };

                createModal(materialModalData, animate);
            }

            window.generateMaterialsModal = generateMaterialsModal;

            window.trackInventoryMaterialState = function trackInventoryMaterialState(elem) {
                const formDom = elem.closest("form");
                const option = elem.options[elem.selectedIndex];
                const item = JSON.parse(option?.dataset.option || "{}");
                const titleInpDom = formDom.querySelector("#title");
                const unitSelectDom = formDom.querySelector("#unit");

                if (item && item.id) {
                    titleInpDom.value = item.name || "";
                    unitSelectDom.value = item.unit || "";
                    titleInpDom.dataset.inventoryItemId = item.id;
                    titleInpDom.dataset.stockQuantity = item.stock_quantity ?? "";
                } else {
                    delete titleInpDom.dataset.inventoryItemId;
                    delete titleInpDom.dataset.stockQuantity;
                }

                window.enableDisableBtn(elem);
            };

            window.enableDisableBtn = function enableDisableBtn(elem) {
                const formDom = elem.closest("form");

                const btnDom = formDom.querySelector("#addMaterial");
                const titleInpDom = formDom.querySelector("#title");
                const unitSelectDom = formDom.querySelector("#unit");
                const quantityInpDom = formDom.querySelector("#quantity");
                const inventorySelectDom = formDom.querySelector("#inventory_item_id");

                if (
                    titleInpDom.value !== "" &&
                    unitSelectDom.value !== "" &&
                    quantityInpDom.value !== ""
                ) {
                    btnDom.disabled = false;
                } else {
                    btnDom.disabled = true;
                }
            };

            window.trackQuantityState = function trackQuantityState(elem) {
                window.enableDisableBtn(elem);

                if (elem.dataset.listenerAdded === "true") return;

                elem.dataset.listenerAdded = "true";

                const formDom = elem.closest("form");
                const addBtn = formDom.querySelector("#addMaterial");

                elem.addEventListener("keydown", function (e) {
                    if (e.key === "Enter") {
                        e.preventDefault();
                        e.stopPropagation();
                        addMaterial(addBtn);
                    }
                });
            };

            window.deleteMaterial = function deleteMaterial(elem) {
                const formDom = elem.closest("form");
                const titleInpDom = formDom.querySelector("#title");

                titleInpDom.focus();

                const index = parseInt(elem.dataset.index, 10);
                if (!Number.isNaN(index)) {
                    materialsArray.splice(index, 1);
                }

                renderMaterialList(elem.closest("#table-body"));
            };

            function renderMaterialList(tableBody) {
                if (materialsArray.length > 0) {
                    tableBody.innerHTML = "";
                    materialsArray.forEach((material, index) => {
                        tableBody.innerHTML += `
                            <div class="flex justify-between items-center border-t border-gray-600 py-2 px-4">
                                <div class="w-[8%]">${index + 1}</div>
                                <div class="w-[28%]">${material.title}</div>
                                <div class="w-[18%]">${material.unit}</div>
                                <div class="w-[15%]">${formatNumbersWithDigits(material.quantity)}</div>
                                <div class="w-[18%]">${material.inventory_item_id ? "Inventory" : "Manual"}</div>
                                <div class="w-[10%] text-center">
                                    <button onclick="deleteMaterial(this)" data-index="${index}" type="button" class="text-[var(--danger-color)] text-xs px-2 py-1 rounded-lg hover:text-[var(--h-danger-color)] transition-all duration-300 ease-in-out cursor-pointer">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        `;
                    });
                } else {
                    tableBody.innerHTML = `
                        <div class="flex justify-between items-center border-t border-gray-600 py-2 px-4">
                            <div class="grow text-center text-[var(--border-error)]">No Materials yet.</div>
                        </div>
                    `;
                }
            }

            window.addthis = function addthis(elem) {
                let materialObject = {};
                const formDom = elem.closest("form");
                const titleInpDom = formDom.querySelector("#title");
                const unitSelectDom = formDom.querySelector("#unit");
                const quantityInpDom = formDom.querySelector("#quantity");
                const tableBodyDom = formDom.querySelector("#table-body");
                materialObject.title = titleInpDom.value;
                materialObject.unit = unitSelectDom.value;
                materialObject.quantity = quantityInpDom.value;
                if (titleInpDom.dataset.inventoryItemId) {
                    materialObject.inventory_item_id = parseInt(titleInpDom.dataset.inventoryItemId);
                }
                materialsArray.push(materialObject);
                titleInpDom.value = "";
                unitSelectDom.value = "";
                quantityInpDom.value = "";
                delete titleInpDom.dataset.inventoryItemId;
                delete titleInpDom.dataset.stockQuantity;
                const inventorySelectDom = formDom.querySelector("#inventory_item_id");
                if (inventorySelectDom) {
                    inventorySelectDom.value = "";
                }
                titleInpDom.focus();
                document.getElementById("materials").value = `${materialsArray.length} Material${
                    materialsArray.length > 1 ? "s" : ""
                } Selected`;
                document.querySelector('input[name="materials"]').value = JSON.stringify(materialsArray);
                renderMaterialList(tableBodyDom);
            };

            window.generateQuantityModal = function generateQuantityModal(item) {
                let quantityModalData = {
                    id: "quantityModalForm",
                    name: "Enter Quantity",
                    class: "h-auto",
                    fields: [
                        {
                            category: "input",
                            label: "Unit",
                            value: item.unit,
                            disabled: true,
                        },
                        {
                            category: "input",
                            id: "tag",
                            name: "tag",
                            type: "hidden",
                            value: item.tag,
                        },
                        {
                            category: "input",
                            label: "Avalaible Quantity",
                            value: item.available_quantity,
                            disabled: true,
                        },
                        {
                            category: "explicitHtml",
                            html: templates.modals?.quantityInput || "",
                            focus: "quantity",
                        },
                    ],
                    fieldsGridCount: "1",
                    bottomActions: [{ id: "add", text: "Add", onclick: "selectWithQuantity(this)" }],
                };

                createModal(quantityModalData);

                document.querySelector('input[name="quantity"]').value = item.selected_quantity || "";
                document.querySelector('input[name="quantity"]').dataset.validate = `max:${
                    item.available_quantity + (item.selected_quantity || 0)
                }`;
            };

            window.selectWithQuantity = function selectWithQuantity(elem) {
                const inputs = elem.closest("form").querySelectorAll("input:not([disabled])");
                let detail = {};

                inputs.forEach((input) => {
                    const name = input.getAttribute("name");
                    if (name != null && name != "_token") {
                        const value = input.value;

                        if (name == "quantity") {
                            detail[name] = parseInt(value);
                        } else {
                            detail[name] = value;
                        }
                    }
                });

                let existingTag = selectedTagsArray.find((tag) => tag.tag == detail.tag);

                if (detail.quantity > 0) {
                    existingTag ? (existingTag.quantity = detail.quantity) : selectedTagsArray.push(detail);
                } else if (existingTag) {
                    selectedTagsArray = selectedTagsArray.filter((tag) => tag.tag !== detail.tag);
                }
                tags.find((tag) => tag.tag === detail.tag).selected_quantity = detail.quantity;
                tags.find((tag) => tag.tag === detail.tag).available_quantity -=
                    tags.find((tag) => tag.tag === detail.tag).selected_quantity;
                document.querySelector('input[name="tags"]').value = JSON.stringify(selectedTagsArray);
                closeModal("quantityModalForm");
                closeModal("tagModalForm", "notAnimate");
                generateSelectTagModal("notAnimate");
                document.getElementById("tags").value =
                    selectedTagsArray.length > 0 ? `${selectedTagsArray.length} Selected` : "";
            };

            window.generateSelectTagModal = function generateSelectTagModal(animate = "animate") {
                const data = tags;
                tagCardData =
                    data.length > 0
                        ? data.map((item) => {
                            return {
                                id: item.tag,
                                name: item.tag,
                                details: {
                                    Supplier: item.supplier_name,
                                    "Available Quantity": item.available_quantity,
                                    "Selected Quantity": item.selected_quantity || 0,
                                },
                                data: item,
                                onclick: `generateQuantityModal(${JSON.stringify(item)})`,
                            };
                        })
                        : [];

                materialModalData = {
                    id: "tagModalForm",
                    basicSearch: true,
                    onBasicSearch: "searchProductionTags(this.value)",
                    cards: { name: "Tags", count: 3, data: tagCardData },
                };

                createModal(materialModalData, animate);
            };

            window.searchProductionTags = function searchProductionTags(searchValue) {
                const query = searchValue.toLowerCase();
                materialModalData.cards.data = tagCardData.filter((item) =>
                    [item.name, item.details?.Supplier]
                        .filter(Boolean)
                        .some((value) => String(value).toLowerCase().includes(query))
                );
                renderCardsInModal(materialModalData);
            };

            window.generateSecondStep = function generateSecondStep(work) {
                let secondStepHTML = "";
                const articleValue = `${selectedArticle.article_no} | ${selectedArticle.season} | ${selectedArticle.size} | ${selectedArticle.category} | ${formatNumbersDigitLess(
                    selectedArticle.quantity
                )} (pcs) | Rs. ${formatNumbersWithDigits(selectedArticle.sales_rate, 1, 1)}`;
                const articleHtml = renderTemplate(templates.issue?.article, {
                    __ARTICLE_VALUE__: articleValue,
                });
                const quantityHtml =
                    !selectedArticle.quantity > 0
                        ? templates.issue?.quantityEditable
                        : renderTemplate(templates.issue?.quantityDisabled, {
                              __ARTICLE_QTY__: selectedArticle.quantity,
                          });
                const partsHtml =
                    selectedArticle.category != "1_pc" ? templates.issue?.parts : "";

                if (
                    work == "Singer" ||
                    work == "Stitching" ||
                    work == "CMT" ||
                    work == "Kaj Button" ||
                    work == "Cropping" ||
                    work == "Packing"
                ) {
                    secondStepHTML =
                        articleHtml +
                        (templates.issue?.materials || "") +
                        quantityHtml +
                        (partsHtml || "") +
                        (templates.issue?.issueDate || "");
                } else if (
                    work == "Print" ||
                    work == "Embroidery" ||
                    work == "Dhaap" ||
                    work == "Wash" ||
                    work == "O/F Look" ||
                    work == "Bartake" ||
                    work == "Press"
                ) {
                    secondStepHTML =
                        articleHtml +
                        quantityHtml +
                        (partsHtml || "") +
                        (templates.issue?.issueDate || "");
                }
                document.getElementById("secondStep").innerHTML = secondStepHTML;

                let partKey = selectedArticle.category + "_" + selectedArticle.season;
                let partsClutter = "";
                const checkboxes_container = document.querySelector(".checkboxes_container");

                const partsReceivedFromCutting = selectedArticle.production
                    .filter((p) => p.work.title === "Cutting")
                    .flatMap((p) => p.parts);

                const allIssuedParts = selectedArticle.production
                    .filter((p) => p.receive_date === null)
                    .flatMap((p) => p.parts);

                const currentWorkParts = selectedArticle.production
                    .filter((p) => p.work.title === work)
                    .flatMap((p) => p.parts);

                const availableParts = partsReceivedFromCutting.filter(
                    (p) => !currentWorkParts.includes(p)
                );

                availableParts.forEach((part) => {
                    partsClutter += `
                        <label class="flex items-center gap-2 cursor-pointer rounded-md border border-[var(--h-bg-color)] bg-[var(--h-bg-color)] px-2 py-[0.1875rem] shadow-sm transition hover:shadow-md hover:border-primary">
                            <input
                                type="checkbox"
                                onchange="toggleThisCheckbox(this)"
                                data-checkbox="${part}"
                                class="checkbox appearance-none bg-[var(--secondary-bg-color)] w-4 h-4 border border-gray-600 rounded-sm checked:bg-[var(--primary-color)] transition"
                            />
                            <span class="text-sm font-medium text-[var(--secondary-text)]">
                                ${part}
                            </span>
                        </label>
                    `;
                });

                if (checkboxes_container) {
                    checkboxes_container.innerHTML = partsClutter;
                }
            };

            window.toggleThisCheckbox = function toggleThisCheckbox(checkbox) {
                const dbPartsInput = document.getElementById("dbParts");

                const checkboxValue = checkbox.dataset.checkbox;
                if (checkbox.checked) {
                    if (!selectedPartsArray.includes(checkboxValue)) {
                        selectedPartsArray.push(checkboxValue);
                    }
                } else {
                    selectedPartsArray = selectedPartsArray.filter((part) => part !== checkboxValue);
                }

                dbPartsInput.value = JSON.stringify(selectedPartsArray);
            };

            window.validateForNextStep = function validateForNextStep() {
                return true;
            };
        } else {
            let cardData = [];
            let ArticleModalData = {};
            let allWorks = Object.entries(workOptions);
            let allWorkers = Object.values(workerOptions);
            let allParts = Object.entries(partsByCategorySeason);
            let allRates = rates;
            let materialModalData = {};
            let tagCardData = [];
            const articleSelectInputDOM = document.getElementById("article");
            const articleIdInputDOM = document.getElementById("article_id");

            let tags = [];
            let selectedTagsArray = [];

            if (!articleSelectInputDOM || !articleIdInputDOM) return;

            articleSelectInputDOM.addEventListener("keydown", (e) => {
                e.preventDefault();
            });

            articleSelectInputDOM.addEventListener("click", () => {
                generateArticlesModal();
            });

            function generateArticlesModal() {
                const filteredArticles = articles.filter((a) => {
                    const partKey = `${a.category}_${a.season}`;
                    const allPartsForArticle = allParts
                        .filter(([key]) => key === partKey)
                        .flatMap(([_, value]) => value);

                    const cuttingParts = a.production
                        .filter((p) => p.work.title === "Cutting")
                        .flatMap((p) => p.parts);

                    return cuttingParts.length !== allPartsForArticle.length;
                });

                cardData =
                    articles.length > 0
                        ? filteredArticles.map((item) => {
                            return {
                                id: item.id,
                                name: item.article_no,
                                image:
                                    item.image === "no_image_icon.png"
                                        ? "/images/no_image_icon.png"
                                        : `/storage/uploads/images/${item.image}`,
                                details: {
                                    Category: item.category,
                                    Season: item.season,
                                    Size: item.size,
                                },
                                data: item,
                                onclick: "selectThisArticle(this)",
                            };
                        })
                        : [];

                ArticleModalData = {
                    id: "modalForm",
                    basicSearch: true,
                    onBasicSearch: "basicSearch(this.value)",
                    cards: { name: "Articles", count: 3, data: cardData },
                };

                createModal(ArticleModalData);
                trackWorkState(document.getElementById("work"));
            }

            window.basicSearch = function basicSearch(searchValue) {
                ArticleModalData.cards.data = cardData.filter((item) =>
                    item.name.toLowerCase().includes(searchValue.toLowerCase())
                );
                renderCardsInModal(ArticleModalData);
            };

            let selectedArticle = null;

            window.selectThisArticle = function selectThisArticle(articleElem) {
                document.getElementById("secondStep").innerHTML = "";
                if (articleElem.dataset?.json) {
                    selectedArticle = JSON.parse(articleElem.getAttribute("data-json")).data;
                } else {
                    selectedArticle = articleElem;
                }

                articleIdInputDOM.value = selectedArticle.id;
                let value = `${selectedArticle.article_no} | ${selectedArticle.season} | ${selectedArticle.size} | ${selectedArticle.category} | ${formatNumbersDigitLess(
                    selectedArticle.quantity
                )} (pcs) | Rs. ${formatNumbersWithDigits(selectedArticle.sales_rate, 1, 1)}`;
                articleSelectInputDOM.value = value;

                if (articleElem.dataset?.json) {
                    closeModal("modalForm");
                    document.querySelector('input[name="work_name"]').disabled = false;
                } else {
                    articleSelectInputDOM.disabled = true;
                }

                const ul = document.querySelector('ul[data-for="work"]');

                ul.innerHTML = `
                    <li data-for="work" data-value="" onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg hover:bg-[var(--h-bg-color)] selected">-- Select Work --</li>
                `;

                allWorks.forEach(([workKey, workValue]) => {
                    let shouldShowWork = false;

                    const productionItems = selectedArticle.production.filter(
                        (p) => p.work.title === workValue.text
                    );

                    const cuttingNotStarted = selectedArticle.production.every(
                        (p) => p.work.title !== "Cutting"
                    );

                    const missingParts = () => {
                        if (productionItems.length === 0) {
                            return [];
                        }
                        const categorySeasonKey = `${selectedArticle.category}_${selectedArticle.season}`;
                        const parts = allParts
                            .filter(([key]) => key === categorySeasonKey)
                            .flatMap(([_, value]) => value);

                        const existingParts = productionItems
                            .flatMap((p) => p.parts)
                            .filter((p) => parts.includes(p));

                        return parts.filter((p) => !existingParts.includes(p));
                    };

                    shouldShowWork = !articleElem.dataset?.json
                        ? true
                        : workValue.text === "Cutting";

                    if (shouldShowWork) {
                        ul.innerHTML += `
                            <li data-for="work" data-value="${workKey}"
                                onmousedown="selectThisOption(this)"
                                class="py-2 px-3 cursor-pointer rounded-lg hover:bg-[var(--h-bg-color)]">
                                ${workValue.text}
                            </li>
                        `;
                    }
                });

                if (ul.children.length == 1) {
                    ul.innerHTML = ``;
                    document.querySelector('input[name="work_name"]').value = "";
                    document.querySelector('input[name="work_name"]').disabled = true;
                }

                const selectedLi = ul.querySelector("li.selected");
                if (selectedLi) {
                    selectedLi.dispatchEvent(new MouseEvent("mousedown", { bubbles: true }));
                }
            };

            window.trackWorkState = function trackWorkState(elem) {
                if (elem.value != "") {
                    let correctWorkers = allWorkers.filter(
                        (worker) => worker.data_option.type.id == elem.value
                    );

                    if (correctWorkers.length > 0) {
                        document.querySelector('input[name="worker_name"]').disabled = false;
                        const ul = document.querySelector('ul[data-for="worker"]');

                        ul.innerHTML = `
                            <li data-for="worker" data-value="" onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg hover:bg-[var(--h-bg-color)] selected">-- Select Worker --</li>
                        `;

                        correctWorkers.forEach((worker) => {
                            if (
                                selectedArticle.production.find(
                                    (p) =>
                                        p.work.id == elem.value &&
                                        p.receive_date == null &&
                                        p.worker_id == worker.data_option.id
                                )
                            ) {
                                ul.innerHTML += `
                                    <li data-for="worker" data-value="${worker.data_option.id}" data-option='${jsonAttr(worker.data_option)}' onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg hover:bg-[var(--h-bg-color)] selected">${worker.text}</li>
                                `;
                            } else {
                                if (worker.data_option.type.title == "Cutting") {
                                    ul.innerHTML += `
                                        <li data-for="worker" data-value="${worker.data_option.id}" data-option='${jsonAttr(worker.data_option)}' onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg hover:bg-[var(--h-bg-color)]">${worker.text}</li>
                                    `;
                                }
                            }
                        });

                        const selectedLi = ul.querySelector("li.selected");
                        if (selectedLi) {
                            selectedLi.dispatchEvent(new MouseEvent("mousedown", { bubbles: true }));
                        }
                    } else {
                        document.querySelector('input[name="worker_name"]').disabled = true;
                        document.querySelector('input[name="worker_name"]').value = "";
                    }
                    generateSecondStep(
                        elem.closest(".selectParent").querySelector("li.selected")?.textContent.trim()
                    );

                    let filteredRates = allRates.filter(
                        (rate) =>
                            rate.type.id == elem.value &&
                            rate.categories.includes(selectedArticle.category) &&
                            rate.seasons.includes(selectedArticle.season) &&
                            rate.sizes.includes(selectedArticle.size)
                    );
                    let selectRateNameDom = document.querySelector('input[name="select_rate_name"]');

                    if (selectRateNameDom) {
                        let ratesUL = document.querySelector('ul[data-for="select_rate"]');
                        selectRateNameDom.value = "-- Select Rates --";
                        selectRateNameDom.disabled = false;
                        ratesUL.innerHTML = `
                            <li data-for="select_rate" data-value="" onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg hover:bg-[var(--h-bg-color)] selected">-- Select Rates --</li>
                        `;
                        if (filteredRates.length > 0) {
                            filteredRates.forEach((rate) => {
                                ratesUL.innerHTML += `
                                    <li data-for="select_rate" data-value="${rate.id}" onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg hover:bg-[var(--h-bg-color)]">${rate.title} | ${formatMoney(rate.rate)}</li>
                                `;
                            });
                        }
                        ratesUL.innerHTML += `
                            <li data-for="select_rate" data-value="0" onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg hover:bg-[var(--h-bg-color)]">Other</li>
                        `;
                    }

                    if (document.querySelector('input[data-for="ticket"]').value != "") {
                        document.querySelector('input[name="worker_name"]').disabled = true;
                    }
                }
            };

            window.trackWorkerState = function trackWorkerState(elem) {
                const selectParent = elem.closest(".selectParent");
                const selectedWorkerData = JSON.parse(
                    selectParent.querySelector("li.selected").dataset.option || "{}"
                );
                document.getElementById("balance").value = formatNumbersWithDigits(
                    selectedWorkerData?.balance || 0,
                    1,
                    1
                );
                tags = selectedWorkerData.taags || [];
                elem.value !== "" && gotoStep(2);
                selectedTagsArray = [];
            };

            window.generateSelectTagModal = function generateSelectTagModal(animate = "animate") {
                const data = tags;
                tagCardData =
                    data.length > 0
                        ? data.map((item) => {
                            return {
                                id: item.tag,
                                name: item.tag,
                                details: {
                                    Supplier: item.supplier_name,
                                    "Available Quantity": item.available_quantity,
                                    "Selected Quantity": item.selected_quantity || 0,
                                },
                                data: item,
                                onclick: `generateQuantityModal(${JSON.stringify(item)})`,
                            };
                        })
                        : [];

                materialModalData = {
                    id: "tagModalForm",
                    basicSearch: true,
                    onBasicSearch: "searchProductionTags(this.value)",
                    cards: { name: "Tags", count: 3, data: tagCardData },
                };

                createModal(materialModalData, animate);
            };

            window.searchProductionTags = function searchProductionTags(searchValue) {
                const query = searchValue.toLowerCase();
                materialModalData.cards.data = tagCardData.filter((item) =>
                    [item.name, item.details?.Supplier]
                        .filter(Boolean)
                        .some((value) => String(value).toLowerCase().includes(query))
                );
                renderCardsInModal(materialModalData);
            };

            window.generateQuantityModal = function generateQuantityModal(item) {
                
                let quantityModalData = {
                    id: "quantityModalForm",
                    name: "Enter Quantity",
                    class: "h-auto",
                    fields: [
                        {
                            category: "input",
                            label: "Unit",
                            value: item.unit,
                            disabled: true,
                        },
                        {
                            category: "input",
                            id: "tag",
                            name: "tag",
                            type: "hidden",
                            value: item.tag,
                        },
                        {
                            category: "input",
                            label: "Avalaible Quantity",
                            value: item.available_quantity,
                            disabled: true,
                        },
                        {
                            category: "explicitHtml",
                            html: templates.modals?.quantityInput || "",
                            focus: "quantity",
                        },
                    ],
                    fieldsGridCount: "1",
                    bottomActions: [{ id: "add", text: "Add", onclick: "selectWithQuantity(this)" }],
                };

                createModal(quantityModalData);

                document.querySelector('input[name="quantity"]').value = item.selected_quantity || "";
                document.querySelector('input[name="quantity"]').dataset.validate = `max:${
                    item.available_quantity + (item.selected_quantity || 0)
                }`;
            };

            window.selectWithQuantity = function selectWithQuantity(elem) {
                const inputs = elem.closest("form").querySelectorAll("input:not([disabled])");
                let detail = {};

                inputs.forEach((input) => {
                    const name = input.getAttribute("name");
                    if (name != null && name != "_token") {
                        const value = input.value;

                        if (name == "quantity") {
                            detail[name] = parseInt(value);
                        } else {
                            detail[name] = value;
                        }
                    }
                });

                let existingTag = selectedTagsArray.find((tag) => tag.tag == detail.tag);

                if (detail.quantity > 0) {
                    existingTag ? (existingTag.quantity = detail.quantity) : selectedTagsArray.push(detail);
                } else if (existingTag) {
                    selectedTagsArray = selectedTagsArray.filter((tag) => tag.tag !== detail.tag);
                }
                tags.find((tag) => tag.tag === detail.tag).selected_quantity = detail.quantity;
                tags.find((tag) => tag.tag === detail.tag).available_quantity -=
                    tags.find((tag) => tag.tag === detail.tag).selected_quantity;
                document.querySelector('input[name="tags"]').value = JSON.stringify(selectedTagsArray);
                closeModal("quantityModalForm");
                closeModal("tagModalForm", "notAnimate");
                generateSelectTagModal("notAnimate");
                document.getElementById("tags").value =
                    selectedTagsArray.length > 0 ? `${selectedTagsArray.length} Selected` : "";
            };

            window.generateSecondStep = function generateSecondStep(work) {
                let secondStepHTML = "";
                let minDate = new Date();

                if (
                    !new Date(
                        selectedArticle.production.find((p) => p.work.title == work && p.receive_date == null)
                            ?.issue_date
                    ) < minDate
                ) {
                    minDate = selectedArticle.production.find(
                        (p) => p.work.title == work && p.receive_date == null
                    )?.issue_date;
                } else {
                    minDate = minDate.setDate(minDate.getDate() - 15);
                }

                const articleValue = `${selectedArticle.article_no} | ${selectedArticle.season} | ${selectedArticle.size} | ${selectedArticle.category} | ${formatNumbersDigitLess(
                    selectedArticle.quantity
                )} (pcs) | Rs. ${formatNumbersWithDigits(selectedArticle.sales_rate, 1, 1)}`;
                const articleHtml = renderTemplate(templates.receive?.article, {
                    __ARTICLE_VALUE__: articleValue,
                });
                const quantityEditable = templates.issue?.quantityEditable;
                const quantityDisabled = renderTemplate(templates.issue?.quantityDisabled, {
                    __ARTICLE_QTY__: selectedArticle.quantity,
                });
                const partsHtml = templates.receive?.parts || "";

                if (work == "Cutting") {
                    secondStepHTML =
                        articleHtml +
                        (templates.receive?.tags || "") +
                        (selectedArticle.quantity ? quantityDisabled : quantityEditable) +
                        (templates.receive?.selectRate || "") +
                        (templates.receive?.titleContainer || "") +
                        (templates.receive?.rateReadonly || "") +
                        (templates.receive?.amountReadonly || "") +
                        (templates.receive?.receiveDateMax || "") +
                        partsHtml;
                } else {
                    secondStepHTML =
                        articleHtml +
                        quantityDisabled +
                        (templates.receive?.title || "") +
                        (templates.receive?.rateEditable || "") +
                        (templates.receive?.amountReadonly || "") +
                        renderTemplate(templates.receive?.receiveDateMin, {
                            __MIN_DATE__: minDate,
                        }) +
                        partsHtml;
                }
                document.getElementById("secondStep").innerHTML = secondStepHTML;
                let selectedTicket = JSON.parse(
                    document
                        .getElementById("ticket")
                        ?.closest(".selectParent")
                        .querySelector("li.selected")
                        ?.dataset.option || "{}"
                );
                const checkboxes_container = document.querySelector(".checkboxes_container");
                let parts = [];
                let partsClutter = "";

                if (selectedTicket && Object.keys(selectedTicket).length > 0) {
                    parts = selectedTicket.parts;
                } else {
                    const partKey = selectedArticle.category + "_" + selectedArticle.season;

                    const allparts = allParts
                        .filter(([key]) => key === partKey)
                        .flatMap(([_, value]) => value);

                    const existingParts = selectedArticle.production
                        .flatMap((p) => p.parts)
                        .filter((p) => allparts.includes(p));

                    if (work == "Cutting") {
                        parts = allparts.filter((p) => !existingParts.includes(p));
                    } else {
                        const showingParts = selectedArticle.production
                            .filter((p) => p.work.title == work)
                            .flatMap((p) => p.parts)
                            .filter((p) => allparts.includes(p));

                        const existingParts = selectedArticle.production
                            .filter((p) => p.work.title == work)
                            .filter((p) => p.receive_date !== null)
                            .flatMap((p) => p.parts)
                            .filter((p) => allparts.includes(p));

                        parts = showingParts.filter((p) => !existingParts.includes(p));
                    }
                }

                parts.forEach((part) => {
                    if (part) {
                        partsClutter += `
                            <label class="flex items-center gap-2 cursor-pointer rounded-md border border-[var(--h-bg-color)] bg-[var(--h-bg-color)] px-2 py-[0.1875rem] shadow-sm transition hover:shadow-md hover:border-primary">
                                <input
                                    type="checkbox"
                                    onchange="toggleThisCheckbox(this)"
                                    data-checkbox="${part}"
                                    class="checkbox appearance-none bg-[var(--secondary-bg-color)] w-4 h-4 border border-gray-600 rounded-sm checked:bg-[var(--primary-color)] transition"
                                />
                                <span class="text-sm font-medium text-[var(--secondary-text)] capitalize">
                                    ${part}
                                </span>
                            </label>
                        `;
                    }
                });

                if (checkboxes_container) {
                    checkboxes_container.innerHTML = partsClutter;
                }
            };

            window.trackSelectRateState = function trackSelectRateState(elem) {
                const rateInput = document.getElementById("rate");
                const titleInput = document.getElementById("title");
                const titleContainer = document.getElementById("titleContainer");

                if (elem.value !== "" && elem.value !== "0") {
                    titleContainer.classList.add("hidden");
                    rateInput.readOnly = true;
                    const selectedText = elem.closest(".selectParent").querySelector("li.selected").textContent;
                    rateInput.value = selectedText.split("|")[1].trim();
                    titleInput.value = selectedText.split("|")[0].trim();
                    calculateAmount();
                } else if (elem.value === "0") {
                    titleContainer.classList.remove("hidden");
                    titleInput.value = "";
                    rateInput.value = "";
                    rateInput.readOnly = false;
                } else {
                    titleInput.value = "";
                    rateInput.value = "";
                    titleContainer.classList.add("hidden");
                    rateInput.readOnly = true;
                }
            };

            window.calculateAmount = function calculateAmount() {
                validateInput(document.getElementById("article_quantity"));
                let quantity = parseFloat(document.getElementById("article_quantity").value);
                let rate = parseFloat(document.getElementById("rate").value);
                document.getElementById("amount").value = (rate * quantity).toFixed(2);
            };

            window.trackTicketState = function trackTicketState(elem) {
                if (elem.value != "") {
                    let selectedTicket = JSON.parse(elem.parentElement.querySelector("li.selected").dataset.option);
                    
                    selectThisArticle(selectedTicket.article);
                    selectThisOption(document.querySelector(`li[data-value="${selectedTicket.work_id}"]`));
                    selectThisOption(document.querySelector(`li[data-value="${selectedTicket.worker_id}"]`));
                }
            };

            window.validateForNextStep = function validateForNextStep() {
                return true;
            };

            let selectedPartsArray = [];

            window.toggleThisCheckbox = function toggleThisCheckbox(checkbox) {
                const dbPartsInput = document.getElementById("dbParts");

                const checkboxValue = checkbox.dataset.checkbox;
                if (checkbox.checked) {
                    if (!selectedPartsArray.includes(checkboxValue)) {
                        selectedPartsArray.push(checkboxValue);
                    }
                } else {
                    selectedPartsArray = selectedPartsArray.filter((part) => part !== checkboxValue);
                }

                dbPartsInput.value = JSON.stringify(selectedPartsArray);
            };
        }
    }

    window.initProductionsAdd = initProductionsAdd;

    function boot() {
        if (window.__productionsAdd) {
            initProductionsAdd();
        }
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", boot);
    } else {
        boot();
    }
})();
