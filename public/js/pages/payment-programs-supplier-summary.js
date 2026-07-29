(function () {
    window.authLayout = 'table';

    window.createRow = function createRow(data) {
        const programs = data?.data?.payment_programs || [];
        const total_amount = programs.reduce((sum, p) => sum + parseFormattedNumber(p.amount), 0);
        const pending_voucher_payment = programs.reduce((sum, p) => sum + parseFormattedNumber(p.pending_voucher_payment), 0);
        const not_received = programs.reduce((sum, p) => sum + parseFormattedNumber(p.not_received), 0);

        if (not_received !== 0 || pending_voucher_payment !== 0) {
            return `
                <div id="${data.id}" class="item row relative group grid grid-cols-4 border-b border-[var(--h-bg-color)] items-center py-2 cursor-pointer hover:bg-[var(--h-secondary-bg-color)] transition-all fade-in ease-in-out">
                    <span>${data.name}</span>
                    <span>${formatMoney(total_amount)}</span>
                    <span>${formatMoney(pending_voucher_payment)}</span>
                    <span>${formatMoney(not_received)}</span>
                </div>`;
        }
    };
})();
