@extends('app')
@section('title', 'Generate Order | ' . $client_company->name)
@section('content')
    @php
        $hideDocumentDiscount = (bool) ($branchBranding['discount_disabled'] ?? false);
    @endphp
    @php
        $isCustomerPortalOrder = Auth::user()?->role === 'customer';
    @endphp
    <!-- Main Content -->
    <!-- Progress Bar -->
    <div class="mb-5 max-w-5xl mx-auto">
        <x-search-header heading="Generate Order" link linkText="Show Orders" linkHref="{{ route('orders.index') }}"/>
        <x-progress-bar :steps="['Generate Order', 'Preview']" :currentStep="1" />
    </div>

    <!-- Form -->
    <form id="form" action="{{ route('orders.store') }}" method="post" enctype="multipart/form-data"
        class="bg-[var(--secondary-bg-color)] text-sm rounded-xl shadow-lg p-8 border border-[var(--glass-border-color)]/20 pt-14 max-w-5xl mx-auto  relative overflow-hidden">
        @csrf
        <x-form-title-bar title="Generate Order" />

        <!-- Step 1: Generate order -->
        <div class="step1 space-y-4 ">
            <div class="flex justify-between gap-4">
                {{-- order date --}}
                <div class="w-1/2 grid grid-cols-2 gap-4">
                    <x-input label="Date" name="date" id="date" type="date" onchange="getDataByDate(this)" validateMax max='{{ now()->toDateString() }}' validateMin min="2024-01-01" required />

                    <x-input label="Deliver To" name="deliver_to" id="deliver_to" placeholder="Optional delivery address / person" />
                </div>

                <input type="hidden" name="generateInvoiceAfterSave" id="generateInvoiceAfterSave" value="0">

                {{-- title --}}
                <div class="grow">
                    <x-select label="Customer" name="customer_id" id="customer_id" :options="[]" required showDefault onchange="trackCustomerState(this)"
                        class="grow" withButton btnId="generateOrderBtn" btnText="Select Articles" />
                </div>
            </div>
            {{-- rate showing --}}
            <div id="order-table" class="w-full text-left text-sm">
                <div class="flex justify-between items-center bg-[var(--h-bg-color)] rounded-lg py-2 px-4 mb-4">
                    <div class="w-[10%]">#</div>
                    <div class="w-1/6">Qty.</div>
                    <div class="grow">Decs.</div>
                    <div class="w-1/6">Rate/Pc</div>
                    <div class="w-1/5">Amount</div>
                    <div class="w-[10%] text-center">Action</div>
                </div>
                <div id="order-list" class="h-[19rem] overflow-y-auto my-scrollbar-2">
                    <div class="text-center bg-[var(--h-bg-color)] rounded-lg py-3 px-4">No Rates Added</div>
                </div>
            </div>

            <div class="flex w-full grid grid-cols-1 md:grid-cols-2 gap-3 text-sm mt-5 text-nowrap">
                <div class="total-qty flex justify-between items-center border border-gray-600 rounded-lg py-2 px-4 w-full">
                    <div class="grow">Total Quantity</div>
                    <div id="finalOrderedQuantity">0</div>
                </div>
                <div class="final {{ $hideDocumentDiscount ? 'hidden' : '' }} flex justify-between items-center border border-gray-600 rounded-lg py-2 px-4 w-full">
                    <div class="grow">Total Amount - Rs.</div>
                    <div id="finalOrderAmount">0.0</div>
                </div>
                @if ($hideDocumentDiscount)
                    <input type="hidden" name="discount" id="discount" value="0">
                @else
                    <div class="final flex justify-between items-center bg-[var(--h-bg-color)] border border-gray-600 rounded-lg py-2 px-4 w-full">
                        <label for="discount" class="grow">Discount - %</label>
                        <input type="number" name="discount" id="discount" value="{{ old('discount', $defaultOrderDiscount ?? 0) }}" min="0" max="100" step="1"
                            class="text-right bg-transparent outline-none w-1/2 border-none" />
                    </div>
                @endif
                <div class="final flex justify-between items-center border border-gray-600 rounded-lg py-2 px-4 w-full">
                    <div class="grow">Net Amount - Rs.</div>
                    <input type="text" name="netAmount" id="finalNetAmount" value="0.0" readonly
                        class="text-right bg-transparent outline-none w-1/2 border-none" />
                </div>
            </div>
            <input type="hidden" name="articles" id="articles" value="" />
        </div>

        <!-- Step 2: view order -->
        <div class="step2 hidden space-y-4 text-black h-[35rem] overflow-y-auto my-scrollbar-2 bg-white rounded-md py-10">
            <div id="preview-container" class="w-[148mm] h-[210mm] mx-auto overflow-hidden relative ">
                <div id="preview" class="preview w-[148mm] h-[210mm] gos-a5-document gos-a5-invoice overflow-hidden flex flex-col">
                    <h1 class="text-[var(--border-error)] font-medium text-center mt-5">No Preview avalaible.</h1>
                </div>
            </div>
        </div>
    </form>

@endsection


@push('page-scripts')
<script defer src="{{ asset('js/pages/orders-generate.js') }}?v={{ config('app.version', 'local') }}"></script>
<script>
        window.__ordersGenerate = {
            lastOrder: @json($last_order),
            nextOrderNo: @json($nextOrderNo ?? $last_order?->order_no ?? null),
            defaultOrderDiscountPercent: @json($defaultOrderDiscount ?? 0),
            companyData: @json($branchBranding ?? $client_company),
            ordersCreateUrl: '{{ route("orders.create") }}',
            customerArticleHistoryUrl: '{{ route("orders.customer-article-history") }}',
            companyLogoBase: '{{ asset("images") }}',
            isCustomerRole: @json($isCustomerPortalOrder),
            maxArticlesAlertHtml: @json('<div class="bg-[var(--danger-color)]/10 border border-[var(--danger-color)] text-[var(--danger-color)] text-xs px-3 py-2 rounded-lg">You have reached the maximum allowed number of 500 articles.</div>'),
        };
    </script>
@endpush
