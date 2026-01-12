@extends('app')
@section('title', 'Add Fabric | ' . $client_company->name)
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
        'white' => ['text' => 'White', 'data_option' => 'wht'],
        'yellow' => ['text' => 'Yellow', 'data_option' => 'ylw'],
        'orange' => ['text' => 'Orange', 'data_option' => 'org'],
        'hazel_grey' => ['text' => 'Hazel Grey', 'data_option' => 'hzg'],
        'pink' => ['text' => 'Pink', 'data_option' => 'pnk'],
    ]
@endphp
    <!-- Main Content -->
    <!-- Progress Bar -->
    <div class="mb-5 max-w-5xl mx-auto">
        <x-search-header heading="Add Fabric" link linkText="Show Fabrics" linkHref="{{ route('fabrics.index') }}" />
    </div>

    <div class="row max-w-5xl mx-auto flex gap-4">
        <!-- Form -->
        <form id="form" action="{{ route('fabrics.store') }}" method="post" enctype="multipart/form-data"
            class="bg-[var(--secondary-bg-color)] text-sm rounded-xl shadow-lg p-8 border border-[var(--glass-border-color)]/20 pt-14 grow relative overflow-hidden">
            @csrf
            <x-form-title-bar title="Add Fabric" />
            <!-- Step 1: Basic Information -->
            <div class="step1 space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- date -->
                    <x-input label="Date" name="date" id="date" validateMin min="2024-01-01" validateMax max="{{ now()->toDateString() }}" type="date" required />

                    {{-- supplier --}}
                    <x-select label="Supplier" name="supplier_id" id="supplier_id" :options="$suppliers_options" required showDefault onchange="generateTagNo()" />

                    {{-- fabric --}}
                    <x-select label="Fabric" name="fabric_id" id="fabric_id" :options="$fabrics_options" required showDefault onchange="generateTagNo()" />

                    {{-- color --}}
                    <x-select label="Color" name="color" id="color" :options="$colors_options" required showDefault onchange="generateTagNo()" />

                    {{-- unit --}}
                    <x-select label="Unit" name="unit" id="unit" :options="[
                        'kgs' => ['text' => 'Kgs'],
                        'meters' => ['text' => 'Meters'],
                        'yards' => ['text' => 'Yards'],
                    ]" required showDefault onchange="generateTagNo()" />

                    <!-- quantity -->
                    <x-input label="Quantity" name="quantity" id="quantity" type="number" placeholder="Enter quantity" required step="0.01" />

                    <!-- reff_no -->
                    <x-input label="Reff. No." name="reff_no" id="reff_no" placeholder="Enter reff no" />

                    {{-- remarks --}}
                    <x-input label="Remarks" name="remarks" id="remarks" type="text" placeholder="Enter remarks" />

                    <div class="col-span-full">
                        <!-- tag -->
                        <x-input label="Tag" name="tag" id="tag" placeholder="tag" required readonly/>
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

        <div
            class="bg-[var(--secondary-bg-color)] rounded-xl shadow-xl p-8 border border-[var(--glass-border-color)]/20 w-[35%] pt-14 relative overflow-hidden fade-in">
            <x-form-title-bar title="Last Record" />

            <!-- Step 1: Basic Information -->
            <div class="step1 space-y-4 ">
                @if (isset($lastRecord) && $lastRecord)
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- date -->
                        <x-input label="Date" id="last_date" disabled
                            value="{{ $lastRecord->date->format('d-M-Y, D') }}" />

                        {{-- supplier --}}
                        <x-input label="Supplier" id="last_supplier" disabled
                            value="{{ $lastRecord->supplier->supplier_name }}" />

                        {{-- fabric --}}
                        <x-input label="Fabric" id="last_fabric" disabled
                            value="{{ $lastRecord->fabric->title }}" />

                        {{-- color --}}
                        <x-input label="Color" id="last_color" disabled
                            value="{{ $lastRecord->color }}" />

                        <!-- unit -->
                        <x-input label="Unit" id="last_unit" disabled capitalized
                            value="{{ $lastRecord->unit }}" />

                        <!-- quantity -->
                        <x-input label="Quantity" id="last_quantity" type="number" disabled
                            value="{{ $lastRecord->quantity }}" />

                        <!-- reff_no -->
                        <x-input label="Reff. No." id="last_reff_no" type="number" disabled
                            value="{{ $lastRecord->reff_no ?? '-' }}" />

                        {{-- remarks --}}
                        <x-input label="Remarks" id="last_remarks" type="text" disabled
                            value="{{ $lastRecord->remarks ?? 'No Remarks' }}" />

                        {{-- tag --}}
                        <div class="col-span-full">
                            <x-input label="Tag" id="last_tag" type="text" disabled
                                value="{{ $lastRecord->tag }}" />
                        </div>
                    </div>
                @else
                    <div class="text-center text-gray-500">
                        <p>No last record found.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <script>
        function generateTagNo() {
            const supplierSelect = document.getElementById('supplier_id');
            const fabricSelect = document.getElementById('fabric_id');
            const colorSelect = document.getElementById('color');
            const unitSelect = document.getElementById('unit');
            const tagInput = document.getElementById('tag');

            const selectedSupplier = JSON.parse(supplierSelect.parentElement.parentElement.parentElement.querySelector('li.selected').getAttribute('data-option') ?? '{}');
            const selectedFabric = JSON.parse(fabricSelect.parentElement.parentElement.parentElement.querySelector('li.selected').getAttribute('data-option') ?? '{}');
            const selectedColor = colorSelect.parentElement.parentElement.parentElement.querySelector('li.selected').getAttribute('data-option') ?? '';

            // Generate supplier code
            const supplierName = selectedSupplier.supplier_name ?? '';
            const supplierCode = supplierName
                .split(' ')
                .map(word => word.slice(0, 3).toUpperCase())
                .join('.');
            // Generate unit code
            const unitCode = (unitSelect.value ?? '').charAt(0).toUpperCase();

            // Get color title
            const colorTitle = selectedColor.toUpperCase() ?? '';

            // Get fabric title
            const fabricTitle = selectedFabric.title ?? '';

            // Combine all to form tag no
            const tagNo = `${supplierCode}-${unitCode}-${colorTitle}-${fabricTitle}`;

            // Output or assign to input
            tagInput.value = tagNo;
        }
    </script>
@endsection
