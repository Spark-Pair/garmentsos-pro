@extends('app')
@section('title', 'Show Fabrics | ' . $client_company->name)
@section('content')
    @php
        $categories_options = [
            'self_account' => ['text' => 'Self Account'],
            'supplier' => ['text' => 'Supplier'],
            'customer' => ['text' => 'Customer'],
            'waiting' => ['text' => 'Waiting'],
        ];

        $searchFields = [
            'Supplier Name' => [
                'id' => 'supplier_name',
                'type' => 'text',
                'placeholder' => 'Enter supplier name',
                'oninput' => 'runDynamicFilter()',
                'dataFilterPath' => 'supplier_name',
            ],
            'Worker Name' => [
                'id' => 'employee_name',
                'type' => 'text',
                'placeholder' => 'Enter worker name',
                'oninput' => 'runDynamicFilter()',
                'dataFilterPath' => 'employee_name',
            ],
            'Fabric' => [
                'id' => 'fabric',
                'type' => 'select',
                'options' => $fabrics_options,
                'onchange' => 'runDynamicFilter()',
                'dataFilterPath' => 'fabric',
            ],
            'Tag' => [
                'id' => 'tag',
                'type' => 'text',
                'placeholder' => 'Enter tag',
                'oninput' => 'runDynamicFilter()',
                'dataFilterPath' => 'tag',
            ],
            'Date Range' => [
                'id' => 'date_range_start',
                'type' => 'date',
                'id2' => 'date_range_end',
                'type2' => 'date',
                'oninput' => 'runDynamicFilter()',
                'dataFilterPath' => 'date',
            ],
        ];
    @endphp
    <div>
        <div class="w-[80%] mx-auto">
            <x-search-header heading="Fabrics" :search_fields=$searchFields />
        </div>

        <!-- Main Content -->
        <section class="text-center mx-auto">
            <div
                class="show-box mx-auto w-[80%] h-[70vh] bg-[var(--secondary-bg-color)] border border-[var(--glass-border-color)]/20 rounded-xl shadow pt-8.5 relative">
                <x-form-title-bar printBtn layout="table" title="Show Fabrics" resetSortBtn />

                @if (count($finalData) > 0)
                    <div class="absolute bottom-3 right-3 flex items-center gap-2 w-fll z-50">
                        <x-section-navigation-button link="{{ route('fabrics.create') }}" title="Add New Fabric"
                            icon="fa-plus" />
                    </div>

                    <div class="details h-full z-40">
                        <div class="container-parent h-full">
                            <div class="card_container px-3 h-full flex flex-col">
                                <div id="table-head" class="flex items-center bg-[var(--h-bg-color)] rounded-lg font-medium py-2 hidden mt-4 mx-2">
                                    <div class="cursor-pointer text-center w-[10%]" onclick="sortByThis(this)">Date</div>
                                    <div class="cursor-pointer text-center w-[15%]" onclick="sortByThis(this)">Supplier / Worker</div>
                                    <div class="cursor-pointer text-center w-[10%]" onclick="sortByThis(this)">Type</div>
                                    <div class="cursor-pointer text-center w-[10%]" onclick="sortByThis(this)">Fabric</div>
                                    <div class="cursor-pointer text-center w-[10%]" onclick="sortByThis(this)">Color</div>
                                    <div class="cursor-pointer text-center w-[10%]" onclick="sortByThis(this)">Unit</div>
                                    <div class="cursor-pointer text-center w-[10%]" onclick="sortByThis(this)">Quantity</div>
                                    <div class="cursor-pointer text-center w-[20%]" onclick="sortByThis(this)">Tag</div>
                                    <div class="cursor-pointer text-center w-[10%]" onclick="sortByThis(this)">Remarks</div>
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
                    <div class="no-records-message w-full h-full flex flex-col items-center justify-center gap-2">
                        <h1 class="text-md text-[var(--secondary-text)] capitalize">No Fabrics yet</h1>
                        <a href="{{ route('fabrics.create') }}"
                            class="text-sm bg-[var(--primary-color)] text-[var(--text-color)] px-4 py-2 rounded-md hover:bg-[var(--h-primary-color)] hover:scale-105 hover:mb-2 transition-all duration-300 ease-in-out font-semibold">Add
                            New</a>
                    </div>
                @endif
            </div>
        </section>
    </div>

    <script>
        let currentUserRole = '{{ Auth::user()->role }}';
        let authLayout = 'table';

        function createRow(data) {
            return `
            <div id="${data.id}" oncontextmenu='${data.oncontextmenu || ""}' onclick='${data.onclick || ""}'
                class="item row relative group flex border-b border-[var(--h-bg-color)] items-center py-2 cursor-pointer hover:bg-[var(--h-secondary-bg-color)] transition-all fade-in ease-in-out"
                data-json='${JSON.stringify(data)}'>

                <span class="text-center w-[10%]">${formatDate(data.date)}</span>
                <span class="text-center w-[15%] capitalize">${data.supplier_name ?? data.employee_name}</span>
                <span class="text-center w-[10%]">${data.type ?? "-"}</span>
                <span class="text-center w-[10%] capitalize">${data.fabric ?? "-"}</span>
                <span class="text-center w-[10%] capitalize">${data.color ?? "-"}</span>
                <span class="text-center w-[10%] capitalize">${data.unit ?? "-"}</span>
                <span class="text-center w-[10%]">${data.quantity ?? "-"}</span>
                <span class="text-center w-[20%]">${data.tag ?? "-"}</span>
                <span class="text-center w-[10%] capitalize">${data.remarks ?? "-"}</span>
            </div>`;
        }

        const fetchedData = @json($finalData);
        let allDataArray = fetchedData.map(item => {
            return {
                id: item.id,
                supplier_name: item.supplier_name,
                employee_name: item.employee_name,
                fabric: item.fabric,
                remarks: item.remarks,
                color: item.color,
                unit: item.unit,
                quantity: item.quantity,
                tag: item.tag,
                type: item.type,
                date: item.date,
                visible: true,
            };
        });

    </script>
@endsection
