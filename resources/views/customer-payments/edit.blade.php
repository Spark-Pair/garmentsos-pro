@extends('app')
@section('title', 'Edit Customer Payment | ' . $client_company->name)
@section('content')
    @php
        $method_options = [
            'cash' => ['text' => 'Cash'],
            'cheque' => ['text' => 'Cheque'],
            'slip' => ['text' => 'Slip'],
            'adjustment' => ['text' => 'Adjustment'],
        ];
        $type_options = [
            'normal' => ['text' => 'Normal'],
            'payment_program' => ['text' => 'Payment Program'],
            'recovery' => ['text' => 'Recovery'],
            'DR' => ['text' => 'DR'],
        ]
    @endphp
    <!-- Progress Bar -->
    <div class="mb-5 max-w-3xl mx-auto">
        <x-search-header heading="Edit Customer Payment" link linkText="Show Payments" linkHref="{{ route('customer-payments.index') }}"/>
    </div>

    <div class="row max-w-3xl mx-auto flex gap-4">
        <!-- Form -->
        <form id="form" action="{{ route('customer-payments.update', ['customer_payment' => $customerPayment->id]) }}" method="post"
            class="bg-[var(--secondary-bg-color)] text-sm rounded-xl shadow-lg p-7 border border-[var(--h-bg-color)] pt-12 w-full mx-auto relative overflow-hidden">
            @csrf
            @method('PUT')
            <input type="hidden" name="customer_id" value="{{ $customerPayment->customer_id }}">
            <x-form-title-bar title="Edit Customer Payment" />

            <div class="step space-y-4 overflow-y-auto max-h-[65vh] p-1 my-scrollbar-2">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    {{-- customer --}}
                    <x-input
                        label="Customer"
                        value="{{ $customerPayment->customer->customer_name }}"
                        disabled
                    />

                    {{-- balance --}}
                    <x-input label="Balance" value="{{ \App\Support\Money::format($customerPayment->customer->balance) }}" disabled />

                    {{-- date --}}
                    <x-input label="Date" name="date" id="date" type="date" required value="{{ $customerPayment->date->format('Y-m-d') }}" readonly onchange="trackDateState(this)"/>

                    {{-- type --}}
                    <x-select
                        label="Type"
                        name="type"
                        id="type"
                        :options="$type_options"
                        required
                        showDefault
                        onchange="trackTypeState(this)"
                        disabled
                    />

                    <div class="col-span-full">
                        <div id="details-inputs-container" class="grid grid-cols-1 md:grid-cols-2 gap-4 col-span-full">
                        </div>
                        {{-- method --}}
                        <x-select
                            label="Method"
                            name="method"
                            id="method"
                            :options="$method_options"
                            required
                            showDefault
                            onchange="trackMethodState(this)"
                        />

                        <hr class="border-gray-600 my-3">

                        <div id="details" class="grid grid-cols-1 md:grid-cols-3 gap-4">

                        </div>
                    </div>
                </div>
            </div>
            <div class="w-full flex justify-end mt-4">
                <button type="submit"
                    class="px-10 py-2 bg-[var(--bg-success)] border border-[var(--bg-success)] text-[var(--text-success)] font-medium text-nowrap rounded-lg hover:bg-[var(--h-bg-success)] hover:border-[var(--border-success)] hover:scale-90 transition-all duration-300 ease-in-out cursor-pointer">
                    <i class='fas fa-save mr-1'></i> Save
                </button>
            </div>
        </form>
    </div>

@endsection


@push('page-scripts')
<script defer src="{{ asset('js/pages/customer-payments-edit.js') }}"></script>
<script>
        window.__customerPaymentsEdit = {
            customerPayment: @json($customerPaymentPayload),
            banksOptions: @json($banks_options),
            isDeveloper: @json(Auth::user()?->role === 'developer'),
        };
    </script>
@endpush
