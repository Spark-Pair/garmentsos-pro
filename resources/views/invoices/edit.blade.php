@extends('app')
@section('title', 'Edit Invoice | ' . $client_company->name)
@section('content')
    @php
        $hideDocumentDiscount = (bool) ($branchBranding['discount_disabled'] ?? false);

        $grossAmount = $invoice->invoiceArticles->sum(function ($line) {
            return ((int) $line->invoice_pcs) * (float) ($line->article?->sales_rate ?? 0);
        });
        $netAmountExisting = (float) $invoice->netAmount;
        $discountPercent = $grossAmount > 0
            ? max(0, round((($grossAmount - $netAmountExisting) / $grossAmount) * 100, 2))
            : 0;

        $initialArticles = $invoiceType === 'order'
            ? $invoice->invoiceArticles->map(function ($line) {
                $article = $line->article;
                $pcsPerPacket = max(1, (int) ($article?->pcs_per_packet ?? 1));
                $packets = $pcsPerPacket > 0 ? ((int) $line->invoice_pcs / $pcsPerPacket) : 0;

                $orderPacketsForArticle = $line->orderArticle?->total_quantity_in_packets;
                $maxPackets = $orderPacketsForArticle !== null
                    ? (int) $orderPacketsForArticle
                    : $packets; // fallback: no linked order article, cap at what's already on the line

                return [
                    'invoice_article_id' => $line->id,
                    'order_article_id' => $line->order_article_id ?? null,
                    'article_id' => $line->article_id,
                    'article' => $article,
                    'description' => $line->description,
                    'total_quantity_in_packets' => $packets,
                    'max_packets' => max($maxPackets, $packets, 1),
                    'ordered_quantity' => (int) $line->invoice_pcs,
                ];
            })->values()
            : collect();
    @endphp

    @php
        $errorAlertTemplate = view('components.alert', ['type' => 'error', 'messages' => '__MESSAGE__'])->render();
    @endphp

    <div class="switch-btn-container hidden absolute top-3 md:top-17 left-3 md:left-5 z-[100]">
        <div class="switch-btn relative flex border-3 border-[var(--secondary-bg-color)] bg-[var(--secondary-bg-color)] rounded-2xl overflow-hidden">
            <div id="highlight" class="absolute h-full rounded-xl bg-[var(--bg-color)] transition-all duration-300 ease-in-out z-0"></div>

            <button id="orderBtn" type="button"
                class="relative z-10 px-3.5 md:px-5 py-1.5 md:py-2 cursor-default rounded-xl transition-colors duration-300"
                disabled>
                <div class="hidden md:block">Order</div>
                <div class="block md:hidden"><i class="fas fa-cart-shopping text-xs"></i></div>
            </button>
        </div>
    </div>

    <!-- Main Content -->
    <!-- Progress Bar -->
    <div class="mb-5 max-w-4xl mx-auto">
        <x-search-header heading="Edit Invoice" link linkText="Show Invoices" linkHref="{{ route('invoices.index') }}"/>
        <x-progress-bar :steps="['Edit Invoice', 'Articles', 'Preview']" :currentStep="1" />
    </div>

    <!-- Form -->
    <form id="form" action="{{ route('invoices.update', ['invoice' => $invoice->id]) }}" method="post"
        class="bg-[var(--secondary-bg-color)] text-sm rounded-xl shadow-lg p-8 border border-[var(--glass-border-color)]/20 pt-14 max-w-4xl mx-auto relative overflow-hidden">
        @csrf
        @method('PUT')
        <x-form-title-bar title="Edit Invoice" />

        @if (session('error'))
            <div class="mb-4 rounded-lg border border-[var(--border-error)] bg-[var(--border-error)]/10 px-4 py-3 text-[var(--border-error)]">
                {{ session('error') }}
            </div>
        @endif

        <!-- Step 1: Invoice Details -->
        <div class="step1 space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                {{-- Invoice No: editable only for developers, readonly for everyone else --}}
                @if ($isDeveloper)
                    <x-input label="Invoice No" name="invoice_no" id="invoice_no"
                        :value="old('invoice_no', $invoice->invoice_no)"
                        required />
                @else
                    <x-input label="Invoice No" name="invoice_no" id="invoice_no"
                        :value="old('invoice_no', $invoice->invoice_no)"
                        required
                        readonly />
                @endif

                <x-input label="Date" name="date" id="date" type="date" :value="old('date', $invoice->date?->format('Y-m-d'))" required />
            </div>

            <div class="grid grid-cols-1 gap-4">
                {{-- Customer: editable select for developers, disabled display for everyone else --}}
                @if ($isDeveloper)
                    <x-select
                        label="Customer"
                        name="customer_id"
                        id="customer_id"
                        :options="$customers"
                        :value="old('customer_id', $invoice->customer_id)"
                        required
                        showDefault
                    />
                @else
                    <x-input label="Customer" id="customer_display"
                        value="{{ $invoice->customer?->customer_name }} | {{ $invoice->customer?->city?->title ?? '-' }}"
                        disabled />
                @endif
            </div>
        </div>

        <!-- Step 2: Articles / Shipment details -->
        <div class="step2 hidden space-y-4">
            @if ($invoiceType === 'order')
                <div class="flex justify-between gap-4">
                    <div class="grow">
                        {{--
                            Order No: developers can pick a different order and reload
                            articles from it. Everyone else keeps the invoice's current
                            order (field shown disabled) and edits the already-loaded
                            article lines below.
                        --}}
                        @if ($isDeveloper)
                            <x-select label="Order Number" name="order_no" id="order_no"
                                :options="$ordersOptions"
                                :value="old('order_no', $invoice->order_no)"
                                required showDefault
                                withButton
                                btnId="loadInvoiceOrderBtn"
                                btnText="Load Articles" />
                        @else
                            <x-input label="Order Number" name="order_no" id="order_no"
                                value="{{ old('order_no', $invoice->order_no) }}" required readonly />
                        @endif
                    </div>
                </div>

                {{-- rate showing --}}
                <div id="article-table" class="w-full text-left text-sm">
                    <div class="flex justify-between items-center bg-[var(--h-bg-color)] rounded-lg py-2 px-4 mb-4">
                        <div class="w-[5%]">#</div>
                        <div class="w-[11%]">Article</div>
                        <div class="w-[11%]">Packets</div>
                        <div class="w-[10%]">Pcs</div>
                        <div class="grow">Decs.</div>
                        <div class="w-[8%]">Pcs/Pkt.</div>
                        <div class="w-[12%] text-right">Rate/Pc</div>
                        <div class="w-[15%] text-right">Amount</div>
                        <div class="w-[15%] text-right">Action</div>
                    </div>
                    <div id="article-list" class="h-[20rem] overflow-y-auto my-scrollbar-2">
                        <div class="text-center bg-[var(--h-bg-color)] rounded-lg py-3 px-4">No Rates Added</div>
                    </div>
                </div>

                <input type="hidden" name="articles_in_invoice" id="articles_in_invoice" value="">
            @else
                <div class="flex justify-between gap-4">
                    <div class="grow grid grid-cols-1 md:grid-cols-2 gap-4">
                        {{--
                            Shipment No: developers can pick a different shipment.
                            Everyone else keeps the invoice's current shipment.
                        --}}
                        @if ($isDeveloper)
                            <x-select label="Shipment Number" name="shipment_no" id="shipment_no"
                                :options="$shipmentsOptions"
                                :value="old('shipment_no', $invoice->shipment_no)"
                                required showDefault />
                        @else
                            <x-input label="Shipment Number" name="shipment_no" id="shipment_no"
                                value="old('shipment_no', $invoice->shipment_no)"
                                required readonly />
                        @endif
                        
                        <x-input label="Carton Count" name="carton_count" id="carton_count" type="number" min="1"
                            :value="old('carton_count', $invoice->carton_count ?? 1)" required />
                    </div>
                </div>

                {{-- Read-only preview of the shipment's article rates, scales live with carton count --}}
                <div id="article-table" class="w-full text-left text-sm">
                    <div class="flex justify-between items-center bg-[var(--h-bg-color)] rounded-lg py-2 px-4 mb-4">
                        <div class="w-[5%]">#</div>
                        <div class="w-[11%]">Article</div>
                        <div class="w-[11%]">Pcs/Ctn</div>
                        <div class="w-[10%]">Pcs</div>
                        <div class="grow">Decs.</div>
                        <div class="w-[8%]">Pcs/Pkt.</div>
                        <div class="w-[12%] text-right">Rate/Pc</div>
                        <div class="w-[20%] text-right">Amount</div>
                    </div>
                    <div id="article-list" class="h-[20rem] overflow-y-auto my-scrollbar-2">
                        <div class="text-center bg-[var(--h-bg-color)] rounded-lg py-3 px-4">No Rates Added</div>
                    </div>
                </div>
            @endif

            <div class="flex w-full grid grid-cols-1 md:grid-cols-2 gap-3 text-sm text-nowrap">
                <div class="total-qty flex justify-between items-center border border-gray-600 cursor-not-allowed rounded-lg py-2 px-4 w-full">
                    <div class="grow">Total Quantity - Pcs</div>
                    <div id="totalQuantityInForm">0</div>
                </div>
                <div class="final {{ $hideDocumentDiscount ? 'hidden' : '' }} flex justify-between items-center border border-gray-600 cursor-not-allowed rounded-lg py-2 px-4 w-full">
                    <div class="grow">Gross Amount - Rs.</div>
                    <div id="totalAmountInForm">0.0</div>
                </div>
                <div class="final {{ $hideDocumentDiscount ? 'hidden' : '' }} flex justify-between items-center border border-gray-600 cursor-not-allowed rounded-lg py-2 px-4 w-full">
                    <div class="grow">Discount - %</div>
                    <div id="dicountInForm">0</div>
                </div>
                <div class="final flex justify-between items-center border border-gray-600 cursor-not-allowed rounded-lg py-2 px-4 w-full">
                    <div class="grow">Net Amount - Rs.</div>
                    <input type="text" name="netAmount" id="netAmountInForm" value="{{ old('netAmount', $invoice->netAmount) }}" readonly
                        class="text-right bg-transparent outline-none w-1/2 border-none" />
                </div>
            </div>
        </div>

        <!-- Step 3: Preview -->
        <div class="step3 hidden space-y-4 text-black h-[35rem] overflow-y-auto my-scrollbar-2 bg-white rounded-md">
            <div id="preview-container" class="w-[148mm] h-[210mm] mx-auto overflow-hidden relative">
                <div id="preview" class="preview w-[148mm] h-[210mm] gos-a5-document gos-a5-invoice overflow-hidden flex flex-col">
                    <h1 class="text-[var(--border-error)] font-medium text-center mt-5">No Preview available.</h1>
                </div>
            </div>
        </div>
    </form>

@endsection


@push('page-scripts')
<script defer src="{{ asset('js/pages/invoices-edit.js') }}?v={{ config('app.version', 'local') }}.invoice-edit-2"></script>
<script>
        window.__invoicesEdit = {
            invoiceId: @json($invoice->id),
            invoiceType: @json($invoiceType),
            isDeveloper: @json($isDeveloper),
            invoiceNo: @json(old('invoice_no', $invoice->invoice_no)),
            invoiceDate: @json(old('date', $invoice->date?->format('Y-m-d'))),
            orderNumber: @json(old('order_no', $invoice->order_no)),
            shipmentNumber: @json(old('shipment_no', $invoice->shipment_no)),
            cartonCount: @json(old('carton_count', $invoice->carton_count ?? 1)),
            csrfToken: @json(csrf_token()),
            companyData: @json($branchBranding ?? $client_company),
            companyLogoBase: @json(asset('images')),
            discountDisabled: @json($hideDocumentDiscount),
            discount: @json($discountPercent),
            netAmount: @json(old('netAmount', $invoice->netAmount)),
            customer: @json($invoice->customer),
            customers: @json($customers),
            deliverTo: @json($invoice->deliver_to ?? ''),
            articles: @json($initialArticles),
            shipmentArticles: @json($invoice->shipment?->articles ?? []),
            errorAlertTemplate: @json($errorAlertTemplate),
        };
    </script>
@endpush