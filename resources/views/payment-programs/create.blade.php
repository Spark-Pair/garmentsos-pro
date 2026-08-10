@extends('app')
@section('title', 'Add Payment Program | ' . $client_company->name)
@section('content')
@php
    $categories_options = [
        'self_account' => ['text' => 'Self Account'],
        'supplier' => ['text' => 'Supplier'],
        // 'customer' => ['text' => 'Customer'],
        'waiting' => ['text' => 'Waiting'],
    ]
@endphp
    <!-- Main Content -->

    <div class="max-w-3xl mx-auto">
        <x-search-header heading="Add Payment Program" link linkText="Show Payment Programs" linkHref="{{ route('payment-programs.index') }}"/>
    </div>

    <!-- Form -->
    <form id="form" action="{{ route('payment-programs.store') }}" method="post"
        class="bg-[var(--secondary-bg-color)] text-sm rounded-xl shadow-lg p-8 border border-[var(--glass-border-color)]/20 pt-14 max-w-3xl mx-auto  relative overflow-hidden">
        @csrf
        <x-form-title-bar title="Add Payment Program" />

        <div class="grid grid-cols-2 gap-4">
            {{-- date --}}
            <x-input label="Date" name="date" id="date" type="date" :value="old('date')" onchange="trackDateState(this)" validateMax max="{{ now()->toDateString() }}" required />

            {{-- cusomer --}}
            <x-select
                label="Customer"
                name="customer_id"
                id="customer_id"
                :options="$customers_options"
                onchange="trackCustomerState(this)"
                required
                showDefault
            />

            {{-- category --}}
            <x-select
                label="Category"
                name="category"
                id="category"
                :options="$categories_options"
                onchange="getCategoryData(this.value)"
                required
                showDefault
            />

            {{-- cusomer --}}
            <x-select
                label="Disabled"
                name="sub_category"
                id="subCategory"
                disabled
                showDefault
            />

            {{-- amount --}}
            <x-input label="Amount" type="amount" name="amount" id="amount" :value="old('amount')" placeholder='Enter Amount' required dataValidate="required|amount" />

            {{-- remarks --}}
            <x-input label="Remarks" name="remarks" id="remarks" :value="old('remarks')" placeholder="Enter Remarks" />

            {{-- <x-input name="program_no" id="program_no" type="hidden" value="{{ $lastProgram->program_no + 1 }}" /> --}}
        </div>
        <div class="w-full flex justify-end mt-4">
            <button type="submit"
                class="px-6 py-1 bg-[var(--bg-success)] border border-[var(--bg-success)] text-[var(--text-success)] font-medium text-nowrap rounded-lg hover:bg-[var(--h-bg-success)] transition-all duration-300 ease-in-out cursor-pointer">
                <i class='fas fa-save mr-1'></i> Save
            </button>
        </div>
    </form>

@endsection

@push('page-scripts')
<script defer src="{{ asset('js/pages/payment-programs-create.js') }}"></script>
<script>
        window.__paymentProgramsCreate = {
            csrfToken: "{{ csrf_token() }}",
        };
    </script>
@endpush
