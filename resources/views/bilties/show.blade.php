@extends('app')
@section('title', 'Show Bilties | ' . $client_company->name)
@section('content')
    @php
        $searchFields = [
            "Customer Name" => [
                "id" => "customer_name",
                "type" => "text",
                "placeholder" => "Enter customer name",
                "oninput" => "runDynamicFilter()",
                "dataFilterPath" => "customer_name",
            ],
            "Invoice No" => [
                "id" => "invoice_no",
                "type" => "text",
                "placeholder" => "Enter invoice no",
                "oninput" => "runDynamicFilter()",
                "dataFilterPath" => "invoice_no",
            ],
            "Cargo Name" => [
                "id" => "cargo_name",
                "type" => "text",
                "placeholder" => "Enter cargo name",
                "oninput" => "runDynamicFilter()",
                "dataFilterPath" => "cargo_name",
            ],
            "Bilty No" => [
                "id" => "bilty_no",
                "type" => "text",
                "placeholder" => "Enter bilty no",
                "oninput" => "runDynamicFilter()",
                "dataFilterPath" => "bilty_no",
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

    {{-- header --}}
    <div class="w-[80%] mx-auto">
        <x-search-header heading="Bilties" :search_fields=$searchFields/>
    </div>

    <!-- Main Content -->
    <section class="text-center mx-auto ">
        <div
            class="show-box mx-auto w-[80%] h-[70vh] bg-[var(--secondary-bg-color)] border border-[var(--glass-border-color)]/20 rounded-xl shadow pt-8.5 relative">
            <x-form-title-bar printBtn layout="table" title="Show Bilties" resetSortBtn />

            <div class="absolute bottom-0 right-0 flex items-center justify-between gap-2 w-fll z-50 p-3 w-full pointer-events-none">
                <x-section-navigation-button direction="right" id="info" icon="fa-info" />
                <x-section-navigation-button link="{{ route('bilties.create') }}" title="Add New Bilty" icon="fa-plus" />
            </div>

            <div class="details h-full z-40">
                <div class="container-parent h-full">
                    <div class="card_container px-3 h-full flex flex-col">
                        <div id="table-head" class="grid grid-cols-6 bg-[var(--h-bg-color)] rounded-lg font-medium py-2 hidden mt-4">
                            <div class="cursor-pointer" onclick="sortByThis(this)">Date</div>
                            <div class="cursor-pointer col-span-2" onclick="sortByThis(this)">Customer Name</div>
                            <div class="cursor-pointer" onclick="sortByThis(this)">Invoice No.</div>
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

    <script>
        let authLayout = 'table';

        function createRow(data) {
            return `
            <div id="${data.id}" oncontextmenu='${data.oncontextmenu || ""}' onclick='${data.onclick || ""}'
                class="item row relative group grid grid-cols-6 text-center border-b border-[var(--h-bg-color)] items-center py-2 cursor-pointer hover:bg-[var(--h-secondary-bg-color)] transition-all fade-in ease-in-out"
                data-json='${JSON.stringify(data)}'>

                <span>${data.date}</span>
                <span class="col-span-2">${data.customer_name}</span>
                <span>${data.invoice_no}</span>
                <span>${data.cargo_name}</span>
                <span>${data.bilty_no}</span>
            </div>`;
        }

        // const fetchedData = [];
        // let allDataArray = fetchedData.map(item => {
        //     return {
        //         id: item.id,
        //         date: item.date,
        //         customer_name: item.invoice.customer.customer_name + ' | ' + item.invoice.customer.city.title,
        //         invoice_no: item.invoice.invoice_no,
        //         cargo_name: item.invoice.cargo_name,
        //         bilty_no: item.bilty_no + ' | ' + item.invoice.cotton_count,
        //         visible: true,
        //     };
        // });
    </script>
@endsection
