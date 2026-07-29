@extends('app')
@section('title', 'Edit Fabric | ' . $client_company->name)
@section('content')
@php
    $colors_options = [
        'black' => ['text' => 'Black', 'data_option' => 'blk'],
        'blue' => ['text' => 'Blue', 'data_option' => 'blu'],
        'golden' => ['text' => 'Golden', 'data_option' => 'gld'],
        'purple' => ['text' => 'Purple', 'data_option' => 'gld'],
        'green' => ['text' => 'Green', 'data_option' => 'grn'],
        'grey' => ['text' => 'Grey', 'data_option' => 'gry'],
        'meroon' => ['text' => 'Meroon', 'data_option' => 'mrn'],
        'multi' => ['text' => 'Multi', 'data_option' => 'mlt'],
        'nevy_blue' => ['text' => 'Nevy Blue', 'data_option' => 'nvy'],
        'off_white' => ['text' => 'Off White', 'data_option' => 'ofw'],
        'printed' => ['text' => 'Printed', 'data_option' => 'prt'],
        'red' => ['text' => 'Red', 'data_option' => 'red'],
        'skin' => ['text' => 'Skin', 'data_option' => 'skn'],
        'brown' => ['text' => 'Brown', 'data_option' => 'brn'],
        'white' => ['text' => 'White', 'data_option' => 'wht'],
        'yellow' => ['text' => 'Yellow', 'data_option' => 'ylw'],
        'orange' => ['text' => 'Orange', 'data_option' => 'org'],
        'hazel_grey' => ['text' => 'Hazel Grey', 'data_option' => 'hzg'],
        'pink' => ['text' => 'Pink', 'data_option' => 'pnk'],
    ];

    $selectedFabricFields = [
        'supplier_id' => old('supplier_id', $fabric->supplier_id),
        'fabric_id' => old('fabric_id', $fabric->fabric_id),
        'color' => old('color', $fabric->color),
        'unit' => old('unit', $fabric->unit),
    ];
@endphp
    <div class="mb-5 max-w-5xl mx-auto">
        <x-search-header heading="Edit Fabric" link linkText="Show Fabrics" linkHref="{{ route('fabrics.index') }}" />
    </div>

    <div class="row max-w-5xl mx-auto flex gap-4">
        <form id="form" action="{{ route('fabrics.update', ['fabric' => $fabric->id]) }}" method="post"
            class="bg-[var(--secondary-bg-color)] text-sm rounded-xl shadow-lg p-8 border border-[var(--glass-border-color)]/20 pt-14 grow relative overflow-hidden">
            @csrf
            @method('PUT')
            <x-form-title-bar title="Edit Fabric" />

            <div class="step1 space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <x-input label="Date" name="date" id="date" validateMin min="2024-01-01" validateMax max="{{ now()->toDateString() }}" type="date" required value="{{ old('date', $fabric->date?->format('Y-m-d')) }}" />

                    <x-select label="Supplier" name="supplier_id" id="supplier_id" :options="$suppliers_options" required showDefault onchange="generateTagNo()" />

                    <x-select label="Fabric" name="fabric_id" id="fabric_id" :options="$fabrics_options" required showDefault onchange="generateTagNo()" />

                    <x-select label="Color" name="color" id="color" :options="$colors_options" required showDefault onchange="generateTagNo()" />

                    <x-select label="Unit" name="unit" id="unit" :options="[
                        'kgs' => ['text' => 'Kgs'],
                        'meters' => ['text' => 'Meters'],
                        'yards' => ['text' => 'Yards'],
                    ]" required showDefault onchange="generateTagNo()" />

                    <x-input label="Quantity" name="quantity" id="quantity" type="number" placeholder="Enter quantity" required step="0.01" value="{{ old('quantity', $fabric->quantity) }}" />

                    <x-input label="Reff. No." name="reff_no" id="reff_no" placeholder="Enter reff no" value="{{ old('reff_no', $fabric->reff_no) }}" />

                    <x-input label="Remarks" name="remarks" id="remarks" type="text" placeholder="Enter remarks" value="{{ old('remarks', $fabric->remarks) }}" />

                    <div class="col-span-full">
                        <x-input label="Tag" name="tag" id="tag" placeholder="tag" required value="{{ old('tag', $fabric->tag) }}" />
                    </div>
                </div>
            </div>

            <div class="w-full flex justify-end mt-4">
                <button type="submit"
                    class="px-6 py-1 bg-[var(--bg-success)] border border-[var(--bg-success)] text-[var(--text-success)] font-medium text-nowrap rounded-lg hover:bg-[var(--h-bg-success)] transition-all 0.3s ease-in-out cursor-pointer">
                    <i class='fas fa-save mr-1'></i> Save
                </button>
            </div>
        </form>
    </div>
@endsection

@push('page-scripts')
<script defer src="{{ asset('js/pages/fabrics-add.js') }}"></script>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const selected = @json($selectedFabricFields);

        for (const [field, value] of Object.entries(selected)) {
            const option = document.querySelector(`li[data-for="${field}"][data-value="${value}"]`);
            if (option) {
                selectThisOption(option);
            }
        }
    });
</script>
@endpush
