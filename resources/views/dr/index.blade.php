@extends('app')
@section('title', 'Show DRs | ' . $client_company->name)
@section('content')
    @php
        $searchFields = [
            "DR No." => [
                "id" => "d_r_no",
                "type" => "text",
                "placeholder" => "Enter dr no.",
                                "dataFilterPath" => "d_r_no",
            ],
            "Customer Name" => [
                "id" => "customer_name",
                "type" => "text",
                "placeholder" => "Enter customer name",
                                "dataFilterPath" => "customer_name",
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
        <x-search-header heading="DRs" :search_fields=$searchFields />
    </div>

    <!-- Main Content -->
    <section class="text-center mx-auto ">
        <div
            class="show-box mx-auto w-[90%] h-[70vh] bg-[var(--secondary-bg-color)] border border-[var(--glass-border-color)]/20 rounded-xl shadow pt-8.5 relative">
            <x-form-title-bar printBtn layout="table" title="Show DRs" resetSortBtn />

            <div class="absolute bottom-3 right-3 flex items-center gap-2 w-fll z-50">
                <x-section-navigation-button link="{{ route('dr.create') }}" title="Add New Record"
                    icon="fa-plus" />
            </div>

            <div class="details h-full z-40">
                <div class="container-parent h-full">
                    <div class="card_container px-3 h-full flex flex-col">
                        <div id="table-head" class="grid grid-cols-3 items-center bg-[var(--h-bg-color)] rounded-lg font-medium py-2 hidden mt-4">
                            <div class="cursor-pointer" onclick="sortByThis(this)">Customer</div>
                            <div class="cursor-pointer" onclick="sortByThis(this)">Date</div>
                            <div class="cursor-pointer" onclick="sortByThis(this)">DR No.</div>
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
<script defer src="{{ asset('js/pages/dr-index.js') }}"></script>
<script>
        window.__drIndex = {
            authLayout: @json($authLayout),
        };
    </script>
@endpush
