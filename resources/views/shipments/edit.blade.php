@extends('app')
@section('title', 'Generate Shipment | ' . $client_company->name)
@section('content')
    <!-- Main Content -->
    <!-- Progress Bar -->
    <div class="mb-5 max-w-4xl mx-auto">
        <x-search-header heading="Generate Shipment" link linkText="Show Shipments" linkHref="{{ route('shipments.index') }}"/>
        <x-progress-bar :steps="['Generate Shipment', 'Preview']" :currentStep="1" />
    </div>

    <!-- Form -->
    <form id="form" action="{{ route('shipments.update', ['shipment' => $shipment->id]) }}" method="post" enctype="multipart/form-data"
        class="bg-[var(--secondary-bg-color)] text-sm rounded-xl shadow-lg p-8 border border-[var(--glass-border-color)]/20 pt-14 max-w-4xl mx-auto  relative overflow-hidden">
        @csrf
        @method('PUT')
        <x-form-title-bar title="Generate Shipment" />

        <!-- Step 1: Generate shipment -->
        <div class="step1 space-y-4 ">
            <div class="flex justify-between items-end gap-4">
                {{-- shipment date --}}
                <div class="grow">
                    <x-input label="Date" id="date" type="date" value="{{ $shipment->date->format('Y-m-d') }}" disabled/>
                </div>

                <div class="w-1/3">
                    <x-select
                        label="City"
                        name="city"
                        id="city"
                        :options="[
                            'all' => array_filter(['text' => 'All', 'selected' => $shipment->city === 'all']),
                            'karachi' => array_filter(['text' => 'Karachi', 'selected' => $shipment->city === 'karachi']),
                            'lahore' => array_filter(['text' => 'Lahore', 'selected' => $shipment->city === 'lahore']),
                        ]"
                        required
                        showDefault />
                </div>

                <button id="generateShipmentBtn" type="button" class="bg-[var(--primary-color)] px-4 py-2 rounded-lg hover:bg-[var(--h-primary-color)] transition-all duration-300 ease-in-out text-nowrap cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed">Select Articles</button>
            </div>
            {{-- rate showing --}}
            <div id="shipment-table" class="w-full text-left text-sm">
                <div class="flex justify-between items-center bg-[var(--h-bg-color)] rounded-lg py-2 px-4 mb-4">
                    <div class="w-[10%]">#</div>
                    <div class="w-1/6">Qty.</div>
                    <div class="grow">Decs.</div>
                    <div class="w-1/6">Rate/Pc</div>
                    <div class="w-1/5">Amount</div>
                    <div class="w-[10%] text-center">Action</div>
                </div>
                <div id="shipment-list" class="h-[20rem] overflow-y-auto my-scrollbar-2">
                    <div class="text-center bg-[var(--h-bg-color)] rounded-lg py-3 px-4">No Rates Added</div>
                </div>
            </div>

            <div class="flex w-full grid grid-cols-1 md:grid-cols-2 gap-3 text-sm mt-5 text-nowrap">
                <div class="total-qty flex justify-between items-center border border-gray-600 rounded-lg py-2 px-4 w-full">
                    <div class="grow">Total Quantity - Pcs</div>
                    <div id="finalShipmentQuantity">0</div>
                </div>
                <div class="final flex justify-between items-center border border-gray-600 rounded-lg py-2 px-4 w-full">
                    <div class="grow">Total Amount - Rs.</div>
                    <div id="finalShipmentAmount">0.0</div>
                </div>
                <div
                    class="final flex justify-between items-center border border-gray-600 rounded-lg py-2 px-4 w-full">
                    <label for="discount" class="grow">Discount - %</label>
                    <input type="text" id="discount" value="10"
                        class="text-right bg-transparent outline-none w-1/2 border-none" readonly />
                </div>
                <div class="final flex justify-between items-center border border-gray-600 rounded-lg py-2 px-4 w-full">
                    <div class="grow">Net Amount - Rs.</div>
                    <input type="text" name="netAmount" id="finalNetAmount" value="0.0" readonly
                        class="text-right bg-transparent outline-none w-1/2 border-none" />
                </div>
            </div>
            <input type="hidden" name="articles" id="articles" value="">
        </div>

        <!-- Step 2: view shipment -->
        <div class="step2 hidden space-y-4 text-black h-[35rem] overflow-y-auto my-scrollbar-2 bg-white rounded-md">
            <div id="preview-container" class="w-[148mm] h-[210mm] mx-auto overflow-hidden relative">
                <div id="preview" class="preview w-[148mm] h-[210mm] gos-a5-document gos-a5-invoice overflow-hidden flex flex-col">
                    <h1 class="text-[var(--border-error)] font-medium text-center mt-5">No Preview avalaible.</h1>
                </div>
            </div>
        </div>
    </form>

@endsection

@push('page-scripts')
<script defer src="{{ asset('js/pages/shipments-edit.js') }}"></script>
<script>
        window.__shipmentsEdit = {
            shipment: @json($shipmentPayload),
            companyData: @json(!empty($branchBranding) ? $branchBranding : $client_company),
            shipmentsCreateUrl: '{{ route("shipments.create") }}',
            companyLogoBase: '{{ asset("images") }}',
            maxArticlesAlertHtml: @json('<div class="bg-[var(--danger-color)]/10 border border-[var(--danger-color)] text-[var(--danger-color)] text-xs px-3 py-2 rounded-lg">You have reached the maximum allowed number of 18 articles.</div>'),
            shipmentNo: '{{ $shipment->shipment_no }}',
        };
    </script>
@endpush
