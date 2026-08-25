@extends('app')
@section('title', 'Show Orders | ' . $client_company->name)
@section('content')
    @php
        $searchFields = [
            "Order No" => [
                "id" => "order_no",
                "type" => "text",
                "placeholder" => "Enter order no",
                                "dataFilterPath" => "order_no",
            ],
            "Customer Name" => [
                "id" => "customer_name",
                "type" => "text",
                "placeholder" => "Enter customer name",
                                "dataFilterPath" => "customer_name",
            ],
            "City" => [
                "id" => "city",
                "type" => "text",
                "placeholder" => "Enter city",
                                "dataFilterPath" => "city",
            ],
            "Category" => [
                "id" => "category",
                "type" => "text",
                "placeholder" => "Enter category",
                                "dataFilterPath" => "category",
            ],
            'Status' => [
                'id' => 'status',
                'type' => 'select',
                'options' => [
                    'pending' => ['text' => 'Pending'],
                    'partially_invoiced' => ['text' => 'Partially Invoiced'],
                    'invoiced' => ['text' => 'Invoiced'],
                ],
                'dataFilterPath' => 'status',
            ],
            "Date Range" => [
                "id" => "date_range_start",
                "type" => "date",
                "id2" => "date_range_end",
                "type2" => "date",
                                "dataFilterPath" => "date",
            ]
        ];
    @endphp

    <div class="w-[80%] mx-auto">
        <x-search-header heading="Orders" :search_fields=$searchFields/>
    </div>

    <!-- Main Content -->
    <section class="text-center mx-auto ">
        <div
            class="show-box mx-auto w-[80%] h-[70vh] bg-[var(--secondary-bg-color)] border border-[var(--glass-border-color)]/20 rounded-xl shadow pt-8.5 relative">
            <x-form-title-bar printBtn title="Show Orders" changeLayoutBtn layout="{{ $authLayout }}" resetSortBtn />

            @if (in_array(Auth::user()->role, ['developer', 'owner', 'admin', 'accountant', 'customer']))
                <div class="absolute bottom-3 right-3 flex items-center gap-2 w-fll z-50">
                    <x-section-navigation-button link="{{ route('orders.create') }}" title="Add New Order" icon="fa-plus" />
                </div>
            @endif

            <div class="details h-full z-40">
                <div class="container-parent h-full">
                    <div class="card_container px-3 h-full flex flex-col">
                        <div id="table-head" class="grid grid-cols-7 bg-[var(--h-bg-color)] rounded-lg font-medium py-2 hidden mt-4">
                            <div class="text-center cursor-pointer" onclick="sortByThis(this)">Date</div>
                            <div class="text-center cursor-pointer" onclick="sortByThis(this)">Order No.</div>
                            <div class="text-center cursor-pointer" onclick="sortByThis(this)">Customer</div>
                            <div class="text-center cursor-pointer" onclick="sortByThis(this)">Discount</div>
                            <div class="text-center cursor-pointer" onclick="sortByThis(this)">Net Amount</div>
                            <div class="text-center cursor-pointer" onclick="sortByThis(this)">Balance Order</div>
                            <div class="text-center cursor-pointer" onclick="sortByThis(this)">Status</div>
                        </div>
                        <p id="noItemsError" style="display: none" class="text-sm text-[var(--border-error)] mt-3">No items found</p>
                        <div class="overflow-y-auto grow my-scrollbar-2">
                            <div class="search_container grid grid-cols-1 sm:grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5 grow">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

@endsection


@push('page-scripts')
<script defer src="{{ asset('js/pages/orders-index.js') }}"></script>
<script>
        window.__ordersIndex = {
            companyData: @json($client_company),
            authLayout: '{{ $authLayout }}',
            currentUserRole: @json(Auth::user()->role),
            openOrderId: @json(request()->integer('open_order') ?: null),
            printOpenOrder: @json(request()->boolean('print_order')),
            ordersBaseUrl: @json(url('orders')),
        };
    </script>
@endpush
