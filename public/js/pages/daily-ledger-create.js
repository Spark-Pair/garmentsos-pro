(() => {
function initDailyLedgerCreate() {
    const config = window.__dailyLedgerCreate || {};
    const dailyLedgerType = config.dailyLedgerType;
    const csrfToken = config.csrfToken;
    const isEdit = Boolean(config.isEdit);

    let btnTypeGlobal = dailyLedgerType === 'deposit' ? 'deposit' : 'use';

    function setVoucherType(btn, btnType) {
        if (isEdit) {
            return;
        }

        if (btnTypeGlobal == btnType) {
            return;
        }

        $.ajax({
            url: "/set-daily-ledger-type",
            type: "POST",
            data: {
                _token: csrfToken,
                daily_ledger_type: btnType
            },
            success: function () {
                location.reload();
            },
            error: function () {
                appAlert("Failed to update daily ledger type.");
                $(btn).prop("disabled", false);
            }
        });

        moveHighlight(btn, btnType);
    }

    function moveHighlight(btn, btnType) {
        const highlight = document.getElementById("highlight");
        if (!highlight || !btn) return;
        const rect = btn.getBoundingClientRect();
        const parentRect = btn.parentElement.getBoundingClientRect();
        highlight.style.width = `${rect.width}px`;
        highlight.style.left = `${rect.left - parentRect.left - 3}px`;
        btnTypeGlobal = btnType;
    }

    const depositBtn = document.getElementById("depositBtn");
    const useBtn = document.getElementById("useBtn");
    if (depositBtn) depositBtn.addEventListener('click', () => setVoucherType(depositBtn, 'deposit'));
    if (useBtn) useBtn.addEventListener('click', () => setVoucherType(useBtn, 'use'));

    const activeBtn = dailyLedgerType === 'deposit'
        ? document.querySelector("#depositBtn")
        : document.querySelector("#useBtn");
    if (activeBtn) moveHighlight(activeBtn, btnTypeGlobal);
}

window.initDailyLedgerCreate = initDailyLedgerCreate;

function boot() {
    if (window.__dailyLedgerCreate) initDailyLedgerCreate();
}

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", boot);
} else {
    boot();
}
})();
