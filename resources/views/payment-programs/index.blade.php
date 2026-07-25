@extends('app')
@section('title', 'Show Payment Programs | ' . $client_company->name)
@section('content')
    @php
        $categories_options = [
            'self_account' => ['text' => 'Self Account'],
            'supplier' => ['text' => 'Supplier'],
            'waiting' => ['text' => 'Waiting'],
        ];

        $searchFields = [
            'Customer Name' => [
                'id' => 'customer_name',
                'type' => 'text',
                'placeholder' => 'Enter customer name',
                                'dataFilterPath' => 'customer_name',
            ],
            "City" => [
                "type" => "text",
                "id" => "city",
                "placeholder" => "Enter city",
                "dataFilterPath" => "city",
            ],
            'Category' => [
                'id' => 'category',
                'type' => 'select',
                'options' => [
                    'supplier' => ['text' => 'Supplier'],
                    'self_account' => ['text' => 'Self Account'],
                    'waiting' => ['text' => 'Waiting'],
                ],
                                'dataFilterPath' => 'category',
            ],
            'Type' => [
                'id' => 'type',
                'type' => 'select',
                'options' => [
                    'order' => ['text' => 'Order'],
                    'program' => ['text' => 'Program'],
                ],
                                'dataFilterPath' => 'type',
            ],
            'Beneficiary' => [
                'id' => 'beneficiary',
                'type' => 'text',
                'placeholder' => 'Enter beneficiary',
                                'dataFilterPath' => 'beneficiary',
            ],
            'Status' => [
                'id' => 'status',
                'type' => 'select',
                'options' => [
                    '__all__' => ['text' => 'All Statuses'],
                    'Paid' => ['text' => 'Paid'],
                    'Unpaid' => ['text' => 'Unpaid', 'selected' => true],
                    'Overpaid' => ['text' => 'Overpaid'],
                ],
                                'dataFilterPath' => 'status',
            ],
            'Date Range' => [
                'id' => 'date_range_start',
                'type' => 'date',
                'id2' => 'date_range_end',
                'type2' => 'date',
                                'dataFilterPath' => 'date',
            ],
        ];
    @endphp
    <div class="w-[80%] mx-auto">
        <x-search-header heading="Payment Programs" :search_fields=$searchFields />
    </div>

    <!-- Main Content -->
    <section class="text-center mx-auto">
        <div
            class="show-box mx-auto w-[80%] h-[70vh] bg-[var(--secondary-bg-color)] border border-[var(--glass-border-color)]/20 rounded-xl shadow pt-8.5 relative">
            <x-form-title-bar printBtn layout="table" title="Show Payment Programs" resetSortBtn />

            <div class="absolute bottom-14 right-0 flex items-center justify-end gap-2 w-fll z-50 p-3 w-full pointer-events-none">
                <x-section-navigation-button link="{{ route('payment-programs.create') }}" title="Add New Program"
                    icon="fa-plus" />
            </div>

            <div class="details h-full z-40">
                <div class="container-parent h-full">
                    <div class="card_container px-3 pb-3 h-full flex flex-col">
                        <div id="table-head" class="flex items-center bg-[var(--h-bg-color)] rounded-lg font-medium py-2 hidden mt-4">
                            <div class="w-[10%] cursor-pointer" onclick="sortByThis(this)">Date</div>
                            <div class="w-[8%] cursor-pointer" onclick="sortByThis(this)">O/P No.</div>
                            <div class="w-[19%] cursor-pointer" onclick="sortByThis(this)">Customer</div>
                            <div class="w-[10%] cursor-pointer" onclick="sortByThis(this)">Cus. Bal.</div>
                            <div class="w-[9%] cursor-pointer" onclick="sortByThis(this)">Category</div>
                            <div class="w-[15%] cursor-pointer" onclick="sortByThis(this)">Beneficiary</div>
                            <div class="w-[10%] cursor-pointer" onclick="sortByThis(this)">Amount</div>
                            <div class="w-[10%] cursor-pointer" onclick="sortByThis(this)">Payment</div>
                            <div class="w-[10%] cursor-pointer" onclick="sortByThis(this)">Balance</div>
                            <div class="w-[10%] cursor-pointer" onclick="sortByThis(this)">Status</div>
                        </div>
                        <p id="noItemsError" style="display: none" class="text-sm text-[var(--border-error)] mt-3 cursor-pointer" onclick="sortByThis(this)">No items found</p>
                        <div class="overflow-y-auto grow my-scrollbar-2">
                            <div class="search_container grid grid-cols-1 sm:grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5 grow">
                            </div>
                        </div>
                        <div id="calc-bottom" class="flex w-full gap-4 text-sm bg-[var(--secondary-bg-color)] pt-2 rounded-lg">
                            <div class="total-Amount flex justify-between items-center border border-gray-600 rounded-lg py-2 px-4 w-full cursor-not-allowed">
                                <div>Total Amount - Rs.</div>
                                <div class="text-right">0.0</div>
                            </div>
                            <div class="total-Payment flex justify-between items-center border border-gray-600 rounded-lg py-2 px-4 w-full cursor-not-allowed">
                                <div>Total Payment - Rs.</div>
                                <div class="text-right">0.0</div>
                            </div>
                            <div class="balance flex justify-between items-center border border-gray-600 rounded-lg py-2 px-4 w-full cursor-not-allowed">
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
<script defer src="{{ asset('js/pages/payment-programs-index.js') }}"></script>
<script>
        @php
            $categorySelectHtml = view('components.select', [
                'label' => 'Category',
                'name' => 'category',
                'id' => 'category',
                'options' => $categories_options,
                'showDefault' => true,
                'onchange' => 'trackCategoryState(this)',
                'required' => true,
            ])->render();

            $subCategorySelectHtml = view('components.select', [
                'label' => 'Sub Category',
                'name' => 'sub_category',
                'id' => 'subCategory',
                'options' => [],
                'showDefault' => true,
            ])->render();
        @endphp

        window.__ppIndex = {
            categorySelectHtml: @json($categorySelectHtml),
            subCategorySelectHtml: @json($subCategorySelectHtml),
            csrfToken: @json(csrf_token()),
            authLayout: @json($authLayout),
            routes: {
                updateProgram: @json(route('payment-programs.update-program')),
                customerPaymentsCreate: @json(route('customer-payments.create')),
                markPaid: @json(route('payment-programs.mark-paid', ':id')),
            },
        };
    </script>
@endpush
