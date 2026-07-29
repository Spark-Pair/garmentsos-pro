@extends('app')
@php
    $isEdit = isset($inventory);
@endphp
@section('title', ($isEdit ? 'Edit Inventory' : 'Add Inventory') . ' | ' . $client_company->name)
@section('content')
    @php
        $stockTransaction = $stockTransaction ?? null;
        $units = collect(app('defaults')->units)->mapWithKeys(fn ($unit) => [$unit => ['text' => $unit]])->all();
        $types = [
            'material' => ['text' => 'Material'],
            'fabric' => ['text' => 'Fabric'],
            'tag' => ['text' => 'Tag'],
            'accessory' => ['text' => 'Accessory'],
            'other' => ['text' => 'Other'],
        ];
        $paymentMethods = [
            'cash' => ['text' => 'Cash'],
            'supplier_credit' => ['text' => 'Supplier Credit'],
            'bank' => ['text' => 'Bank'],
        ];
    @endphp

    <div class="mb-5 max-w-4xl mx-auto">
        <x-search-header heading="{{ $isEdit ? 'Edit Inventory' : 'Add Inventory' }}" link linkText="Show Inventory" linkHref="{{ route('inventory.index') }}" />
        <x-progress-bar
            :steps="['Item Details', 'Stock In']"
            :currentStep="1"
        />
    </div>

    <div class="row max-w-4xl mx-auto flex gap-4">
        <form id="form" action="{{ $isEdit ? route('inventory.update', $inventory) : route('inventory.store') }}" method="post" enctype="multipart/form-data"
            class="bg-[var(--secondary-bg-color)] text-sm rounded-xl shadow-lg p-8 border border-[var(--glass-border-color)]/20 pt-14 grow relative overflow-hidden">
            @csrf
            @if($isEdit)
                @method('PUT')
            @endif
            <x-form-title-bar title="{{ $isEdit ? 'Edit Inventory Purchase / Stock In' : 'Inventory Purchase / Stock In' }}" />

            <div class="step1 space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <x-input label="Date" name="date" id="date" type="date" value="{{ old('date', $stockTransaction?->date?->toDateString() ?? now()->toDateString()) }}" required />
                    <x-input label="Item Name" name="name" id="name" placeholder="Enter item name" value="{{ old('name', $inventory->name ?? '') }}" required />
                    <x-select label="Type" name="type" id="type" :options="$types" value="{{ old('type', $inventory->type ?? '') }}" showDefault required onchange="trackInventoryType(this)" />
                    <x-select label="Fabric" name="fabric_id" id="fabric_id" :options="$fabricOptions" value="{{ old('fabric_id', $inventory->fabric_id ?? '') }}" showDefault />
                    <x-input label="Tag" name="tag" id="tag" placeholder="Optional tag / batch no." value="{{ old('tag', $inventory->tag ?? '') }}" />
                    <x-input label="Color" name="color" id="color" placeholder="Optional color" value="{{ old('color', $inventory->color ?? '') }}" />
                    @if($isEdit)
                        <x-select label="Status" name="is_active" id="is_active" :options="[1 => ['text' => 'Active'], 0 => ['text' => 'Inactive']]" value="{{ old('is_active', $inventory->is_active ? 1 : 0) }}" required />
                    @endif
                </div>
            </div>

            <div class="step2 hidden space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <x-select label="Unit" name="unit" id="unit" :options="$units" value="{{ old('unit', $inventory->unit ?? '') }}" showDefault required />
                    <x-input label="Quantity" name="quantity" id="quantity" type="number" step="0.001" placeholder="Enter quantity" value="{{ old('quantity', $stockTransaction?->quantity ?? '') }}" required oninput="calculateInventoryAmount()" />
                    <x-input label="Unit Price" name="unit_price" id="unit_price" type="number" step="0.01" placeholder="Enter unit price" value="{{ old('unit_price', $stockTransaction?->unit_price ?? '') }}" oninput="calculateInventoryAmount()" />
                    <x-input label="Amount" name="amount" id="amount" type="number" step="0.01" placeholder="Amount" value="{{ old('amount', $stockTransaction?->amount ?? '') }}" readonly />
                    <x-select label="Supplier" name="supplier_id" id="supplier_id" :options="$supplierOptions" value="{{ old('supplier_id', $stockTransaction?->supplier_id ?? '') }}" showDefault />
                    <x-select label="Payment Method" name="payment_method" id="payment_method" :options="$paymentMethods" value="{{ old('payment_method', $stockTransaction?->payment_method ?? '') }}" showDefault />
                    <x-input label="Reference No." name="reference_no" id="reference_no" placeholder="Bill / reference no." value="{{ old('reference_no', $stockTransaction?->reference_no ?? '') }}" />
                    <x-input label="Remarks" name="remarks" id="remarks" placeholder="Optional remarks" value="{{ old('remarks', $inventory->remarks ?? $stockTransaction?->remarks ?? '') }}" />
                </div>
            </div>
        </form>
    </div>
@endsection

@push('page-scripts')
<script defer src="{{ asset('js/pages/inventory-create.js') }}"></script>
@endpush
