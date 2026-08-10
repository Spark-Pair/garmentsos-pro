@extends('app')
@section('title', 'Show Physical Quantities | ' . $client_company->name)
@section('content')
    @php
        $searchFields = [
            "Article No" => [
                "id" => "article_no",
                "type" => "text",
                "placeholder" => "Enter article no or 0001-0010",
                "dataFilterPath" => "article_no",
                "listInput" => true,
            ],
            "Processed By" => [
                "id" => "processed_by",
                "type" => "text",
                "placeholder" => "Enter processed by",
                "dataFilterPath" => "processed_by",
            ],
            'Shipment' => [
                'id' => 'shipment',
                'type' => 'select',
                'options' => [
                    'all' => ['text' => 'All'],
                    'karachi' => ['text' => 'Karachi'],
                    'other' => ['text' => 'Other'],
                ],
                'dataFilterPath' => 'shipment',
            ]
        ];
    @endphp

    <div class="w-[80%] mx-auto">
        <x-search-header heading="Physical Quantity" :search_fields=$searchFields />
    </div>

    <!-- Main Content -->
    <section class="text-center mx-auto ">
        <div
            class="show-box mx-auto w-[80%] h-[70vh] bg-[var(--secondary-bg-color)] border border-[var(--glass-border-color)]/20 rounded-xl shadow pt-8.5 relative">
            <x-form-title-bar printBtn layout="table" title="Show Physical Quantities" resetSortBtn />

            <div class="absolute bottom-3 right-3 flex items-center gap-2 w-fll z-50">
                <x-section-navigation-button link="{{ route('physical-quantities.create') }}" title="Add Phy. Qty."
                    icon="fa-plus" />
            </div>

            <div class="details h-full z-40">
                <div class="container-parent h-full">
                    <div class="card_container px-3 h-full flex flex-col">
                        <div id="table-head" class="flex items-center bg-[var(--h-bg-color)] rounded-lg font-medium py-2 hidden mt-4 text-xs">
                            <div class="cursor-pointer w-[7%]" onclick="sortByThis(this)">Article</div>
                            <div class="cursor-pointer w-[7%]" onclick="sortByThis(this)">Proc. By</div>
                            <div class="cursor-pointer w-[4%]" onclick="sortByThis(this)">Unit</div>
                            <div class="cursor-pointer w-[8%]" onclick="sortByThis(this)">Orderable</div>
                            <div class="cursor-pointer w-[8%]" onclick="sortByThis(this)">Total</div>
                            <div class="cursor-pointer w-[8%]" onclick="sortByThis(this)">Received</div>
                            <div class="cursor-pointer w-[8%]" onclick="sortByThis(this)">Ordered</div>
                            <div class="cursor-pointer w-[8%]" onclick="sortByThis(this)">Invoiced</div>
                            <div class="cursor-pointer w-[8%]" onclick="sortByThis(this)">Return</div>
                            <div class="cursor-pointer w-[8%]" onclick="sortByThis(this)">Adjustment</div>
                            <div class="cursor-pointer w-[8%]" onclick="sortByThis(this)">Current Stock</div>
                            <div class="cursor-pointer w-[8%]" onclick="sortByThis(this)">A</div>
                            <div class="cursor-pointer w-[8%]" onclick="sortByThis(this)">B</div>
                            <div class="cursor-pointer w-[8%]" onclick="sortByThis(this)">C</div>
                            <div class="cursor-pointer w-[8%]" onclick="sortByThis(this)">Remaining</div>
                            <div class="cursor-pointer w-[8%]" onclick="sortByThis(this)">Shipment</div>
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
<script defer src="{{ asset('js/pages/physical-quantities-index.js') }}?v={{ config('app.version', 'local') }}.partial-modal-1"></script>
<script>
        window.__physicalQuantitiesIndex = {
            authLayout: @json($authLayout),
        };
    </script>
@endpush
