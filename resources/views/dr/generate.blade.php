@extends('app')
@section('title', 'Generate DR | ' . $client_company->name)
@section('content')
    @php $isEdit = isset($dR); @endphp
    @php
        $editData = $isEdit ? [
            'id' => $dR->id,
            'customer_id' => $dR->customer_id,
            'customer_text' => $dR->customer?->customer_name . ($dR->customer?->city?->short_title ? ' | ' . strtoupper($dR->customer->city->short_title) : ''),
            'date' => $dR->date?->format('Y-m-d'),
            'return_payments' => $dR->return_payments,
            'return_payment_details' => $return_payment_details,
            'new_payments' => $dR->new_payments,
            'new_payment_details' => $new_payment_details,
        ] : null;
    @endphp
    @php
        $method_options = [
            'cash' => ['text' => 'Cash'],
            'cheque' => ['text' => 'Cheque'],
            'slip' => ['text' => 'Slip'],
            'online' => ['text' => 'Online'],
        ];
    @endphp
    @php
        $bankSelectHtml = view('components.select', [
            'label' => 'Bank',
            'name' => 'bank_id',
            'id' => 'bank_id',
            'required' => true,
            'options' => $bank_options,
            'showDefault' => true,
        ])->render();

        $remarksInputHtml = view('components.input', [
            'label' => 'Remarks',
            'name' => 'remarks',
            'id' => 'remarks',
            'placeholder' => 'Enter remarks',
            'dataValidate' => 'friendly',
            'oninput' => 'trackAmountState(this)',
        ])->render();

        $selectPaymentAlertHtml = view('components.alert', [
            'type' => 'error',
            'messages' => 'Please select at least one payment before submitting.',
        ])->render();

        $amountMismatchAlertHtml = view('components.alert', [
            'type' => 'error',
            'messages' => 'The total added amount must be equal to the total selected amount.',
        ])->render();
    @endphp
    <!-- Main Content -->
    <!-- Progress Bar -->
    <div class="mb-5 max-w-5xl mx-auto">
        <x-search-header heading="{{ $isEdit ? 'Edit DR' : 'Generate DR' }}" link linkText="Show DR" linkHref="{{ route('dr.index') }}"/>
        <x-progress-bar :steps="['Select Payment', 'Add Payment']" :currentStep="1" />
    </div>

    <!-- Form -->
    <form id="form" action="{{ $isEdit ? url('/dr/' . $dR->id) : route('dr.store') }}" method="post" enctype="multipart/form-data" {{ $isEdit ? 'data-skip-validation novalidate' : '' }}
        class="bg-[var(--secondary-bg-color)] text-sm rounded-xl shadow-lg p-8 border border-[var(--glass-border-color)]/20 pt-14 max-w-5xl mx-auto  relative overflow-hidden">
        @csrf
        @if ($isEdit) @method('PUT') @endif
        <x-form-title-bar title="{{ $isEdit ? 'Edit DR' : 'Generate DR' }}" />

        <!-- Step 1: Generate cargo list -->
        <div class="step1 space-y-4 ">
            <div class="flex items-end gap-4">
                <div class="grow">
                    <!-- customer -->
                    <x-select
                        label="Customer"
                        id="customer"
                        name="customer_id"
                        :options="$customer_options"
                        :value="$isEdit ? $dR->customer_id : ''"
                        showDefault
                        onchange="trackCustomerState(this)"
                    />
                </div>

                <div class="w-1/4">
                    {{-- date --}}
                    <x-input label="Date" name="date" id="date" type="date" validateMax max="{{ today()->toDateString() }}" required value="{{ $isEdit ? $dR->date?->format('Y-m-d') : '' }}"/>
                </div>

                <button id="showPaymentBtn" type="button" class="bg-[var(--primary-color)] px-4 py-2 rounded-lg hover:bg-[var(--h-primary-color)] transition-all duration-300 ease-in-out text-nowrap cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed" disabled onclick="getPayments()">Show Payments</button>
            </div>
            <input type="hidden" name="returnPayments" id="selectedPaymentsArray">
            {{-- show-payment-table --}}
            <div id="show-payment-table" class="w-full text-left text-sm">
                <div class="flex justify-between items-center bg-[var(--h-bg-color)] rounded-lg py-2 px-4">
                    <div class="w-[8%]">S.No.</div>
                    <div class="w-1/6">Date</div>
                    <div class="w-[10%]">Method</div>
                    <div class="w-1/6">Reff. No.</div>
                    <div class="w-1/6">Amount</div>
                    <div class="w-1/6">Issued</div>
                    <div class="w-[10%] text-center">Select</div>
                </div>
                <div id="show-payments" class="h-[20rem] overflow-y-auto my-scrollbar-2">
                    <div class="text-center bg-[var(--h-bg-color)] rounded-lg py-2 px-4 mt-4">No Payments Added</div>
                </div>
            </div>

            <div class="w-full grid grid-cols-2 gap-4 text-sm mt-5 text-nowrap">
                <div class="flex justify-between items-center border border-gray-600 rounded-lg py-2 px-4 w-full">
                    <div class="grow">Total Selected Payments</div>
                    <div id="finalSelectedPayments">0</div>
                </div>
                <div class="flex justify-between items-center border border-gray-600 rounded-lg py-2 px-4 w-full">
                    <div class="grow">Total Selected Amount</div>
                    <div class="finalTotalSelectedAmount">0</div>
                </div>
            </div>
        </div>

        <!-- Step 2: view shipment -->
        <div class="step2 hidden space-y-4">
            <div class="flex items-end gap-4">
                <div class="grow">
                    <!-- method -->
                    <x-select
                        label="Method"
                        id="method"
                        required
                        showDefault
                        :options="$method_options"
                        onchange="trackMethodState(this)"
                    />
                </div>
            </div>
            <input type="hidden" name="newPayments" id="addedPaymentsArray">
            {{-- add-payment-table --}}
            <div id="add-payment-table" class="w-full text-left text-sm">
                <div class="grid grid-cols-5 bg-[var(--h-bg-color)] rounded-lg py-2 px-4">
                    <div>S.No.</div>
                    <div>Method</div>
                    <div>Reff. No.</div>
                    <div>Amount</div>
                    <div class="text-center">Action</div>
                </div>
                <div id="added-payments" class="h-[20rem] overflow-y-auto my-scrollbar-2">
                    <div class="text-center bg-[var(--h-bg-color)] rounded-lg py-2 px-4 mt-4">No Payments Added</div>
                </div>
            </div>

            <div class="w-full grid grid-cols-2 gap-4 text-sm mt-5 text-nowrap">
                <div class="flex justify-between items-center border border-gray-600 rounded-lg py-2 px-4 w-full">
                    <div class="grow">Total Selected Amount</div>
                    <div class="finalTotalSelectedAmount">0</div>
                </div>
                <div class="flex justify-between items-center border border-gray-600 rounded-lg py-2 px-4 w-full">
                    <div class="grow">Total Added Payment</div>
                    <div id="finalTotalAddedAmount">0</div>
                </div>
            </div>
        </div>
    </form>

@endsection

@push('page-scripts')
<script>
        window.__drGenerate = {
            editData: @json($editData),
            bankSelectHtml: @json($bankSelectHtml),
            remarksInputHtml: @json($remarksInputHtml),
            selectPaymentAlertHtml: @json($selectPaymentAlertHtml),
            amountMismatchAlertHtml: @json($amountMismatchAlertHtml),
        };
    </script>
<script defer src="{{ asset('js/pages/dr-generate.js') }}?v={{ filemtime(public_path('js/pages/dr-generate.js')) }}"></script>
@endpush
