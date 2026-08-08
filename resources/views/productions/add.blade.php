@extends('app')
@section('title', 'Add Production | ' . $client_company->name)
@section('content')
@php
    $productionType = Auth::user()->production_type;
@endphp

@php
    use Illuminate\View\ComponentAttributeBag;

    $units = app('defaults')->units;
    $units_options = [];
    foreach ($units as $unit) {
        $units_options[$unit] = ['text' => $unit];
    }

    $todayDate = now()->toDateString();

    $materialsAttrs = new ComponentAttributeBag([
        'class' => 'cursor-pointer',
        'onclick' => 'generateMaterialsModal()',
    ]);
    $tagsAttrs = new ComponentAttributeBag([
        'class' => 'cursor-pointer',
        'onclick' => 'generateSelectTagModal()',
    ]);

    $templates = [
        'issue' => [
            'article' => view('components.input', [
                'label' => 'Article',
                'name' => 'article',
                'id' => 'article',
                'disabled' => true,
                'value' => '__ARTICLE_VALUE__',
            ])->render(),
            'materials' => view('components.input', [
                'label' => 'Materials',
                'id' => 'materials',
                'placeholder' => 'Select Materials',
                'required' => true,
                'autocomplete' => 'off',
                'attributes' => $materialsAttrs,
            ])->render() . '<input type="hidden" name="materials" value="" />',
            'quantityEditable' => view('components.input', [
                'label' => 'Quantity',
                'name' => 'article_quantity',
                'id' => 'article_quantity',
                'type' => 'number',
                'placeholder' => 'Enter Quantity',
                'required' => true,
                'oninput' => 'calculateAmount()',
            ])->render(),
            'quantityDisabled' => view('components.input', [
                'label' => 'Quantity',
                'name' => 'article_quantity',
                'id' => 'article_quantity',
                'type' => 'number',
                'value' => '__ARTICLE_QTY__',
                'disabled' => true,
            ])->render(),
            'parts' => view('components.input', [
                'label' => 'Parts',
                'name' => 'partsInView',
                'id' => 'parts',
                'withCheckbox' => true,
                'checkBoxes' => [],
                'required' => true,
            ])->render() . '<input type="hidden" name="parts" id="dbParts" value="[]" /><input type="hidden" name="production_flows" id="productionFlows" value="[]" />',
            'selectRate' => view('components.select', [
                'label' => 'Select Rate',
                'id' => 'select_rate',
                'options' => [],
                'showDefault' => true,
                'onchange' => 'trackSelectRateState(this)',
            ])->render(),
            'titleContainer' => '<div id="titleContainer" class="col-span-full hidden">' . view('components.input', [
                'label' => 'Title',
                'name' => 'title',
                'id' => 'title',
                'placeholder' => 'Enter Title',
            ])->render() . '</div>',
            'rateEditable' => view('components.input', [
                'label' => 'Rate',
                'name' => 'rate',
                'id' => 'rate',
                'placeholder' => 'Rate',
                'oninput' => 'calculateAmount()',
                'dataValidate' => 'numeric',
            ])->render(),
            'amountOptional' => view('components.input', [
                'label' => 'Amount',
                'name' => 'amount',
                'id' => 'amount',
                'readonly' => true,
                'placeholder' => 'Amount',
            ])->render(),
            'issueDate' => view('components.input', [
                'label' => 'Issue Date',
                'name' => 'issue_date',
                'id' => 'issue_date',
                'type' => 'date',
                'required' => true,
                'validateMin' => true,
                'min' => '2024-01-01',
                'validateMax' => true,
                'max' => $todayDate,
            ])->render(),
            'issuedBy' => view('components.input', [
                'label' => 'Issued By',
                'name' => 'issued_by_name',
                'id' => 'issued_by_name',
                'placeholder' => 'Supervisor / issuer name',
                'value' => '__ISSUED_BY_VALUE__',
            ])->render(),
            'receivedBy' => view('components.input', [
                'label' => 'Received By',
                'name' => 'received_by_name',
                'id' => 'received_by_name',
                'placeholder' => 'Worker representative name',
                'value' => '__RECEIVED_BY_VALUE__',
            ])->render(),
        ],
        'receive' => [
            'article' => view('components.input', [
                'label' => 'Article',
                'name' => 'article',
                'id' => 'article',
                'disabled' => true,
                'value' => '__ARTICLE_VALUE__',
            ])->render(),
            'tags' => view('components.input', [
                'label' => 'Tags',
                'id' => 'tags',
                'placeholder' => 'Select Tags',
                'required' => true,
                'autocomplete' => 'off',
                'attributes' => $tagsAttrs,
            ])->render() . '<input type="hidden" name="tags" value="" />',
            'selectRate' => view('components.select', [
                'label' => 'Select Rate',
                'id' => 'select_rate',
                'options' => [],
                'showDefault' => true,
                'onchange' => 'trackSelectRateState(this)',
            ])->render(),
            'titleContainer' => '<div id="titleContainer" class="col-span-full hidden">' . view('components.input', [
                'label' => 'Title',
                'name' => 'title',
                'id' => 'title',
                'placeholder' => 'Enter Title',
            ])->render() . '</div>',
            'title' => view('components.input', [
                'label' => 'Title',
                'name' => 'title',
                'id' => 'title',
                'placeholder' => 'Enter Title',
            ])->render(),
            'rateReadonly' => view('components.input', [
                'label' => 'Rate',
                'name' => 'rate',
                'id' => 'rate',
                'readonly' => true,
                'placeholder' => 'Rate',
                'oninput' => 'calculateAmount()',
                'dataValidate' => 'numeric',
            ])->render(),
            'rateEditable' => view('components.input', [
                'label' => 'Rate',
                'name' => 'rate',
                'id' => 'rate',
                'placeholder' => 'Rate',
                'oninput' => 'calculateAmount()',
                'dataValidate' => 'numeric',
            ])->render(),
            'amountReadonly' => view('components.input', [
                'label' => 'Amount',
                'name' => 'amount',
                'id' => 'amount',
                'readonly' => true,
                'placeholder' => 'Amount',
                'dataValidate' => 'required|amount',
                'oninput' => 'validateInput(this)',
            ])->render(),
            'receiveDateMax' => view('components.input', [
                'label' => 'Receving Date',
                'name' => 'receive_date',
                'id' => 'receive_date',
                'type' => 'date',
                'required' => true,
                'validateMin' => true,
                'min' => '2024-01-01',
                'validateMax' => true,
                'max' => $todayDate,
            ])->render(),
            'receiveDateMin' => view('components.input', [
                'label' => 'Receving Date',
                'name' => 'receive_date',
                'id' => 'receive_date',
                'type' => 'date',
                'required' => true,
                'validateMin' => true,
                'min' => '__MIN_DATE__',
                'validateMax' => true,
                'max' => $todayDate,
            ])->render(),
            'parts' => view('components.input', [
                'label' => 'Parts',
                'id' => 'parts',
                'withCheckbox' => true,
                'checkBoxes' => [],
                'required' => true,
            ])->render() . '<input type="hidden" name="parts" id="dbParts" value="[]" /><input type="hidden" name="production_flows" id="productionFlows" value="[]" />',
            'issuedBy' => view('components.input', [
                'label' => 'Issued By',
                'name' => 'issued_by_name',
                'id' => 'issued_by_name',
                'placeholder' => 'Worker representative name',
                'value' => '__ISSUED_BY_VALUE__',
            ])->render(),
            'receivedBy' => view('components.input', [
                'label' => 'Received By',
                'name' => 'received_by_name',
                'id' => 'received_by_name',
                'placeholder' => 'Supervisor / receiver name',
                'value' => '__RECEIVED_BY_VALUE__',
            ])->render(),
        ],
        'modals' => [
            'quantityInput' => view('components.input', [
                'label' => 'Quantity',
                'name' => 'quantity',
                'id' => 'quantity',
                'type' => 'number',
                'placeholder' => 'Enter quantity',
                'required' => true,
                'oninput' => 'validateInput(this)',
            ])->render(),
        ],
        'unitsSelect' => view('components.select', [
            'label' => 'Units',
            'name' => 'unit',
            'id' => 'unit',
            'options' => $units_options,
            'showDefault' => true,
            'required' => true,
        ])->render(),
    ];

    $productionBranchBranding = $branchBranding ?? [
        'name' => $client_company->name ?? config('app.name'),
        'phone' => $client_company->phone_number ?? '',
        'address' => '',
        'logo_url' => !empty($client_company->logo) ? asset('images/' . $client_company->logo) : '',
    ];
@endphp

    <div class="switch-btn-container flex absolute top-3 md:top-17 left-3 md:left-5 z-40">
        <div class="switch-btn relative flex border-3 border-[var(--secondary-bg-color)] bg-[var(--secondary-bg-color)] rounded-2xl overflow-hidden">
            <!-- Highlight rectangle -->
            <div id="highlight" class="absolute h-full rounded-xl bg-[var(--bg-color)] transition-all duration-300 ease-in-out z-0"></div>

            <!-- Buttons -->
            <button
                id="issueBtn"
                type="button"
                class="relative z-10 px-3.5 md:px-5 py-1.5 md:py-2 cursor-pointer rounded-xl transition-colors duration-300"
                onclick="setProductionType(this, 'issue')"
            >
                <div class="hidden md:block">Issue</div>
                <div class="block md:hidden"><i class="fas fa-cart-shopping text-xs"></i></div>
            </button>
            <button
                id="receiveBtn"
                type="button"
                class="relative z-10 px-3.5 md:px-5 py-1.5 md:py-2 cursor-pointer rounded-xl transition-colors duration-300"
                onclick="setProductionType(this, 'receive')"
            >
                <div class="hidden md:block">Receive</div>
                <div class="block md:hidden"><i class="fas fa-box-open text-xs"></i></div>
            </button>
        </div>
    </div>

    @if ($productionType == 'issue')
        <!-- Main Content -->
        <div class="max-w-4xl mx-auto">
            <x-search-header heading="Issue Production" link linkText="Show Productions" linkHref="{{ route('productions.index') }}"/>
            <x-progress-bar :steps="['Master Information', 'Details']" :currentStep="1" />
        </div>

        <!-- Form -->
        <form id="form" action="{{ route('productions.store') }}" method="post"
            class="bg-[var(--secondary-bg-color)] text-sm rounded-xl shadow-lg p-8 border border-[var(--glass-border-color)]/20 pt-14 max-w-4xl mx-auto relative overflow-hidden">
            @csrf
            <x-form-title-bar title="Issue Production" />

            <div class="step1 space-y-4 ">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    {{-- article --}}
                    <x-input label="Article" id="article" placeholder='Select Article' class="cursor-pointer" addBtnLink="/articles/create" required />
                    <input type="hidden" name="article_id" id="article_id" value="" />

                    {{-- work --}}
                    <x-select
                        label="Work"
                        name="work_id"
                        id="work"
                        :options="[]"
                        showDefault
                        required
                        onchange="trackWorkState(this)"
                        disabled
                    />

                    {{-- worker --}}
                    <x-select
                        label="Worker"
                        name="worker_id"
                        id="worker"
                        :options="[]"
                        showDefault
                        required
                        onchange="trackWorkerState(this)"
                        disabled
                    />

                    {{-- balance --}}
                    <x-input label="Balance" id="balance" placeholder='Balance' disabled/>
                </div>
            </div>

            <div class="step2 space-y-4 hidden">
                <div id="secondStep" class="grid grid-cols-1 sm:grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="col-span-full text-center text-[var(--border-error)]">No Detailes yet.</div>
                </div>
            </div>
        </form>

    @else
        <!-- Main Content -->
        <div class="max-w-4xl mx-auto">
            <x-search-header heading="Add Production" link linkText="Show Productions" linkHref="{{ route('productions.index') }}"/>
            <x-progress-bar :steps="['Master Information', 'Details']" :currentStep="1" />
        </div>

        <!-- Form -->
        <form id="form" action="{{ route('productions.store') }}" method="post"
            class="bg-[var(--secondary-bg-color)] text-sm rounded-xl shadow-lg p-8 border border-[var(--glass-border-color)]/20 pt-14 max-w-4xl mx-auto relative overflow-hidden">
            @csrf
            <x-form-title-bar title="Add Production" />

            <div class="step1 space-y-4 ">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    {{-- ticket --}}
                    <x-select
                        label="Ticket"
                        name="ticket_name"
                        id="ticket"
                        :options="$ticket_options"
                        showDefault
                        onchange="trackTicketState(this)"
                    />

                    {{-- article --}}
                    <x-input label="Article" id="article" placeholder='Select Article' class="cursor-pointer" addBtnLink="/articles/create" required />
                    <input type="hidden" name="article_id" id="article_id" value="" />

                    {{-- work --}}
                    <x-select
                        label="Work"
                        name="work_id"
                        id="work"
                        :options="[]"
                        showDefault
                        required
                        onchange="trackWorkState(this)"
                        disabled
                    />

                    {{-- worker --}}
                    <x-select
                        label="Worker"
                        name="worker_id"
                        id="worker"
                        :options="[]"
                        showDefault
                        required
                        onchange="trackWorkerState(this)"
                        disabled
                    />

                    {{-- balance --}}
                    <x-input label="Balance" id="balance" placeholder='Balance' disabled />
                </div>
            </div>

            <div class="step2 space-y-4 hidden">
                <div id="secondStep" class="grid grid-cols-1 sm:grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="col-span-full text-center text-[var(--border-error)]">No Detailes yet.</div>
                </div>
            </div>
        </form>

    @endif

@endsection


@push('page-scripts')
<script defer src="{{ asset('js/pages/production-ticket-print.js') }}"></script>
<script defer src="{{ asset('js/pages/productions-add.js') }}"></script>
<script>
        window.__productionTicketPrint = {
            company: @json($productionBranchBranding),
        };
        window.__productionsAdd = {
            productionType: @json($productionType),
            csrfToken: @json(csrf_token()),
            templates: @json($templates),
            todayDate: @json($todayDate),
            articles: @json($articles),
            workOptions: @json($work_options),
            workerOptions: @json($worker_options),
            partsByCategorySeason: @json(app('article')->parts),
            units: @json(app('defaults')->units),
            rates: @json($rates),
            tickets: @json($ticket_options ?? []),
            inventoryItems: @json($inventoryItems ?? []),
            currentUserName: @json(Auth::user()?->name),
            isInventoryEnabled: @json(module_enabled('inventory')),
            availabilityUrl: @json(route('productions.availability')),
            workAvailabilityUrl: @json(route('productions.availability.works')),
            printTicket: @json(session('production_ticket_preview')),
        };
    </script>
@endpush
