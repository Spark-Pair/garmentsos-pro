<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\BankAccount;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\Employee;
use App\Models\EmployeePayment;
use App\Models\Expense;
use App\Models\Fabric;
use App\Models\InvoiceArticles;
use App\Models\Invoice;
use App\Models\InventoryTransaction;
use App\Models\IssuedFabric;
use App\Models\OrderArticles;
use App\Models\ProductionTag;
use App\Models\ReturnFabric;
use App\Models\SupplierPayment;
use App\Models\Supplier;
use App\Models\Setup;
use App\Models\StatementAdjustment;
use App\Models\Voucher;
use App\Services\Branches\ModuleBranchService;
use App\Services\PhysicalQuantityReportService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class ReportController extends Controller
{
    public function statement(Request $request)
    {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'manager', 'admin', 'accountant', 'guest', 'store_keeper', 'customer', 'supplier'])) {
            return $resp;
        }

        $branchContext = $this->statementBranchContext($request);
        $statementBranches = $branchContext['branches'];
        $selectedBranchIds = $branchContext['selected_ids'];
        $selectedBranchLabels = $branchContext['selected_labels'];
        $statementBranding = $this->statementBranding($branchContext);

        if (!empty($request)) {
            $type = $request->type;
            $category = $request->category;
            $id = $request->id;
            $dateFrom = $request->date_from;
            $dateTo = $request->date_to;

            if ($this->isCustomerRole()) {
                $customer = $this->currentCustomer();
                if (!$customer) {
                    return response()->json(['error' => 'Customer account not linked with this user.'], 403);
                }
                $category = 'customer';
                $id = $customer->id;
            } elseif ($this->isSupplierRole()) {
                $supplier = $this->currentSupplier();
                if (!$supplier) {
                    return response()->json(['error' => 'Supplier account not linked with this user.'], 403);
                }
                $category = 'supplier';
                $id = $supplier->id;
            }


            if ($request->withData) {
                $dateFrom = filled($dateFrom) ? $dateFrom : '1900-01-01';
                $dateTo = filled($dateTo) ? $dateTo : now()->toDateString();

                // return $request;
                if ($category === 'customer') {
                    $customer = Customer::find($id);
                    if (!$customer) {
                        return response()->json(['error' => 'Customer not found'], 404);
                    }

                    $data = $customer->getStatement($dateFrom, $dateTo, $type, $selectedBranchIds, $branchContext['include_null_main_records'] ?? false);
                    $data['branch_scope_label'] = implode(', ', $selectedBranchLabels);
                    $data['branch_scope_mode'] = $branchContext['mode'];

                    return view("reports.statement", compact('data', 'statementBranches', 'selectedBranchIds', 'selectedBranchLabels', 'statementBranding'));
                }

                if ($category === 'supplier') {
                    $supplier = Supplier::find($id);
                    if (!$supplier) {
                        return response()->json(['error' => 'Supplier not found'], 404);
                    }

                    $data = $supplier->getStatement($dateFrom, $dateTo, $type, $selectedBranchIds, $branchContext['include_null_main_records'] ?? false);

                    $data['branch_scope_label'] = implode(', ', $selectedBranchLabels);
                    $data['branch_scope_mode'] = $branchContext['mode'];

                    return view("reports.statement", compact('data', 'statementBranches', 'selectedBranchIds', 'selectedBranchLabels', 'statementBranding'));
                }

                if ($category === 'employee') {
                    $employee = Employee::find($id);
                    if (!$employee) {
                        return response()->json(['error' => 'Employee not found'], 404);
                    }

                    $data = $employee->getStatement($dateFrom, $dateTo, $type, $selectedBranchIds, $branchContext['include_null_main_records'] ?? false);

                    $data['branch_scope_label'] = implode(', ', $selectedBranchLabels);
                    $data['branch_scope_mode'] = $branchContext['mode'];

                    return view("reports.statement", compact('data', 'statementBranches', 'selectedBranchIds', 'selectedBranchLabels', 'statementBranding'));
                }

                if ($category === 'bank account') {
                    $bank_account = BankAccount::find($id);
                    if (!$bank_account) {
                        return response()->json(['error' => 'Bank account not found'], 404);
                    }

                    $data = $bank_account->getStatement($dateFrom, $dateTo, $type, $selectedBranchIds, $branchContext['include_null_main_records'] ?? false);

                    $data['branch_scope_label'] = implode(', ', $selectedBranchLabels);
                    $data['branch_scope_mode'] = $branchContext['mode'];

                    return view("reports.statement", compact('data', 'statementBranches', 'selectedBranchIds', 'selectedBranchLabels', 'statementBranding'));
                }
            }
        }

        return view("reports.statement", compact('statementBranches', 'selectedBranchIds', 'selectedBranchLabels', 'statementBranding'));
    }

    private function statementBranchContext(Request $request): array
    {
        $moduleKey = str_contains((string) $request->path(), 'pending-payments')
            ? 'reports_pending_payments'
            : 'reports_statement';

        $context = app(ModuleBranchService::class)->reportBranchContext($moduleKey);

        return array_merge($context, [
            'selected_ids' => $context['branch_ids'],
            'selected_labels' => $context['branch_names'],
        ]);
    }

    private function statementBranding(array $branchContext): object
    {
        return (object) app(ModuleBranchService::class)->documentBranding(
            'reports_statement',
            ($branchContext['branding_branch'] ?? null)
                ? (object) ['branch_id' => $branchContext['branding_branch']->id]
                : null
        );
    }

    public function statementRecordDetails(Request $request)
    {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'manager', 'admin', 'accountant', 'guest', 'store_keeper', 'customer', 'supplier'])) {
            return $resp;
        }

        $validated = $request->validate([
            'type' => 'required|string|in:expense,inventory_transaction,voucher,supplier_payment,employee_payment,invoice,customer_payment,statement_adjustment',
            'id' => 'required|integer|min:1',
        ]);

        $payload = match ($validated['type']) {
            'expense' => $this->expenseStatementPayload((int) $validated['id']),
            'inventory_transaction' => $this->inventoryTransactionStatementPayload((int) $validated['id']),
            'voucher' => $this->voucherStatementPayload((int) $validated['id']),
            'supplier_payment' => $this->supplierPaymentStatementPayload((int) $validated['id']),
            'employee_payment' => $this->employeePaymentStatementPayload((int) $validated['id']),
            'invoice' => $this->invoiceStatementPayload((int) $validated['id']),
            'customer_payment' => $this->customerPaymentStatementPayload((int) $validated['id']),
            'statement_adjustment' => $this->statementAdjustmentPayload((int) $validated['id']),
            default => null,
        };

        if (!$payload) {
            return response()->json(['error' => 'Statement record not found.'], 404);
        }

        return response()->json($payload);
    }

    // fucntion get names based on category
    public function getNames(Request $request)
    {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'manager', 'admin', 'accountant', 'guest', 'store_keeper', 'customer', 'supplier'])) {
            return $resp;
        }

        $category = $request->category;

        if (!$category) {
            return response()->json(['error' => 'Category required'], 400);
        }

        if ($this->isCustomerRole()) {
            if ($category !== 'customer') {
                return response()->json([], 200);
            }

            $customer = $this->currentCustomer();
            if (!$customer) {
                return response()->json([], 200);
            }

            $customer->load('city');
            return response()->json([$this->formatNameOptionPayload($customer)]);
        }

        if ($this->isSupplierRole()) {
            if ($category !== 'supplier') {
                return response()->json([], 200);
            }

            $supplier = $this->currentSupplier();
            if (!$supplier) {
                return response()->json([], 200);
            }

            return response()->json([$this->formatNameOptionPayload($supplier)]);
        }

        if ($category === 'customer') {
            $customers = app(ModuleBranchService::class)->applyRelatedScope(Customer::whereHas('user', function ($query) {
                $query->where('status', 'active');
            }), 'customers', 'reports_statement')->with('city')->get();
            return response()->json($customers->map(fn ($customer) => $this->formatNameOptionPayload($customer))->values());
        }

        if ($category === 'supplier') {
            $suppliers = app(ModuleBranchService::class)->applyRelatedScope(Supplier::whereHas('user', function ($query) {
                $query->where('status', 'active');
            }), 'suppliers', 'reports_statement')->get();
            return response()->json($suppliers->map(fn ($supplier) => $this->formatNameOptionPayload($supplier))->values());
        }

        if ($category === 'employee') {
            $employees = app(ModuleBranchService::class)
                ->applyRelatedScope(Employee::with('type')->where('status', 'active'), 'employees', 'reports_statement')
                ->get();
            return response()->json($employees->map(fn ($employee) => $this->formatNameOptionPayload($employee))->values());
        }

        if ($category === 'bank_account') {
            $bank_accounts = app(ModuleBranchService::class)->applyRelatedScope(BankAccount::with('bank')->where('status', 'active'), 'bank_accounts', 'reports_statement')->get();
            return response()->json($bank_accounts->map(fn ($account) => [
                'id' => $account->id,
                'date' => $account->date,
                'account_title' => $account->account_title,
                'category' => $account->category,
                'bank' => [
                    'id' => $account->bank?->id,
                    'title' => $account->bank?->title,
                    'short_title' => $account->bank?->short_title,
                ],
            ])->values());
        }

        return response()->json(['error' => 'Invalid category'], 400);
    }

    private function formatNameOptionPayload($record): array
    {
        $employeeType = $record instanceof Employee ? $record->type : null;

        return [
            'id' => $record->id,
            'customer_name' => $record->customer_name ?? null,
            'supplier_name' => $record->supplier_name ?? null,
            'employee_name' => $record->employee_name ?? null,
            'type' => $employeeType ? [
                'id' => $employeeType->id,
                'title' => $employeeType->title,
            ] : null,
            'date' => optional($record->date)->format('Y-m-d'),
            'joining_date' => optional($record->joining_date)->format('Y-m-d'),
            'city' => $record->city ? [
                'id' => $record->city->id,
                'title' => $record->city->title,
                'short_title' => $record->city->short_title,
            ] : null,
        ];
    }
    public function pendingPayments(Request $request)
    {
        $branchContext = $this->statementBranchContext($request);
        $reportBranches = $branchContext['branches'];
        $selectedBranchIds = $branchContext['selected_ids'];
        $selectedBranchLabels = $branchContext['selected_labels'];
        $pendingBranding = (object) app(ModuleBranchService::class)->documentBranding(
            'reports_pending_payments',
            ($branchContext['branding_branch'] ?? null)
                ? (object) ['branch_id' => $branchContext['branding_branch']->id]
                : null
        );
        $cities_options = Setup::where('type', 'city')
            ->orderBy('title')
            ->get()
            ->mapWithKeys(fn ($city) => [(int) $city->id => ['text' => $city->title]])
            ->toArray();

        if ($request->filled('date')) {
            $validated = $request->validate([
                'date' => 'required|date',
                'city' => 'nullable',
            ]);

            $date = $validated['date'];
            $selectedCities = collect(is_array($request->city) ? $request->city : explode(',', (string) $request->city))
                ->map(fn ($cityId) => trim((string) $cityId))
                ->filter(fn ($cityId) => ctype_digit($cityId))
                ->map(fn ($cityId) => (int) $cityId)
                ->filter(fn ($cityId) => $cityId > 0)
                ->unique()
                ->values()
                ->all();

            if (!empty($selectedCities)) {
                $validCityCount = Setup::where('type', 'city')->whereIn('id', $selectedCities)->count();
                if ($validCityCount !== count($selectedCities)) {
                    return redirect()->back()->with('error', 'Invalid city selected.')->withInput();
                }
            }

            $payments = CustomerPayment::with([
                    'customer.city',
                    'paymentClearRecord',
                ])
                ->whereNotNull('customer_id')
                ->whereIn('method', ['cheque', 'slip'])
                ->when(Schema::hasColumn('customer_payments', 'branch_id') && !empty($selectedBranchIds), function ($query) use ($selectedBranchIds, $branchContext) {
                    $query->where(function ($scope) use ($selectedBranchIds, $branchContext) {
                        $scope->whereIn('branch_id', $selectedBranchIds);
                        if ($branchContext['include_null_main_records'] ?? false) {
                            $scope->orWhereNull('branch_id');
                        }
                    });
                })
                ->when(!empty($selectedCities), function ($query) use ($selectedCities) {
                    $query->whereHas('customer', function ($customerQuery) use ($selectedCities) {
                        $customerQuery->whereIn('city_id', $selectedCities);
                    });
                })
                ->get()
                ->filter(function ($payment) use ($date) {
                    $paymentDate = $payment->method === 'cheque'
                        ? $payment->cheque_date
                        : $payment->slip_date;

                    if (!$paymentDate) {
                        return false;
                    }

                    if (\Carbon\Carbon::parse($paymentDate)->gt(\Carbon\Carbon::parse($date))) {
                        return false;
                    }

                    $totalAmount = (float) $payment->amount;
                    $receivedAmount = 0;

                    if ($payment->paymentClearRecord && count($payment->paymentClearRecord) > 0) {
                        $receivedAmount = collect($payment->paymentClearRecord)->sum('amount');
                    } elseif ($payment->clear_date !== null) {
                        $receivedAmount = $totalAmount;
                    }

                    $balance = $totalAmount - $receivedAmount;

                    if ($balance <= 0) {
                        return false;
                    }

                    $payment->received_amount = $receivedAmount;
                    $payment->balance = $balance;

                    return true;
                })
                ->values();

            $data = $payments->groupBy(function ($payment) {
                $cityTitle = $payment->customer?->city?->title ?? '';
                return ($payment->customer?->customer_name ?? 'Unknown') . ' | ' . $cityTitle;
            })
            ->map(function ($group, $customerKey) {
                $totalAmount = $group->sum('amount');
                $totalReceived = $group->sum('received_amount');
                $totalBalance = $totalAmount - $totalReceived;

                $paymentsArray = $group->map(function ($payment) {
                    return [
                        'id' => $payment->id,
                        'method' => $payment->method,
                        'reff_no' => $payment->cheque_no ?? $payment->slip_no,
                        'date' => $payment->method === 'cheque' ? $payment->cheque_date : $payment->slip_date,
                        'amount' => $payment->amount,
                        'received_amount' => $payment->received_amount,
                        'balance' => $payment->balance,
                    ];
                })->values();

                return [
                    'customer' => $customerKey,
                    'payments' => $paymentsArray,
                    'totals' => [
                        'amount' => $totalAmount,
                        'received_amount' => $totalReceived,
                        'balance' => $totalBalance,
                    ],
                ];
            })
            ->values();

            return view("reports.pending-payments", compact('data', 'cities_options', 'selectedCities', 'reportBranches', 'selectedBranchIds', 'selectedBranchLabels', 'pendingBranding'));
        }

        $selectedCities = [];
        return view("reports.pending-payments", compact('cities_options', 'selectedCities', 'reportBranches', 'selectedBranchIds', 'selectedBranchLabels', 'pendingBranding'));
    }

    public function article(Request $request)
    {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'manager', 'admin', 'accountant', 'guest', 'store_keeper'])) {
            return $resp;
        }

        if ($request->ajax()) {
            $branchContext = app(ModuleBranchService::class)->reportBranchContext('reports_article');
            $selectedBranchIds = $branchContext['branch_ids'];
            $reffStartDate = $request->reff_date_range_start
                ? Carbon::parse($request->reff_date_range_start)->startOfDay()
                : null;
            $reffEndDate = $request->reff_date_range_end
                ? Carbon::parse($request->reff_date_range_end)->endOfDay()
                : null;
            $invoiceStartDate = $request->invoice_date_range_start
                ? Carbon::parse($request->invoice_date_range_start)->toDateString()
                : null;
            $invoiceEndDate = $request->invoice_date_range_end
                ? Carbon::parse($request->invoice_date_range_end)->toDateString()
                : null;

            $invoiceArticles = InvoiceArticles::with([
                'article',
                'invoice.customer.city',
                'invoice.order.articles',
                'invoice.shipment.articles',
            ]);

            $orderArticles = OrderArticles::with([
                'article',
                'order.customer.city',
            ]);

            $invoiceArticles->whereHas('invoice', fn ($q) => $this->applyReportBranchScope($q, 'invoices', $selectedBranchIds, $branchContext));
            $orderArticles->whereHas('order', fn ($q) => $this->applyReportBranchScope($q, 'orders', $selectedBranchIds, $branchContext));

            if ($request->article_no) {
                $articleFilter = function ($q) use ($request) {
                    $q->where('article_no', 'like', "%{$request->article_no}%");
                };
                $invoiceArticles->whereHas('article', $articleFilter);
                $orderArticles->whereHas('article', $articleFilter);
            }

            if ($request->customer_name) {
                $invoiceArticles->whereHas('invoice.customer', function ($q) use ($request) {
                    $q->where('customer_name', 'like', "%{$request->customer_name}%");
                });
                $orderArticles->whereHas('order.customer', function ($q) use ($request) {
                    $q->where('customer_name', 'like', "%{$request->customer_name}%");
                });
            }

            if ($request->invoice_no) {
                $invoiceArticles->whereHas('invoice', function ($q) use ($request) {
                    $q->where('invoice_no', 'like', "%{$request->invoice_no}%");
                });
            }

            if ($request->reff_no) {
                $invoiceArticles->whereHas('invoice', function ($q) use ($request) {
                    $q->where(function ($referenceQuery) use ($request) {
                        $referenceQuery
                            ->where('order_no', 'like', "%{$request->reff_no}%")
                            ->orWhere('shipment_no', 'like', "%{$request->reff_no}%");
                    });
                });
                $orderArticles->whereHas('order', function ($q) use ($request) {
                    $q->where('order_no', 'like', "%{$request->reff_no}%");
                });
            }

            if ($invoiceStartDate || $invoiceEndDate) {
                $invoiceArticles->whereHas('invoice', function ($q) use ($invoiceStartDate, $invoiceEndDate) {
                    if ($invoiceStartDate && $invoiceEndDate) {
                        $q->whereDate('date', '>=', $invoiceStartDate)->whereDate('date', '<=', $invoiceEndDate);
                    } elseif ($invoiceStartDate) {
                        $q->whereDate('date', '>=', $invoiceStartDate);
                    } elseif ($invoiceEndDate) {
                        $q->whereDate('date', '<=', $invoiceEndDate);
                    }
                });
            }

            if ($reffStartDate || $reffEndDate) {
                $orderArticles->whereHas('order', function ($q) use ($reffStartDate, $reffEndDate) {
                    if ($reffStartDate && $reffEndDate) {
                        $q->whereBetween('date', [$reffStartDate, $reffEndDate]);
                    } elseif ($reffStartDate) {
                        $q->whereDate('date', '>=', $reffStartDate);
                    } elseif ($reffEndDate) {
                        $q->whereDate('date', '<=', $reffEndDate);
                    }
                });
            }

            $rowKey = function ($orderNo, $articleId, $customerId) {
                return implode('|', [
                    trim((string) ($orderNo ?? '')),
                    (string) ($articleId ?? ''),
                    (string) ($customerId ?? ''),
                ]);
            };

            $formatCustomer = function ($customer) {
                return ($customer?->customer_name ?? '-') . ' | ' . ($customer?->city?->short_title ?? '-');
            };

            $invoiceArticleRecords = $invoiceArticles->get();
            $orderArticleRecords = $orderArticles->get();

            $invoiceGroups = $invoiceArticleRecords
                ->filter(fn ($invoiceArticle) => filled($invoiceArticle->invoice?->order_no))
                ->groupBy(function ($invoiceArticle) use ($rowKey) {
                    $invoice = $invoiceArticle->invoice;

                    return $rowKey($invoice?->order_no, $invoiceArticle->article_id, $invoice?->customer_id);
                })
                ->map(function ($group) {
                    $invoiceDates = $group
                        ->map(fn ($invoiceArticle) => $invoiceArticle->invoice?->date)
                        ->filter();

                    return [
                        'invoice_nos' => $group
                            ->map(fn ($invoiceArticle) => $invoiceArticle->invoice?->invoice_no)
                            ->filter()
                            ->unique()
                            ->values(),
                        'invoice_dates' => $invoiceDates
                            ->map(fn ($date) => $date->format('d-M-Y, D'))
                            ->unique()
                            ->values(),
                        'invoice_date_raw' => optional($invoiceDates->sortDesc()->first())->format('Y-m-d H:i:s'),
                        'invoice_quantity' => (int) $group->sum(fn ($invoiceArticle) => (int) ($invoiceArticle->invoice_pcs ?? 0)),
                    ];
                });

            $matchedOrderKeys = collect();
            $requiresInvoiceMatch = $request->filled('invoice_no') || $invoiceStartDate || $invoiceEndDate;

            $orderData = $orderArticleRecords
                ->map(function ($orderArticle) use ($formatCustomer, $invoiceGroups, $requiresInvoiceMatch, $rowKey, $matchedOrderKeys) {
                    $order = $orderArticle->order;
                    $customer = $order?->customer;
                    $article = $orderArticle->article;
                    $key = $rowKey($order?->order_no, $orderArticle->article_id, $order?->customer_id);
                    $invoiceInfo = $invoiceGroups->get($key);

                    if ($requiresInvoiceMatch && !$invoiceInfo) {
                        return null;
                    }

                    if ($invoiceInfo) {
                        $matchedOrderKeys->push($key);
                    }

                    return [
                        'id' => 'order-' . $orderArticle->id,
                        'article_no' => $article?->article_no,
                        'reff_no' => $order?->order_no ?? '-',
                        'invoice_no' => $invoiceInfo ? ($invoiceInfo['invoice_nos']->implode(', ') ?: '-') : '-',
                        'customer_name' => $formatCustomer($customer),
                        'reff_date' => $order?->date?->format('d-M-Y, D') ?? '-',
                        'reff_date_raw' => $order?->date?->format('Y-m-d H:i:s'),
                        'invoice_date' => $invoiceInfo ? ($invoiceInfo['invoice_dates']->implode(', ') ?: '-') : '-',
                        'invoice_date_raw' => $invoiceInfo['invoice_date_raw'] ?? null,
                        'sort_date_raw' => $invoiceInfo['invoice_date_raw'] ?? $order?->date?->format('Y-m-d H:i:s'),
                        'pcs_per_packet' => (float) ($article?->pcs_per_packet ?? 0),
                        'reff_quantity' => (int) ($orderArticle->ordered_pcs ?? 0),
                        'invoice_quantity' => $invoiceInfo['invoice_quantity'] ?? 0,
                        'quantity' => (int) ($orderArticle->ordered_pcs ?? 0),
                    ];
                })
                ->filter()
                ->values();

            $invoiceOnlyData = $invoiceArticleRecords->filter(function ($invoiceArticle) use ($matchedOrderKeys, $reffStartDate, $reffEndDate, $rowKey) {
                $invoice = $invoiceArticle->invoice;
                $key = $rowKey($invoice?->order_no, $invoiceArticle->article_id, $invoice?->customer_id);
                $referenceDate = $invoice?->order?->date ?? $invoice?->shipment?->date;
                $isUnmatched = blank($invoice?->order_no) || !$matchedOrderKeys->contains($key);

                if (!$isUnmatched) {
                    return false;
                }

                if ($reffStartDate && (!$referenceDate || $referenceDate->lt($reffStartDate))) {
                    return false;
                }

                if ($reffEndDate && (!$referenceDate || $referenceDate->gt($reffEndDate))) {
                    return false;
                }

                return true;
            })->map(function ($invoiceArticle) use ($formatCustomer) {
                $invoice = $invoiceArticle->invoice;
                $customer = $invoice?->customer;
                $article = $invoiceArticle->article;
                $reference = $invoice?->order ?? $invoice?->shipment;
                $referenceArticle = $reference?->articles
                    ?->firstWhere('article_id', $invoiceArticle->article_id);

                return [
                    'id' => 'invoice-' . $invoiceArticle->id,
                    'article_no' => $article?->article_no,
                    'reff_no' => $invoice?->order_no ?? $invoice?->shipment_no ?? '-',
                    'invoice_no' => $invoice?->invoice_no ?? '-',
                    'customer_name' => $formatCustomer($customer),
                    'reff_date' => $reference?->date?->format('d-M-Y, D') ?? '-',
                    'reff_date_raw' => $reference?->date?->format('Y-m-d H:i:s'),
                    'invoice_date' => $invoice?->date?->format('d-M-Y, D') ?? '-',
                    'invoice_date_raw' => $invoice?->date?->format('Y-m-d H:i:s'),
                    'sort_date_raw' => $invoice?->date?->format('Y-m-d H:i:s'),
                    'pcs_per_packet' => (float) ($article?->pcs_per_packet ?? 0),
                    'reff_quantity' => (int) ($referenceArticle?->ordered_pcs ?? $referenceArticle?->shipment_pcs ?? 0),
                    'invoice_quantity' => (int) ($invoiceArticle->invoice_pcs ?? 0),
                    'quantity' => (int) ($referenceArticle?->ordered_pcs ?? $referenceArticle?->shipment_pcs ?? 0),
                ];
            });

            $data = $orderData
                ->merge($invoiceOnlyData)
                ->sortByDesc('sort_date_raw')
                ->values();

            if ($request->limit) {
                $data = $data->take((int) $request->limit)->values();
            }

            $totalReffQuantity = $data->sum('reff_quantity');
            $totalInvoiceQuantity = $data->sum('invoice_quantity');
            $totalReffPackets = $data->sum(fn ($row) => ($row['pcs_per_packet'] ?? 0) > 0
                ? ((float) ($row['reff_quantity'] ?? 0) / (float) $row['pcs_per_packet'])
                : 0);
            $totalInvoicePackets = $data->sum(fn ($row) => ($row['pcs_per_packet'] ?? 0) > 0
                ? ((float) ($row['invoice_quantity'] ?? 0) / (float) $row['pcs_per_packet'])
                : 0);

            $authLayout = $this->getAuthLayout($request->route()->getName(), 'table');
            return response()->json([
                'data' => $data,
                'authLayout' => $authLayout,
                'branch_scope' => [
                    'mode' => $branchContext['mode'],
                    'labels' => $branchContext['branch_names'],
                ],
                'calculations' => [
                    'total_quantity' => $totalReffQuantity,
                    'total_reff_quantity' => $totalReffQuantity,
                    'total_invoice_quantity' => $totalInvoiceQuantity,
                    'total_reff_packets' => $totalReffPackets,
                    'total_invoice_packets' => $totalInvoicePackets,
                ],
            ]);
        }

        $authLayout = $this->getAuthLayout($request->route()->getName(), 'table');
        $branchContext = app(ModuleBranchService::class)->reportBranchContext('reports_article');
        $selectedBranchLabels = $branchContext['branch_names'];
        return view('reports.article', compact('authLayout', 'selectedBranchLabels'));
    }

    public function fabric(Request $request)
    {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'manager', 'admin', 'accountant', 'guest', 'store_keeper'])) {
            return $resp;
        }

        if ($request->ajax()) {
            $branchContext = app(ModuleBranchService::class)->reportBranchContext('reports_fabric');
            $selectedBranchIds = $branchContext['branch_ids'];
            $startDate = $request->date_range_start ? Carbon::parse($request->date_range_start)->startOfDay() : null;
            $endDate = $request->date_range_end ? Carbon::parse($request->date_range_end)->endOfDay() : null;
            $fabricSearch = trim((string) $request->fabric);
            $tagSearch = trim((string) $request->tag);
            $supplierSearch = trim((string) $request->supplier_name);
            $workerSearch = trim((string) $request->worker_name);
            $articleSearch = trim((string) $request->article_no);
            $sourceSearch = trim((string) $request->source);

            $fabricLots = Schema::hasTable('fabrics')
                ? $this->applyReportBranchScope(
                    Fabric::with(['fabric', 'supplier'])->orderByDesc('date')->orderByDesc('id'),
                    'fabrics',
                    $selectedBranchIds,
                    $branchContext
                )->get()
                : collect();

            $fabricByTag = $fabricLots->keyBy(fn ($fabric) => (string) $fabric->tag);
            $sourceQuantity = [];
            $workerQuantity = [];
            $rows = collect();

            $addSourceQuantity = function (string $tag, float $delta) use (&$sourceQuantity) {
                if ($tag === '') {
                    return;
                }

                $sourceQuantity[$tag] = ($sourceQuantity[$tag] ?? 0) + $delta;
            };

            $addWorkerQuantity = function (string $tag, ?int $workerId, float $delta) use (&$workerQuantity) {
                if ($tag === '' || !$workerId) {
                    return;
                }

                $key = $tag . '|' . $workerId;
                $workerQuantity[$key] = ($workerQuantity[$key] ?? 0) + $delta;
            };

            foreach ($fabricLots as $fabric) {
                $tag = (string) ($fabric->tag ?? '');
                $quantity = (float) ($fabric->quantity ?? 0);
                $addSourceQuantity($tag, $quantity);

                $rows->push([
                    'id' => 'fabric-' . $fabric->id,
                    'source' => 'Received',
                    'date' => $fabric->date?->format('d-M-Y, D') ?? '-',
                    'date_raw' => $fabric->date?->format('Y-m-d') ?? $fabric->created_at?->format('Y-m-d'),
                    'fabric' => $fabric->fabric?->title ?? '-',
                    'tag' => $tag ?: '-',
                    'supplier_name' => $fabric->supplier?->supplier_name ?? '-',
                    'worker_name' => '-',
                    'article_no' => '-',
                    'color' => $fabric->color ?: '-',
                    'reference' => $fabric->reff_no ?: '-',
                    'unit' => $fabric->unit ?: '-',
                    'received_quantity' => $quantity,
                    'issued_quantity' => 0,
                    'returned_quantity' => 0,
                    'production_quantity' => 0,
                    'source_balance' => 0,
                    'worker_balance' => null,
                    'remarks' => $fabric->remarks ?: '-',
                    'created_at' => $fabric->created_at,
                ]);
            }

            if (Schema::hasTable('inventory_transactions')) {
                $inventoryRows = $this->applyReportBranchScope(
                    InventoryTransaction::with(['item.fabric', 'supplier'])->orderByDesc('date')->orderByDesc('id'),
                    'inventory_transactions',
                    $selectedBranchIds,
                    $branchContext
                )->whereHas('item', function ($query) {
                    $query->where('type', 'fabric')->orWhereNotNull('fabric_id');
                })->get();

                foreach ($inventoryRows as $transaction) {
                    $item = $transaction->item;
                    $tag = (string) ($item?->tag ?? $transaction->reference_no ?? '');
                    $quantity = (float) ($transaction->quantity ?? 0);
                    $isIn = strtolower((string) $transaction->direction) === 'in';
                    $addSourceQuantity($tag, $isIn ? $quantity : -$quantity);

                    $rows->push([
                        'id' => 'inventory-' . $transaction->id,
                        'source' => $isIn ? 'Inventory In' : 'Inventory Out',
                        'date' => $transaction->date?->format('d-M-Y, D') ?? '-',
                        'date_raw' => $transaction->date?->format('Y-m-d') ?? $transaction->created_at?->format('Y-m-d'),
                        'fabric' => $item?->fabric?->title ?? $item?->name ?? '-',
                        'tag' => $tag ?: '-',
                        'supplier_name' => $transaction->supplier?->supplier_name ?? '-',
                        'worker_name' => '-',
                        'article_no' => '-',
                        'color' => $item?->color ?: '-',
                        'reference' => $transaction->reference_no ?: '-',
                        'unit' => $transaction->unit ?: $item?->unit ?: '-',
                        'received_quantity' => $isIn ? $quantity : 0,
                        'issued_quantity' => $isIn ? 0 : $quantity,
                        'returned_quantity' => 0,
                        'production_quantity' => 0,
                        'source_balance' => 0,
                        'worker_balance' => null,
                        'remarks' => $transaction->remarks ?: '-',
                        'created_at' => $transaction->created_at,
                    ]);
                }
            }

            $issuedRows = Schema::hasTable('issued_fabrics')
                ? $this->applyReportBranchScope(
                    IssuedFabric::with('worker')->orderByDesc('date')->orderByDesc('id'),
                    'issued_fabrics',
                    $selectedBranchIds,
                    $branchContext
                )->get()
                : collect();

            foreach ($issuedRows as $issued) {
                $tag = (string) ($issued->tag ?? '');
                $quantity = (float) ($issued->quantity ?? 0);
                $lot = $fabricByTag->get($tag);
                $workerId = $issued->worker_id ? (int) $issued->worker_id : null;
                $addSourceQuantity($tag, -$quantity);
                $addWorkerQuantity($tag, $workerId, $quantity);

                $rows->push([
                    'id' => 'issued-' . $issued->id,
                    'source' => 'Issued to Worker',
                    'date' => $issued->date?->format('d-M-Y, D') ?? '-',
                    'date_raw' => $issued->date?->format('Y-m-d') ?? $issued->created_at?->format('Y-m-d'),
                    'fabric' => $lot?->fabric?->title ?? '-',
                    'tag' => $tag ?: '-',
                    'supplier_name' => $lot?->supplier?->supplier_name ?? '-',
                    'worker_name' => $issued->worker?->employee_name ?? '-',
                    'article_no' => '-',
                    'color' => $lot?->color ?: '-',
                    'reference' => '-',
                    'unit' => $lot?->unit ?: '-',
                    'received_quantity' => 0,
                    'issued_quantity' => $quantity,
                    'returned_quantity' => 0,
                    'production_quantity' => 0,
                    'source_balance' => 0,
                    'worker_balance' => 0,
                    'worker_key' => $tag . '|' . $workerId,
                    'remarks' => $issued->remarks ?: '-',
                    'created_at' => $issued->created_at,
                ]);
            }

            $returnRows = Schema::hasTable('return_fabrics')
                ? $this->applyReportBranchScope(
                    ReturnFabric::with('worker')->orderByDesc('date')->orderByDesc('id'),
                    'return_fabrics',
                    $selectedBranchIds,
                    $branchContext
                )->get()
                : collect();

            foreach ($returnRows as $return) {
                $tag = (string) ($return->tag ?? '');
                $quantity = (float) ($return->quantity ?? 0);
                $lot = $fabricByTag->get($tag);
                $workerId = $return->worker_id ? (int) $return->worker_id : null;
                $addSourceQuantity($tag, $quantity);
                $addWorkerQuantity($tag, $workerId, -$quantity);

                $rows->push([
                    'id' => 'return-' . $return->id,
                    'source' => 'Returned by Worker',
                    'date' => $return->date?->format('d-M-Y, D') ?? '-',
                    'date_raw' => $return->date?->format('Y-m-d') ?? $return->created_at?->format('Y-m-d'),
                    'fabric' => $lot?->fabric?->title ?? '-',
                    'tag' => $tag ?: '-',
                    'supplier_name' => $lot?->supplier?->supplier_name ?? '-',
                    'worker_name' => $return->worker?->employee_name ?? '-',
                    'article_no' => '-',
                    'color' => $lot?->color ?: '-',
                    'reference' => '-',
                    'unit' => $lot?->unit ?: '-',
                    'received_quantity' => 0,
                    'issued_quantity' => 0,
                    'returned_quantity' => $quantity,
                    'production_quantity' => 0,
                    'source_balance' => 0,
                    'worker_balance' => 0,
                    'worker_key' => $tag . '|' . $workerId,
                    'remarks' => $return->remarks ?: '-',
                    'created_at' => $return->created_at,
                ]);
            }

            $productionRows = Schema::hasTable('production_tags')
                ? $this->applyReportBranchScope(
                    ProductionTag::with(['production.article', 'production.worker'])->orderByDesc('id'),
                    'production_tags',
                    $selectedBranchIds,
                    $branchContext
                )->get()
                : collect();

            foreach ($productionRows as $productionTag) {
                $tag = (string) ($productionTag->tag ?? '');
                $quantity = (float) ($productionTag->quantity ?? 0);
                $production = $productionTag->production;
                $worker = $productionTag->worker ?: $production?->worker;
                $lot = $fabricByTag->get($tag);
                $workerId = $productionTag->worker_id ?: $production?->worker_id;
                $addWorkerQuantity($tag, $workerId ? (int) $workerId : null, -$quantity);

                $rows->push([
                    'id' => 'production-tag-' . $productionTag->id,
                    'source' => 'Used in Production',
                    'date' => $production?->issue_date?->format('d-M-Y, D') ?? $productionTag->created_at?->format('d-M-Y, D') ?? '-',
                    'date_raw' => $production?->issue_date?->format('Y-m-d') ?? $productionTag->created_at?->format('Y-m-d'),
                    'fabric' => $lot?->fabric?->title ?? '-',
                    'tag' => $tag ?: '-',
                    'supplier_name' => $lot?->supplier?->supplier_name ?? '-',
                    'worker_name' => $worker?->employee_name ?? '-',
                    'article_no' => $production?->article?->article_no ?? '-',
                    'color' => $lot?->color ?: '-',
                    'reference' => $production?->ticket ?: '-',
                    'unit' => $productionTag->unit ?: $lot?->unit ?: '-',
                    'received_quantity' => 0,
                    'issued_quantity' => 0,
                    'returned_quantity' => 0,
                    'production_quantity' => $quantity,
                    'source_balance' => 0,
                    'worker_balance' => 0,
                    'worker_key' => $tag . '|' . ($workerId ?: ''),
                    'remarks' => 'Production ticket: ' . ($production?->ticket ?: '-'),
                    'created_at' => $productionTag->created_at,
                ]);
            }

            $rows = $rows
                ->map(function (array $row) use ($sourceQuantity, $workerQuantity) {
                    $tag = $row['tag'] === '-' ? '' : (string) $row['tag'];
                    $row['source_balance'] = $sourceQuantity[$tag] ?? 0;
                    if (isset($row['worker_key'])) {
                        $row['worker_balance'] = $workerQuantity[$row['worker_key']] ?? 0;
                    }
                    unset($row['worker_key']);

                    return $row;
                })
                ->filter(function (array $row) use ($startDate, $endDate, $fabricSearch, $tagSearch, $supplierSearch, $workerSearch, $articleSearch, $sourceSearch) {
                    $date = !empty($row['date_raw']) ? Carbon::parse($row['date_raw']) : null;
                    if ($startDate && (!$date || $date->lt($startDate))) {
                        return false;
                    }
                    if ($endDate && (!$date || $date->gt($endDate))) {
                        return false;
                    }

                    $matches = fn (string $value, string $needle) => $needle === '' || str_contains(strtolower($value), strtolower($needle));

                    return $matches((string) $row['fabric'], $fabricSearch)
                        && $matches((string) $row['tag'], $tagSearch)
                        && $matches((string) $row['supplier_name'], $supplierSearch)
                        && $matches((string) $row['worker_name'], $workerSearch)
                        && $matches((string) $row['article_no'], $articleSearch)
                        && $matches((string) $row['source'], $sourceSearch);
                })
                ->sortByDesc(fn (array $row) => ($row['date_raw'] ?? '') . ' ' . ($row['created_at'] ?? ''))
                ->values();

            $reportMode = $request->input('mode', Auth::user()?->fabric_report_type ?? 'worker');
            if (!in_array($reportMode, ['worker', 'tag', 'article'], true)) {
                $reportMode = 'worker';
            }

            $summaryRows = $this->summarizeFabricReportRows($rows, $reportMode);

            if ($request->limit) {
                $summaryRows = $summaryRows->take((int) $request->limit)->values();
            }

            $calculations = $this->fabricReportSummaryCalculations($summaryRows);

            $authLayout = $this->getAuthLayout($request->route()->getName(), 'table');
            return response()->json([
                'data' => $summaryRows,
                'authLayout' => $authLayout,
                'branch_scope' => [
                    'mode' => $branchContext['mode'],
                    'labels' => $branchContext['branch_names'],
                ],
                'calculations' => $calculations,
            ]);
        }

        $authLayout = $this->getAuthLayout($request->route()->getName(), 'table');
        $branchContext = app(ModuleBranchService::class)->reportBranchContext('reports_fabric');
        $selectedBranchLabels = $branchContext['branch_names'];
        $fabricReportType = Auth::user()?->fabric_report_type ?? 'worker';
        if (!in_array($fabricReportType, ['worker', 'tag', 'article'], true)) {
            $fabricReportType = 'worker';
        }

        return view('reports.fabric', compact('authLayout', 'selectedBranchLabels', 'fabricReportType'));
    }

    public function physicalQuantity(Request $request, PhysicalQuantityReportService $physicalQuantityReportService)
    {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'manager', 'admin', 'accountant', 'guest', 'store_keeper'])) {
            return $resp;
        }

        $branchContext = app(ModuleBranchService::class)->reportBranchContext('reports_physical_quantity');
        $selectedBranchIds = $branchContext['branch_ids'];
        $selectedBranchLabels = $branchContext['branch_names'];
        $physicalBranding = (object) app(ModuleBranchService::class)->documentBranding(
            'reports_physical_quantity',
            ($branchContext['branding_branch'] ?? null)
                ? (object) ['branch_id' => $branchContext['branding_branch']->id]
                : null
        );
        $includeNullBranchRecords = $branchContext['include_null_main_records'] ?? false;
        $articleOptions = $physicalQuantityReportService->getArticleOptions($selectedBranchIds, $includeNullBranchRecords);
        $mode = $request->input('mode', 'all_articles');
        $reportType = Auth::user()?->physical_quantity_report_type ?? 'altration';
        if (!in_array($mode, ['all_articles', 'article_wise', 'proceed_by_wise'], true)) {
            $mode = 'all_articles';
        }
        if (!in_array($reportType, ['stock', 'altration'], true)) {
            $reportType = 'altration';
        }
        $data = null;

        if ($request->boolean('withData')) {
            $filters = [];
            $canGenerate = true;

            if ($mode === 'article_wise' && $request->filled('article_id')) {
                $filters['article_id'] = (int) $request->input('article_id');
            } elseif ($mode === 'article_wise') {
                $canGenerate = false;
            }

            if ($mode === 'proceed_by_wise' && $request->filled('proceed_by')) {
                $filters['processed_by'] = $request->input('proceed_by');
            } elseif ($mode === 'proceed_by_wise') {
                $canGenerate = false;
            }

            $rows = $canGenerate
                ? $physicalQuantityReportService->getArticleReportRows($filters, $reportType, $selectedBranchIds, $includeNullBranchRecords)
                : collect();
            $maxRowsPerColumn = 58;
            $maxRowsPerPage = $maxRowsPerColumn * 2;

            $pages = $rows->chunk($maxRowsPerPage)->map(function ($pageRows) use ($maxRowsPerColumn) {
                $leftCount = (int) ceil($pageRows->count() / 2);
                $leftCount = min($leftCount, $maxRowsPerColumn);

                return [
                    'left' => $pageRows->take($leftCount)->values(),
                    'right' => $pageRows->slice($leftCount)->values(),
                ];
            })->values();

            $data = [
                'mode' => $mode,
                'report_type' => $reportType,
                'article_id' => $request->input('article_id'),
                'proceed_by' => $request->input('proceed_by'),
                'rows' => $rows,
                'pages' => $pages,
                'generated_at' => now(),
                'branch_scope_label' => implode(', ', $selectedBranchLabels),
            ];
        }

        return view('reports.physical-quantity', compact('articleOptions', 'mode', 'reportType', 'data', 'selectedBranchLabels', 'physicalBranding'));
    }

    private function applyReportBranchScope($query, string $tableName, array $branchIds, array $branchContext, string $branchColumn = 'branch_id')
    {
        if (!Schema::hasColumn($tableName, $branchColumn) || empty($branchIds)) {
            return $query;
        }

        return $query->where(function ($scope) use ($branchIds, $branchContext, $branchColumn) {
            $scope->whereIn($branchColumn, $branchIds);

            if ($branchContext['include_null_main_records'] ?? false) {
                $scope->orWhereNull($branchColumn);
            }
        });
    }

    private function summarizeFabricReportRows($rows, string $mode)
    {
        $groups = [];

        foreach ($rows as $row) {
            $source = (string) ($row['source'] ?? '');
            $fabric = $this->cleanFabricReportValue($row['fabric'] ?? '-');
            $tag = $this->cleanFabricReportValue($row['tag'] ?? '-');
            $unit = $this->cleanFabricReportValue($row['unit'] ?? '-');
            $worker = $this->cleanFabricReportValue($row['worker_name'] ?? '-');
            $article = $this->cleanFabricReportValue($row['article_no'] ?? '-');
            $received = 0.0;
            $used = 0.0;
            $returned = 0.0;

            if ($mode === 'worker') {
                if ($worker === '-') {
                    continue;
                }

                if ($source === 'Issued to Worker') {
                    $received = (float) ($row['issued_quantity'] ?? 0);
                } elseif ($source === 'Used in Production') {
                    $used = (float) ($row['production_quantity'] ?? 0);
                } elseif ($source === 'Returned by Worker') {
                    $returned = (float) ($row['returned_quantity'] ?? 0);
                } else {
                    continue;
                }

                $key = $this->fabricReportKey([$worker, $tag, $unit]);
                $groups[$key] ??= [
                    'mode' => $mode,
                    'worker_name' => $worker,
                    'fabric' => $fabric,
                    'tag' => $tag,
                    'unit' => $unit,
                    'received_quantity' => 0.0,
                    'used_quantity' => 0.0,
                    'returned_quantity' => 0.0,
                    'available_quantity' => 0.0,
                    'details' => [],
                ];
            } elseif ($mode === 'tag') {
                if ($source === 'Received' || $source === 'Inventory In') {
                    $received = (float) ($row['received_quantity'] ?? 0);
                } elseif ($source === 'Returned by Worker') {
                    $received = (float) ($row['returned_quantity'] ?? 0);
                } elseif ($source === 'Used in Production') {
                    $used = (float) ($row['production_quantity'] ?? 0);
                } elseif ($source === 'Inventory Out') {
                    $used = (float) ($row['issued_quantity'] ?? 0);
                } else {
                    continue;
                }

                $key = $this->fabricReportKey([$fabric, $tag, $unit]);
                $groups[$key] ??= [
                    'mode' => $mode,
                    'fabric' => $fabric,
                    'tag' => $tag,
                    'unit' => $unit,
                    'received_quantity' => 0.0,
                    'used_quantity' => 0.0,
                    'returned_quantity' => 0.0,
                    'available_quantity' => 0.0,
                    'details' => [],
                ];
            } else {
                if ($source !== 'Used in Production' || $article === '-') {
                    continue;
                }

                $used = (float) ($row['production_quantity'] ?? 0);
                $key = $this->fabricReportKey([$fabric, $tag, $unit, $article]);
                $groups[$key] ??= [
                    'mode' => $mode,
                    'fabric' => $fabric,
                    'tag' => $tag,
                    'unit' => $unit,
                    'article_no' => $article,
                    'received_quantity' => 0.0,
                    'used_quantity' => 0.0,
                    'returned_quantity' => 0.0,
                    'available_quantity' => 0.0,
                    'details' => [],
                ];
            }

            $groups[$key]['received_quantity'] += $received;
            $groups[$key]['used_quantity'] += $used;
            $groups[$key]['returned_quantity'] += $returned;
            $groups[$key]['available_quantity'] =
                $groups[$key]['received_quantity'] - $groups[$key]['used_quantity'] - $groups[$key]['returned_quantity'];
            $groups[$key]['details'][] = array_merge($row, [
                'summary_received_quantity' => $received,
                'summary_used_quantity' => $used,
                'summary_returned_quantity' => $returned,
            ]);
        }

        return collect($groups)
            ->sortBy([
                fn ($row) => strtolower((string) ($row['worker_name'] ?? $row['fabric'] ?? '')),
                fn ($row) => strtolower((string) ($row['tag'] ?? '')),
                fn ($row) => strtolower((string) ($row['article_no'] ?? '')),
            ])
            ->values();
    }

    private function fabricReportSummaryCalculations($rows): array
    {
        return [
            'total_received' => (float) $rows->sum('received_quantity'),
            'total_issued' => 0.0,
            'total_returned' => (float) $rows->sum('returned_quantity'),
            'total_production_used' => (float) $rows->sum('used_quantity'),
            'total_used' => (float) $rows->sum('used_quantity'),
            'total_available' => (float) $rows->sum('available_quantity'),
            'total_balance' => (float) $rows->sum('available_quantity'),
        ];
    }

    private function cleanFabricReportValue($value): string
    {
        $value = trim((string) $value);

        return $value === '' ? '-' : $value;
    }

    private function fabricReportKey(array $parts): string
    {
        return implode('|', array_map(fn ($part) => strtolower($this->cleanFabricReportValue($part)), $parts));
    }

    private function expenseStatementPayload(int $id): ?array
    {
        $expense = app(ModuleBranchService::class)
            ->applyScope(Expense::with(['supplier:id,supplier_name', 'expenseSetups:id,title']), 'expenses')
            ->find($id);
        if (!$expense) return null;

        if ($this->isSupplierRole() && $expense->supplier_id !== $this->currentSupplier()?->id) {
            abort(403, 'You are not authorized to view this statement record.');
        }

        return [
            'type' => 'expense',
            'data' => $expense->toFormattedArray(),
        ];
    }

    private function inventoryTransactionStatementPayload(int $id): ?array
    {
        $inventory = app(ModuleBranchService::class)
            ->applyScope(InventoryTransaction::with(['supplier:id,supplier_name', 'item'])->where('direction', 'in'), 'inventory_transactions')
            ->find($id);
        if (!$inventory) return null;

        if ($this->isSupplierRole() && $inventory->supplier_id !== $this->currentSupplier()?->id) {
            abort(403, 'You are not authorized to view this statement record.');
        }

        return [
            'type' => 'inventory_transaction',
            'data' => $inventory->toFormattedArray(),
        ];
    }

    private function voucherStatementPayload(int $id): ?array
    {
        $voucher = app(ModuleBranchService::class)->applyScope(Voucher::with([
            'supplier:id,supplier_name',
            'payments.cheque.customer:id,customer_name,city_id',
            'payments.cheque.customer.city:id,short_title,title',
            'payments.slip.customer:id,customer_name,city_id',
            'payments.slip.customer.city:id,short_title,title',
            'payments.program.customer:id,customer_name,city_id',
            'payments.program.customer.city:id,short_title,title',
            'payments.bankAccount.bank:id,short_title',
            'payments.selfAccount.bank:id,short_title',
        ]), 'vouchers')->find($id);
        if (!$voucher) return null;

        if ($this->isSupplierRole() && $voucher->supplier_id !== $this->currentSupplier()?->id) {
            abort(403, 'You are not authorized to view this statement record.');
        }

        return [
            'type' => 'voucher',
            'data' => $voucher->toFormattedArray(),
        ];
    }

    private function supplierPaymentStatementPayload(int $id): ?array
    {
        $payment = app(ModuleBranchService::class)->applyScope(SupplierPayment::with([
            'supplier:id,supplier_name',
            'voucher.supplier:id,supplier_name',
            'bankAccount.bank:id,short_title',
            'selfAccount.bank:id,short_title',
            'program.customer:id,customer_name,city_id',
            'program.customer.city:id,short_title,title',
            'program.customerPayments.paymentClearRecord.bankAccount.bank',
            'cheque.customer:id,customer_name,city_id',
            'cheque.customer.city:id,short_title,title',
            'cheque.paymentClearRecord.bankAccount.bank',
            'cheque.dr',
            'slip.customer:id,customer_name,city_id',
            'slip.customer.city:id,short_title,title',
            'slip.paymentClearRecord.bankAccount.bank',
            'slip.dr',
            'cr',
        ]), 'supplier_payments')->find($id);
        if (!$payment) return null;

        if ($this->isSupplierRole() && $payment->supplier_id !== $this->currentSupplier()?->id) {
            abort(403, 'You are not authorized to view this statement record.');
        }

        return [
            'type' => 'supplier_payment',
            'data' => $payment->toFormattedArray(),
        ];
    }

    private function employeePaymentStatementPayload(int $id): ?array
    {
        $payment = app(ModuleBranchService::class)
            ->applyScope(EmployeePayment::with('employee.type'), 'employee_payments')
            ->find($id);
        if (!$payment) return null;

        return [
            'type' => 'employee_payment',
            'data' => $payment->toFormattedArray(),
        ];
    }

    private function invoiceStatementPayload(int $id): ?array
    {
        $invoice = app(ModuleBranchService::class)->applyScope(Invoice::with([
            'order',
            'shipment',
            'invoiceArticles.article',
            'salesReturns',
            'customer.city',
        ]), 'invoices')->find($id);
        if (!$invoice) return null;

        if ($this->isCustomerRole() && $invoice->customer_id !== $this->currentCustomer()?->id) {
            abort(403, 'You are not authorized to view this statement record.');
        }

        return [
            'type' => 'invoice',
            'data' => $invoice->toFormattedArray(),
        ];
    }

    private function customerPaymentStatementPayload(int $id): ?array
    {
        $payment = app(ModuleBranchService::class)->applyScope(CustomerPayment::whereNotNull('customer_id')
            ->with([
                'customer.city',
                'cheque.supplier',
                'cheque.voucher.supplier.bankAccounts.bank',
                'cheque.cr',
                'slip.supplier',
                'slip.voucher.supplier.bankAccounts.bank',
                'slip.cr',
                'program.subCategory',
                'bankAccount.subCategory',
                'paymentClearRecord.bankAccount.bank',
                'paymentClearRecord.creator',
                'dr',
            ]), 'customer_payments')->find($id);
        if (!$payment) return null;

        if ($this->isCustomerRole() && $payment->customer_id !== $this->currentCustomer()?->id) {
            abort(403, 'You are not authorized to view this statement record.');
        }

        return [
            'type' => 'customer_payment',
            'data' => $payment->toFormattedArray(),
        ];
    }

    private function statementAdjustmentPayload(int $id): ?array
    {
        $adjustment = app(ModuleBranchService::class)
            ->applyScope(StatementAdjustment::with('adjustable'), 'statement_adjustments')
            ->find($id);
        if (!$adjustment) return null;

        return [
            'type' => 'statement_adjustment',
            'data' => $adjustment->toFormattedArray(),
        ];
    }
}
