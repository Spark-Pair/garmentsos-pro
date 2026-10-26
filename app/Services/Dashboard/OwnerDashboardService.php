<?php

namespace App\Services\Dashboard;

use App\Models\Article;
use App\Models\BankAccount;
use App\Models\Bilty;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\Expense;
use App\Models\InventoryItem;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\PaymentProgram;
use App\Models\Production;
use App\Models\SalesReturn;
use App\Models\Shipment;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\UtilityBill;
use App\Services\ArticleStockService;
use App\Services\Branches\ModuleBranchService;
use App\Services\Orders\OrderBalanceService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class OwnerDashboardService
{
    public function __construct(
        private readonly ModuleBranchService $branches,
        private readonly OrderBalanceService $orderBalances,
        private readonly ArticleStockService $articleStock,
    ) {}

    public function summary(Carbon $from, Carbon $to, bool $fresh = false): array
    {
        $from = $from->copy()->startOfDay();
        $to = $to->copy()->endOfDay();
        $cacheKey = 'owner-dashboard:'.$from->toDateString().':'.$to->toDateString();

        if ($fresh) {
            Cache::forget($cacheKey);
        }

        return Cache::remember($cacheKey, now()->addMinute(), fn () => $this->build($from, $to));
    }

    private function build(Carbon $from, Carbon $to): array
    {
        $modules = collect([
            'articles', 'customers', 'suppliers', 'orders', 'invoices', 'customer_payments',
            'supplier_payments', 'payment_programs', 'bank_accounts', 'expenses', 'utility_bills',
            'productions', 'physical_quantities', 'inventory', 'shipments', 'cargos', 'bilties',
            'sales_returns',
        ])->mapWithKeys(fn (string $key) => [$key => $this->branches->isClientModuleEnabled($key)])->all();

        $customers = $modules['customers'] && $modules['invoices'] ? Customer::query()->get() : collect();
        $suppliers = $modules['suppliers'] ? Supplier::query()->get() : collect();
        $customerBalances = $this->partyBalances($customers, 'customer');
        $supplierBalances = $this->partyBalances($suppliers, 'supplier');

        $receivable = $customerBalances->where('balance', '>', 0)->sum('balance');
        $customerAdvance = abs($customerBalances->where('balance', '<', 0)->sum('balance'));
        $payable = $supplierBalances->where('balance', '>', 0)->sum('balance');
        $supplierAdvance = abs($supplierBalances->where('balance', '<', 0)->sum('balance'));

        $cashAccounts = $modules['bank_accounts']
            ? BankAccount::query()->where('category', 'self')->where('status', 'active')->get()
            : collect();
        $cashRows = $cashAccounts->map(fn (BankAccount $account) => [
            'name' => $account->account_title ?: 'Account #'.$account->id,
            'detail' => ucfirst((string) $account->sub_category),
            'amount' => (float) $account->calculateBalance(),
        ])->sortByDesc('amount')->values();
        $cashBalance = (float) $cashRows->sum('amount');

        $grossSales = $modules['invoices'] ? $this->period(Invoice::query(), 'date', $from, $to)->sum('netAmount') : 0;
        $salesReturns = $modules['sales_returns'] ? $this->period(SalesReturn::query(), 'date', $from, $to)->sum('amount') : 0;
        $collections = $modules['customer_payments']
            ? $this->period(CustomerPayment::query(), 'date', $from, $to)
                ->where('is_return', false)->whereNotIn('type', ['DR', 'sales_return'])->sum('amount')
            : 0;
        $supplierPayments = $modules['supplier_payments']
            ? $this->period(SupplierPayment::query(), 'date', $from, $to)
                ->where('is_return', false)->whereNotNull('voucher_id')->sum('amount')
            : 0;
        $expenses = $modules['expenses'] ? $this->period(Expense::query(), 'date', $from, $to)->sum('amount') : 0;
        $periodOrders = $modules['orders'] ? $this->period(Order::query(), 'date', $from, $to) : null;

        $pendingInstruments = $modules['customer_payments']
            ? $this->pendingInstruments()
            : collect();
        $programs = $modules['payment_programs']
            ? PaymentProgram::query()->with(['customer:id,customer_name', 'customerPayments:id,program_id,amount'])->get()
            : collect();
        $unpaidPrograms = $programs->map(function (PaymentProgram $program) {
            $balance = max(0, (float) $program->amount - (float) $program->customerPayments->sum('amount'));

            return [
                'name' => $program->program_no ?: 'Program #'.$program->id,
                'detail' => $program->customer?->customer_name ?? 'No customer',
                'date' => optional($program->date)->format('d-M-Y'),
                'amount' => $balance,
            ];
        })->where('amount', '>', 0)->sortByDesc('amount')->values();

        $orders = $modules['orders']
            ? Order::query()->with(['customer:id,customer_name', 'articles.article:id,sales_rate'])->get()
            : collect();
        $pendingOrders = $orders->map(function (Order $order) {
            $pendingPcs = (int) $order->articles->sum(fn ($line) => max(0, (int) $line->ordered_pcs - (int) $line->dispatched_pcs));

            return [
                'name' => $order->order_no ?: 'Order #'.$order->id,
                'detail' => $order->customer?->customer_name ?? 'No customer',
                'date' => optional($order->date)->format('d-M-Y'),
                'status' => str_replace('_', ' ', (string) $order->status),
                'quantity' => $pendingPcs,
                'amount' => (float) $this->orderBalances->pendingForOrder($order),
            ];
        })->where('quantity', '>', 0)->sortBy('date')->values();

        $stock = $this->stockSummary($modules);
        $inventory = $this->inventorySummary($modules);
        $operations = $this->operations($modules, $from, $to);
        $alerts = $this->alerts($modules, $pendingOrders, $unpaidPrograms, $stock, $inventory);

        return [
            'meta' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'period_label' => $from->isSameDay($to) ? $from->format('d M Y') : $from->format('d M Y').' — '.$to->format('d M Y'),
                'updated_at' => now()->format('d M Y, h:i A'),
            ],
            'modules' => $modules,
            'financial' => [
                'receivable' => (float) $receivable,
                'payable' => (float) $payable,
                'cash' => $cashBalance,
                'net_position' => $cashBalance + $receivable - $payable,
                'customer_advance' => (float) $customerAdvance,
                'supplier_advance' => (float) $supplierAdvance,
                'pending_instruments' => (float) $pendingInstruments->sum('amount'),
                'unpaid_programs' => (float) $unpaidPrograms->sum('amount'),
            ],
            'activity' => [
                'gross_sales' => (float) $grossSales,
                'sales_returns' => (float) $salesReturns,
                'net_sales' => (float) $grossSales - (float) $salesReturns,
                'collections' => (float) $collections,
                'supplier_payments' => (float) $supplierPayments,
                'expenses' => (float) $expenses,
                'orders_count' => $periodOrders?->count() ?? 0,
                'orders_value' => (float) ($periodOrders?->sum('netAmount') ?? 0),
            ],
            'receivables' => $customerBalances->where('balance', '>', 0)->sortByDesc('balance')->take(10)->values()->all(),
            'payables' => $supplierBalances->where('balance', '>', 0)->sortByDesc('balance')->take(10)->values()->all(),
            'cash_accounts' => $cashRows->all(),
            'pending_instruments' => $pendingInstruments->take(10)->values()->all(),
            'unpaid_programs' => $unpaidPrograms->take(10)->values()->all(),
            'orders' => [
                'pending_count' => $pendingOrders->count(),
                'pending_pcs' => (int) $pendingOrders->sum('quantity'),
                'pending_value' => (float) $pendingOrders->sum('amount'),
                'partial_count' => $orders->where('status', 'partially_invoiced')->count(),
                'rows' => $pendingOrders->take(10)->values()->all(),
            ],
            'stock' => $stock,
            'inventory' => $inventory,
            'operations' => $operations,
            'alerts' => $alerts,
            'branches' => $this->branchComparison($modules, $customers, $suppliers, $from, $to),
            'recent' => $this->recentActivity($modules),
        ];
    }

    private function partyBalances(Collection $parties, string $type, ?array $branchIds = null, bool $includeNull = false): Collection
    {
        return $parties->map(function ($party) use ($type, $branchIds, $includeNull) {
            $balance = (float) $party->calculateBalance(null, null, false, true, $branchIds, $includeNull);
            $name = $type === 'customer' ? $party->customer_name : $party->supplier_name;

            return [
                'id' => $party->id,
                'name' => $name ?: ucfirst($type).' #'.$party->id,
                'detail' => $type === 'customer' ? ($party->person_name ?: 'Customer') : ($party->person_name ?: 'Supplier'),
                'amount' => $balance,
                'balance' => $balance,
            ];
        });
    }

    private function pendingInstruments(): Collection
    {
        return CustomerPayment::query()
            ->with(['customer:id,customer_name', 'paymentClearRecord:id,payment_id,amount'])
            ->where('is_return', false)
            ->whereIn('method', ['cheque', 'slip'])
            ->get()
            ->map(function (CustomerPayment $payment) {
                $dueDate = $payment->method === 'cheque' ? $payment->cheque_date : $payment->slip_date;
                $cleared = $payment->paymentClearRecord->sum('amount');
                if ($payment->clear_date && $payment->paymentClearRecord->isEmpty()) {
                    $cleared = (float) $payment->amount;
                }
                $balance = max(0, (float) $payment->amount - (float) $cleared);

                return [
                    'name' => $payment->customer?->customer_name ?? 'Unknown customer',
                    'detail' => strtoupper((string) $payment->method).' · '.($payment->cheque_no ?: $payment->slip_no ?: '-'),
                    'date' => $dueDate ? Carbon::parse($dueDate)->format('d-M-Y') : '-',
                    'amount' => $balance,
                ];
            })
            ->where('amount', '>', 0)
            ->sortByDesc('amount')
            ->values();
    }

    private function stockSummary(array $modules): array
    {
        if (! $modules['articles'] || ! $modules['physical_quantities']) {
            return ['available' => false, 'rows' => []];
        }

        $articles = Article::query()->orderBy('article_no')->get(['id', 'article_no', 'sales_rate']);
        $summaries = $this->articleStock->summaries($articles->pluck('id'));
        $rows = $articles->map(function (Article $article) use ($summaries) {
            $summary = $summaries->get($article->id, []);

            return [
                'name' => $article->article_no ?: 'Article #'.$article->id,
                'detail' => 'Orderable '.number_format((int) ($summary['orderable_quantity_pcs'] ?? 0)).' pcs',
                'quantity' => (int) ($summary['current_stock_pcs'] ?? 0),
                'remaining' => (int) ($summary['remaining_quantity_pcs'] ?? 0),
                'a' => (int) ($summary['a_category_pcs'] ?? 0),
                'b' => (int) ($summary['b_category_pcs'] ?? 0),
                'c' => (int) ($summary['c_category_pcs'] ?? 0),
            ];
        })->sortByDesc('quantity')->values();

        return [
            'available' => true,
            'current_pcs' => (int) $rows->sum('quantity'),
            'remaining_receive_pcs' => (int) $rows->sum('remaining'),
            'a_pcs' => (int) $rows->sum('a'),
            'b_pcs' => (int) $rows->sum('b'),
            'c_pcs' => (int) $rows->sum('c'),
            'zero_count' => $rows->where('quantity', '<=', 0)->count(),
            'rows' => $rows->take(10)->all(),
        ];
    }

    private function inventorySummary(array $modules): array
    {
        if (! $modules['inventory']) {
            return ['available' => false, 'rows' => [], 'units' => []];
        }

        $items = InventoryItem::query()->with('transactions')->where('is_active', true)->get();
        $rows = $items->map(function (InventoryItem $item) {
            $stock = (float) $item->stock_quantity;
            $latestIn = $item->transactions->where('direction', 'in')->sortByDesc('id')->first();

            return [
                'name' => $item->name,
                'detail' => ucfirst(str_replace('_', ' ', (string) $item->type)),
                'quantity' => $stock,
                'unit' => $item->unit ?: 'unit',
                'amount' => $stock * (float) ($latestIn?->unit_price ?? 0),
            ];
        })->sortBy('quantity')->values();

        return [
            'available' => true,
            'items_count' => $items->count(),
            'estimated_value' => (float) $rows->sum('amount'),
            'zero_count' => $rows->where('quantity', '<=', 0)->count(),
            'units' => $rows->groupBy('unit')->map(fn (Collection $group, string $unit) => [
                'unit' => $unit,
                'quantity' => (float) $group->sum('quantity'),
            ])->values()->all(),
            'rows' => $rows->take(10)->all(),
        ];
    }

    private function operations(array $modules, Carbon $from, Carbon $to): array
    {
        $pendingProductions = $modules['productions']
            ? Production::query()->with(['article:id,article_no', 'worker:id,employee_name'])
                ->whereNull('receive_date')->orderBy('issue_date')->get()
            : collect();
        $productionRows = $pendingProductions->map(fn (Production $production) => [
            'name' => $production->ticket ?: 'Ticket #'.$production->id,
            'detail' => ($production->article?->article_no ?? 'No article').' · '.($production->worker?->employee_name ?? 'No worker'),
            'date' => optional($production->issue_date)->format('d-M-Y'),
            'amount' => (float) $production->amount,
        ])->take(10)->values()->all();

        $unshipped = $modules['invoices'] && $modules['shipments']
            ? Invoice::query()->whereNull('shipment_no')->count()
            : 0;
        $withoutBilty = $modules['invoices'] && $modules['bilties']
            ? Invoice::query()->whereDoesntHave('bilty')->count()
            : 0;

        return [
            'pending_productions' => $pendingProductions->count(),
            'pending_production_amount' => (float) $pendingProductions->sum('amount'),
            'production_rows' => $productionRows,
            'shipments_in_period' => $modules['shipments'] ? $this->period(Shipment::query(), 'date', $from, $to)->count() : 0,
            'unshipped_invoices' => $unshipped,
            'without_bilty' => $withoutBilty,
            'bilties_in_period' => $modules['bilties'] ? $this->period(Bilty::query(), 'date', $from, $to)->count() : 0,
        ];
    }

    private function alerts(array $modules, Collection $pendingOrders, Collection $programs, array $stock, array $inventory): array
    {
        $alerts = collect();
        if ($modules['utility_bills']) {
            $bills = UtilityBill::query()->with('account')->where('is_paid', false)->whereDate('due_date', '<=', today()->addDays(5))->get();
            $overdue = $bills->filter(fn (UtilityBill $bill) => $bill->due_date?->isBefore(today()));
            if ($overdue->isNotEmpty()) {
                $alerts->push(['level' => 'danger', 'title' => 'Overdue utility bills', 'detail' => $overdue->count().' bill(s)', 'amount' => (float) $overdue->sum('amount')]);
            }
            $dueSoon = $bills->reject(fn (UtilityBill $bill) => $overdue->contains('id', $bill->id));
            if ($dueSoon->isNotEmpty()) {
                $alerts->push(['level' => 'warning', 'title' => 'Utility bills due soon', 'detail' => $dueSoon->count().' bill(s)', 'amount' => (float) $dueSoon->sum('amount')]);
            }
        }
        if ($pendingOrders->isNotEmpty()) {
            $alerts->push(['level' => 'warning', 'title' => 'Pending customer orders', 'detail' => $pendingOrders->count().' order(s)', 'amount' => (float) $pendingOrders->sum('amount')]);
        }
        if ($programs->isNotEmpty()) {
            $alerts->push(['level' => 'warning', 'title' => 'Unpaid payment programs', 'detail' => $programs->count().' program(s)', 'amount' => (float) $programs->sum('amount')]);
        }
        if (($stock['available'] ?? false) && ($stock['zero_count'] ?? 0) > 0) {
            $alerts->push(['level' => 'danger', 'title' => 'Articles with zero stock', 'detail' => $stock['zero_count'].' article(s)', 'amount' => null]);
        }
        if (($inventory['available'] ?? false) && ($inventory['zero_count'] ?? 0) > 0) {
            $alerts->push(['level' => 'danger', 'title' => 'Inventory items with zero stock', 'detail' => $inventory['zero_count'].' item(s)', 'amount' => null]);
        }

        return $alerts->values()->all();
    }

    private function branchComparison(array $modules, Collection $customers, Collection $suppliers, Carbon $from, Carbon $to): array
    {
        $mainBranchId = $this->branches->mainBranch()?->id;

        return Branch::query()->where('status', 'active')->orderByDesc('is_main')->orderBy('name')->get()
            ->map(function (Branch $branch) use ($modules, $customers, $suppliers, $from, $to, $mainBranchId) {
                $includeNull = (int) $branch->id === (int) $mainBranchId;
                $branchIds = [(int) $branch->id];
                $invoiceEnabled = $modules['invoices'] && $this->branches->isModuleBranchEnabled('invoices', $branch->id);
                $ordersEnabled = $modules['orders'] && $this->branches->isModuleBranchEnabled('orders', $branch->id);
                $expensesEnabled = $modules['expenses'] && $this->branches->isModuleBranchEnabled('expenses', $branch->id);

                $salesQuery = $this->scopeBranch(Invoice::query(), 'invoices', $branch->id, $includeNull);
                $expenseQuery = $this->scopeBranch(Expense::query(), 'expenses', $branch->id, $includeNull);
                $orderQuery = $this->scopeBranch(Order::query(), 'orders', $branch->id, $includeNull);

                return [
                    'name' => $branch->displayName(),
                    'code' => $branch->code,
                    'sales' => $invoiceEnabled ? (float) $this->period($salesQuery, 'date', $from, $to)->sum('netAmount') : null,
                    'receivable' => $invoiceEnabled ? (float) $this->partyBalances($customers, 'customer', $branchIds, $includeNull)->where('balance', '>', 0)->sum('balance') : null,
                    'payable' => $modules['suppliers'] ? (float) $this->partyBalances($suppliers, 'supplier', $branchIds, $includeNull)->where('balance', '>', 0)->sum('balance') : null,
                    'pending_orders' => $ordersEnabled ? $orderQuery->where('status', '!=', 'invoiced')->count() : null,
                    'expenses' => $expensesEnabled ? (float) $this->period($expenseQuery, 'date', $from, $to)->sum('amount') : null,
                ];
            })->values()->all();
    }

    private function recentActivity(array $modules): array
    {
        $rows = collect();
        if ($modules['orders']) {
            Order::query()->latest('id')->take(4)->get()->each(fn (Order $row) => $rows->push([
                'date_raw' => optional($row->date)->toDateString(), 'date' => optional($row->date)->format('d-M-Y'),
                'type' => 'Order', 'reference' => $row->order_no, 'amount' => (float) $row->netAmount,
            ]));
        }
        if ($modules['invoices']) {
            Invoice::query()->latest('id')->take(4)->get()->each(fn (Invoice $row) => $rows->push([
                'date_raw' => optional($row->date)->toDateString(), 'date' => optional($row->date)->format('d-M-Y'),
                'type' => 'Invoice', 'reference' => $row->invoice_no, 'amount' => (float) $row->netAmount,
            ]));
        }
        if ($modules['expenses']) {
            Expense::query()->latest('id')->take(4)->get()->each(fn (Expense $row) => $rows->push([
                'date_raw' => optional($row->date)->toDateString(), 'date' => optional($row->date)->format('d-M-Y'),
                'type' => 'Expense', 'reference' => $row->reff_no ?: 'Expense #'.$row->id, 'amount' => (float) $row->amount,
            ]));
        }

        return $rows->sortByDesc('date_raw')->take(8)->values()->all();
    }

    private function period(Builder $query, string $column, Carbon $from, Carbon $to): Builder
    {
        return $query->whereDate($column, '>=', $from->toDateString())->whereDate($column, '<=', $to->toDateString());
    }

    private function scopeBranch(Builder $query, string $table, int $branchId, bool $includeNull): Builder
    {
        if (! Schema::hasColumn($table, 'branch_id')) {
            return $query;
        }

        return $query->where(function (Builder $scope) use ($branchId, $includeNull) {
            $scope->where('branch_id', $branchId);
            if ($includeNull) {
                $scope->orWhereNull('branch_id');
            }
        });
    }
}
