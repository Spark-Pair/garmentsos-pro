@extends('app')
@section('title', 'Show Employee Payments | ' . $client_company->name)
@section('content')
@php
    $searchFields = [
        "Employee Name" => [
            "id" => "employee_name",
            "type" => "text",
            "placeholder" => "Enter employee name",
            "oninput" => "runDynamicFilter()",
            "dataFilterPath" => "name",
        ],
        "Method" => [
            "id" => "method",
            "type" => "text",
            "placeholder" => "Enter method",
            "oninput" => "runDynamicFilter()",
            "dataFilterPath" => "details.Method",
        ],
        "Category" => [
            "id" => "category",
            "type" => "select",
            "options" => [
                        'staff' => ['text' => 'Staff'],
                        'worker' => ['text' => 'Worker'],
                    ],
            "onchange" => "runDynamicFilter()",
            "dataFilterPath" => "details.Category",
        ],
        "Type" => [
            "id" => "type",
            "type" => "text",
            "placeholder" => "Enter type",
            "oninput" => "runDynamicFilter()",
            "dataFilterPath" => "type",
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
        <x-search-header heading="Employee Payments" :search_fields=$searchFields/>
    </div>

    <!-- Main Content -->
    <section class="text-center mx-auto ">
        <div
            class="show-box mx-auto w-[80%] h-[70vh] bg-[var(--secondary-bg-color)] border border-[var(--glass-border-color)]/20 rounded-xl shadow pt-8.5 relative">
            <x-form-title-bar printBtn title="Show Employee Payments" changeLayoutBtn layout="{{ $authLayout }}" />

            @if (count($payments) > 0)
                <div class="absolute bottom-0 right-0 flex items-center justify-between gap-2 w-fll z-50 p-3 w-full pointer-events-none">
                    <x-section-navigation-button direction="right" id="info" icon="fa-info" />
                    <x-section-navigation-button link="{{ route('employee-payments.create') }}" title="Add New Payment" icon="fa-plus" />
                </div>

                <div class="details h-full z-40">
                    <div class="container-parent h-full">
                        <div class="card_container px-3 pb-3 h-full flex flex-col">
                            <div id="table-head" class="grid grid-cols-5 bg-[var(--h-bg-color)] rounded-lg font-medium py-2 hidden mt-4">
                                <div class="text-center">Date</div>
                                <div class="text-center">Category</div>
                                <div class="text-center">Employee</div>
                                <div class="text-center">Method</div>
                                <div class="text-center">Amount</div>
                            </div>
                            <p id="noItemsError" style="display: none" class="text-sm text-[var(--border-error)] mt-3">No items found</p>
                            <div class="overflow-y-auto grow my-scrollbar-2">
                                <div class="search_container grid grid-cols-1 sm:grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5 grow ">
                                    {{-- class="search_container overflow-y-auto grow my-scrollbar-2"> --}}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @else
                <div class="no-records-message w-full h-full flex flex-col items-center justify-center gap-2">
                    <h1 class="text-sm text-[var(--secondary-text)] capitalize">No Payment Found</h1>
                    <a href="{{ route('employee-payments.create') }}"
                        class="text-sm bg-[var(--primary-color)] text-[var(--text-color)] px-4 py-2 rounded-md hover:bg-[var(--h-primary-color)] hover:scale-105 hover:mb-2 transition-all duration-300 ease-in-out font-semibold">Add
                        New</a>
                </div>
            @endif
        </div>
    </section>

    <script>
        let authLayout = '{{ $authLayout }}';

        function createRow(data) {
            return `
                <div id="${data.id}" oncontextmenu='${data.oncontextmenu || ""}' onclick='${data.onclick || ""}'
                    class="item row relative group grid grid-cols-5 border-b border-[var(--h-bg-color)] items-center py-2 cursor-pointer hover:bg-[var(--h-secondary-bg-color)] transition-all fade-in ease-in-out"
                    data-json='${JSON.stringify(data)}'>

                    <span class="text-center">${data.details['Date']}</span>
                    <span class="text-center">${data.details['Category']}</span>
                    <span class="text-center">${data.name}</span>
                    <span class="text-center capitalize">${data.details["Method"]}</span>
                    <span class="text-center">${data.details['Amount']}</span>
                </div>
            `;
        }

        const fetchedData = @json($payments);
        let allDataArray = fetchedData.map(item => {
            return {
                id: item.id,
                name: item.employee.employee_name + ' | ' + item.employee.type.title.split('|')[0].trim(),
                details: {
                    'Category': item.employee.category,
                    'Method': item.method,
                    'Date': formatDate(item.date),
                    'Amount': formatNumbersWithDigits(item.amount, 1, 1),
                },
                date: item.date,
                type: item.employee.type.title,
                oncontextmenu: "generateContextMenu(event)",
                onclick: "generateModal(this)",
                visible: true,
            };
        });

        function generateContextMenu(e) {
            e.preventDefault();
            let item = e.target.closest('.item');
            let data = JSON.parse(item.dataset.json);

            let contextMenuData = {
                item: item,
                data: data,
                x: e.pageX,
                y: e.pageY,
                actions: [
                    // {id: 'edit-payment', text: 'Edit Payment', dataId: data.id}
                ],
            };

            createContextMenu(contextMenuData);
        }

        function generateModal(item) {
            let data = JSON.parse(item.dataset.json);

            let modalData = {
                id: 'modalForm',
                class: 'h-auto',
                name: data.name,
                details: {
                    'Date': data.details['Date'],
                    'Category': data.details['Category'],
                    'Method': data.details['Method'],
                    'Amount': data.details['Amount'],
                },
                bottomActions: [
                    // {id: 'edit-payment', text: 'Edit Payment', dataId: data.id}
                ],
            }

            createModal(modalData);
        }

        let infoDom = document.getElementById('info').querySelector('span');

        function onFilter() {
            infoDom.textContent = `Showing ${visibleData.length} of ${allDataArray.length} payments.`;
        }
    </script>
@endsection
