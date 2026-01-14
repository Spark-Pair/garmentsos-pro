@extends('app')
@section('title', 'Show CRs | ' . $client_company->name)
@section('content')
    @php
        $searchFields = [
            "Supplier Name" => [
                "id" => "supplier_name",
                "type" => "text",
                "placeholder" => "Enter supplier name",
                "oninput" => "runDynamicFilter()",
                "dataFilterPath" => "supplier_name",
            ],
            "CR No." => [
                "id" => "c_r_no",
                "type" => "text",
                "placeholder" => "Enter cr no.",
                "oninput" => "runDynamicFilter()",
                "dataFilterPath" => "c_r_no",
            ],
            "Voucher No." => [
                "id" => "voucher_no",
                "type" => "text",
                "placeholder" => "Enter voucher no.",
                "oninput" => "runDynamicFilter()",
                "dataFilterPath" => "voucher_no",
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
        <x-search-header heading="CRs" :search_fields=$searchFields />
    </div>

    <!-- Main Content -->
    <section class="text-center mx-auto ">
        <div
            class="show-box mx-auto w-[80%] h-[70vh] bg-[var(--secondary-bg-color)] border border-[var(--glass-border-color)]/20 rounded-xl shadow pt-8.5 relative">
            <x-form-title-bar printBtn layout="table" title="Show CRs" resetSortBtn />

            @if (count($crs) > 0)
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
            @else
                <div class="no-record-message w-full h-full flex flex-col items-center justify-center gap-2">
                    <h1 class="text-sm text-[var(--secondary-text)] capitalize">No Record Found</h1>
                    <a href="{{ route('cr.create') }}"
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
                class="item row relative group grid grid-cols-5 border-b border-[var(--h-bg-color)] items-center py-2 cursor-pointer hover:bg-[var(--h-secondary-bg-color)] transition-all fade-in ease-in-out"
                data-json='${JSON.stringify(data)}'>

                <span>${formatDate(data.date)}</span>
                <span>${data.supplier_name}</span>
                <span>${data.c_r_no}</span>
                <span>${formatNumbersWithDigits(data.amount, 1, 1)}</span>
                <span>${data.voucher_no}</span>
            </div>`;
        }


        const fetchedData = @json($crs);

        let allDataArray = fetchedData.map(item => {
            return {
                id: item.id,
                date: item.date,
                amount: item.new_payments.reduce((total, p) => total + parseInt(p.amount), 0),
                c_r_no: item.c_r_no,
                voucher_no: item.voucher?.voucher_no,
                supplier_name: item.voucher?.supplier?.supplier_name ?? "{{ $client_company->name }}",
                visible: true,
            };
        });
    </script>
@endsection
