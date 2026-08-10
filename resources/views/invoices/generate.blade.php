@extends('app')
@section('title', 'Generate Invoice | ' . $client_company->name)
@section('content')
    @php
        $hideDocumentDiscount = (bool) ($branchBranding['discount_disabled'] ?? false);
    @endphp

    @php
        $searchFields = [
            "Customer Name" => [
                "id" => "customer_name",
                "type" => "text",
                "placeholder" => "Enter customer name",
                "dataFilterPath" => "customer_name",
            ],
            "Urdu Title" => [
                "id" => "urdu_title",
                "type" => "text",
                "placeholder" => "Enter Urdu title",
                "dataFilterPath" => "urdu_title",
            ],
            "City" => [
                "id" => "city",
                "type" => "text",
                "placeholder" => "Enter city",
                "dataFilterPath" => "city.title",
            ],
            "Category" => [
                "id" => "category",
                "type" => "select",
                "options" => [
                    'whole_sale' => ['text' => 'Whole Sale'],
                    'shopkeeper' => ['text' => 'Shopkeeper'],
                    'person' => ['text' => 'Person'],
                    'garments' => ['text' => 'Garments'],
                ],
                "dataFilterPath" => "category",
            ],
            "Phone Number" => [
                "id" => "phone_number",
                "type" => "text",
                "placeholder" => "Enter phone number",
                "dataFilterPath" => "phone_number",
            ],
        ];
    @endphp

    @php
        ob_start();
    @endphp
        @foreach ($searchFields as $search_field => $value)
            @if ($value['type'] == "select")
                <x-select label="{{ $search_field }}" id="{{ $value['id'] }}" :options="$value['options']" :dataClearable="true" dataFilterPath="{{ $value['dataFilterPath'] }}" required showDefault />
            @elseif ($value['type'] == "text")
                <x-input label="{{ $search_field }}" id="{{ $value['id'] }}" type="{{ $value['type'] }}" :dataClearable="true" dataFilterPath="{{ $value['dataFilterPath'] }}" required placeholder="{{ $value['placeholder'] }}" />
            @elseif (isset($value['type2']) && isset($value['id2']))
                <x-input label="{{ $search_field }}" id="{{ $value['id'] }}" type="{{ $value['type'] }}" dualInput id2="{{ $value['id2'] }}" type2="{{ $value['type2'] }}" :dataClearable="true" dataFilterPath="{{ $value['dataFilterPath'] }}" required/>
            @else
                <x-input label="{{ $search_field }}" id="{{ $value['id'] }}" type="{{ $value['type'] }}" :dataClearable="true" dataFilterPath="{{ $value['dataFilterPath'] }}" required/>
            @endif
        @endforeach
    @php
        $searchFieldsHtml = trim(ob_get_clean());
        $errorAlertTemplate = view('components.alert', ['type' => 'error', 'messages' => '__MESSAGE__'])->render();
    @endphp

    <div class="switch-btn-container absolute top-3 md:top-17 left-3 md:left-5 z-[100]">
        <div class="switch-btn relative flex border-3 border-[var(--secondary-bg-color)] bg-[var(--secondary-bg-color)] rounded-2xl overflow-hidden">
            <!-- Highlight rectangle -->
            <div id="highlight" class="absolute h-full rounded-xl bg-[var(--bg-color)] transition-all duration-300 ease-in-out z-0"></div>

            <!-- Buttons -->
            <button
                id="orderBtn"
                type="button"
                class="relative z-10 px-3.5 md:px-5 py-1.5 md:py-2 cursor-pointer rounded-xl transition-colors duration-300"
                onclick="setInvoiceType(this, 'order')"
            >
                <div class="hidden md:block">Order</div>
                <div class="block md:hidden"><i class="fas fa-cart-shopping text-xs"></i></div>
            </button>
            <button
                id="shipmentBtn"
                type="button"
                class="relative z-10 px-3.5 md:px-5 py-1.5 md:py-2 cursor-pointer rounded-xl transition-colors duration-300"
                onclick="setInvoiceType(this, 'shipment')"
            >
                <div class="hidden md:block">Shipment</div>
                <div class="block md:hidden"><i class="fas fa-truck text-xs"></i></div>
            </button>
        </div>
    </div>

    <!-- Main Content -->
    <!-- Progress Bar -->
    <div class="mb-5 max-w-4xl mx-auto">
        <x-search-header heading="Generate Invoice" link linkText="Show Invoices" linkHref="{{ route('invoices.index') }}"/>
        <x-progress-bar :steps="['Generate Invoice', 'Preview']" :currentStep="1" />
    </div>

    <!-- Form -->
    <form id="form" action="{{ route('invoices.store') }}" method="post" enctype="multipart/form-data"
        class="bg-[var(--secondary-bg-color)] text-sm rounded-xl shadow-lg p-8 border border-[var(--glass-border-color)]/20 pt-14 max-w-4xl mx-auto  relative overflow-hidden">
        @csrf
        <x-form-title-bar title="Generate Invoice" />

        <!-- Step 1: Generate Invoice -->
            <div class="step1 space-y-4 ">
                <div class="flex justify-between gap-4">
                    <input type="hidden" name="date" value='{{ now()->toDateString() }}'>
                    @if ($invoiceType === 'shipment')
                        <div class="grow">
                            <x-select label="Shipment Number" name="shipment_no" id="shipment_no" :options="$shipmentsOptions" required showDefault withButton btnId="selectCustomersBtn" btnText="Select Customers" />
                        </div>
                    @else
                        <div class="grow">
                            <x-select label="Order Number" name="order_no" id="order_no" :options="$ordersOptions" :value="$orderNumber ?? ''" required showDefault withButton btnId="generateInvoiceBtn" btnText="Generate Invoice" />
                        </div>
                    @endif
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

                <input type="hidden" id="customers_array" name="customers_array">
                <input type="hidden" name="articles_in_invoice" id="articles_in_invoice" value="">

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
                        <input type="text" name="netAmount" id="netAmountInForm" value="0.0" readonly
                            class="text-right bg-transparent outline-none w-1/2 border-none" />
                    </div>
                </div>
            </div>

        <!-- Step 2: view order -->
        <div class="step2 hidden space-y-4 text-black h-[35rem] overflow-y-auto my-scrollbar-2 bg-white rounded-md">
            <div id="preview-container" class="w-[148mm] h-[210mm] mx-auto overflow-hidden relative ">
                <div id="preview" class="preview w-[148mm] h-[210mm] gos-a5-document gos-a5-invoice overflow-hidden flex flex-col">
                    <h1 class="text-[var(--border-error)] font-medium text-center mt-5">No Preview avalaible.</h1>
                </div>
            </div>
        </div>
    </form>

@endsection


@push('page-scripts')
<script defer src="{{ asset('js/pages/invoices-generate.js') }}?v={{ config('app.version', 'local') }}.invoice-fix-1"></script>
<script>
        window.__invoicesGenerate = {
            invoiceType: @json($invoiceType),
            csrfToken: @json(csrf_token()),
            lastInvoice: @json($last_Invoice),
            nextInvoiceNo: @json($nextInvoiceNo ?? $last_Invoice?->invoice_no ?? null),
            companyData: @json($branchBranding ?? $client_company),
            orderNumber: @json($orderNumber),
            companyLogoBase: @json(asset('images')),
            searchFieldsHtml: @json($searchFieldsHtml),
            errorAlertTemplate: @json($errorAlertTemplate),
            discountDisabled: @json($hideDocumentDiscount),
        };
    </script>
@endpush
