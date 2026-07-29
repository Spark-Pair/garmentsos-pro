(() => {
    function initDrIndex() {
        const config = window.__drIndex || {};
        window.authLayout = config.authLayout || "table";

        window.createRow = function createRow(data) {
            return `
            <div id="${data.id}" oncontextmenu='${htmlAttr(data.oncontextmenu || "")}' onclick='${htmlAttr(data.onclick || "")}'
                class="item row relative group grid grid-cols-3 border-b border-[var(--h-bg-color)] items-center py-2 cursor-pointer hover:bg-[var(--h-secondary-bg-color)] transition-all fade-in ease-in-out"
                data-json='${jsonAttr(data)}'>

                <span>${data.customer_name}</span>
                <span>${data.date}</span>
                <span>${data.d_r_no}</span>
            </div>`;
        };

        window.generateContextMenu = function generateContextMenu(e) {
            e.preventDefault();
            let item = e.target.closest(".item");
            if (!item) return;
            let data = JSON.parse(item.dataset.json);

            let contextMenuData = {
                item: item,
                data: data,
                x: e.pageX,
                y: e.pageY,
                actions: [],
            };

            if (isDeveloperUser()) {
                contextMenuData.actions.push({
                    id: 'delete-dr',
                    text: 'Delete',
                    onclick: `submitResourceDelete('/dr/${data.id}')`,
                });
            }

            createContextMenu(contextMenuData);
        };

        window.generateModal = function generateModal(item) {
            let data = JSON.parse(item.dataset.json);
            const rows = [
                ...(data.return_payments_details || []),
                ...(data.new_payments_details || []),
            ];

            let tableBody = rows.map((row, index) => ([
                { data: index + 1, class: 'w-[5%]' },
                { data: row.type || '-', class: 'w-[8%]' },
                { data: row.date || '-', class: 'w-1/5' },
                { data: row.method || '-', class: 'w-[10%] capitalize' },
                { data: row.beneficiary || '-', class: 'w-1/4 capitalize' },
                { data: row.account_title || '-', class: 'w-1/4 capitalize' },
                { data: row.bank || '-', class: 'w-[10%] capitalize' },
                { data: formatNumbersWithDigits(row.amount || 0, 1, 1), class: 'w-[10%]' },
                { data: row.reff || '-', class: 'w-[10%]' },
            ]));

            let modalData = {
                id: 'modalForm',
                class: 'max-w-4xl h-[37rem]',
                name: data.d_r_no,
                details: {
                    'Date': data.date,
                    'DR No': data.d_r_no,
                    'Customer': data.customer_name,
                },
                table: {
                    name: 'Payments',
                    headers: [
                        { label: "#", class: "w-[5%]" },
                        { label: "Type", class: "w-[8%]" },
                        { label: "Date", class: "w-1/5" },
                        { label: "Method", class: "w-[10%]" },
                        { label: "Beneficiary", class: "w-1/4" },
                        { label: "Acc. Title", class: "w-1/4" },
                        { label: "Bank", class: "w-[10%]" },
                        { label: "Amount", class: "w-[10%]" },
                        { label: "Reff. No.", class: "w-[10%]" },
                    ],
                    body: tableBody,
                    scrollable: true,
                },
                calcBottom: [
                    {label: 'Return Amount - Rs.', name: 'return_total', value: formatNumbersWithDigits(data.return_payments_amount || 0, 1, 1), disabled: true},
                    {label: 'New Amount - Rs.', name: 'new_total', value: formatNumbersWithDigits(data.new_payments_amount || 0, 1, 1), disabled: true},
                ],
                bottomActions: [],
            };

            if (isDeveloperUser()) {
                modalData.bottomActions.push({
                    id: 'delete-dr',
                    text: 'Delete',
                    onclick: `submitResourceDelete('/dr/${data.id}')`,
                });
            }

            createModal(modalData);
        };
    }

    window.initDrIndex = initDrIndex;

    function boot() {
        if (window.__drIndex) {
            initDrIndex();
        }
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", boot);
    } else {
        boot();
    }
})();
