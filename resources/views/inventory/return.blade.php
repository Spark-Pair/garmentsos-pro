@extends('app')
@section('title', 'Return Inventory | ' . $client_company->name)

@php
    $paymentMethods = [
        'cash' => ['text' => 'Cash'],
        'supplier_credit' => ['text' => 'Supplier Credit'],
        'bank' => ['text' => 'Bank'],
    ];
@endphp

@section('content')
    <div class="mb-5 max-w-5xl mx-auto">
        <x-search-header heading="Return Inventory" link linkText="Show Inventory" linkHref="{{ route('inventory.index') }}" />
        <x-progress-bar :steps="['Item Details', 'Return Details']" :currentStep="1" />
    </div>

    <div class="row max-w-5xl mx-auto flex gap-4">
        <form id="form" action="{{ route('inventory.return', $inventory) }}" method="post"
            class="bg-[var(--secondary-bg-color)] text-sm rounded-xl shadow-lg p-8 border border-[var(--glass-border-color)]/20 pt-14 grow relative overflow-hidden">
            @csrf
            <x-form-title-bar title="Return Inventory" />

            <div class="step1 grid grid-cols-1 md:grid-cols-2 gap-4">
                <x-input label="Item Name" id="return_item_name" value="{{ $inventory->name }}" readonly />
                <x-input label="Type" id="return_item_type" value="{{ str_replace('_', ' ', $inventory->type) }}" readonly capitalized />
                <x-input label="Fabric" id="return_item_fabric" value="{{ $inventory->fabric?->title ?? '-' }}" readonly />
                <x-input label="Tag" id="return_item_tag" value="{{ $inventory->tag ?? '-' }}" readonly />
                <x-input label="Color" id="return_item_color" value="{{ $inventory->color ?? '-' }}" readonly />
                <x-input label="Available" id="return_item_available" value="{{ $inventoryData['stock_quantity_formatted'] }} {{ $inventory->unit }}" readonly />
            </div>

            <div class="step2 hidden grid grid-cols-1 md:grid-cols-2 gap-4">
                <x-input label="Date" name="date" id="return_date" type="date" value="{{ old('date', now()->toDateString()) }}" required />
                <x-input label="Unit" id="return_unit" value="{{ $inventory->unit ?? '-' }}" readonly />
                <x-input label="Quantity" name="quantity" id="return_quantity" type="number" step="0.001"
                    value="{{ old('quantity') }}" placeholder="Select supplier first" required disabled
                    oninput="clampInventoryReturnQuantity(this); calculateInventoryReturnAmount()" />
                <x-input label="Unit Price" name="unit_price" id="return_unit_price" type="number" step="0.01" readonly />
                <x-input label="Amount" name="amount" id="return_amount" type="number" step="0.01" readonly />
                <x-select label="Supplier" name="supplier_id" id="return_supplier_id" :options="$supplierOptions"
                    value="{{ $selectedSupplierId }}" showDefault required onchange="trackInventoryReturnSupplier(this)" />
                <x-select label="Payment Method" name="payment_method" id="return_payment_method" :options="$paymentMethods"
                    value="{{ $selectedPaymentMethod }}" showDefault onchange="updateInventoryReturnSummary()" />
                <x-input label="Reference No." name="reference_no" id="return_reference" value="{{ old('reference_no') }}" placeholder="Optional reference" />
                <div class="md:col-span-2">
                    <x-input label="Remarks" name="remarks" id="return_remarks" value="{{ old('remarks') }}" placeholder="Return remarks" />
                </div>
            </div>

            @if (empty($supplierOptions))
                <p class="mt-4 text-sm text-[var(--border-error)]">No supplier has returnable stock for this item.</p>
            @endif
        </form>

        <div class="bg-[var(--secondary-bg-color)] rounded-xl shadow-xl p-8 border border-[var(--glass-border-color)]/20 w-[35%] pt-14 relative overflow-hidden fade-in">
            <x-form-title-bar title="Current Stock" />
            <div id="lastRecordStep1" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <x-input label="Item" id="current_item" disabled value="{{ $inventory->name }}" />
                <x-input label="Type" id="current_type" disabled capitalized value="{{ str_replace('_', ' ', $inventory->type) }}" />
                <x-input label="Unit" id="current_unit" disabled capitalized value="{{ $inventory->unit ?? '-' }}" />
                <x-input label="Available" id="current_available" disabled value="{{ $inventoryData['stock_quantity_formatted'] }}" />
                <x-input label="Fabric" id="current_fabric" disabled value="{{ $inventory->fabric?->title ?? '-' }}" />
                <x-input label="Tag" id="current_tag" disabled value="{{ $inventory->tag ?? '-' }}" />
            </div>
            <div id="lastRecordStep2" class="hidden grid grid-cols-1 md:grid-cols-2 gap-4">
                <x-input label="Supplier" id="summary_supplier" disabled value="-" />
                <x-input label="Available" id="summary_available" disabled value="-" />
                <x-input label="Quantity" id="summary_quantity" disabled value="-" />
                <x-input label="Unit Price" id="summary_rate" disabled value="-" />
                <x-input label="Amount" id="summary_amount" disabled value="-" />
                <x-input label="Payment" id="summary_payment" disabled value="-" />
            </div>
        </div>
    </div>
@endsection

@push('page-scripts')
<script>
    window.__inventoryReturn = {
        oldSupplierId: @json($selectedSupplierId),
        oldQuantity: @json(old('quantity')),
    };
</script>
<script defer src="{{ asset('js/pages/inventory-return.js') }}"></script>
@endpush
