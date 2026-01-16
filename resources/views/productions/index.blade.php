@extends('app')
@section('title', 'Show Productions | ' . $client_company->name)
@section('content')
    @php
        $searchFields = [
            "Article No." => [
                "id" => "article_no",
                "type" => "text",
                "placeholder" => "Enter article no.",
                "oninput" => "runDynamicFilter()",
                "dataFilterPath" => "article_no",
            ],
            "Worker Name" => [
                "id" => "worker_name",
                "type" => "text",
                "placeholder" => "Enter worker name",
                "oninput" => "runDynamicFilter()",
                "dataFilterPath" => "worker_name",
            ],
            "Ticket" => [
                "id" => "ticket",
                "type" => "text",
                "placeholder" => "Enter ticket",
                "oninput" => "runDynamicFilter()",
                "dataFilterPath" => "ticket",
            ],
        ];
    @endphp
    <div class="w-[80%] mx-auto">
        <x-search-header heading="Production" :search_fields=$searchFields/>
    </div>

    <!-- Main Content -->
    <section class="text-center mx-auto ">
        <div
            class="show-box mx-auto w-[80%] h-[70vh] bg-[var(--secondary-bg-color)] border border-[var(--glass-border-color)]/20 rounded-xl shadow pt-8.5 relative">
            <x-form-title-bar printBtn layout="table" title="Show Productions" resetSortBtn />

            <div class="absolute bottom-3 right-3 flex items-center gap-2 w-fll z-50">
                <x-section-navigation-button link="{{ route('productions.create') }}" title="Add New Productions" icon="fa-plus" />
            </div>

            <div class="details h-full z-40">
                <div class="container-parent h-full">
                    <div class="card_container px-3 h-full flex flex-col">
                        <div id="table-head" class="grid grid-cols-6 bg-[var(--h-bg-color)] rounded-lg font-medium py-2 hidden mt-4">
                            <div class="cursor-pointer" onclick="sortByThis(this)">Article No.</div>
                            <div class="col-span-2 cursor-pointer" onclick="sortByThis(this)">Worker Name</div>
                            <div class="cursor-pointer" onclick="sortByThis(this)">Ticket</div>
                            <div class="cursor-pointer" onclick="sortByThis(this)">Issue Date</div>
                            <div class="cursor-pointer" onclick="sortByThis(this)">Receive Date</div>
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

                <span>${data.article_no}</span>
                <span class="col-span-2">${data.worker_name}</span>
                <span>${data.ticket}</span>
                <span>${data.issue_date}</span>
                <span>${data.receive_date}</span>
            </div>`;
        }

        // const fetchedData = [];
        // let allDataArray = fetchedData.map(item => {
        //     return {
        //         id: item.id,
        //         article_no: item.article.article_no,
        //         worker_name: item.worker.employee_name + ' | ' + item.work.title,
        //         ticket: item.ticket,
        //         issue_date: item.issue_date ? formatDate(item.issue_date) : '-',
        //         receive_date: item.receive_date ? formatDate(item.receive_date) : '-',
        //         visible: true,
        //     };
        // });
    </script>
@endsection
