@extends('app')
@section('title', 'Add Customer Payment | ' . $client_company->name)
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
        ]
    @endphp
    <!-- Progress Bar -->
    <div class="max-w-6xl mx-auto w-full">
        <x-search-header heading="Add Customer Payment" link linkText="Show Payments" linkHref="{{ route('customer-payments.index') }}"/>
    </div>

    <div class="row max-w-6xl mx-auto flex gap-4">
        <!-- Form -->
        <form id="form" action="{{ route('customer-payments.store') }}" method="post"
            class="bg-[var(--secondary-bg-color)] text-sm rounded-xl shadow-lg p-7 border border-[var(--h-bg-color)] pt-12 w-[70%] mx-auto relative overflow-hidden">
            @csrf
            <x-form-title-bar title="Add Customer Payment" />

            <div class="step space-y-4 overflow-y-auto max-h-[65vh] p-1 my-scrollbar-2">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    {{-- customer --}}
                    <x-select
                        label="Customer"
                        name="customer_id"
                        id="customer_id"
                        :options="$customers_options"
                        required
                        showDefault
                        onchange="trackCustomerState()"
                    />

                    {{-- balance --}}
                    <x-input label="Balance" placeholder="Select customer first" name="balance" id="balance" disabled />

                    {{-- date --}}
                    <x-input label="Date" name="date" id="date" type="date" required disabled onchange="trackDateState(this)"/>

                    {{-- type --}}
                    <x-select
                        label="Type"
                        name="type"
                        id="type"
                        :options="$type_options"
                        required
                        showDefault
                        onchange="trackTypeState(this)"
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

        <div class="bg-[var(--secondary-bg-color)] rounded-xl shadow-xl p-8 border border-[var(--glass-border-color)]/20 w-[35%] pt-12 relative overflow-hidden fade-in">
            <x-form-title-bar title="Last Record" />

            <!-- Step last record -->
            <div class="step1 space-y-4">
                @if ($lastRecord)
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        {{-- Customer --}}
                        <x-input label="Customer" name="last_customer" id="last_customer" type="text" disabled
                            value="{{ $lastRecord->customer->customer_name ?? '-' }}" />

                        <!-- date -->
                        <x-input label="Date" name="last_date" id="last_date" disabled
                            value="{{ $lastRecord->date->format('d-M-Y, D') ?? '-' }}" />

                        {{-- type --}}
                        <x-input label="Type" name="last_type" id="last_type" type="text" disabled capitalized
                            value="{{ str_replace('_', ' ', $lastRecord->type) ?? '-' }}" />

                        {{-- method --}}
                        <x-input label="Method" name="last_method" id="last_method" type="text" disabled capitalized
                            value="{{ $lastRecord->method ?? '-' }}" />

                        <!-- reff_no -->
                        <x-input label="Reff. No." name="last_reff_no" id="last_reff_no" disabled
                            value="{{ $lastRecord->slip_no ?? $lastRecord->cheque_no ?? $lastRecord->transaction_id ?? '-' }}" />

                        <!-- amount -->
                        <x-input label="Amount" name="last_amount" id="last_amount" disabled
                            value="{{ \App\Support\Money::format($lastRecord?->amount ?? 0) }}" />

                        {{-- remarks --}}
                        <x-input label="Remarks" name="last_remarks" id="last_remarks" type="text" disabled
                            value="{{ $lastRecord->remarks ?? 'No Remarks' }}" />

                        <div class="flex items-end">
                            <button type="button" data-record='@json($lastRecordPayload)' onclick="repeatThisRecord(this)"
                                class="w-full px-6 py-2 bg-[var(--bg-warning)] border border-[var(--bg-warning)] text-[var(--text-warning)] font-medium text-nowrap rounded-lg hover:bg-[var(--h-bg-warning)] hover:border-[var(--border-warning)] hover:scale-90 transition-all duration-300 ease-in-out cursor-pointer">
                                <i class='fas fa-repeat mr-1'></i> Repeat
                            </button>
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

@endsection


@push('page-scripts')
<script defer src="{{ asset('js/pages/customer-payments-create.js') }}?v={{ config('app.version', 'local') }}"></script>
<script>
        window.__customerPaymentsCreate = {
            banksOptions: @json($banks_options),
            programFromParam: @json($programPayload ?? null),
            programCustomerId: @json($programCustomerId ?? null),
        };

        @if ($programPayload)
            if (window.history.replaceState) {
                const customerPaymentUrl = new URL(window.location.href);
                customerPaymentUrl.search = '';
                window.history.replaceState({}, document.title, customerPaymentUrl.toString());
            }
        @endif
    </script>
@endpush
