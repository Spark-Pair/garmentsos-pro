@php
    $money = fn ($value) => \App\Support\Money::format((float) $value);
    $financial = $summary['financial'];
    $activity = $summary['activity'];
    $modules = $summary['modules'];
    $kpis = [
        ['label' => 'Customer Receivable', 'value' => $financial['receivable'], 'icon' => 'fa-arrow-trend-up', 'detail' => 'receivables', 'show' => $modules['customers'] && $modules['invoices']],
        ['label' => 'Supplier Payable', 'value' => $financial['payable'], 'icon' => 'fa-arrow-trend-down', 'detail' => 'payables', 'show' => $modules['suppliers']],
        ['label' => 'Cash / Bank', 'value' => $financial['cash'], 'icon' => 'fa-building-columns', 'detail' => 'cash_accounts', 'show' => $modules['bank_accounts']],
        ['label' => 'Net Position', 'value' => $financial['net_position'], 'icon' => 'fa-scale-balanced', 'detail' => null, 'show' => true],
    ];
    $periodKpis = [
        ['label' => 'Net Sales', 'value' => $activity['net_sales'], 'sub' => 'Gross ' . $money($activity['gross_sales']), 'icon' => 'fa-chart-line', 'show' => $modules['invoices']],
        ['label' => 'Collections', 'value' => $activity['collections'], 'sub' => 'Customer receipts', 'icon' => 'fa-wallet', 'show' => $modules['customer_payments']],
        ['label' => 'Expenses', 'value' => $activity['expenses'], 'sub' => 'Selected period', 'icon' => 'fa-receipt', 'show' => $modules['expenses']],
        ['label' => 'Pending Orders', 'value' => $summary['orders']['pending_value'], 'sub' => number_format($summary['orders']['pending_pcs']) . ' pcs', 'icon' => 'fa-box-open', 'show' => $modules['orders'], 'detail' => 'orders'],
    ];
@endphp

<div class="space-y-5 pt-1 text-[var(--text-color)]">
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($kpis as $kpi)
            @continue(!$kpi['show'])
            <button type="button" @if($kpi['detail']) data-dashboard-detail="{{ $kpi['detail'] }}" @endif
                class="dashboard-card dashboard-card--primary group rounded-xl border p-4 text-left transition-all duration-200 {{ $kpi['detail'] ? 'cursor-pointer hover:-translate-y-0.5 hover:border-[var(--primary-color)] hover:shadow-md' : 'cursor-default' }}">
                <div class="mb-3 flex items-center justify-between gap-3">
                    <span class="text-xs font-medium uppercase tracking-wide">{{ $kpi['label'] }}</span>
                    <span class="dashboard-kpi-icon flex size-8 shrink-0 items-center justify-center rounded-lg bg-[var(--primary-color)]/12 text-[var(--primary-color)]">
                        <i class="fas {{ $kpi['icon'] }} text-sm"></i>
                    </span>
                </div>
                <div class="text-xl font-semibold">{{ $money($kpi['value']) }}</div>
                @if ($kpi['detail'])
                    <div class="mt-1 text-xs text-[var(--secondary-text)]">Click for details</div>
                @endif
            </button>
        @endforeach
    </div>

    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($periodKpis as $kpi)
            @continue(!$kpi['show'])
            <button type="button" @if(!empty($kpi['detail'])) data-dashboard-detail="{{ $kpi['detail'] }}" @endif
                class="dashboard-card rounded-xl border p-4 text-left transition-all duration-200 {{ !empty($kpi['detail']) ? 'hover:-translate-y-0.5 hover:border-[var(--primary-color)] hover:shadow-md' : 'cursor-default' }}">
                <div class="flex items-start justify-between">
                    <div>
                        <div class="text-xs font-medium uppercase tracking-wide text-[var(--secondary-text)]">{{ $kpi['label'] }}</div>
                        <div class="mt-1 text-lg font-semibold">{{ $money($kpi['value']) }}</div>
                        <div class="mt-1 text-xs text-[var(--secondary-text)]">{{ $kpi['sub'] }}</div>
                    </div>
                    <div class="dashboard-kpi-icon flex size-8 shrink-0 items-center justify-center rounded-lg bg-[var(--primary-color)]/12 text-[var(--primary-color)]">
                        <i class="fas {{ $kpi['icon'] }}"></i>
                    </div>
                </div>
            </button>
        @endforeach
    </div>

    <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
        <div class="dashboard-panel rounded-xl border p-4">
            <div class="mb-3 flex items-center justify-between">
                <div>
                    <h3 class="font-semibold">Action centre</h3>
                    <p class="text-xs text-[var(--secondary-text)]">Items needing attention</p>
                </div>
                @if (count($summary['alerts']))
                    <button type="button" data-dashboard-detail="alerts" class="text-xs text-[var(--primary-color)] hover:underline">View all</button>
                @endif
            </div>
            <div class="space-y-2">
                @forelse (array_slice($summary['alerts'], 0, 5) as $alert)
                    <div class="flex items-center justify-between gap-3 rounded-lg border border-[var(--glass-border-color)]/10 bg-[var(--secondary-bg-color)] px-3 py-2">
                        <div class="min-w-0">
                            <div class="truncate text-sm font-medium">{{ $alert['title'] }}</div>
                            <div class="text-xs text-[var(--secondary-text)]">{{ $alert['detail'] }}</div>
                        </div>
                        @if ($alert['amount'] !== null)
                            <span class="text-sm font-semibold">{{ $money($alert['amount']) }}</span>
                        @endif
                    </div>
                @empty
                    <div class="rounded-lg bg-[var(--secondary-bg-color)] p-5 text-center text-sm text-[var(--secondary-text)]">No urgent items.</div>
                @endforelse
            </div>
        </div>

        <div class="dashboard-panel rounded-xl border p-4">
            <div class="mb-3 flex items-center justify-between">
                <div>
                    <h3 class="font-semibold">Receivables & payables</h3>
                    <p class="text-xs text-[var(--secondary-text)]">Largest current balances</p>
                </div>
            </div>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                @if ($modules['customers'] && $modules['invoices'])
                    <button type="button" data-dashboard-detail="receivables" class="rounded-lg bg-[var(--secondary-bg-color)] p-3 text-left hover:ring-1 hover:ring-[var(--primary-color)]">
                        <div class="mb-2 text-xs font-semibold uppercase text-[var(--secondary-text)]">Top customers</div>
                        @forelse (array_slice($summary['receivables'], 0, 3) as $row)
                            <div class="flex justify-between gap-2 border-b border-[var(--glass-border-color)]/10 py-1.5 text-sm last:border-0">
                                <span class="truncate">{{ $row['name'] }}</span><span class="font-medium">{{ $money($row['amount']) }}</span>
                            </div>
                        @empty
                            <div class="text-sm text-[var(--secondary-text)]">No receivable balance.</div>
                        @endforelse
                    </button>
                @endif
                @if ($modules['suppliers'])
                    <button type="button" data-dashboard-detail="payables" class="rounded-lg bg-[var(--secondary-bg-color)] p-3 text-left hover:ring-1 hover:ring-[var(--primary-color)]">
                        <div class="mb-2 text-xs font-semibold uppercase text-[var(--secondary-text)]">Top suppliers</div>
                        @forelse (array_slice($summary['payables'], 0, 3) as $row)
                            <div class="flex justify-between gap-2 border-b border-[var(--glass-border-color)]/10 py-1.5 text-sm last:border-0">
                                <span class="truncate">{{ $row['name'] }}</span><span class="font-medium">{{ $money($row['amount']) }}</span>
                            </div>
                        @empty
                            <div class="text-sm text-[var(--secondary-text)]">No payable balance.</div>
                        @endforelse
                    </button>
                @endif
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 xl:grid-cols-3">
        @if ($summary['stock']['available'])
            <button type="button" data-dashboard-detail="stock" class="dashboard-panel rounded-xl border p-4 text-left transition hover:border-[var(--primary-color)]">
                <div class="mb-3 flex items-center justify-between"><h3 class="font-semibold">Finished stock</h3><i class="fas fa-shirt text-[var(--primary-color)]"></i></div>
                <div class="text-2xl font-semibold">{{ number_format($summary['stock']['current_pcs']) }} <span class="text-sm font-normal text-[var(--secondary-text)]">pcs</span></div>
                <div class="mt-3 grid grid-cols-3 gap-2 text-center text-xs">
                    <div class="rounded-lg bg-[var(--secondary-bg-color)] p-2"><b>{{ number_format($summary['stock']['a_pcs']) }}</b><br><span class="text-[var(--secondary-text)]">A</span></div>
                    <div class="rounded-lg bg-[var(--secondary-bg-color)] p-2"><b>{{ number_format($summary['stock']['b_pcs']) }}</b><br><span class="text-[var(--secondary-text)]">B</span></div>
                    <div class="rounded-lg bg-[var(--secondary-bg-color)] p-2"><b>{{ number_format($summary['stock']['c_pcs']) }}</b><br><span class="text-[var(--secondary-text)]">C</span></div>
                </div>
            </button>
        @endif

        @if ($summary['inventory']['available'])
            <button type="button" data-dashboard-detail="inventory" class="dashboard-panel rounded-xl border p-4 text-left transition hover:border-[var(--primary-color)]">
                <div class="mb-3 flex items-center justify-between"><h3 class="font-semibold">Material inventory</h3><i class="fas fa-boxes-stacked text-[var(--primary-color)]"></i></div>
                <div class="text-2xl font-semibold">{{ number_format($summary['inventory']['items_count']) }} <span class="text-sm font-normal text-[var(--secondary-text)]">items</span></div>
                <div class="mt-2 text-xs text-[var(--secondary-text)]">Estimated value {{ $money($summary['inventory']['estimated_value']) }}</div>
                <div class="mt-3 flex flex-wrap gap-2">
                    @foreach (array_slice($summary['inventory']['units'], 0, 4) as $unit)
                        <span class="rounded-lg bg-[var(--secondary-bg-color)] px-2 py-1 text-xs">{{ number_format($unit['quantity'], 2) }} {{ $unit['unit'] }}</span>
                    @endforeach
                </div>
            </button>
        @endif

        @if ($modules['productions'])
            <button type="button" data-dashboard-detail="productions" class="dashboard-panel rounded-xl border p-4 text-left transition hover:border-[var(--primary-color)]">
                <div class="mb-3 flex items-center justify-between"><h3 class="font-semibold">Production</h3><i class="fas fa-gears text-[var(--primary-color)]"></i></div>
                <div class="text-2xl font-semibold">{{ number_format($summary['operations']['pending_productions']) }} <span class="text-sm font-normal text-[var(--secondary-text)]">pending</span></div>
                <div class="mt-2 text-xs text-[var(--secondary-text)]">Pending amount {{ $money($summary['operations']['pending_production_amount']) }}</div>
            </button>
        @endif
    </div>

    @if (count($summary['branches']))
        <div class="dashboard-panel rounded-xl border p-4">
            <div class="mb-3 flex items-center justify-between">
                <div><h3 class="font-semibold">Branch comparison</h3><p class="text-xs text-[var(--secondary-text)]">Period activity and current balances</p></div>
                <button type="button" data-dashboard-detail="branches" class="text-xs text-[var(--primary-color)] hover:underline">Open details</button>
            </div>
            <div class="overflow-x-auto my-scrollbar-2">
                <div class="min-w-[46rem]">
                    <div class="grid grid-cols-6 rounded-lg bg-[var(--secondary-bg-color)] px-3 py-2 text-xs font-semibold uppercase text-[var(--secondary-text)]">
                        <span>Branch</span><span class="text-right">Sales</span><span class="text-right">Receivable</span><span class="text-right">Payable</span><span class="text-right">Pending Orders</span><span class="text-right">Expenses</span>
                    </div>
                    @foreach ($summary['branches'] as $branch)
                        <div class="grid grid-cols-6 border-b border-[var(--glass-border-color)]/10 px-3 py-2 text-sm last:border-0">
                            <span class="font-medium">{{ $branch['name'] }}</span>
                            <span class="text-right">{{ $branch['sales'] === null ? 'Off' : $money($branch['sales']) }}</span>
                            <span class="text-right">{{ $branch['receivable'] === null ? 'Off' : $money($branch['receivable']) }}</span>
                            <span class="text-right">{{ $branch['payable'] === null ? 'Off' : $money($branch['payable']) }}</span>
                            <span class="text-right">{{ $branch['pending_orders'] === null ? 'Off' : number_format($branch['pending_orders']) }}</span>
                            <span class="text-right">{{ $branch['expenses'] === null ? 'Off' : $money($branch['expenses']) }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
        @if ($modules['customer_payments'])
            <button type="button" data-dashboard-detail="pending_instruments" class="dashboard-panel rounded-xl border p-4 text-left transition hover:border-[var(--primary-color)]">
                <div class="flex items-center justify-between"><div><h3 class="font-semibold">Pending cheques / slips</h3><p class="text-xs text-[var(--secondary-text)]">Outstanding customer instruments</p></div><strong>{{ $money($financial['pending_instruments']) }}</strong></div>
            </button>
        @endif
        <button type="button" data-dashboard-detail="recent" class="dashboard-panel rounded-xl border p-4 text-left transition hover:border-[var(--primary-color)]">
            <div class="flex items-center justify-between"><div><h3 class="font-semibold">Recent activity</h3><p class="text-xs text-[var(--secondary-text)]">Orders, invoices and expenses</p></div><strong>{{ count($summary['recent']) }} records</strong></div>
        </button>
    </div>
</div>
