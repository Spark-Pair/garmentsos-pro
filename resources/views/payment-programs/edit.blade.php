@extends('app')
@section('title', 'Edit Payment Program | ' . $client_company->name)
@section('content')
@php
    $categories_options = [
        'self_account' => ['text' => 'Self Account'],
        'supplier' => ['text' => 'Supplier'],
        'waiting' => ['text' => 'Waiting'],
    ];
@endphp
    <div class="max-w-3xl mx-auto">
        <x-search-header heading="Edit Payment Program" link linkText="Show Payment Programs" linkHref="{{ route('payment-programs.index') }}"/>
    </div>

    <form id="form" action="{{ route('payment-programs.update', ['payment_program' => $paymentProgram->id]) }}" method="post"
        class="bg-[var(--secondary-bg-color)] text-sm rounded-xl shadow-lg p-8 border border-[var(--glass-border-color)]/20 pt-14 max-w-3xl mx-auto relative overflow-hidden">
        @csrf
        @method('PUT')
        <x-form-title-bar title="Edit Payment Program" />

        @if ($hasReceivedPayments)
            <div class="col-span-full mb-4 rounded-lg border border-[var(--glass-border-color)] bg-[var(--h-bg-color)] px-4 py-3 text-[var(--secondary-text)]">
                This program already has received payments. Customer and category are locked; only amount, date, and remarks can be updated.
            </div>
        @endif

        <div class="grid grid-cols-2 gap-4">
            <x-input label="Date" name="date" id="date" type="date" value="{{ old('date', $paymentProgram->date?->format('Y-m-d')) }}" onchange="trackDateState(this)" validateMax max="{{ now()->toDateString() }}" required />

            <x-select
                label="Customer"
                name="customer_id"
                id="customer_id"
                :options="$customers_options"
                onchange="trackCustomerState(this)"
                required
                :disabled="$hasReceivedPayments"
                showDefault
            />

            <x-select
                label="Category"
                name="category"
                id="category"
                :options="$categories_options"
                onchange="getCategoryData(this.value)"
                required
                :disabled="$hasReceivedPayments"
                showDefault
            />

            <x-select
                label="Disabled"
                name="sub_category"
                id="subCategory"
                :disabled="$hasReceivedPayments"
                showDefault
            />

            @if ($hasReceivedPayments)
                <input type="hidden" name="customer_id" value="{{ $paymentProgram->customer_id }}">
                <input type="hidden" name="category" value="{{ $paymentProgram->category }}">
                <input type="hidden" name="sub_category" value="{{ $paymentProgram->sub_category_id }}">
            @endif

            <x-input label="Remarks" name="remarks" id="remarks" value="{{ old('remarks', $paymentProgram->remarks) }}" placeholder="Enter Remarks" />

            <div class="col-span-full">
                <x-input label="Amount" type="amount" name="amount" id="amount" value="{{ old('amount', \App\Support\Money::format($paymentProgram->amount)) }}" placeholder='Enter Amount' required dataValidate="required|amount" />
            </div>
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
    window.__paymentProgramsEdit = {{ Illuminate\Support\Js::from([
        'customer_id' => old('customer_id', $paymentProgram->customer_id),
        'category' => old('category', $paymentProgram->category),
        'sub_category' => old('sub_category', $paymentProgram->sub_category_id),
    ]) }};
</script>
@endpush
