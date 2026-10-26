@extends('app')
@section('title', 'Show CRs | ' . $client_company->name)
@section('content')
    @php
        $searchFields = [
            "Supplier Name" => [
                "id" => "supplier_name",
                "type" => "text",
                "placeholder" => "Enter supplier name",
                                "dataFilterPath" => "supplier_name",
            ],
            "CR No." => [
                "id" => "c_r_no",
                "type" => "text",
                "placeholder" => "Enter cr no.",
                                "dataFilterPath" => "c_r_no",
            ],
            "Voucher No." => [
                "id" => "voucher_no",
                "type" => "text",
                "placeholder" => "Enter voucher no.",
                                "dataFilterPath" => "voucher_no",
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
        <x-search-header heading="CRs" :search_fields=$searchFields />
    </div>

    <!-- Main Content -->
    <section class="text-center mx-auto ">
        <div
            class="show-box mx-auto w-[90%] h-[70vh] bg-[var(--secondary-bg-color)] border border-[var(--glass-border-color)]/20 rounded-xl shadow pt-8.5 relative">
            <x-form-title-bar printBtn layout="table" title="Show CRs" resetSortBtn />

            <div class="absolute bottom-3 right-3 flex items-center gap-2 w-fll z-50">
                <x-section-navigation-button link="{{ route('cr.create') }}" title="Add New Record"
                    icon="fa-plus" />
            </div>

            <div class="details h-full z-40">
                <div class="container-parent h-full">
                    <div class="card_container px-3 h-full flex flex-col text-center">
                        <div id="table-head" class="grid grid-cols-5 items-center bg-[var(--h-bg-color)] rounded-lg font-medium py-2 hidden mt-4">
                            <div class="cursor-pointer" onclick="sortByThis(this)">Date</div>
                            <div class="cursor-pointer" onclick="sortByThis(this)">Supplier Name</div>
                            <div class="cursor-pointer" onclick="sortByThis(this)">CR No.</div>
                            <div class="cursor-pointer" onclick="sortByThis(this)">Amount</div>
                            <div class="cursor-pointer" onclick="sortByThis(this)">Voucher No.</div>
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
<script defer src="{{ asset('js/pages/cr-index.js') }}"></script>
<script>
        window.__crIndex = {
            authLayout: @json($authLayout),
        };
    </script>
@endpush
