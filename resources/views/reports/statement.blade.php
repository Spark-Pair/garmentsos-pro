@extends('app')
@section('title', 'Statement | ' . $client_company->name)
@section('content')
@php
    $companyData = $statementBranding ?? (object) app(\App\Services\Branches\ModuleBranchService::class)->documentBranding('reports');
    $statementBranches = collect($statementBranches ?? []);
    $selectedBranchIds = collect($selectedBranchIds ?? $statementBranches->pluck('id')->all())->map(fn($id) => (int) $id)->all();
    $selectedBranchLabels = $selectedBranchLabels ?? ['All Branches'];
    $statementType = Auth::user()->statement_type ?? 'general';
    if (!in_array($statementType, ['summarized', 'detailed', 'general'], true)) {
        $statementType = 'general';
    }

    $portalRole = Auth::user()->role ?? '';
    $portalCategory = null;
    $portalId = null;
    if ($portalRole === 'customer') {
        $portalCategory = 'customer';
        $portalId = \App\Models\Customer::where('user_id', Auth::id())->value('id');
    } elseif ($portalRole === 'supplier') {
        $portalCategory = 'supplier';
        $portalId = \App\Models\Supplier::where('user_id', Auth::id())->value('id');
    }
@endphp
    <div class="switch-btn-container flex absolute top-3 md:top-17 left-3 md:left-5 z-4">
        <div class="switch-btn relative flex border-3 border-[var(--secondary-bg-color)] bg-[var(--secondary-bg-color)] rounded-2xl overflow-hidden">
            <!-- Highlight rectangle -->
            <div id="highlight" class="absolute h-full rounded-xl bg-[var(--bg-color)] transition-all duration-300 ease-in-out z-0"></div>

            <!-- Buttons -->
            <button id="generalBtn" type="button" class="relative z-10 px-3.5 md:px-5 py-1.5 md:py-2 cursor-pointer rounded-xl transition-colors duration-300" onclick="setVoucherType(this, 'general')">
                <div class="hidden md:block">General</div>
                <div class="block md:hidden"><i class="fas fa-layer-group text-xs"></i></div>
            </button>
            <button id="summarizedBtn" type="button" class="relative z-10 px-3.5 md:px-5 py-1.5 md:py-2 cursor-pointer rounded-xl transition-colors duration-300" onclick="setVoucherType(this, 'summarized')">
                <div class="hidden md:block">Summarized</div>
                <div class="block md:hidden"><i class="fas fa-cart-shopping text-xs"></i></div>
            </button>
            <button id="detailedBtn" type="button" class="relative z-10 px-3.5 md:px-5 py-1.5 md:py-2 cursor-pointer rounded-xl transition-colors duration-300" onclick="setVoucherType(this, 'detailed')">
                <div class="hidden md:block">Detailed</div>
                <div class="block md:hidden"><i class="fas fa-box-open text-xs"></i></div>
            </button>
        </div>
    </div>


    <!-- Main Content -->
    <!-- Progress Bar -->
    <div class="mb-5 max-w-4xl mx-auto">
        <x-search-header heading="Statement"/>
        <x-progress-bar :steps="['Generate Statement', 'Preview']" :currentStep="1" />
    </div>

    <!-- Form -->
    <form id="form" action="{{ route('orders.store') }}" method="post" enctype="multipart/form-data"
        class="bg-[var(--secondary-bg-color)] text-sm rounded-xl shadow-lg p-8 border border-[var(--glass-border-color)]/20 pt-14 max-w-4xl mx-auto  relative overflow-hidden">
        @csrf
        <x-form-title-bar title="Generate Statement" />

        <!-- Step 1: Generate Staement -->
        <div class="step1 space-y-4 ">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                {{-- category --}}
                <x-select
                    label="Category"
                    name="category"
                    id="category"
                    :options="[
                        'customer' => ['text' => 'Customer'],
                        'supplier' => ['text' => 'Supplier'],
                        'employee' => ['text' => 'Employee'],
                        'bank_account' => ['text' => 'Bank Account'],
                    ]"
                    showDefault
                    onchange="fetchNames(this.value)"
                />

                {{-- name --}}
                <x-select
                    label="Name"
                    name="name"
                    id="nameSelect"
                    :options="[]"
                    showDefault
                    onchange="nameChanged(this)"
                />

                <div class="col-span-full grid grid-cols-3 gap-4">
                    {{-- RangeFilter --}}
                    <x-select
                        label="Range"
                        name="range"
                        id="range"
                        :options="[
                            'current_month' => ['text' => 'Current Month'],
                            'last_month' => ['text' => 'Last Month'],
                            'last_three_months' => ['text' => 'Last Three Months'],
                            'last_six_months' => ['text' => 'Last Six Months'],
                            'custom' => ['text' => 'Custom'],
                        ]"
                        showDefault
                        disabled
                        onchange="applyRange(this.value)"
                    />

                    <!-- date_from -->
                    <x-input
                        label="Date From"
                        name="date_from"
                        id="date_from"
                        validateMax
                        max="{{ now()->toDateString() }}"
                        type="date"
                        onchange="updateDateConstraints()"
                    />

                    <!-- date_to -->
                    <x-input
                        label="Date To"
                        name="date_to"
                        id="date_to"
                        validateMax
                        max="{{ now()->toDateString() }}"
                        type="date"
                        onchange="updateDateConstraints()"
                    />
                </div>

            </div>
        </div>

        <!-- Step 2: view order -->
        <div class="step2 hidden space-y-4 text-black h-[35rem] overflow-auto my-scrollbar-2">
            @if (isset($data))
                @php
                    $statements = collect($data['statements']);
                    $hasOpeningBalanceEntryRow = $statements->contains(function ($statement) {
                        return str_starts_with((string) ($statement['reff_no'] ?? ''), 'OB-')
                            || strcasecmp((string) ($statement['method'] ?? ''), 'Opening Balance') === 0;
                    });
                    $openingBalanceRow = collect([[
                        'type' => 'opening_balance',
                        'date' => null,
                        'reff_no' => '-',
                        'method' => '-',
                        'description' => 'Opening Balance',
                        'bill' => 0,
                        'payment' => 0,
                        'source' => null,
                    ]]);
                    $shouldUseActualOpeningEntryOnly = ((float) ($data['opening_balance'] ?? 0)) == 0.0 && $hasOpeningBalanceEntryRow;
                    $statementRows = $shouldUseActualOpeningEntryOnly ? $statements : $openingBalanceRow->merge($statements);
                    $topSummaryLabel = match ($data['category'] ?? null) {
                        'customer' => 'Total Order Balance',
                        'employee' => 'Total Employee Balance',
                        default => 'Total Pending Payment',
                    };
                    $topSummaryValue = match ($data['category'] ?? null) {
                        'customer' => $data['totals']['order_balance'] ?? 0,
                        'employee' => $data['totals']['balance'] ?? 0,
                        default => $data['totals']['pending_payment'] ?? 0,
                    };
                    $statementPartyCity = data_get($data, 'customer.city.title');
                    $statementPartyAddress = data_get($data, 'customer.address');
                    $datedStatementRows = $statements
                        ->pluck('date')
                        ->filter()
                        ->map(fn ($date) => $date instanceof \DateTimeInterface ? \Carbon\Carbon::instance($date) : \Carbon\Carbon::parse($date))
                        ->sort()
                        ->values();
                    $statementDateLabel = $datedStatementRows->isNotEmpty()
                        ? $datedStatementRows->first()->format('d-M-Y') . ' - ' . $datedStatementRows->last()->format('d-M-Y')
                        : $data['date'];
                    $balance = $data['opening_balance'];

                    // Pehle page ke liye 30 rows lo
                    $firstPage = $statementRows->take(30);

                    $otherPages = $statementRows->skip(30)->chunk(33);
                @endphp

                {{-- First Page (30 rows) --}}
                <div class="statement-preview-toolbar sticky top-0 z-20 mb-2 hidden justify-end gap-2 bg-white/95 p-2 text-black shadow-sm md:hidden">
                    <button type="button" class="statement-zoom-btn rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-semibold" data-statement-zoom="out">-</button>
                    <button type="button" class="statement-zoom-btn rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-semibold" data-statement-zoom="reset">100%</button>
                    <button type="button" class="statement-zoom-btn rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-semibold" data-statement-zoom="in">+</button>
                </div>
                <div id="preview-container" class="statement-preview-container h-full relative">
                    <div class="preview-page w-[210mm] h-[297mm] mx-auto overflow-hidden relative bg-white rounded-md p-2">
                        <div id="preview" class="preview flex flex-col h-full">
                            <div id="preview-document" class="preview-document flex flex-col h-full">
                                {{-- Company Logo + Banner --}}
                                <div id="preview-banner" class="preview-banner w-full flex justify-between items-center pl-5 pr-8">
                                    <div class="flex items-center gap-3">
                                        @if($companyData->logo_url)
                                            <div class="h-[3.50rem] w-[13.5rem] flex items-center justify-center gap-2.5">
                                                <img
                                                    src="{{ $companyData->logo_url }}"
                                                    alt="garmentsos-pro"
                                                    class="max-h-full max-w-full object-contain"
                                                />
                                                @if($companyData->logo_text)
                                                    <h1 class="text-lg font-bold tracking-wide">
                                                        {{ $companyData->logo_text }}
                                                    </h1>
                                                @endif
                                            </div>
                                        @endif
                                    </div>
                                    <div class="right">
                                        <div>
                                            <h1 class="text-2xl font-medium text-[var(--primary-color)] pr-2 capitalize">{{ $data['category' ]}} Statement</h1>
                                            <div class='mt-1 text-sm'>{{ $companyData->phone_number }}</div>
                                        </div>
                                    </div>
                                </div>

                                <hr class="w-full my-2 border-gray-700">

                                {{-- Header Info --}}
                                <div id="preview-header" class="preview-header w-full px-5 text-black font-medium">
                                    <div class="flex h-[3.25rem] items-center justify-between gap-3 overflow-hidden text-[12px] leading-[1.15]">
                                        <div class="w-[29%] shrink-0 space-y-1 overflow-hidden">
                                            <div class="flex gap-1 min-w-0">
                                                <span class="font-semibold shrink-0">Date:</span>
                                                <span class="">{{ $statementDateLabel }}</span>
                                            </div>
                                            <div class="flex gap-1 min-w-0">
                                                <span class="font-semibold shrink-0">Branches:</span>
                                                <span class="">{{ $data['branch_scope_label'] ?? implode(', ', $selectedBranchLabels) }}</span>
                                            </div>
                                            <div class="flex gap-1 min-w-0">
                                                <span class="font-semibold shrink-0">{{ $topSummaryLabel }}:</span>
                                                <span class="">{{ \App\Support\Money::format($topSummaryValue) }}</span>
                                            </div>
                                        </div>

                                        <div class="min-w-0 flex-1 text-center overflow-hidden">
                                            @if (($data['category'] ?? null) === 'customer')
                                                <div class="name capitalize font-semibold text-[12px] leading-none ">{{ $data['name'] }} | {{ $statementPartyCity ?: '-' }}</div>
                                                <div class="mx-auto mt-1 max-w-full text-[10px] font-semibold leading-[1.05] line-clamp-2" title="{{ $statementPartyAddress ?: '-' }}">{{ $statementPartyAddress ?: '-' }}</div>
                                            @else
                                                <div class="name capitalize font-semibold text-[12px] leading-none ">{{ $data['name'] }}</div>
                                            @endif
                                        </div>

                                        <div class="w-[34%] shrink-0 space-y-1 overflow-hidden pr-1">
                                            <div class="flex justify-end gap-1.5 min-w-0">
                                                <span class="font-semibold shrink-0">Total Bill:</span>
                                                <span class="text-right tabular-nums whitespace-nowrap">{{ \App\Support\Money::format($data['totals']['bill']) }}</span>
                                            </div>
                                            <div class="flex justify-end gap-1.5 min-w-0">
                                                <span class="font-semibold shrink-0">Total Payment:</span>
                                                <span class="text-right tabular-nums whitespace-nowrap">{{ \App\Support\Money::format($data['totals']['payment']) }}</span>
                                            </div>
                                            <div class="flex justify-end gap-1.5 min-w-0">
                                                <span class="font-semibold shrink-0">Closing Balance:</span>
                                                <span class="text-right tabular-nums whitespace-nowrap">{{ \App\Support\Money::format($data['closing_balance']) }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <hr class="w-full my-2 border-gray-700">

                                {{-- Table --}}
                                <div id="preview-body" class="preview-body w-[97%] grow mx-auto">
                                    <div class="preview-table w-full">
                                        <div class="table w-full border border-gray-700 rounded-lg p-1 text-xs">
                                            {{-- Table Header --}}
                                            <div class="thead w-full">
                                                <div class="tr flex justify-between w-full px-1.5 py-1.5 bg-[var(--primary-color)] text-white text-center rounded-md">
                                                    <div class="th font-medium w-[2.5%]">#</div>
                                                    <div class="th font-medium w-[11.5%]">Date</div>
                                                    @if(in_array($statementType, ['detailed', 'general']))
                                                        <div class="th font-medium w-[10%]">Reff. No.</div>
                                                        <div class="th font-medium w-[10%]">Method</div>
                                                        <div class="th font-medium w-[33%]">Description</div>
                                                    @endif
                                                    <div class="th font-medium w-[10%]">Bill</div>
                                                    <div class="th font-medium w-[10%]">Payment</div>
                                                    <div class="th font-medium w-[10%]">Balance</div>
                                                </div>
                                            </div>

                                            {{-- Table Body --}}
                                            <div id="tbody" class="tbody w-full mt-1.5 pb-1">
                                                @foreach ($firstPage as $statement)
                                                    @php
                                                        $isOpeningBalanceRow = ($statement['type'] ?? null) === 'opening_balance';
                                                        $isOpeningBalanceEntryRow = !$isOpeningBalanceRow && (
                                                            str_starts_with((string) ($statement['reff_no'] ?? ''), 'OB-')
                                                            || strcasecmp((string) ($statement['method'] ?? ''), 'Opening Balance') === 0
                                                        );

                                                        if ($statement['type'] == 'invoice') {
                                                            $balance += $statement['bill'];
                                                        } elseif ($statement['type'] == 'payment') {
                                                            $balance -= $statement['payment'];
                                                        }

                                                        $statementSource = $statement['source'] ?? null;
                                                        $isStatementClickable = !empty($statementSource);
                                                    @endphp
                                                    <div>
                                                        @unless($loop->first)
                                                            <hr class="w-full my-2 border-gray-700 border-dashed">
                                                        @endunless
                                                        <div
                                                            class="tr flex justify-between w-full px-2.5 text-center gap-1 {{ $isStatementClickable ? 'statement-record-trigger cursor-pointer rounded-md transition-colors hover:bg-slate-100/80' : '' }}"
                                                            @if($isStatementClickable)
                                                                data-source='@json($statementSource)'
                                                                role="button"
                                                                tabindex="0"
                                                            @endif
                                                        >
                                                            <div class="td font-semibold w-[2.5%]">{{ $loop->iteration }}.</div>
                                                            <div class="td font-medium w-[11.5%]">{{ $isOpeningBalanceRow ? ($statementType === 'summarized' ? 'Opening Balance' : '-') : $statement['date']->format('d-M-Y') }}</div>
                                                            @if(in_array($statementType, ['detailed', 'general']))
                                                                <div class="td font-medium w-[10%]">{{ $statement['reff_no'] }}</div>
                                                                <div class="td font-medium w-[10%] capitalize">{{ $isOpeningBalanceEntryRow ? 'Opening Entry' : ($statement['method'] ?? "-") }}</div>
                                                                <div class="td font-medium w-[33%] text-nowrap  {{ $isOpeningBalanceRow ? 'text-left font-semibold' : '' }}">{{ $isOpeningBalanceEntryRow ? 'Opening Balance Entry' : ($statement['description'] ?? "-") }}</div>
                                                            @endif
                                                            <div class="td font-medium w-[10%]">{{ \App\Support\Money::format($statement['bill'] ?? 0) }}</div>
                                                            <div class="td font-medium w-[10%]">{{ \App\Support\Money::format($statement['payment'] ?? 0) }}</div>
                                                            <div class="td font-medium w-[10%]">{{ \App\Support\Money::format($balance) }}</div>
                                                        </div>
                                                    </div>
                                                @endforeach
                                                @if ($otherPages->isEmpty())
                                                    <hr class="w-full my-2 border-gray-700 border-dashed">
                                                    <div class="tr flex justify-between w-full px-2.5 gap-1 text-center font-bold rounded-md">
                                                        <div class="td w-[2.5%]"></div>
                                                        <div class="td w-[12.5%] text-left">Total</div>
                                                        @if(in_array($statementType, ['detailed', 'general']))
                                                            <div class="td w-[10%]"></div>
                                                            <div class="td w-[10%]"></div>
                                                            <div class="td w-[33%]"></div>
                                                        @endif
                                                        <div class="td w-[10%]">{{ \App\Support\Money::format($data['totals']['bill']) }}</div>
                                                        <div class="td w-[10%]">{{ \App\Support\Money::format($data['totals']['payment']) }}</div>
                                                        <div class="td w-[10%]">{{ \App\Support\Money::format($data['closing_balance']) }}</div>
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                {{-- Footer --}}
                                <hr class="w-full my-2 border-gray-700">
                                <div class="tfooter flex w-full text-sm px-4 justify-between text-gray-800 leading-none text-xs">
                                    <p>Powered by SparkPair &copy; {{ now()->year }} SparkPair | +92 316 5825495</p>
                                    <p>Page 1 of {{ 1 + $otherPages->count() }}</p>
                                </div>

                            </div>
                        </div>
                    </div>

                    {{-- Other Pages (33 rows each) --}}
                    @foreach ($otherPages as $pageIndex => $chunk)
                        <hr class="w-full my-2 border-gray-500">
                        <div class="preview-page w-[210mm] h-[297mm] mx-auto overflow-hidden relative bg-white rounded-md p-2">
                            <div id="preview" class="preview flex flex-col h-full">
                                <div id="preview-document" class="preview-document flex flex-col h-full">
                                    {{-- Banner --}}
                                    <div id="preview-banner" class="preview-banner w-full flex justify-between items-center pl-5 pr-8">
                                        <div class="flex items-center gap-3">
                                            @if($companyData->logo_url)
                                                <div class="h-[3.50rem] w-[13.5rem] flex items-center justify-center gap-2.5">
                                                    <img
                                                        src="{{ $companyData->logo_url }}"
                                                        alt="garmentsos-pro"
                                                        class="max-h-full max-w-full object-contain"
                                                    />
                                                    @if($companyData->logo_text)
                                                        <h1 class="text-lg font-bold tracking-wide">
                                                            {{ $companyData->logo_text }}
                                                        </h1>
                                                    @endif
                                                </div>
                                            @endif
                                        </div>
                                        <div class="right">
                                            <div>
                                                <h1 class="text-xl font-medium text-[var(--primary-color)] pr-2 leading-none capitalize">{{ $data['category' ]}} Statement</h1>
                                                <div class='text-xs'>{{ $data['name'] }}</div>
                                                @if (($data['category'] ?? null) === 'customer')
                                                    <div class="text-[10px] leading-tight text-gray-800">{{ $statementPartyCity ?: '-' }}</div>
                                                    <div class="text-[10px] leading-tight text-gray-800  max-w-[12rem]">{{ $statementPartyAddress ?: '-' }}</div>
                                                @endif
                                            </div>
                                        </div>
                                    </div>

                                    <hr class="w-full my-2 border-gray-700">

                                    {{-- Table --}}
                                    <div id="preview-body" class="preview-body w-[97%] grow mx-auto">
                                        <div class="preview-table w-full">
                                            <div class="table w-full border border-gray-700 rounded-lg p-1 text-xs">
                                                {{-- Table Header --}}
                                                <div class="thead w-full">
                                                    <div class="tr flex justify-between w-full px-1.5 py-1.5 bg-[var(--primary-color)] text-white text-center rounded-md">
                                                        <div class="th font-medium w-[2.5%]">#</div>
                                                        <div class="th font-medium w-[11.5%]">Date</div>
                                                        @if(in_array($statementType, ['detailed', 'general']))
                                                            <div class="th font-medium w-[10%]">Reff. No.</div>
                                                            <div class="th font-medium w-[10%]">Method</div>
                                                            <div class="th font-medium w-[33%]">Description</div>
                                                        @endif
                                                        <div class="th font-medium w-[10%]">Bill</div>
                                                        <div class="th font-medium w-[10%]">Payment</div>
                                                        <div class="th font-medium w-[10%]">Balance</div>
                                                    </div>
                                                </div>

                                                {{-- Table Body --}}
                                                <div id="tbody" class="tbody w-full mt-1.5 pb-1">
                                                    @foreach ($chunk as $statement)
                                                        @php
                                                            $isOpeningBalanceRow = ($statement['type'] ?? null) === 'opening_balance';
                                                            $isOpeningBalanceEntryRow = !$isOpeningBalanceRow && (
                                                                str_starts_with((string) ($statement['reff_no'] ?? ''), 'OB-')
                                                                || strcasecmp((string) ($statement['method'] ?? ''), 'Opening Balance') === 0
                                                            );

                                                            if ($statement['type'] == 'invoice') {
                                                                $balance += $statement['bill'];
                                                            } elseif ($statement['type'] == 'payment') {
                                                                $balance -= $statement['payment'];
                                                            }

                                                            $statementSource = $statement['source'] ?? null;
                                                            $isStatementClickable = !empty($statementSource);
                                                        @endphp
                                                        <div>
                                                            @unless($loop->first)
                                                                <hr class="w-full my-2 border-gray-700 border-dashed">
                                                            @endunless
                                                            <div
                                                                class="tr flex justify-between w-full px-2.5 gap-1 text-center {{ $isStatementClickable ? 'statement-record-trigger cursor-pointer rounded-md transition-colors hover:bg-slate-100/80' : '' }}"
                                                                @if($isStatementClickable)
                                                                    data-source='@json($statementSource)'
                                                                    role="button"
                                                                    tabindex="0"
                                                                @endif
                                                            >
                                                                <div class="td font-semibold w-[2.5%]">{{ $loop->iteration + 30 + ($pageIndex * 33) }}.</div>
                                                                <div class="td font-medium w-[11.5%]">{{ $isOpeningBalanceRow ? ($statementType === 'summarized' ? 'Opening Balance' : '-') : $statement['date']->format('d-M-Y') }}</div>
                                                                @if(in_array($statementType, ['detailed', 'general']))
                                                                    <div class="td font-medium w-[10%]">{{ $statement['reff_no'] }}</div>
                                                                    <div class="td font-medium w-[10%] capitalize">{{ $isOpeningBalanceEntryRow ? 'Opening Entry' : ($statement['method'] ?? "-") }}</div>
                                                                    <div class="td font-medium w-[33%] text-nowrap overflow-hidden {{ $isOpeningBalanceRow ? 'text-left font-semibold' : '' }}">{{ $isOpeningBalanceEntryRow ? 'Opening Balance Entry' : ($statement['description'] ?? "-") }}</div>
                                                                @endif
                                                                <div class="td font-medium w-[10%]">{{ \App\Support\Money::format($statement['bill'] ?? 0) }}</div>
                                                                <div class="td font-medium w-[10%]">{{ \App\Support\Money::format($statement['payment'] ?? 0) }}</div>
                                                                <div class="td font-medium w-[10%]">{{ \App\Support\Money::format($balance) }}</div>
                                                            </div>
                                                        </div>
                                                    @endforeach
                                                    @if ($loop->last)
                                                        <hr class="w-full my-2 border-gray-700 border-dashed">
                                                        <div class="tr flex justify-between w-full px-2.5 gap-1 text-center font-bold rounded-md">
                                                            <div class="td w-[2.5%]"></div>
                                                            <div class="td w-[12.5%] text-left">Total</div>
                                                            @if(in_array($statementType, ['detailed', 'general']))
                                                                <div class="td w-[10%]"></div>
                                                                <div class="td w-[10%]"></div>
                                                                <div class="td w-[33%]"></div>
                                                            @endif
                                                            <div class="td w-[10%]">{{ \App\Support\Money::format($data['totals']['bill']) }}</div>
                                                            <div class="td w-[10%]">{{ \App\Support\Money::format($data['totals']['payment']) }}</div>
                                                            <div class="td w-[10%]">{{ \App\Support\Money::format($data['closing_balance']) }}</div>
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Footer --}}
                                    <hr class="w-full my-2 border-gray-700">
                                    <div class="tfooter flex w-full text-sm px-4 justify-between text-gray-800 leading-none text-xs">
                                        <p>Powered by SparkPair &copy; {{ now()->year }} SparkPair | +92 316 5825495</p>
                                        <p>Page {{ $pageIndex + 2 }} of {{ 1 + $otherPages->count() }}</p>
                                    </div>

                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </form>

@endsection


@push('page-scripts')
<script defer src="{{ asset('js/pages/reports-statement.js') }}"></script>
<script>
    window.__reportsStatement = {
        statementType: @json($statementType),
        csrfToken: @json(csrf_token()),
        setTypeUrl: @json(url('/set-statement-type')),
        getNamesUrl: @json(route('reports.statement.get-names')),
        statementUrl: @json(route('reports.statement')),
        recordDetailsUrl: @json(route('reports.statement.record-details')),
        companyData: @json($companyData),
        companyLogoBase: @json(url('/') . '/'),
        portal: {
            role: @json($portalRole),
            category: @json($portalCategory),
            id: @json($portalId),
        },
    };
</script>
@endpush
