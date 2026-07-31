@extends('app')
@section('title', 'Show Customer Payments | ' . $client_company->name)
@section('content')
@php
    $searchFields = [
        "Date Range" => [
            "id" => "date_range_start",
            "type" => "date",
            // "value" => now()->startOfMonth()->toDateString(),
            "id2" => "date_range_end",
            "type2" => "date",
            // "value2" => now()->toDateString(),
            "dataFilterPath" => "date",
        ],
        "Created Date Range" => [
            "id" => "created_range_start",
            "type" => "date",
            "id2" => "created_range_end",
            "type2" => "date",
            "dataFilterPath" => "created_at",
        ],
        "Reff. No." => [
            "id" => "reff_no",
            "type" => "text",
            "placeholder" => "Enter reff. no.",
            "dataFilterPath" => "reff_no",
        ],
        "Customer Name" => [
            "type" => "text",
            "id" => "customer_name",
            "placeholder" => "Enter customer name",
            "dataFilterPath" => "customer_name",
        ],
        "City" => [
            "type" => "text",
            "id" => "city",
            "placeholder" => "Enter city",
            "dataFilterPath" => "city",
        ],
        "Beneficiary" => [
            "type" => "text",
            "id" => "beneficiary",
            "placeholder" => "Enter beneficiary",
            "dataFilterPath" => "beneficiary",
        ],
        "Supplier Name" => [
            "type" => "text",
            "id" => "supplier_name",
            "placeholder" => "Enter supplier name",
            "dataFilterPath" => "supplier_name",
        ],
        "Method" => [
            "type" => "select",
            "id" => "method",
            "options" => [
                        'cash' => ['text' => 'Cash'],
                        'cheque' => ['text' => 'Cheque'],
                        'slip' => ['text' => 'Slip'],
                        'program' => ['text' => 'Program'],
                        'adjustment' => ['text' => 'Adjustment'],
                    ],
            "dataFilterPath" => "method",
        ],
        "Category" => [
            "type" => "select",
            "id" => "category",
            "options" => [
                        'cash' => ['text' => 'Cash'],
                        'non-cash' => ['text' => 'Non Cash'],
                    ],
            "dataFilterPath" => "category",
        ],
        "Type" => [
            "type" => "select",
            "id" => "type",
            "options" => [
                        'normal' => ['text' => 'Normal'],
                        'payment_program' => ['text' => 'Payment Program'],
                        'recovery' => ['text' => 'Recovery'],
                    ],
            "dataFilterPath" => "type",
        ],
        "Issued" => [
            "type" => "select",
            "id" => "issued",
            "options" => [
                        'Issued' => ['text' => 'Issued'],
                        'Return' => ['text' => 'Return'],
                        'DR' => ['text' => 'DR'],
                        'Not Issued' => ['text' => 'Not Issued'],
                    ],
            "dataFilterPath" => "issued",
        ],
        "Status" => [
            "type" => "select",
            "id" => "status",
            "options" => [
                        'Cleared' => ['text' => 'Cleared'],
                        'Pending' => ['text' => 'Pending'],
                    ],
            "dataFilterPath" => "status",
        ],
        "Voucher / CR No." => [
            "type" => "text",
            "id" => "voucher_no",
            "placeholder" => "Enter voucher no.",
            "dataFilterPath" => "voucher_no",
        ],
        "Amount" => [
            "type" => "text",
            "id" => "amount",
            "placeholder" => "Enter Amount",
            "dataFilterPath" => "amount",
        ],
    ];
@endphp
    <div class="w-[80%] mx-auto">
        <x-search-header heading="Customer Payments" :search_fields=$searchFields/>
    </div>

    <!-- Main Content -->
    <section class="text-center mx-auto ">
        <div
            class="show-box mx-auto w-[80%] h-[70vh] bg-[var(--secondary-bg-color)] border border-[var(--glass-border-color)]/20 rounded-xl shadow pt-8.5 relative">
            <x-form-title-bar printBtn title="Show Customer Payments" changeLayoutBtn layout="{{ $authLayout }}" resetSortBtn />

            <div class="absolute bottom-14 right-0 flex items-center justify-between gap-2 w-fll z-50 p-3 w-full pointer-events-none">
                <x-section-navigation-button link="{{ route('customer-payments.create') }}" title="Add New Payment" icon="fa-plus" />
            </div>

            <div class="details h-full z-40">
                <div class="container-parent h-full">
                    <div class="card_container px-3 pb-3 h-full flex flex-col">
                        <div id="table-head" class="flex justify-between bg-[var(--h-bg-color)] rounded-lg font-medium py-2 hidden mt-4">
                            <div class="text-center w-1/8 cursor-pointer" onclick="sortByThis(this)">Date</div>
                            <div class="text-center w-1/4 cursor-pointer" onclick="sortByThis(this)">Customer</div>
                            <div class="text-center w-1/6 cursor-pointer" onclick="sortByThis(this)">Supplier Name</div>
                            <div class="text-center w-1/6 cursor-pointer" onclick="sortByThis(this)">Beneficiary</div>
                            <div class="text-center w-1/12 cursor-pointer" onclick="sortByThis(this)">Method</div>
                            <div class="text-center w-1/12 cursor-pointer" onclick="sortByThis(this)">Amount</div>
                            <div class="text-center w-1/12 cursor-pointer" onclick="sortByThis(this)">Reff. No.</div>
                            <div class="text-center w-1/8 cursor-pointer" onclick="sortByThis(this)">Clear Date</div>
                            <div class="text-center w-1/10 cursor-pointer" onclick="sortByThis(this)">Cleared Amt.</div>
                            <div class="text-center w-1/11 cursor-pointer" onclick="sortByThis(this)">Vou./CR No.</div>
                            <div class="text-center w-1/12 cursor-pointer" onclick="sortByThis(this)">DR No.</div>
                        </div>
                        <p id="noItemsError" style="display: none" class="text-sm text-[var(--border-error)] mt-3">No items found</p>
                        <div class="overflow-y-auto grow my-scrollbar-2">
                            <div class="search_container grid grid-cols-1 sm:grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5 grow">
                            </div>
                        </div>
                        <div id="calc-bottom" class="flex w-full gap-4 text-sm bg-[var(--secondary-bg-color)] pt-2 rounded-lg">
                            <div
                                class="total-Amount flex justify-between items-center border border-gray-600 rounded-lg py-2 px-4 w-full cursor-not-allowed">
                                <div>Total Amount - Rs.</div>
                                <div class="text-right">0.0</div>
                            </div>
                            <div
                                class="total-Payment flex justify-between items-center border border-gray-600 rounded-lg py-2 px-4 w-full cursor-not-allowed">
                                <div>Total Payment - Rs.</div>
                                <div class="text-right">0.0</div>
                            </div>
                            <div
                                class="balance flex justify-between items-center border border-gray-600 rounded-lg py-2 px-4 w-full cursor-not-allowed">
                                <div>Balance - Rs.</div>
                                <div class="text-right">0.0</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

@endsection


@push('page-scripts')
<script defer src="{{ asset('js/pages/customer-payments-index.js') }}"></script>
<script>
        @php
            $methodSelectHtml = view('components.select', [
                'label' => 'Method',
                'name' => 'method_select',
                'id' => 'method_select',
                'options' => [
                    'online' => ['text' => 'Online'],
                    'cash' => ['text' => 'Cash'],
                ],
                'required' => true,
                'showDefault' => true,
                'onchange' => 'trackMethodState(this)',
            ])->render();

            $bankAccountSelectHtml = view('components.select', [
                'label' => 'Bank Account',
                'addBtnLink' => '/bank-accounts/create',
                'name' => 'bank_account_id',
                'id' => 'bank_account_id',
                'options' => [],
                'required' => true,
                'disabled' => true,
                'showDefault' => true,
            ])->render();

            $amountInputHtml = view('components.input', [
                'label' => 'Amount',
                'name' => 'amount',
                'id' => 'amount',
                'type' => 'amount',
                'placeholder' => 'Enter amount',
                'dataValidate' => 'required|amount',
                'oninput' => 'validateInput(this)',
                'required' => true,
            ])->render();

            $reffInputHtml = view('components.input', [
                'label' => 'Reff. No.',
                'name' => 'reff_no',
                'id' => 'reff_no',
                'placeholder' => 'Enter reff. no.',
                'required' => true,
                'disabled' => true,
            ])->render();
        @endphp

        window.__customerPaymentsIndex = {
            companyData: @json($client_company),
            authLayout: @json($authLayout),
            methodSelectHtml: @json($methodSelectHtml),
            bankAccountSelectHtml: @json($bankAccountSelectHtml),
            amountInputHtml: @json($amountInputHtml),
            reffNoInputHtml: @json($reffInputHtml),
            routes: {
                splitPayment: @json(url('customer-payments') . '/:id/split'),
            },
        };
    </script>
@endpush
