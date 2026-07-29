@extends('app')
@section('title', 'Show Supplier Payments | ' . $client_company->name)
@section('content')
@php
    $searchFields = [
        // "Date Range" => [
        //     "id" => "date_range_start",
        //     "type" => "date",
        //     // "value" => now()->startOfMonth()->toDateString(),
        //     "id2" => "date_range_end",
        //     "type2" => "date",
        //     // "value2" => now()->toDateString(),
        //     "dataFilterPath" => "date",
        // ],
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
                        'Cash' => ['text' => 'Cash'],
                        'Cheque' => ['text' => 'Cheque'],
                        'Slip' => ['text' => 'Slip'],
                        'program' => ['text' => 'Program'],
                        'Self Cheque' => ['text' => 'Self Cheque'],
                        'ATM' => ['text' => 'ATM'],
                        'Adjustment' => ['text' => 'Adjustment'],
                    ],
            "dataFilterPath" => "method",
        ],
        "Issued" => [
            "type" => "select",
            "id" => "issued",
            "options" => [
                        'Issued' => ['text' => 'Issued'],
                        'Not Issued' => ['text' => 'Not Issued'],
                    ],
            "dataFilterPath" => "issued",
        ],
        "Reff No." => [
            "type" => "text",
            "id" => "reff_no",
            "placeholder" => "Enter reff no.",
            "dataFilterPath" => "reff_no",
        ],
        "Voucher / CR No." => [
            "type" => "text",
            "id" => "voucher_no",
            "placeholder" => "Enter voucher no.",
            "dataFilterPath" => "voucher_no",
        ],
    ];
@endphp
    <div class="w-[80%] mx-auto">
        <x-search-header heading="Supplier Payments" :search_fields=$searchFields/>
    </div>

    <!-- Main Content -->
    <section class="text-center mx-auto ">
        <div
            class="show-box mx-auto w-[80%] h-[70vh] bg-[var(--secondary-bg-color)] border border-[var(--glass-border-color)]/20 rounded-xl shadow pt-8.5 relative">
            <x-form-title-bar printBtn title="Show Supplier Payments" layout="table" resetSortBtn />

            <div class="details h-full z-40">
                <div class="container-parent h-full">
                    <div class="card_container px-3 h-full flex flex-col">
                        <div id="table-head" class="flex justify-between bg-[var(--h-bg-color)] rounded-lg font-medium py-2 hidden mt-4 pr-1">
                            <div class="text-center w-1/8 cursor-pointer" onclick="sortByThis(this)">Date</div>
                            <div class="text-center w-1/6 cursor-pointer" onclick="sortByThis(this)">Supplier Name</div>
                            <div class="text-center w-1/8 cursor-pointer" onclick="sortByThis(this)">Method</div>
                            <div class="text-center w-1/8 cursor-pointer" onclick="sortByThis(this)">Amount</div>
                            <div class="text-center w-1/6 cursor-pointer" onclick="sortByThis(this)">Customer/Self Acc.</div>
                            <div class="text-center w-1/8 cursor-pointer" onclick="sortByThis(this)">Source</div>
                            <div class="text-center w-1/8 cursor-pointer" onclick="sortByThis(this)">Reff No.</div>
                            <div class="text-center w-1/8 cursor-pointer" onclick="sortByThis(this)">Voucher / CR No.</div>
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
<script defer src="{{ asset('js/pages/supplier-payments-index.js') }}"></script>
@endpush
