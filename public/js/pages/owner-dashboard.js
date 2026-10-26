(() => {
    const config = window.__ownerDashboard || {};
    let summary = config.summary || {};
    let loading = false;

    const money = value => new Intl.NumberFormat('en-PK', {
        style: 'currency', currency: 'PKR', maximumFractionDigits: 0,
    }).format(Number(value || 0));
    const safe = value => {
        const element = document.createElement('div');
        element.textContent = value == null ? '-' : String(value);
        return element.innerHTML;
    };

    const displayDate = value => {
        if (!value) return '';
        const date = new Date(`${value}T00:00:00`);
        return Number.isNaN(date.getTime())
            ? value
            : date.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
    };

    const updateRangeLabel = (from, to) => {
        const label = document.querySelector('[data-dashboard-range-label]');
        if (label && from && to) label.textContent = `${displayDate(from)} — ${displayDate(to)}`;
    };

    function modalTable(key) {
        const definitions = {
            receivables: ['Customer Receivables', summary.receivables || [], ['Party', 'Details', 'Balance'], row => [row.name, row.detail, money(row.amount)]],
            payables: ['Supplier Payables', summary.payables || [], ['Party', 'Details', 'Balance'], row => [row.name, row.detail, money(row.amount)]],
            cash_accounts: ['Cash / Bank Accounts', summary.cash_accounts || [], ['Account', 'Type', 'Balance'], row => [row.name, row.detail, money(row.amount)]],
            pending_instruments: ['Pending Cheques / Slips', summary.pending_instruments || [], ['Customer', 'Instrument', 'Due Date', 'Balance'], row => [row.name, row.detail, row.date, money(row.amount)]],
            unpaid_programs: ['Unpaid Payment Programs', summary.unpaid_programs || [], ['Program', 'Customer', 'Date', 'Balance'], row => [row.name, row.detail, row.date, money(row.amount)]],
            orders: ['Pending Orders', summary.orders?.rows || [], ['Order', 'Customer', 'Date', 'Pending Pcs', 'Pending Value'], row => [row.name, row.detail, row.date, row.quantity, money(row.amount)]],
            stock: ['Finished Article Stock', summary.stock?.rows || [], ['Article', 'Current Pcs', 'Remaining Receive', 'A / B / C'], row => [row.name, row.quantity, row.remaining, `${row.a} / ${row.b} / ${row.c}`]],
            inventory: ['Material Inventory', summary.inventory?.rows || [], ['Item', 'Type', 'Available', 'Estimated Value'], row => [row.name, row.detail, `${row.quantity} ${row.unit}`, money(row.amount)]],
            productions: ['Pending Production', summary.operations?.production_rows || [], ['Ticket', 'Article / Worker', 'Issue Date', 'Amount'], row => [row.name, row.detail, row.date, money(row.amount)]],
            alerts: ['Action Centre', summary.alerts || [], ['Item', 'Details', 'Amount'], row => [row.title, row.detail, row.amount == null ? '-' : money(row.amount)]],
            branches: ['Branch Comparison', summary.branches || [], ['Branch', 'Sales', 'Receivable', 'Payable', 'Pending Orders', 'Expenses'], row => [row.name, row.sales == null ? 'Off' : money(row.sales), row.receivable == null ? 'Off' : money(row.receivable), row.payable == null ? 'Off' : money(row.payable), row.pending_orders == null ? 'Off' : row.pending_orders, row.expenses == null ? 'Off' : money(row.expenses)]],
            recent: ['Recent Activity', summary.recent || [], ['Date', 'Type', 'Reference', 'Amount'], row => [row.date, row.type, row.reference || '-', money(row.amount)]],
        };
        return definitions[key] || null;
    }

    function openDetails(key) {
        const definition = modalTable(key);
        if (!definition || typeof window.createModal !== 'function') return;
        const [title, rows, headers, mapRow] = definition;
        const widthClass = {
            3: 'w-[33.333%]',
            4: 'w-1/4',
            5: 'w-1/5',
            6: 'w-1/6',
        }[headers.length] || 'grow';

        window.createModal({
            id: 'dashboardDetailModal',
            name: title,
            class: 'max-w-5xl h-[34rem] max-h-[calc(100vh-7rem)]',
            details: rows.length ? { Records: rows.length } : { Status: 'No records available' },
            table: {
                name: title,
                headers: headers.map(label => ({ label, class: widthClass })),
                body: rows.map(row => mapRow(row).map(value => ({ data: safe(value), class: widthClass }))),
                scrollable: true,
            },
        });
    }

    function setBusy(busy) {
        loading = busy;
        document.querySelectorAll('[data-dashboard-apply], [data-dashboard-refresh]').forEach(button => {
            button.disabled = busy;
            button.classList.toggle('opacity-50', busy);
        });
        const refreshIcon = document.querySelector('[data-dashboard-refresh] i');
        refreshIcon?.classList.toggle('fa-spin', busy);
    }

    async function loadDashboard(forceRefresh = false) {
        if (loading) return;
        const from = document.getElementById('dashboard_from')?.value;
        const to = document.getElementById('dashboard_to')?.value;
        if (!from || !to) return;

        setBusy(true);
        const controller = new AbortController();
        const timeout = window.setTimeout(() => controller.abort(), 20000);
        try {
            const url = new URL(config.url, window.location.origin);
            url.searchParams.set('from', from);
            url.searchParams.set('to', to);
            if (forceRefresh) url.searchParams.set('refresh', '1');
            const response = await fetch(url, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                signal: controller.signal,
            });
            const payload = await response.json();
            if (!response.ok) throw new Error(payload.message || 'Dashboard could not be loaded.');

            summary = payload.summary;
            document.getElementById('dashboard-summary').innerHTML = payload.html;
            updateRangeLabel(summary.meta.from, summary.meta.to);
            document.dispatchEvent(new CustomEvent('app:data:rendered'));
        } catch (error) {
            const message = error.name === 'AbortError'
                ? 'Dashboard request timed out. Please try again.'
                : (error.message || 'Dashboard could not be loaded.');
            if (typeof window.showToast === 'function') {
                window.showToast('error', message);
            } else {
                console.error(error);
            }
        } finally {
            window.clearTimeout(timeout);
            setBusy(false);
        }
    }

    document.addEventListener('click', event => {
        const detail = event.target.closest('[data-dashboard-detail]');
        if (detail) openDetails(detail.dataset.dashboardDetail);
        if (event.target.closest('[data-dashboard-apply]')) loadDashboard(false);
        if (event.target.closest('[data-dashboard-refresh]')) loadDashboard(true);
    });

    window.applyDashboardRange = function applyDashboardRange(range) {
        const from = document.getElementById('dashboard_from');
        const to = document.getElementById('dashboard_to');
        const custom = document.getElementById('dashboard-custom-range');
        if (!from || !to || !custom) return;

        const now = new Date();
        const localDate = date => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
        let start = null;
        let end = now;

        if (range === 'custom') {
            custom.classList.remove('hidden');
            custom.classList.add('flex');
            from.disabled = false;
            to.disabled = false;
            from.focus();
            return;
        }

        if (range === 'last_month') {
            start = new Date(now.getFullYear(), now.getMonth() - 1, 1);
            end = new Date(now.getFullYear(), now.getMonth(), 0);
        } else if (range === 'last_three_months') {
            start = new Date(now.getFullYear(), now.getMonth() - 2, 1);
        } else if (range === 'last_six_months') {
            start = new Date(now.getFullYear(), now.getMonth() - 5, 1);
        } else {
            start = new Date(now.getFullYear(), now.getMonth(), 1);
        }

        from.value = localDate(start);
        to.value = localDate(end);
        from.disabled = true;
        to.disabled = true;
        custom.classList.add('hidden');
        custom.classList.remove('flex');
        updateRangeLabel(from.value, to.value);
        loadDashboard(false);
    };

})();
