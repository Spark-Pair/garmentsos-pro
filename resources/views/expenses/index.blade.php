@extends('app')
@section('title', 'Show Expenses | ' . $client_company->name)
@section('content')
    @php
        $searchFields = [
            "Id" => [
                "id" => "id",
                "type" => "text",
                "placeholder" => "Enter id",
                                "dataFilterPath" => "id",
            ],
            "Supplier Name" => [
                "id" => "supplier_name",
                "type" => "text",
                "placeholder" => "Enter supplier name",
                                "dataFilterPath" => "supplier_name",
            ],
            "Reff. No" => [
                "id" => "reff_no",
                "type" => "text",
                "placeholder" => "Enter reff. no",
                                "dataFilterPath" => "reff_no",
            ],
            "Expense" => [
                "id" => "expense",
                "type" => "select",
                "options" => $expenseOptions,
                                "dataFilterPath" => "expense",
            ],
            "Amount" => [
                "id" => "amount",
                "type" => "text",
                "placeholder" => "Enter amount",
                                "dataFilterPath" => "amount",
            ],
            "Remarks" => [
                "id" => "remarks",
                "type" => "text",
                "placeholder" => "Enter remarks",
                                "dataFilterPath" => "remarks",
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
    <div class="w-[90%] mx-auto">
        <x-search-header heading="Expenses" :search_fields=$searchFields/>
    </div>

    <!-- Main Content -->
    <section class="text-center mx-auto ">
        <div
            class="show-box mx-auto w-[90%] h-[70vh] bg-[var(--secondary-bg-color)] border border-[var(--glass-border-color)]/20 rounded-xl shadow pt-8.5 relative">
            <x-form-title-bar printBtn  layout="table" title="Show Expenses" resetSortBtn />

            <div class="absolute bottom-14 right-0 flex items-center justify-end gap-2 w-fll z-50 p-3 w-full pointer-events-none">
                <x-section-navigation-button link="{{ route('expenses.create') }}" title="Add New Expense" icon="fa-plus" />
            </div>

            <div class="details h-full z-40">
                <div class="container-parent h-full">
                    <div class="card_container px-3 pb-3 h-full flex flex-col">
                        <div id="table-head" class="grid grid-cols-9 bg-[var(--h-bg-color)] rounded-lg font-medium py-2 hidden mt-4">
                            <div class="cursor-pointer" onclick="sortByThis(this)">Id</div>
                            <div class="cursor-pointer" onclick="sortByThis(this)">Date</div>
                            <div class="cursor-pointer col-span-2" onclick="sortByThis(this)">Supplier Name</div>
                            <div class="cursor-pointer" onclick="sortByThis(this)">Reff. No.</div>
                            <div class="cursor-pointer" onclick="sortByThis(this)">Expense</div>
                            <div class="cursor-pointer" onclick="sortByThis(this)">Lot No.</div>
                            <div class="cursor-pointer" onclick="sortByThis(this)">Amount</div>
                            <div class="cursor-pointer" onclick="sortByThis(this)">Remarks</div>
                        </div>
                        <p id="noItemsError" style="display: none" class="text-sm text-[var(--border-error)] mt-3">No items found</p>
                        <div class="overflow-y-auto grow my-scrollbar-2">
                            <div class="search_container grid grid-cols-1 sm:grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5 grow">
                            </div>
                        </div>
                        <div id="calc-bottom" class="flex w-full gap-4 text-sm bg-[var(--secondary-bg-color)] pt-2 rounded-lg">
                            <div
                                class="total-Amount flex justify-between items-center border border-gray-600 rounded-lg py-2 px-4 w-full cursor-not-allowed">
                                <div>Total Expense - Rs.</div>
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
<script defer src="{{ asset('js/pages/expenses-index.js') }}"></script>
@endpush
