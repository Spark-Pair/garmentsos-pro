(() => {
function initUtilityBillsIndex() {
    const config = window.__utilityBillsIndex || {};
    const csrfToken = config.csrfToken;
    const canDeveloperManage = isDeveloperUser('utility_bills');
    let authLayout = 'table';
    let today = formatDate(new Date(), false, true);

    window.createRow = function(data) {
        return `
        <div id="${data.id}" oncontextmenu='${htmlAttr(data.oncontextmenu || "")}' onclick='${htmlAttr(data.onclick || "")}'
            class="item row relative group grid grid-cols-9 border-b border-[var(--h-bg-color)] items-center py-2 cursor-pointer hover:bg-[var(--h-secondary-bg-color)] transition-all fade-in ease-in-out"
            data-json='${jsonAttr(data)}'>

            <span class="capitalize">${data.bill_type}</span>
            <span class="capitalize">${data.location}</span>
            <span class="capitalize">${data.account_title}</span>
            <span class="capitalize">${data.account_no}</span>
            <span class="capitalize">${data.month}</span>
            <span class="capitalize">${data.units}</span>
            <span class="capitalize">${formatMoney(data.amount)}</span>
            <span class="capitalize">${data.due_date}</span>
            <span class="capitalize">${data.status}</span>
        </div>`;
    }

    window.generateModal = function(item) {
        const data = JSON.parse(item.dataset.json);
        const details = {
            "Bill Type": data.bill_type,
            Location: data.location,
            "Account Title": data.account_title,
            "Account No.": data.account_no,
            Month: data.month,
            Units: data.units ?? "-",
            Amount: formatMoney(data.amount),
            "Due Date": data.due_date,
            Status: data.status,
        };

        const bottomActions = [
            {
                id: "edit",
                text: "Edit",
                link: `/utility-bills/${data.id}/edit`,
            },
        ];

        if (canDeveloperManage) {
            bottomActions.push({
                id: "delete-utility-bill",
                text: "Delete",
                onclick: `submitUtilityBillDelete(${data.id})`,
            });
        }

        if (!data.is_paid) {
            bottomActions.push({
                id: "mark-paid",
                text: "Mark Paid",
                onclick: `markThisPaid(${data.id})`,
            });
        }

        createModal({
            id: "utilityBillDetailsModal",
            method: "GET",
            class: "p-5 max-w-2xl h-auto",
            name: `${data.bill_type} Bill`,
            status: data.is_paid ? "active" : "pending",
            details,
            bottomActions,
        });
    }

    window.generateContextMenu = function(e) {
        e.preventDefault();
        let item = e.target.closest('.item');
        let data = JSON.parse(item.dataset.json);

        let contextMenuData = {
            item: item,
            data: data,
            x: e.pageX,
            y: e.pageY,
            actions: [
                { id: 'edit', text: 'Edit', link: `/utility-bills/${data.id}/edit` },
            ],
            onlyThisActions: true,
        };

        if (!data.is_paid) {
            contextMenuData.actions.push({id: 'mark-paid', text: 'Mark Paid', onclick: `markThisPaid(${data.id})`})
        }
        if (canDeveloperManage) {
            contextMenuData.actions.push({ id: 'delete-utility-bill', text: 'Delete', onclick: `submitUtilityBillDelete(${data.id})` });
        }
        createContextMenu(contextMenuData);
    }

    window.submitUtilityBillDelete = function(id) {
        submitDeleteForm(`/utility-bills/${id}`, csrfToken);
    }

    window.markThisPaid = function(id) {
        $.ajax({
            url: `/utility-bills/${id}/mark-paid`,
            type: "PUT",
            data: {
                _token: csrfToken,
                _method: "put",
            },
            success: function (response) {
                if (response?.success) {
                    location.reload();
                    return;
                }

                showUtilityBillError(response?.message || "Failed to mark paid.");
            },
            error: function (xhr) {
                showUtilityBillError(xhr?.responseJSON?.message || "Failed to mark paid.");
            }
        });
    }

    function showUtilityBillError(message) {
        if (typeof showToast === 'function') {
            showToast('error', message);
            return;
        }

        if (typeof showMessageBox === 'function') {
            showMessageBox('error', message);
            return;
        }

        console.error(message);
    }

    function submitDeleteForm(action, token) {
        const form = document.createElement("form");
        form.method = "POST";
        form.action = action;
        form.innerHTML = `
            <input type="hidden" name="_token" value="${token || document.querySelector('meta[name="csrf-token"]')?.content || ""}">
            <input type="hidden" name="_method" value="DELETE">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

window.initUtilityBillsIndex = initUtilityBillsIndex;

function boot() {
    if (window.__utilityBillsIndex) initUtilityBillsIndex();
}

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", boot);
} else {
    boot();
}
})();
