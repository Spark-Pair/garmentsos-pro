@extends('app')
@section('title', 'Show Physical Quantities | ' . $client_company->name)
@section('content')
    @php
        $searchFields = [
            "Article No" => [
                "id" => "article_no",
                "type" => "text",
                "placeholder" => "Enter article no",
                "oninput" => "runDynamicFilter()",
                "dataFilterPath" => "name",
            ],
            "Processed By" => [
                "id" => "processed_by",
                "type" => "text",
                "placeholder" => "Enter processed by",
                "oninput" => "runDynamicFilter()",
                "dataFilterPath" => "processed_by",
            ],
            'Shipment' => [
                'id' => 'shipment',
                'type' => 'select',
                'options' => [
                    'all' => ['text' => 'All'],
                    'karachi' => ['text' => 'Karachi'],
                    'lahore' => ['text' => 'Lahore'],
                ],
                'dataFilterPath' => 'shipment',
            ],
            "Date Range" => [
                "id" => "date_range_start",
                "type" => "date",
                "id2" => "date_range_end",
                "type2" => "date",
                "oninput" => "runDynamicFilter()",
                "dataFilterPath" => "date",
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

            @if (count($physicalQuantities) > 0)
                <div class="absolute bottom-3 right-3 flex items-center gap-2 w-fll z-50">
                    <x-section-navigation-button link="{{ route('physical-quantities.create') }}" title="Add Phy. Qty."
                        icon="fa-plus" />
                </div>

                <div class="details h-full z-40">
                    <div class="container-parent h-full">
                        <div class="card_container px-3 h-full flex flex-col">
                            <div id="table-head" class="flex items-center bg-[var(--h-bg-color)] rounded-lg font-medium py-2 hidden mt-4 mx-2">
                                <div class="w-[10%] cursor-pointer" onclick="sortByThis(this)">Article No.</div>
                                <div class="w-[7%] cursor-pointer" onclick="sortByThis(this)">Proc. By</div>
                                <div class="w-[8%] cursor-pointer" onclick="sortByThis(this)">Unit</div>
                                <div class="w-[18%] cursor-pointer" onclick="sortByThis(this)">Total Qty.</div>
                                <div class="w-[12%] cursor-pointer" onclick="sortByThis(this)">Received Qty.</div>
                                <div class="w-[12%] cursor-pointer" onclick="sortByThis(this)">Current Stock Qty.</div>
                                <div class="w-[12%] cursor-pointer" onclick="sortByThis(this)">A</div>
                                <div class="w-[12%] cursor-pointer" onclick="sortByThis(this)">B</div>
                                <div class="w-[12%] cursor-pointer" onclick="sortByThis(this)">C</div>
                                <div class="w-[12%] cursor-pointer" onclick="sortByThis(this)">Remaining Qty.</div>
                                <div class="w-[10%] cursor-pointer" onclick="sortByThis(this)">Shipment</div>
                            </div>
                            <p id="noItemsError" style="display: none" class="text-sm text-[var(--border-error)] mt-3">No items found</p>
                            <div class="overflow-y-auto grow my-scrollbar-2">
                                <div class="search_container grid grid-cols-1 sm:grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5 grow">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @else
                <div class="no-record-message w-full h-full flex flex-col items-center justify-center gap-2">
                    <h1 class="text-sm text-[var(--secondary-text)] capitalize">No Record Found</h1>
                    <a href="{{ route('physical-quantities.create') }}"
                        class="text-sm bg-[var(--primary-color)] text-[var(--text-color)] px-4 py-2 rounded-md hover:bg-[var(--h-primary-color)] hover:scale-105 hover:mb-2 transition-all duration-300 ease-in-out font-semibold">Add
                        New</a>
                </div>
            @endif
        </div>
    </section>

    <script>
        let authLayout = 'table';

        function createRow(data) {
            return `
            <div id="${data.id}" oncontextmenu='${data.oncontextmenu || ""}' onclick='${data.onclick || ""}'
                class="item row relative group flex items-center border-b border-[var(--h-bg-color)] items-center py-2 cursor-pointer hover:bg-[var(--h-secondary-bg-color)] transition-all fade-in ease-in-out"
                data-json='${JSON.stringify(data)}'>

                <span class="w-[10%]">${data.name}</span>
                <span class="capitalize w-[7%]">${data.processed_by}</span>
                <span class="w-[8%]">${data.unit}</span>
                <span class="w-[18%]">${data.total_quantity}</span>
                <span class="w-[12%]">${data.received_quantity}</span>
                <span class="w-[12%]">${data.current_stock}</span>
                <span class="w-[12%]">${data.a_category}</span>
                <span class="w-[12%]">${data.b_category}</span>
                <span class="w-[12%]">${data.c_category}</span>
                <span class="w-[12%]">${data.remaining_quantity}</span>
                <span class="w-[10%]">${data.shipment}</span>
            </div>`;
        }


        const fetchedData = @json($physicalQuantities);
        let allDataArray = fetchedData.map(item => {
            return {
                id: item.id,
                name: item.article.article_no,
                processed_by: item.article.processed_by,
                phone_number: item.phone_number,
                unit: item.article.pcs_per_packet,
                total_quantity: `${formatNumbersWithDigits(item.article.quantity / 12, 1, 1)} - Dz. | ${item.article.quantity / item.article.pcs_per_packet}  - Pkts.`,
                received_quantity: `${item.total_packets} - Pkts.`,
                current_stock: `${item.current_stock} - Pkts.`,
                a_category: `${item.a_category} - Pkts.`,
                b_category: `${item.b_category} - Pkts.`,
                c_category: `${item.c_category} - Pkts.`,
                remaining_quantity: `${formatNumbersWithDigits((item.article.quantity / item.article.pcs_per_packet) - item.total_packets, 1, 1)} - Pkts.`,
                shipment: item.shipment || '-',
                visible: true,
            };
        });
    </script>
@endsection
