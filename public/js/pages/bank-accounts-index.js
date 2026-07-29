(() => {
    function initBankAccountsIndex(config) {
        const currentUserRole = config?.currentUserRole;
        const authLayout = config?.authLayout;
        const statusUrl = config?.bankAccountStatusUrl;
        const updateSerialBaseUrl = config?.bankAccountsUpdateSerialBase;

        if (authLayout) {
            window.authLayout = authLayout;
        }

        window.createRow = function(data) {
            return `
            <div id="${data.id}" oncontextmenu='${htmlAttr(data.oncontextmenu || "")}' onclick='${htmlAttr(data.onclick || "")}'
                class="item row relative group grid grid-cols-9 border-b border-[var(--h-bg-color)] items-center text-center py-2 cursor-pointer hover:bg-[var(--h-secondary-bg-color)] transition-all fade-in ease-in-out"
                data-json='${jsonAttr(data)}'>

                <span>${data.date}</span>
                <span class="col-span-2">${data.name}</span>
                <span class="capitalize col-span-2">${data.details["Name"]}</span>
                <span class="capitalize">${data.bank}</span>
                <span class="capitalize">${data.details["Category"]}</span>
                <span>${formatMoney(data.details["Balance"])}</span>
                <span class="capitalize">${data.status}</span>
            </div>`;
        }

        const infoRoot = document.getElementById('info');
        const infoDom = infoRoot?.querySelector('span') || null;
        function updateInfo(items = []) {
            if (!infoDom) return;
            const activeAccounts = items.filter(account => account.status === 'active');
            infoDom.textContent = `Total Bank Account: ${items.length} | Active: ${activeAccounts.length}`;
        }

        updateInfo(window.allDataArray || []);
        document.addEventListener('app:data:rendered', (event) => {
            updateInfo(event.detail?.items || []);
        });

        window.generateContextMenu = function(e) {
            let item = e.target.closest('.item');
            let data = JSON.parse(item.dataset.json);

            let contextMenuData = {
                item: item,
                data: data,
                actions: [
                    { id: 'edit', text: 'Edit', link: `/bank-accounts/${data.id}/edit` },
                ],
                action: statusUrl,
                x: e.pageX,
                y: e.pageY,
            };

            if (data.details['Category'] === 'self') {
                contextMenuData.actions.push(
                    {id: 'update-cheque-book-serial', text: 'Update Serial', onclick: `generateUpdateChequeBookSerialModel(${JSON.stringify(data)})`},
                );
            }

            if (isDeveloperUser()) {
                contextMenuData.actions.push({
                    id: 'delete-bank-account',
                    text: 'Delete',
                    onclick: `submitResourceDelete('/bank-accounts/${data.id}')`,
                });
            }

            createContextMenu(contextMenuData);
        }

        window.generateModal = function(item) {
            let data = JSON.parse(item.dataset.json);

            let modalData = {
                id: 'modalForm',
                uId: data.id,
                status: data.status,
                name: data.name,
                action: statusUrl,
                details: {
                    'Name': data.details['Name'],
                    'Category': data.details['Category'],
                    'Bank': data.bank,
                    'Date': data.date,
                    'Balance': formatMoney(data.details['Balance']),
                },
                bottomActions: [
                    { id: 'edit', text: 'Edit', link: `/bank-accounts/${data.id}/edit` },
                ],
            }

            if (data.details['Category'] === 'self') {
                modalData.details['Account No'] = data.account_no;
                modalData.details['Cheque Book Serial'] = data.chqbkSerialStart + ' - ' + data.chqbkSerialEnd;
                modalData.bottomActions.push(
                    {id: 'update-cheque-book-serial', text: 'Update Serial', onclick: `generateUpdateChequeBookSerialModel(${JSON.stringify(data)})`},
                );
            }

            if (isDeveloperUser()) {
                modalData.bottomActions.push({
                    id: 'delete-bank-account',
                    text: 'Delete',
                    onclick: `submitResourceDelete('/bank-accounts/${data.id}')`,
                });
            }

            createModal(modalData);
        }

    window.generateUpdateChequeBookSerialModel = function(data) {
        let modalData = {
                id: 'updateChequeBookSerialModelForm',
                class: 'h-auto',
                method: 'POST',
                action: `${updateSerialBaseUrl}/${data.id}/update-serial`,
                name: 'Update Serial',
                fields: [
                    {
                        category: 'input',
                        type: 'hidden',
                        name: '_method',
                        value: 'PUT',
                    },
                    {
                        category: 'input',
                        label: 'Account Title',
                        value: data.name,
                        disabled: true,
                    },
                    {
                        category: 'input',
                        type: 'hidden',
                        name: 'category',
                        value: data.details['Category'],
                    },
                    {
                        category: 'input',
                        label: 'Cheque Serial Start',
                        name: 'cheque_book_serial[start]',
                        id: 'cheque_book_serial_start',
                        type: 'number',
                        placeholder: 'Enter serial start',
                        value: data.chqbkSerialStart,
                        required: true,
                        oninput: 'trackSerialRange()',
                    },
                    {
                        category: 'input',
                        label: 'Cheque Serial End',
                        name: 'cheque_book_serial[end]',
                        id: 'cheque_book_serial_end',
                        type: 'number',
                        placeholder: 'Enter serial end',
                        value: data.chqbkSerialEnd,
                        required: true,
                    },
                ],
                fieldsGridCount: '2',
                bottomActions: [
                    {id: 'update-cheque-book-serial', text: 'Update Serial', type: 'submit'},
                ],
                defaultListener: true,
            }

            createModal(modalData);
            trackSerialRange();
        }

        window.trackSerialRange = function trackSerialRange() {
            const startInput = document.getElementById('cheque_book_serial_start');
            const endInput = document.getElementById('cheque_book_serial_end');
            if (!startInput || !endInput) return;

            const startValue = Number(startInput.value || 0);
            if (Number.isFinite(startValue)) {
                const minValue = startValue + 1;
                endInput.min = String(minValue);
                if (endInput.value && Number(endInput.value) < minValue) {
                    endInput.value = '';
                }
            }
        }
    }

    window.initBankAccountsIndex = initBankAccountsIndex;

    function boot() {
        if (window.__bankAccountsIndex) {
            initBankAccountsIndex(window.__bankAccountsIndex);
        }
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", boot);
    } else {
        boot();
    }
})();
