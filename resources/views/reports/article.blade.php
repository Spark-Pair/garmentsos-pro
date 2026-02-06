@extends('app')
@section('title', 'Show Article Report | ' . $client_company->name)
@section('content')
    @php
        $searchFields = [
            "Article No" => [
                "id" => "article_no",
                "type" => "text",
                "placeholder" => "Enter article no",
                "oninput" => "runDynamicFilter()",
                "dataFilterPath" => "article_no",
            ],
        ];
    @endphp

    <div class="w-[80%] mx-auto">
        <x-search-header heading="Article Report" :search_fields=$searchFields />
    </div>

    <!-- Main Content -->
    <section class="text-center mx-auto ">
        <div
            class="show-box mx-auto w-[80%] h-[70vh] bg-[var(--secondary-bg-color)] border border-[var(--glass-border-color)]/20 rounded-xl shadow pt-8.5 relative">
            <x-form-title-bar printBtn layout="table" title="Show Article Report" resetSortBtn />

            <div class="details h-full z-40">
                <div class="container-parent h-full">
                    <div class="card_container px-3 h-full flex flex-col">
                        <div id="table-head" class="flex items-center bg-[var(--h-bg-color)] rounded-lg font-medium py-2 hidden mt-4">
                            <div class="w-1/6 cursor-pointer" onclick="sortByThis(this)">Invoice Date</div>
                            <div class="w-1/6 cursor-pointer" onclick="sortByThis(this)">Article No.</div>
                            <div class="w-1/6 cursor-pointer" onclick="sortByThis(this)">Invoice No.</div>
                            <div class="grow cursor-pointer" onclick="sortByThis(this)">Customer</div>
                            <div class="w-1/6 cursor-pointer" onclick="sortByThis(this)">Quantity</div>
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
            <div id="${data.id}"
                class="item row relative group flex items-center border-b border-[var(--h-bg-color)] items-center py-2 cursor-pointer hover:bg-[var(--h-secondary-bg-color)] transition-all fade-in ease-in-out"
                data-json='${JSON.stringify(data)}'>

                <span class="w-1/6">${data.invoice_date}</span>
                <span class="w-1/6">${data.article_no}</span>
                <span class="w-1/6">${data.invoice_no}</span>
                <span class="grow">${data.customer_name}</span>
                <span class="w-1/6">${data.invoice_pcs}</span>
            </div>`;
        }
    </script>
@endsection
