@extends('app')
@section('title', 'Show Sales Return | ' . $client_company->name)
@section('content')
    @php
        $searchFields = [
            "ID" => [
                "id" => "id",
                "type" => "text",
                "placeholder" => "Enter ID or 650-660",
                "dataFilterPath" => "id",
                "listInput" => true,
            ],
            "Customer" => [
                "id" => "customer",
                "type" => "text",
                "placeholder" => "Enter customer",
                                "dataFilterPath" => "customer",
            ],
            "Article No" => [
                "id" => "article_no",
                "type" => "text",
                "placeholder" => "Enter article no",
                                "dataFilterPath" => "article_no",
            ],
            "Invoice No" => [
                "id" => "invoice_no",
                "type" => "text",
                "placeholder" => "Enter invoice no",
                                "dataFilterPath" => "invoice_no",
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
        <x-search-header heading="Sales Return" :search_fields=$searchFields />
    </div>

    <!-- Main Content -->
    <section class="text-center mx-auto ">
        <div
            class="show-box mx-auto w-[80%] h-[70vh] bg-[var(--secondary-bg-color)] border border-[var(--glass-border-color)]/20 rounded-xl shadow pt-8.5 relative">
            <x-form-title-bar printBtn layout="table" title="Show Sales Return" resetSortBtn />

            <div class="absolute bottom-14 right-0 flex items-center justify-end gap-2 w-fll z-50 p-3 w-full pointer-events-none">
                <x-section-navigation-button link="{{ route('sales-returns.create') }}" title="Add New Record"
                    icon="fa-plus" />
            </div>

            <div class="details h-full z-40">
                <div class="container-parent h-full">
                    <div class="card_container px-3 pb-3 h-full flex flex-col">
                        <div id="table-head" class="grid grid-cols-[0.5fr_1.25fr_1.8fr_1.15fr_1.15fr_0.85fr_0.9fr_1.05fr] items-center bg-[var(--h-bg-color)] rounded-lg font-medium py-2 hidden mt-4">
                            <div class="cursor-pointer" onclick="sortByThis(this)">ID</div>
                            <div class="cursor-pointer" onclick="sortByThis(this)">Date</div>
                            <div class="cursor-pointer" onclick="sortByThis(this)">Customer</div>
                            <div class="cursor-pointer" onclick="sortByThis(this)">Article No.</div>
                            <div class="cursor-pointer" onclick="sortByThis(this)">Invoice No.</div>
                            <div class="cursor-pointer" onclick="sortByThis(this)">Type</div>
                            <div class="cursor-pointer" onclick="sortByThis(this)">Quantity</div>
                            <div class="cursor-pointer" onclick="sortByThis(this)">Amount</div>
                        </div>
                        <p id="noItemsError" style="display: none" class="text-sm text-[var(--border-error)] mt-3">No items found</p>
                        <div class="overflow-y-auto grow my-scrollbar-2">
                            <div class="search_container grid grid-cols-1 sm:grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5 grow">
                            </div>
                        </div>
                        <div id="calc-bottom" class="flex w-full gap-4 text-sm bg-[var(--secondary-bg-color)] pt-2 rounded-lg">
                            <div class="total-Amount flex justify-between items-center border border-gray-600 rounded-lg py-2 px-4 w-full cursor-not-allowed">
                                <div>Total Sales Return - Rs.</div>
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
<script defer src="{{ asset('js/pages/sales-return-index.js') }}"></script>
<script>
        window.__salesReturnIndex = {
            authLayout: @json($authLayout),
        };
    </script>
@endpush
