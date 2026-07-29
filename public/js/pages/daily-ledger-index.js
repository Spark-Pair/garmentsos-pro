(() => {
function initDailyLedgerIndex() {
    const config = window.__dailyLedgerIndex || {};
    const csrfToken = config.csrfToken || document.querySelector('meta[name="csrf-token"]')?.content || "";
    const isDeveloper = config.currentUserRole === "developer";
    let totalDepositAmount = 0;
    let totalUseAmount = 0;
    let authLayout = 'table';

    window.createRow = function(data) {
        return `
            <div id="${data.ledger_type}-${data.id}" oncontextmenu='${htmlAttr(data.oncontextmenu || "")}' onclick='${htmlAttr(data.onclick || "")}'
                class="item row relative group grid grid-cols-5 text-center border-b border-[var(--h-bg-color)] items-center py-2 cursor-pointer hover:bg-[var(--h-secondary-bg-color)] transition-all fade-in ease-in-out"
                data-json='${jsonAttr(data)}'>

                <span>${data.date}</span>
                <span>${data.description}</span>
                <span>${formatNumbersWithDigits(data.deposit, 1, 1)}</span>
                <span>${formatNumbersWithDigits(data.use, 1, 1)}</span>
                <span>${formatNumbersWithDigits(data.balance, 1, 1)}</span>
            </div>
        `;
    }

    window.generateModal = function(item) {
        const data = JSON.parse(item.dataset.json);
        const isDeposit = data.ledger_type === "deposit";
        const details = {
            Type: isDeposit ? "Deposit" : "Use",
            Date: data.date,
            Description: data.description,
            Amount: formatNumbersWithDigits(isDeposit ? data.deposit : data.use, 1, 1),
            Balance: formatNumbersWithDigits(data.balance, 1, 1),
        };

        if (isDeposit) {
            details.Method = data.method || "-";
            details["Reff. No"] = data.reff_no || "-";
        } else {
            details.Case = data.case || "-";
            details.Remarks = data.remarks || "-";
        }

        const bottomActions = [];
        if (isDeveloper) {
            bottomActions.push({
                id: "daily-ledger-change",
                text: "Edit",
                link: `/daily-ledger/${data.id}/edit?type=${encodeURIComponent(data.ledger_type)}`,
            });
            bottomActions.push({
                id: "delete-daily-ledger",
                text: "Delete",
                onclick: `deleteDailyLedgerRecord(${data.id}, '${data.ledger_type}')`,
            });
        }

        createModal({
            id: "dailyLedgerDetailsModal",
            method: "GET",
            class: "p-5 max-w-2xl h-auto",
            name: isDeposit ? "Daily Ledger Deposit" : "Daily Ledger Use",
            status: isDeposit ? "active" : "transparent",
            details,
            bottomActions,
        });
    }

    window.generateContextMenu = function(e) {
        e.preventDefault();
        const item = e.target.closest(".item");
        if (!item) return;

        const data = JSON.parse(item.dataset.json);
        const actions = [];
        if (isDeveloper) {
            actions.push({
                id: "daily-ledger-change",
                text: "Edit",
                link: `/daily-ledger/${data.id}/edit?type=${encodeURIComponent(data.ledger_type)}`,
            });
            actions.push({
                id: "delete-daily-ledger",
                text: "Delete",
                onclick: `deleteDailyLedgerRecord(${data.id}, '${data.ledger_type}')`,
            });
        }

        createContextMenu({
            item,
            data,
            x: e.pageX,
            y: e.pageY,
            actions,
        });
    }

    window.deleteDailyLedgerRecord = function(id, ledgerType) {
        if (!isDeveloper) {
            return;
        }

        $.ajax({
            url: `/daily-ledger/${id}`,
            type: "POST",
            data: {
                _token: csrfToken,
                _method: "DELETE",
                ledger_type: ledgerType,
            },
            success: function(response) {
                if (typeof showToast === "function") {
                    showToast("success", response?.message || "Daily ledger record deleted successfully.");
                }
                setTimeout(() => location.reload(), 350);
            },
            error: function(xhr) {
                const message = xhr?.responseJSON?.message || "Failed to delete daily ledger record.";
                if (typeof showToast === "function") {
                    showToast("error", message);
                } else if (typeof showMessageBox === "function") {
                    showMessageBox("error", message);
                } else {
                    console.error(message);
                }
            }
        });
    }

    let allDataArray = window.allDataArray || [];
    let openingBalanceDom = document.querySelector('#calc-bottom >.opening-balance .text-right');
    let totalDepositDom = document.querySelector('#calc-bottom >.total-Deposit .text-right');
    let totalUseDom = document.querySelector('#calc-bottom >.total-Payment .text-right');
    let balanceDom = document.querySelector('#calc-bottom >.balance .text-right');
    let closingBalanceDom = document.querySelector('#calc-bottom >.closing-balance .text-right');
    const infoRoot = document.getElementById('info');
    let infoDom = infoRoot ? infoRoot.querySelector('span') : null;

    function renderCalculation(data) {
        const opening = parseFormattedNumber(data.opening_balance);
        const totalDeposit = parseFormattedNumber(data.total_deposit);
        const totalUse = parseFormattedNumber(data.total_use);
        const closing = parseFormattedNumber(data.closing_balance);

        openingBalanceDom.innerText = formatNumbersWithDigits(opening, 1, 1);
        totalDepositDom.innerText = formatNumbersWithDigits(totalDeposit, 1, 1);
        totalUseDom.innerText = formatNumbersWithDigits(totalUse, 1, 1);
        balanceDom.innerText = formatNumbersWithDigits(totalDeposit - totalUse, 1, 1);
        closingBalanceDom.innerText = formatNumbersWithDigits(closing, 1, 1);
    }

    window.renderCalculation = renderCalculation;

    window.onFilter = function() {
        const visibleRows = window.visibleData || [];
        if (visibleRows.length === 0) {
            if (infoDom) {
                infoDom.textContent = `Showing 0 of ${allDataArray.length} records.`;
            }

            if (allDataArray.length > 0) {
                let fullDeposit = allDataArray.reduce((sum, d) => sum + parseFormattedNumber(d.deposit), 0);
                let fullUse = allDataArray.reduce((sum, d) => sum + parseFormattedNumber(d.use), 0);
                let fullBalance = fullDeposit - fullUse;

                openingBalanceDom.innerText = formatNumbersWithDigits(fullBalance, 1, 1);
                totalDepositDom.innerText = "0.0";
                totalUseDom.innerText = "0.0";
                balanceDom.innerText = "0.0";
                closingBalanceDom.innerText = formatNumbersWithDigits(fullBalance, 1, 1);
            } else {
                openingBalanceDom.innerText = "0.0";
                totalDepositDom.innerText = "0.0";
                totalUseDom.innerText = "0.0";
                balanceDom.innerText = "0.0";
                closingBalanceDom.innerText = "0.0";
            }
            return;
        }

        let sortedVisibleData = [...visibleRows].sort((a, b) => {
            let dateCompare = new Date(a.date) - new Date(b.date);
            if (dateCompare !== 0) return dateCompare;
            return new Date(a.created_at) - new Date(b.created_at);
        });

        let oldestVisible = sortedVisibleData[0];

        let beforeRecords = allDataArray.filter(d => {
            let dDate = new Date(d.date);
            let oldestDate = new Date(oldestVisible.date);

            if (dDate < oldestDate) return true;
            if (dDate.getTime() === oldestDate.getTime()) {
                return new Date(d.created_at) < new Date(oldestVisible.created_at);
            }
            return false;
        });

        let openingDeposit = beforeRecords.reduce((sum, d) => sum + parseFloat(d.deposit || 0), 0);
        let openingUse = beforeRecords.reduce((sum, d) => sum + parseFloat(d.use || 0), 0);
        let openingBalance = openingDeposit - openingUse;

        let visibleDeposit = sortedVisibleData.reduce((sum, d) => sum + parseFloat(d.deposit || 0), 0);
        let visibleUse = sortedVisibleData.reduce((sum, d) => sum + parseFloat(d.use || 0), 0);

        let runningBalance = openingBalance;
        sortedVisibleData.forEach(row => {
            runningBalance += parseFloat(row.deposit || 0);
            runningBalance -= parseFloat(row.use || 0);
            row.balance = runningBalance;
        });

        let closingBalance = runningBalance;

        if (infoDom) {
            infoDom.textContent = `Showing ${visibleRows.length} of ${allDataArray.length} records.`;
        }
        openingBalanceDom.innerText = formatNumbersWithDigits(openingBalance, 1, 1);
        totalDepositDom.innerText = formatNumbersWithDigits(visibleDeposit, 1, 1);
        totalUseDom.innerText = formatNumbersWithDigits(visibleUse, 1, 1);
        balanceDom.innerText = formatNumbersWithDigits(visibleDeposit - visibleUse, 1, 1);
        closingBalanceDom.innerText = formatNumbersWithDigits(closingBalance, 1, 1);
    }

    document.addEventListener('app:data:rendered', (event) => {
        allDataArray = event.detail?.items || window.allDataArray || [];
    });
}

window.initDailyLedgerIndex = initDailyLedgerIndex;

function boot() {
    initDailyLedgerIndex();
}

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", boot);
} else {
    boot();
}
})();
