(() => {
function initEmployeePaymentsIndex() {
    const config = window.__employeePaymentsIndex || {};
        window.authLayout = config.authLayout || "table";

        window.createRow = function createRow(data) {
            return `
                <div id="${data.id}" oncontextmenu='${htmlAttr(data.oncontextmenu || "")}' onclick='${htmlAttr(data.onclick || "")}'
                    class="item row relative group grid grid-cols-5 border-b border-[var(--h-bg-color)] items-center py-2 cursor-pointer hover:bg-[var(--h-secondary-bg-color)] transition-all fade-in ease-in-out"
                    data-json='${jsonAttr(data)}'>

                    <span class="text-center">${data.details["Date"]}</span>
                    <span class="text-center">${data.details["Category"]}</span>
                    <span class="text-center">${data.name}</span>
                    <span class="text-center capitalize">${data.details["Method"]}</span>
                    <span class="text-center">${data.details["Amount"]}</span>
                </div>
            `;
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
                actions: window.authLayout === "developer"
                    ? [
                        { id: "edit-employee-payment", text: "Edit", link: `/employee-payments/${data.id}/edit` },
                        { id: "delete-employee-payment", text: "Delete", onclick: `submitResourceDelete('/employee-payments/${data.id}')` },
                    ]
                    : [],
            };

            createContextMenu(contextMenuData);
        };

        window.generateModal = function generateModal(item) {
            let data = JSON.parse(item.dataset.json);

            let modalData = {
                id: "modalForm",
                class: "h-auto",
                name: data.name,
                details: {
                    Date: data.details["Date"],
                    Category: data.details["Category"],
                    Method: data.details["Method"],
                    Amount: data.details["Amount"],
                },
                bottomActions: window.authLayout === "developer"
                    ? [
                        { id: "edit-employee-payment", text: "Edit", link: `/employee-payments/${data.id}/edit` },
                        { id: "delete-employee-payment", text: "Delete", onclick: `submitResourceDelete('/employee-payments/${data.id}')` },
                    ]
                    : [],
            };

            createModal(modalData);
        };

        let infoDom = document.getElementById("info")?.querySelector("span");
        let allDataArray = window.allDataArray || [];

        window.onFilter = function onFilter() {
            if (!infoDom) return;
            const visibleRows = window.visibleData || [];
            infoDom.textContent = `Showing ${visibleRows.length} of ${allDataArray.length} payments.`;
        };

        document.addEventListener('app:data:rendered', (event) => {
            allDataArray = event.detail?.items || window.allDataArray || [];
        });
}

window.initEmployeePaymentsIndex = initEmployeePaymentsIndex;

function boot() {
    if (window.__employeePaymentsIndex) initEmployeePaymentsIndex();
}

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", boot);
} else {
    boot();
}
})();
