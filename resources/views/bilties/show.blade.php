@extends('app')
@section('title', 'Show Bilties | ' . $client_company->name)
@section('content')
    @php
        $searchFields = [
            "Customer Name" => [
                "id" => "customer_name",
                "type" => "text",
                "placeholder" => "Enter customer name",
                                "dataFilterPath" => "customer_name",
            ],
            "Invoice No" => [
                "id" => "invoice_no",
                "type" => "text",
                "placeholder" => "Enter invoice no",
                                "dataFilterPath" => "invoice_no",
            ],
            "Cargo Name" => [
                "id" => "cargo_name",
                "type" => "text",
                "placeholder" => "Enter cargo name",
                                "dataFilterPath" => "cargo_name",
            ],
            "Bilty No" => [
                "id" => "bilty_no",
                "type" => "text",
                "placeholder" => "Enter bilty no",
                                "dataFilterPath" => "bilty_no",
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

    {{-- header --}}
    <div class="w-[90%] mx-auto">
        <x-search-header heading="Bilties" :search_fields=$searchFields/>
    </div>

    <!-- Main Content -->
    <section class="text-center mx-auto ">
        <div
            class="show-box mx-auto w-[90%] h-[70vh] bg-[var(--secondary-bg-color)] border border-[var(--glass-border-color)]/20 rounded-xl shadow pt-8.5 relative">
            <x-form-title-bar printBtn layout="table" title="Show Bilties" resetSortBtn />

            <div class="absolute bottom-0 right-0 flex items-center justify-between gap-2 w-fll z-50 p-3 w-full pointer-events-none">
                <x-section-navigation-button direction="right" id="info" icon="fa-info" />
                <x-section-navigation-button link="{{ route('bilties.create') }}" title="Add New Bilty" icon="fa-plus" />
            </div>

            <div class="details h-full z-40">
                <div class="container-parent h-full">
                    <div class="card_container px-3 h-full flex flex-col">
                        <div id="table-head" class="grid grid-cols-7 bg-[var(--h-bg-color)] rounded-lg font-medium py-2 hidden mt-4">
                            <div class="cursor-pointer" onclick="sortByThis(this)">Date</div>
                            <div class="cursor-pointer col-span-2" onclick="sortByThis(this)">Customer Name</div>
                            <div class="cursor-pointer" onclick="sortByThis(this)">Invoice No.</div>
                            <div class="cursor-pointer" onclick="sortByThis(this)">Amount</div>
                            <div class="cursor-pointer" onclick="sortByThis(this)">Cargo Name</div>
                            <div class="cursor-pointer" onclick="sortByThis(this)">Bilty No.</div>
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
<script defer src="{{ asset('js/pages/bilties-show.js') }}"></script>
<script>
        window.__biltiesShow = {
            authLayout: @json($authLayout),
        };
    </script>
@endpush
