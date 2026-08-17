@extends('app')
@php
    $isEdit = isset($cargo);
@endphp
@section('title', ($isEdit ? 'Edit Cargo List' : 'Generate Cargo List') . ' | ' . $client_company->name)
@section('content')
    <!-- Main Content -->
    <!-- Progress Bar -->
    <div class="mb-5 max-w-4xl mx-auto">
        <x-search-header :heading="$isEdit ? 'Edit Cargo List' : 'Generate Cargo List'" link linkText="Show Cargo Lists" linkHref="{{ route('cargos.index') }}"/>
        <x-progress-bar :steps="['Generate Cargo List', 'Preview']" :currentStep="1" />
    </div>

    <!-- Form -->
    <form id="form" action="{{ $isEdit ? route('cargos.update', $cargo) : route('cargos.store') }}" method="post" enctype="multipart/form-data"
        class="bg-[var(--secondary-bg-color)] text-sm rounded-xl shadow-lg p-8 border border-[var(--glass-border-color)]/20 pt-14 max-w-4xl mx-auto  relative overflow-hidden">
        @csrf
        @if($isEdit)
            @method('PUT')
        @endif
        <x-form-title-bar :title="$isEdit ? 'Edit Cargo List' : 'Generate Cargo List'" />

        <!-- Step 1: Generate cargo list -->
        <div class="step1 space-y-4 ">
            <div class="flex items-end gap-4">
                {{-- cargo date --}}
                <div class="grow">
                    <x-input label="Date" name="date" id="date" type="date" onchange="trackStateOfgenerateBtn(this)"
                        validateMax max='{{ now()->toDateString() }}' validateMin
                        min="2024-01-01" :value="$isEdit ? $cargo->date?->format('Y-m-d') : ''" required />
                </div>

                <div class="grow">
                    <!-- customer_name -->
                    <x-input
                        label="Cargo Name"
                        name="cargo_name"
                        id="cargo_name"
                        placeholder="Enter cargo name"
                        :value="$isEdit ? $cargo->cargo_name : ''"
                        required
                    />
                </div>

                <button id="generateListBtn" type="button"
                    class="bg-[var(--primary-color)] px-4 py-2 rounded-lg hover:bg-[var(--h-primary-color)] transition-all duration-300 ease-in-out text-nowrap cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed">Select Invoices</button>
            </div>
            {{-- cargo-list-table --}}
            <div id="cargo-list-table" class="w-full text-left text-sm">
                <div class="flex justify-between items-center bg-[var(--h-bg-color)] rounded-lg py-2 px-4 mb-4">
                    <div class="w-[10%]">S.No.</div>
                    <div class="w-1/6">Date</div>
                    <div class="w-[14%]">Invoice No.</div>
                    <div class="w-[14%]">Shipment No.</div>
                    <div class="w-1/6">Cartons</div>
                    <div class="grow">Customer</div>
                    <div class="w-[10%]">City</div>
                    <div class="w-[10%] text-center">Action</div>
                </div>
                <div id="cargo-list" class="h-[20rem] overflow-y-auto my-scrollbar-2">
                    <div class="text-center bg-[var(--h-bg-color)] rounded-lg py-3 px-4">No Rates Added</div>
                </div>
            </div>

            <input type="hidden" name="invoices_array" id="invoices" value="">
            <div class="w-full grid grid-cols-1 text-sm mt-5 text-nowrap">
                <div class="total-qty flex justify-between items-center border border-gray-600 rounded-lg py-2 px-4 w-full">
                    <div class="grow">Total Cartons</div>
                    <div id="finalTotalCartons">0</div>
                </div>
            </div>
        </div>

        <!-- Step 2: view shipment -->
        <div class="step2 hidden space-y-4 text-black h-[35rem] overflow-y-auto my-scrollbar-2 bg-white rounded-md">
            <div id="preview-container" class="w-[210mm] h-[297mm] mx-auto overflow-hidden relative">
                <div id="preview" class="preview w-[210mm] h-[297mm] gos-a4-document cargo-list-a4-document overflow-hidden flex flex-col">
                    <h1 class="text-[var(--border-error)] font-medium text-center mt-5">No Preview avalaible.</h1>
                </div>
            </div>
        </div>
    </form>

@endsection

@push('page-scripts')
<script defer src="{{ asset('js/pages/cargos-generate.js') }}"></script>
<script>
        window.__cargosGenerate = {
            isEdit: @json($isEdit),
            lastCargo: @json($last_cargo),
            cargo: @json($cargo ?? null),
            companyData: @json($client_company),
            invoices: @json($invoices),
            invoicesUrl: @json(route('cargos.create')),
            selectedInvoices: @json($selectedInvoices ?? []),
            companyLogoBase: '{{ asset("images") }}',
        };
    </script>
@endpush
