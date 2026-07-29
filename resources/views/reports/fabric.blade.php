@extends('app')
@section('title', 'Show Fabric Report | ' . $client_company->name)
@section('content')
    @php
        $searchFields = [
            "Fabric" => [
                "id" => "fabric",
                "type" => "text",
                "placeholder" => "Enter fabric name",
                "dataFilterPath" => "fabric",
            ],
            "Tag" => [
                "id" => "tag",
                "type" => "text",
                "placeholder" => "Enter fabric tag",
                "dataFilterPath" => "tag",
            ],
            "Supplier" => [
                "id" => "supplier_name",
                "type" => "text",
                "placeholder" => "Enter supplier name",
                "dataFilterPath" => "supplier_name",
            ],
            "Worker" => [
                "id" => "worker_name",
                "type" => "text",
                "placeholder" => "Enter worker name",
                "dataFilterPath" => "worker_name",
            ],
            "Article No" => [
                "id" => "article_no",
                "type" => "text",
                "placeholder" => "Enter article no",
                "dataFilterPath" => "article_no",
            ],
            "Type" => [
                "id" => "source",
                "type" => "text",
                "placeholder" => "Received, issued, returned, production",
                "dataFilterPath" => "source",
            ],
            "Date Range" => [
                "id" => "date_range_start",
                "type" => "date",
                "id2" => "date_range_end",
                "type2" => "date",
                "dataFilterPath" => "date_raw",
            ],
        ];
    @endphp

    <div class="w-[80%] mx-auto">
        <x-search-header heading="Fabric Report" :search_fields=$searchFields />
    </div>

    <section class="text-center mx-auto">
        <div
            class="show-box mx-auto w-[80%] h-[70vh] bg-[var(--secondary-bg-color)] border border-[var(--glass-border-color)]/20 rounded-xl shadow pt-8.5 relative">
            <x-form-title-bar printBtn layout="table" title="Show Fabric Report" resetSortBtn />

            <div class="details h-full z-40">
                <div class="container-parent h-full">
                    <div class="card_container px-3 pb-3 h-full flex flex-col">
                        <div id="table-head" class="flex items-center bg-[var(--h-bg-color)] rounded-lg font-medium py-2 hidden mt-4 text-xs">
                            <div class="w-[11%] cursor-pointer text-left px-3" onclick="sortByThis(this)">Date</div>
                            <div class="w-[15%] cursor-pointer text-left px-3" onclick="sortByThis(this)">Supplier / Worker</div>
                            <div class="w-[10%] cursor-pointer text-left px-3" onclick="sortByThis(this)">Type</div>
                            <div class="w-[12%] cursor-pointer text-left px-3" onclick="sortByThis(this)">Fabric</div>
                            <div class="w-[8%] cursor-pointer text-left px-3" onclick="sortByThis(this)">Color</div>
                            <div class="w-[7%] cursor-pointer text-left px-3" onclick="sortByThis(this)">Unit</div>
                            <div class="w-[11%] cursor-pointer text-right px-3" onclick="sortByThis(this)">Quantity</div>
                            <div class="grow cursor-pointer text-left px-3" onclick="sortByThis(this)">Tag / Remarks</div>
                        </div>
                        <p id="noItemsError" style="display: none" class="text-sm text-[var(--border-error)] mt-3">No items found</p>
                        <div class="overflow-y-auto grow my-scrollbar-2">
                            <div class="search_container grid grid-cols-1 sm:grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5 grow">
                            </div>
                        </div>
                        <div id="calc-bottom" class="flex w-full gap-4 text-sm bg-[var(--secondary-bg-color)] pt-2 rounded-lg">
                            <div class="total-received flex justify-between items-center border border-gray-600 rounded-lg py-2 px-4 w-full cursor-not-allowed">
                                <div>Total In</div>
                                <div class="text-right">0</div>
                            </div>
                            <div class="total-out flex justify-between items-center border border-gray-600 rounded-lg py-2 px-4 w-full cursor-not-allowed">
                                <div>Total Out</div>
                                <div class="text-right">0</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

@endsection

@push('page-scripts')
<script defer src="{{ asset('js/pages/reports-fabric.js') }}"></script>
<script>
    window.__reportsFabric = {
        authLayout: @json($authLayout),
    };
</script>
@endpush
