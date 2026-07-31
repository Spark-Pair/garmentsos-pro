@extends('app')
@section('title', 'Generate CR | ' . $client_company->name)
@section('content')
    @php
        $method_options = [
            'cheque' => ['text' => 'Cheque'],
            'slip' => ['text' => 'Slip'],
            'self_cheque' => ['text' => 'Self Cheque'],
            'program' => ['text' => 'Payment Program'],
        ];
    @endphp
    @php
        $voucherErrorAlertTemplate = view('components.alert', [
            'type' => 'error',
            'messages' => '__MESSAGE__',
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
        <x-search-header heading="Generate CR" link linkText="Show CR" linkHref="{{ route('cr.index') }}"/>
        <x-progress-bar :steps="['Select Payment', 'Add Payment']" :currentStep="1" />
    </div>

    <!-- Form -->
    <form id="form" action="{{ route('cr.store') }}" method="post" enctype="multipart/form-data"
        class="bg-[var(--secondary-bg-color)] text-sm rounded-xl shadow-lg p-8 border border-[var(--glass-border-color)]/20 pt-14 max-w-5xl mx-auto  relative overflow-hidden">
        @csrf
        <x-form-title-bar title="Generate CR" />

        <!-- Step 1: Generate cargo list -->
        <div class="step1 space-y-4 ">
            <div class="grid grid-cols-4 gap-4">
                <!-- voucher_no -->
                <x-input
                    label="Voucher No."
                    id="voucher_no"
                    name="voucher_no"
                    placeholder="Enter Voucher No."
                    required
                    onkeydown="trackVoucherState(event)"
                />
                <input type="hidden" name="voucher_id" id="voucher_id">

                {{-- cargo date --}}
                <x-input label="Date" name="date" id="date" type="date" validateMax max="{{ today()->toDateString() }}" required disabled/>

                <!-- supplier_name -->
                <x-input
                    label="Supplier Name"
                    id="supplier_name"
                    disabled
                    placeholder="Supplier Name"
                />

                {{-- c_r_no --}}
                <x-input label="CR No." name="c_r_no" id="c_r_no" required value=""/>
            </div>
            <input type="hidden" name="returnPayments" id="selectedPaymentsArray">
            {{-- show-payment-table --}}
            <div id="show-payment-table" class="w-full text-left text-sm">
                <div class="flex justify-between items-center bg-[var(--h-bg-color)] rounded-lg py-2 px-4 mb-4">
                    <div class="w-[8%]">S.No.</div>
                    <div class="w-1/6">Date</div>
                    <div class="w-[10%]">Method</div>
                    <div class="w-1/6">Reff. No.</div>
                    <div class="w-1/6">Amount</div>
                    <div class="grow">Customer</div>
                    <div class="w-[10%] text-center">Select</div>
                </div>
                <div id="show-payment" class="h-[20rem] overflow-y-auto my-scrollbar-2">
                    <div class="text-center bg-[var(--h-bg-color)] rounded-lg py-3 px-4">No Payments Added</div>
                </div>
            </div>

            <div class="w-full grid grid-cols-2 gap-4 text-sm mt-5 text-nowrap">
                <div class="flex justify-between items-center border border-gray-600 rounded-lg py-2 px-4 w-full">
                    <div class="grow">Total Voucher Payment</div>
                    <div id="finalTotalPayment">0</div>
                </div>
                <div class="flex justify-between items-center border border-gray-600 rounded-lg py-2 px-4 w-full">
                    <div class="grow">Total Selected Payment</div>
                    <div id="finalTotalSelectedPayment">0</div>
                </div>
            </div>
        </div>

        <!-- Step 2: view shipment -->
        <div class="step2 hidden space-y-4">
            <div class="flex items-end gap-4">
                <!-- method -->
                <x-select
                    label="Method"
                    id="method"
                    :options="$method_options"
                    required
                    showDefault
                    onchange="trackMethodState(this)"
                />

                <div class="grow">
                    <!-- payment -->
                    <x-select
                        label="Payment"
                        id="payment"
                        :options="$payment_options"
                        required
                        showDefault
                        onchange="trackPaymentState(this)"
                    />
                </div>

                <!-- supplier_name -->
                <x-input
                    label="Amount"
                    id="amount"
                    name="amount"
                    disabled
                    placeholder="Enter Amount"
                    type="amount"
                    dataValidate="required|amount"
                    oninput="trackAmountState(this)"
                    onkeydown="enterToAdd(event)"
                />

                <button id="addPaymentBtn" type="button" class="bg-[var(--primary-color)] px-4 py-2 rounded-lg hover:bg-[var(--h-primary-color)] transition-all duration-300 ease-in-out text-nowrap cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed" onclick="addPayment()">Add Payment</button>
            </div>
            <input type="hidden" name="newPayments" id="addedPaymentsArray">
            {{-- add-payment-table --}}
            <div id="add-payment-table" class="w-full text-left text-sm">
                <div class="grid grid-cols-6 bg-[var(--h-bg-color)] rounded-lg py-2 px-4 mb-4">
                    <div>S.No.</div>
                    <div>Method</div>
                    <div class="col-span-2">Payment</div>
                    <div>Amount</div>
                    <div class="text-center">Action</div>
                </div>
                <div id="add-payment" class="h-[20rem] overflow-y-auto my-scrollbar-2">
                    <div class="text-center bg-[var(--h-bg-color)] rounded-lg py-3 px-4">No Payments Added</div>
                </div>
            </div>

            <div class="w-full grid grid-cols-2 gap-4 text-sm mt-5 text-nowrap">
                <div class="flex justify-between items-center border border-gray-600 rounded-lg py-2 px-4 w-full">
                    <div class="grow">Total Selected Payment</div>
                    <div id="finalTotalSelectedPayment">0</div>
                </div>
                <div class="flex justify-between items-center border border-gray-600 rounded-lg py-2 px-4 w-full">
                    <div class="grow">Total Added Payment</div>
                    <div id="finalTotalAddedPayment">0</div>
                </div>
            </div>
        </div>
    </form>

@endsection

@push('page-scripts')
<script defer src="{{ asset('js/pages/cr-generate.js') }}"></script>
<script>
        window.__crGenerate = {
            voucherErrorAlertTemplate: @json($voucherErrorAlertTemplate),
            selectPaymentAlertHtml: @json($selectPaymentAlertHtml),
            amountMismatchAlertHtml: @json($amountMismatchAlertHtml),
        };
    </script>
@endpush
