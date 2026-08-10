(() => {
    function initProductionsAdd() {
        const config = window.__productionsAdd || {};
        const productionType = config.productionType || "issue";
        const csrfToken = config.csrfToken || "";
        const templates = config.templates || {};
        const currentUserName = config.currentUserName || "";
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
        const availabilityUrl = config.availabilityUrl || "";
        const workAvailabilityUrl = config.workAvailabilityUrl || "";
        if (config.companyData) {
            window.companyData = config.companyData;
        }

        let currentAvailableParts = [];
        let selectedPartQuantities = [];

        function setAvailableParts(parts) {
            currentAvailableParts = Array.isArray(parts) ? parts : [];
            selectedPartQuantities = currentAvailableParts.map((item) => ({
                part: item.part,
                quantity: Number(item.quantity || 0),
            }));
            syncSelectedPartInputs();
        }

        function syncSelectedPartInputs() {
            const dbPartsInput = document.getElementById("dbParts");
            const flowInput = document.getElementById("productionFlows");
            const quantityInput = document.getElementById("article_quantity");
            if (dbPartsInput) {
                dbPartsInput.value = JSON.stringify(selectedPartQuantities.map((item) => item.part));
            }
            if (flowInput) {
                flowInput.value = JSON.stringify(selectedPartQuantities);
            }
            if (quantityInput) {
                quantityInput.value = selectedPartQuantities.length
                    ? Math.max(...selectedPartQuantities.map((item) => Number(item.quantity || 0)))
                    : 0;
            }
        }

        function renderPartQuantityCheckboxes(container) {
            if (!container) return;

            const fieldGroup = container.closest(".form-group");
            if (fieldGroup) {
                fieldGroup.classList.add("md:col-span-2");
            }

            container.className = "checkboxes_container max-h-56 overflow-y-auto rounded-md border border-gray-600 bg-[var(--secondary-bg-color)]";

            if (!currentAvailableParts.length) {
                container.innerHTML = `<div class="px-3 py-3 text-center text-xs font-medium text-[var(--border-error)]">No available parts.</div>`;
                syncSelectedPartInputs();
                return;
            }

            container.innerHTML = `
                <div class="sticky top-0 z-10 grid grid-cols-[2.25rem_minmax(0,1fr)_7rem_6rem] gap-2 border-b border-gray-600 bg-[var(--h-bg-color)] px-3 py-2 text-[11px] font-semibold uppercase text-[var(--secondary-text)]">
                    <span></span>
                    <span>Part</span>
                    <span class="text-right">Quantity</span>
                    <span class="text-right">Available</span>
                </div>
                ${currentAvailableParts.map((item) => `
                    <label class="grid cursor-pointer grid-cols-[2.25rem_minmax(0,1fr)_7rem_6rem] items-center gap-2 border-b border-[color-mix(in_srgb,var(--h-bg-color)_70%,transparent)] px-3 py-2 last:border-b-0 hover:bg-[var(--h-bg-color)]">
                        <span class="flex justify-center">
                            <input type="checkbox"
                                checked
                                onchange="toggleThisCheckbox(this)"
                                data-checkbox="${item.part}"
                                data-max="${Number(item.quantity || 0)}"
                                class="checkbox size-4 appearance-none rounded-sm border border-gray-600 bg-[var(--secondary-bg-color)] transition checked:bg-[var(--primary-color)]" />
                        </span>
                        <span class="truncate text-sm font-medium capitalize text-[var(--text-color)]">${item.part}</span>
                        <input type="number"
                            min="0"
                            max="${Number(item.quantity || 0)}"
                            value="${Number(item.quantity || 0)}"
                            data-part-quantity="${item.part}"
                            oninput="trackPartQuantity(this)"
                            class="w-full rounded-md border border-gray-600 bg-[var(--h-bg-color)] px-2 py-1 text-right text-sm text-[var(--text-color)]" />
                        <span class="text-right text-sm font-medium text-[var(--secondary-text)]">${Number(item.quantity || 0)}</span>
                    </label>
                `).join("")}
            `;
            syncSelectedPartInputs();
        }

        async function loadAvailableParts({ articleId, workId, mode, ticket = "" }) {
            if (!availabilityUrl || !articleId) return [];

            const url = new URL(availabilityUrl, window.location.origin);
            url.searchParams.set("article_id", articleId);
            url.searchParams.set("mode", mode);
            if (workId) url.searchParams.set("work_id", workId);
            if (ticket) url.searchParams.set("ticket", ticket);

            const response = await fetch(url.toString(), {
                headers: {
                    "X-Requested-With": "XMLHttpRequest",
                    Accept: "application/json",
                },
            });
            if (!response.ok) return [];

            const payload = await response.json();
            return payload.parts || [];
        }

        async function loadAvailableWorkIds({ articleId, mode }) {
            if (!workAvailabilityUrl || !articleId) return [];

            const url = new URL(workAvailabilityUrl, window.location.origin);
            url.searchParams.set("article_id", articleId);
            url.searchParams.set("mode", mode);

            const response = await fetch(url.toString(), {
                headers: {
                    "X-Requested-With": "XMLHttpRequest",
                    Accept: "application/json",
                },
            });
            if (!response.ok) return [];

            const payload = await response.json();
            return Array.isArray(payload.work_ids) ? payload.work_ids.map((id) => String(id)) : [];
        }

        function selectedDbValue(id) {
            return document.querySelector(`input[data-for="${id}"]`)?.value || "";
        }

        function cleanWorkTitle(title) {
            return String(title || "").split("|")[0].trim().toLowerCase();
        }

        function workerMatchesWork(worker, workText) {
            const workerType = cleanWorkTitle(worker?.data_option?.type?.title);
            const work = cleanWorkTitle(workText);

            if (work === "stitching") {
                return ["stitching", "cmt", "cut to pack"].includes(workerType);
            }

            if (work === "cmt") {
                return workerType === "cmt";
            }

            return worker?.data_option?.type?.id == selectedDbValue("work");
        }

        window.trackPartQuantity = function trackPartQuantity(input) {
            const max = Number(input.max || 0);
            let quantity = Number(input.value || 0);
            if (quantity > max) {
                quantity = max;
                input.value = max;
            }

            const part = input.dataset.partQuantity;
            const checkbox = input.closest("label")?.querySelector(".checkbox");
            selectedPartQuantities = selectedPartQuantities.filter((item) => item.part !== part);

            if (checkbox?.checked && quantity > 0) {
                selectedPartQuantities.push({ part, quantity });
            }
            syncSelectedPartInputs();
            if (typeof calculateAmount === "function") {
                calculateAmount();
            }
        };

        window.trackSelectRateState = function trackSelectRateState(elem) {
            const rateInput = document.getElementById("rate");
            const titleInput = document.getElementById("title");
            const titleContainer = document.getElementById("titleContainer");
            if (!rateInput || !titleInput || !titleContainer) return;

            if (elem.value !== "" && elem.value !== "0") {
                titleContainer.classList.add("hidden");
                rateInput.readOnly = true;
                const selectedText = elem.closest(".selectParent").querySelector("li.selected").textContent;
                rateInput.value = selectedText.split("|")[1]?.trim() || "";
                titleInput.value = selectedText.split("|")[0]?.trim() || "";
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
            const quantityInput = document.getElementById("article_quantity");
            const rateInput = document.getElementById("rate");
            const amountInput = document.getElementById("amount");
            if (!quantityInput || !rateInput || !amountInput) return;

            if (typeof validateInput === "function") {
                validateInput(quantityInput);
            }
            const quantity = parseFloat(quantityInput.value || 0);
            const rate = parseFloat(rateInput.value || 0);
            amountInput.value = rate > 0 ? (rate * quantity).toFixed(2) : "";
        };

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
            setTimeout(() => window.showProductionTicket(printTicket, false), 300);
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

            function renderIssueWorkOptions() {
                const workInput = document.querySelector('input[name="work_name"]');
                const ul = document.querySelector('ul[data-for="work"]');
                if (!workInput || !ul) return;

                workInput.disabled = false;
                ul.innerHTML = `
                    <li data-for="work" data-value="" onmousedown="selectThisOption(this)"
                        class="py-2 px-3 cursor-pointer rounded-lg hover:bg-[var(--h-bg-color)] selected">
                        -- Select Work --
                    </li>
                `;

                allWorks.forEach(([workKey, workValue]) => {
                    const workTitle = workValue.text;
                    if (cleanWorkTitle(workTitle) === "cutting") {
                        return;
                    }

                    ul.innerHTML += `
                        <li data-for="work" data-value="${workKey}"
                            onmousedown="selectThisOption(this)"
                            class="py-2 px-3 cursor-pointer rounded-lg hover:bg-[var(--h-bg-color)]">
                            ${workTitle.split("|")[0].trim()}
                        </li>
                    `;
                });
            }

            renderIssueWorkOptions();

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
            let workLoadToken = 0;

            window.selectThisArticle = function selectThisArticle(articleElem) {
                const currentWorkLoadToken = ++workLoadToken;
                selectedArticle = JSON.parse(articleElem.getAttribute("data-json")).data;

                articleIdInputDOM.value = selectedArticle.id;
                let value = `${selectedArticle.article_no} | ${selectedArticle.season} | ${selectedArticle.size} | ${selectedArticle.category} | ${formatNumbersDigitLess(
                    selectedArticle.quantity
                )} (pcs) | Rs. ${formatNumbersWithDigits(selectedArticle.sales_rate, 1, 1)}`;
                articleSelectInputDOM.value = value;

                closeModal("modalForm");

                const ul = document.querySelector('ul[data-for="work"]');
                renderIssueWorkOptions();

                loadAvailableWorkIds({
                        articleId: selectedArticle.id,
                        mode: "issue",
                }).then((availableWorkIds) => {
                    if (currentWorkLoadToken !== workLoadToken) return;
                    ul.querySelectorAll('li[data-for="work"][data-value]:not([data-value=""])').forEach((li) => {
                        if (!availableWorkIds.includes(String(li.dataset.value))) {
                            li.remove();
                        }
                    });

                    if (ul.children.length === 1) {
                        ul.innerHTML = ``;
                        document.querySelector('input[name="work_name"]').value = "";
                        document.querySelector('input[name="work_name"]').disabled = true;
                    }
                });
            };

            window.trackWorkState = function trackWorkState(elem) {
                if (elem.value !== "") {
                    if (!selectedArticle) {
                        appAlert("Select article first.");
                        selectThisOption(elem.closest(".selectParent").querySelector('li[data-value=""]'));
                        return;
                    }

                    const selectedWorkText = elem.closest(".selectParent").querySelector("li.selected")?.textContent.trim();
                    let correctWorkers = allWorkers.filter((worker) => workerMatchesWork(worker, selectedWorkText));

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

            window.generateSecondStep = async function generateSecondStep(work) {
                let secondStepHTML = "";
                const workerName = document.querySelector('input[data-for="worker"]')?.value || "";
                const issueHandoverHtml =
                    renderTemplate(templates.issue?.issuedBy, {
                        __ISSUED_BY_VALUE__: currentUserName,
                    }) +
                    renderTemplate(templates.issue?.receivedBy, {
                        __RECEIVED_BY_VALUE__: workerName,
                    });
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
                const rateHtml =
                    (templates.issue?.selectRate || "") +
                    (templates.issue?.titleContainer || "") +
                    (templates.issue?.rateEditable || "") +
                    (templates.issue?.amountOptional || "");

                if (work !== null && work !== undefined && work !== "") {
                    secondStepHTML =
                        articleHtml +
                        (templates.issue?.materials || "") +
                        quantityHtml +
                        rateHtml +
                        (partsHtml || "") +
                        issueHandoverHtml +
                        (templates.issue?.issueDate || "");
                }
                document.getElementById("secondStep").innerHTML = secondStepHTML;

                const checkboxes_container = document.querySelector(".checkboxes_container");
                const parts = await loadAvailableParts({
                    articleId: selectedArticle.id,
                    workId: selectedDbValue("work"),
                    mode: "issue",
                });
                setAvailableParts(parts);
                renderPartQuantityCheckboxes(checkboxes_container);
            };

            window.toggleThisCheckbox = function toggleThisCheckbox(checkbox) {
                const checkboxValue = checkbox.dataset.checkbox;
                const quantityInput = checkbox.closest("label")?.querySelector(`[data-part-quantity="${checkboxValue}"]`);
                const quantity = Number(quantityInput?.value || checkbox.dataset.max || 0);
                selectedPartQuantities = selectedPartQuantities.filter((item) => item.part !== checkboxValue);
                if (checkbox.checked) {
                    if (quantityInput) {
                        quantityInput.disabled = false;
                    }
                    if (quantity > 0) {
                        selectedPartQuantities.push({ part: checkboxValue, quantity });
                    }
                } else {
                    if (quantityInput) {
                        quantityInput.disabled = true;
                    }
                }
                syncSelectedPartInputs();
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

            function renderReceiveWorkOptions() {
                const workInput = document.querySelector('input[name="work_name"]');
                const ul = document.querySelector('ul[data-for="work"]');
                if (!workInput || !ul) return;

                workInput.disabled = false;
                ul.innerHTML = `
                    <li data-for="work" data-value="" onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg hover:bg-[var(--h-bg-color)] selected">-- Select Work --</li>
                `;

                allWorks.forEach(([workKey, workValue]) => {
                    const workTitle = workValue.text;
                    ul.innerHTML += `
                        <li data-for="work" data-value="${workKey}" onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg hover:bg-[var(--h-bg-color)]">
                            ${workTitle.split("|")[0].trim()}
                        </li>
                    `;
                });
            }

            renderReceiveWorkOptions();

            articleSelectInputDOM.addEventListener("keydown", (e) => {
                e.preventDefault();
            });

            articleSelectInputDOM.addEventListener("click", () => {
                generateArticlesModal();
            });

            function generateArticlesModal() {
                const filteredArticles = articles;

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
                    if (!selectedArticle) {
                        appAlert("Select article first.");
                        selectThisOption(elem.closest(".selectParent").querySelector('li[data-value=""]'));
                        return;
                    }

                    const selectedWorkText = elem.closest(".selectParent").querySelector("li.selected")?.textContent.trim();
                    let correctWorkers = allWorkers.filter((worker) => workerMatchesWork(worker, selectedWorkText));

                    if (correctWorkers.length > 0) {
                        document.querySelector('input[name="worker_name"]').disabled = false;
                        const ul = document.querySelector('ul[data-for="worker"]');

                        ul.innerHTML = `
                            <li data-for="worker" data-value="" onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg hover:bg-[var(--h-bg-color)] selected">-- Select Worker --</li>
                        `;

                        correctWorkers.forEach((worker) => {
                            const isSelectedWorker = selectedArticle.production.find(
                                (p) =>
                                    p.work.id == elem.value &&
                                    p.receive_date == null &&
                                    p.worker_id == worker.data_option.id
                            );
                            ul.innerHTML += `
                                <li data-for="worker" data-value="${worker.data_option.id}" data-option='${jsonAttr(worker.data_option)}' onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg hover:bg-[var(--h-bg-color)] ${isSelectedWorker ? "selected" : ""}">${worker.text}</li>
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

            window.generateSecondStep = async function generateSecondStep(work) {
                let secondStepHTML = "";
                let minDate = new Date();
                const workerName = document.querySelector('input[data-for="worker"]')?.value || "";
                const receiveHandoverHtml =
                    renderTemplate(templates.receive?.issuedBy, {
                        __ISSUED_BY_VALUE__: workerName,
                    }) +
                    renderTemplate(templates.receive?.receivedBy, {
                        __RECEIVED_BY_VALUE__: currentUserName,
                    });

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
                        receiveHandoverHtml +
                        (templates.receive?.receiveDateMax || "") +
                        partsHtml;
                } else {
                    secondStepHTML =
                        articleHtml +
                        quantityDisabled +
                        (templates.receive?.title || "") +
                        (templates.receive?.rateEditable || "") +
                        (templates.receive?.amountReadonly || "") +
                        receiveHandoverHtml +
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
                const selectedTicketName = document.querySelector('input[data-for="ticket"]')?.value || "";
                const parts = await loadAvailableParts({
                    articleId: selectedArticle.id,
                    workId: selectedDbValue("work"),
                    mode: "receive",
                    ticket: selectedTicketName && selectedTicketName !== "-- Select Ticket --" ? selectedTicketName : "",
                });
                setAvailableParts(parts);
                renderPartQuantityCheckboxes(checkboxes_container);
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

            window.toggleThisCheckbox = function toggleThisCheckbox(checkbox) {
                const checkboxValue = checkbox.dataset.checkbox;
                const quantityInput = checkbox.closest("label")?.querySelector(`[data-part-quantity="${checkboxValue}"]`);
                const quantity = Number(quantityInput?.value || checkbox.dataset.max || 0);
                selectedPartQuantities = selectedPartQuantities.filter((item) => item.part !== checkboxValue);
                if (checkbox.checked) {
                    if (quantityInput) {
                        quantityInput.disabled = false;
                    }
                    if (quantity > 0) {
                        selectedPartQuantities.push({ part: checkboxValue, quantity });
                    }
                } else {
                    if (quantityInput) {
                        quantityInput.disabled = true;
                    }
                }

                syncSelectedPartInputs();
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
