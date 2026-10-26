@extends('app')
@section('title', 'Show Vouchers | ' . $client_company->name)
@section('content')
    @php
        $searchFields = [
            "Supplier Name" => [
                "id" => "supplier_name",
                "type" => "text",
                "placeholder" => "Enter supplier name",
                                "dataFilterPath" => "details.Supplier",
            ],
            "Voucher No" => [
                "id" => "voucher_no",
                "type" => "text",
                "placeholder" => "Enter voucher number",
                                "dataFilterPath" => "name",
            ],
            "Date Range" => [
                "id" => "date_range_start",
                "type" => "date",
                "id2" => "date_range_end",
                "type2" => "date",
                                "dataFilterPath" => "data.date",
            ]
        ];
    @endphp

    <div class="w-[90%] mx-auto">
        <x-search-header heading="Vouchers" :search_fields=$searchFields/>
    </div>

    <!-- Main Content -->
    <section class="text-center mx-auto ">
        <div
            class="show-box mx-auto w-[90%] h-[70vh] bg-[var(--secondary-bg-color)] border border-[var(--glass-border-color)]/20 rounded-xl shadow pt-8.5 relative">
            <x-form-title-bar printBtn title="Show Vouchers" changeLayoutBtn layout="{{ $authLayout }}" resetSortBtn />

            <div class="absolute bottom-0 right-0 flex items-center justify-between gap-2 w-fll z-50 p-3 w-full pointer-events-none">
                <x-section-navigation-button link="{{ route('vouchers.create') }}" title="Add New Voucher" icon="fa-plus" />
            </div>

            <div class="details h-full z-40">
                <div class="container-parent h-full">
                    <div class="card_container px-3 h-full flex flex-col">
                        <div id="table-head" class="grid grid-cols-4 bg-[var(--h-bg-color)] rounded-lg font-medium py-2 hidden mt-4">
                            <div class="text-center cursor-pointer" onclick="sortByThis(this)">Supplier</div>
                            <div class="text-center cursor-pointer" onclick="sortByThis(this)">Voucher No</div>
                            <div class="text-center cursor-pointer" onclick="sortByThis(this)">Date</div>
                            <div class="text-center cursor-pointer" onclick="sortByThis(this)">Amount</div>
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

        <div class="context-menu absolute top-0 left-0 text-sm z-50" style="display: none;">
            <div
                class="border border-gray-600 w-48 bg-[var(--secondary-bg-color)] text-[var(--text-color)] shadow-md rounded-xl transform transition-all duration-300 ease-in-out z-50">
                <ul class="p-2">
                    <li>
                        <button id="show-details" type="button"
                            class="w-full px-4 py-2 text-left hover:bg-[var(--h-bg-color)] rounded-md transition-all duration-300 ease-in-out cursor-pointer">Show
                            Details</button>
                    </li>
                    <li>
                        <button id="print" type="button"
                            class="w-full px-4 py-2 text-left hover:bg-[var(--h-bg-color)] rounded-md transition-all duration-300 ease-in-out cursor-pointer">Print
                            Voucher</button>
                    </li>
                </ul>
            </div>
        </div>
    </section>

@endsection


@push('page-scripts')
<script defer src="{{ asset('js/pages/vouchers-index.js') }}"></script>
    <script>
        window.__vouchersIndex = {
            companyData: @json($client_company),
            companyLogoBase: @json(asset('/')),
            authLayout: @json($authLayout),
        };
    </script>
@endpush
