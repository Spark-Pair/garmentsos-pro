@extends('app')
@section('title', 'Show Article Report | ' . $client_company->name)
@section('content')
    @php
        $searchFields = [
            "Article No" => [
                "id" => "article_no",
                "type" => "text",
                "placeholder" => "Enter article no",
                                "dataFilterPath" => "article_no",
            ],
            "Customer Name" => [
                "id" => "customer_name",
                "type" => "text",
                "placeholder" => "Enter customer name",
                                "dataFilterPath" => "customer_name",
            ],
            "Invoice No." => [
                "id" => "invoice_no",
                "type" => "text",
                "placeholder" => "Enter invoice no",
                                "dataFilterPath" => "invoice_no",
            ],
            "Reff No." => [
                "id" => "reff_no",
                "type" => "text",
                "placeholder" => "Enter order or shipment no",
                                "dataFilterPath" => "reff_no",
            ],
            "Reff Date Range" => [
                "id" => "reff_date_range_start",
                "type" => "date",
                "id2" => "reff_date_range_end",
                "type2" => "date",
                                "dataFilterPath" => "reff_date",
            ],
            "Invoice Date Range" => [
                "id" => "invoice_date_range_start",
                "type" => "date",
                "id2" => "invoice_date_range_end",
                "type2" => "date",
                                "dataFilterPath" => "invoice_date",
            ]
        ];
    @endphp

    <div class="w-[90%] mx-auto">
        <x-search-header heading="Article Report" :search_fields=$searchFields />
    </div>

    <!-- Main Content -->
    <section class="text-center mx-auto ">
        <div
            class="show-box mx-auto w-[90%] h-[70vh] bg-[var(--secondary-bg-color)] border border-[var(--glass-border-color)]/20 rounded-xl shadow pt-8.5 relative">
            <x-form-title-bar printBtn layout="table" title="Show Article Report" resetSortBtn />

            <div class="details h-full z-40">
                <div class="container-parent h-full">
                    <div class="card_container px-3 pb-3 h-full flex flex-col">
                        <div id="table-head" class="flex items-center bg-[var(--h-bg-color)] rounded-lg font-medium py-2 hidden mt-4 text-xs">
                            <div class="w-[8%] cursor-pointer" onclick="sortByThis(this)">Article No.</div>
                            <div class="grow cursor-pointer text-left" onclick="sortByThis(this)">Customer</div>
                            <div class="w-[10%] cursor-pointer" onclick="sortByThis(this)">Reff Date</div>
                            <div class="w-[9%] cursor-pointer" onclick="sortByThis(this)">Reff No.</div>
                            <div class="w-[12%] cursor-pointer" onclick="sortByThis(this)">Reff Qty</div>
                            <div class="w-[6%] cursor-pointer" onclick="sortByThis(this)">Unit</div>
                            <div class="w-[10%] cursor-pointer" onclick="sortByThis(this)">Invoice Date</div>
                            <div class="w-[9%] cursor-pointer" onclick="sortByThis(this)">Invoice No.</div>
                            <div class="w-[12%] cursor-pointer" onclick="sortByThis(this)">Invoice Qty</div>
                        </div>
                        <p id="noItemsError" style="display: none" class="text-sm text-[var(--border-error)] mt-3">No items found</p>
                        <div class="overflow-y-auto grow my-scrollbar-2">
                            <div class="search_container grid grid-cols-1 sm:grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5 grow">
                            </div>
                        </div>
                        <div id="calc-bottom" class="flex w-full gap-4 text-sm bg-[var(--secondary-bg-color)] pt-2 rounded-lg">
                            <div class="total-reff-quantity flex justify-between items-center border border-gray-600 rounded-lg py-2 px-4 w-full cursor-not-allowed">
                                <div>Total Reff Qty</div>
                                <div class="text-right">0Pc</div>
                            </div>
                            <div class="total-invoice-quantity flex justify-between items-center border border-gray-600 rounded-lg py-2 px-4 w-full cursor-not-allowed">
                                <div>Total Invoice Qty</div>
                                <div class="text-right">0Pc</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

@endsection

@push('page-scripts')
<script defer src="{{ asset('js/pages/reports-article.js') }}"></script>
<script>
    window.__reportsArticle = {
        authLayout: @json($authLayout),
    };
</script>
@endpush
