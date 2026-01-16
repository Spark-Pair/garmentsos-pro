@extends('app')
@section('title', 'Show Utility Bills | ' . $client_company->name)
@section('content')
    @php
        $searchFields = [
            "Bill Type" => [
                "id" => "bill_type",
                "type" => "text",
                "placeholder" => "Enter bill type",
                "oninput" => "runDynamicFilter()",
                "dataFilterPath" => "bill_type",
            ],
            "Location" => [
                "id" => "location",
                "type" => "text",
                "placeholder" => "Enter location",
                "oninput" => "runDynamicFilter()",
                "dataFilterPath" => "location",
            ],
            "Account Title" => [
                "id" => "account_title",
                "type" => "text",
                "placeholder" => "Enter account title",
                "oninput" => "runDynamicFilter()",
                "dataFilterPath" => "account_title",
            ],
            "Account No." => [
                "id" => "account_mo",
                "type" => "text",
                "placeholder" => "Enter account no.",
                "oninput" => "runDynamicFilter()",
                "dataFilterPath" => "account_no",
            ],
            "Month" => [
                "id" => "month",
                "type" => "month",
                "oninput" => "runDynamicFilter()",
                "dataFilterPath" => "month",
            ],
            'Status' => [
                'id' => 'status',
                'type' => 'select',
                'options' => [
                    'paid' => ['text' => 'Paid'],
                    'due-today' => ['text' => 'Due Today'],
                    'overdue' => ['text' => 'Overdue'],
                    'upcoming' => ['text' => 'Upcoming'],
                ],
                'dataFilterPath' => 'status',
            ]
        ];
    @endphp

    <div class="w-[80%] mx-auto">
        <x-search-header heading="Utility Bills" :search_fields=$searchFields />
    </div>

    <!-- Main Content -->
    <section class="text-center mx-auto ">
        <div
            class="show-box mx-auto w-[80%] h-[70vh] bg-[var(--secondary-bg-color)] border border-[var(--glass-border-color)]/20 rounded-xl shadow pt-8.5 relative">
            <x-form-title-bar printBtn layout="table" title="Show Utility Bills" resetSortBtn />

            <div class="absolute bottom-3 right-3 flex items-center gap-2 w-fll z-50">
                <x-section-navigation-button link="{{ route('utility-bills.create') }}" title="Add Utility Bill"
                    icon="fa-plus" />
            </div>

            <div class="details h-full z-40">
                <div class="container-parent h-full">
                    <div class="card_container px-3 h-full flex flex-col text-center">
                        <div id="table-head" class="grid grid-cols-9 bg-[var(--h-bg-color)] rounded-lg font-medium py-2 hidden mt-4">
                            <div class="cursor-pointer" onclick="sortByThis(this)">Bill Type</div>
                            <div class="cursor-pointer" onclick="sortByThis(this)">Location</div>
                            <div class="cursor-pointer" onclick="sortByThis(this)">Account Title</div>
                            <div class="cursor-pointer" onclick="sortByThis(this)">Account No.</div>
                            <div class="cursor-pointer" onclick="sortByThis(this)">Month</div>
                            <div class="cursor-pointer" onclick="sortByThis(this)">Units</div>
                            <div class="cursor-pointer" onclick="sortByThis(this)">Amount</div>
                            <div class="cursor-pointer" onclick="sortByThis(this)">Due Date</div>
                            <div class="cursor-pointer" onclick="sortByThis(this)">Status</div>
                        </div>
                        <p id="noItemsError" style="display: none" class="text-sm text-[var(--border-error)] mt-3">No Record found</p>
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
        let today = formatDate(new Date(), false, true);

        function createRow(data) {
            return `
            <div id="${data.id}" oncontextmenu='${data.oncontextmenu || ""}' onclick='${data.onclick || ""}'
                class="item row relative group grid grid-cols-9 border-b border-[var(--h-bg-color)] items-center py-2 cursor-pointer hover:bg-[var(--h-secondary-bg-color)] transition-all fade-in ease-in-out"
                data-json='${JSON.stringify(data)}'>

                <span class="capitalize">${data.bill_type}</span>
                <span class="capitalize">${data.location}</span>
                <span class="capitalize">${data.account_title}</span>
                <span class="capitalize">${data.account_no}</span>
                <span class="capitalize">${data.month}</span>
                <span class="capitalize">${data.units}</span>
                <span class="capitalize">${data.amount}</span>
                <span class="capitalize">${data.due_date}</span>
                <span class="capitalize">${data.status}</span>
            </div>`;
        }

        // const fetchedData = [];
        // let allDataArray = fetchedData.map(item => {
        //     return {
        //         id: item.id,
        //         bill_type: item.account.bill_type.title,
        //         location: item.account.location.title,
        //         account_title: item.account.account_title,
        //         account_no: item.account.account_no,
        //         month: new Date(item.month + '-01').toLocaleString('en-US', {month: 'short', year: 'numeric',}).replace(' ', '-'),
        //         units: item.units || '-' ,
        //         amount: formatNumbersWithDigits(item.amount, 1, 1),
        //         due_date: formatDate(item.due_date),
        //         is_paid: item.is_paid,
        //         status: item.is_paid ? 'Paid' : formatDate(item.due_date, false, true) < today ? 'Overdue' : formatDate(item.due_date, false, true) == today ? 'Due Today' : formatDate(item.due_date, false, true) > today ? 'Upcoming' : '-',
        //         oncontextmenu: "generateContextMenu(event)",
        //         visible: true,
        //     };
        // });

        function generateContextMenu(e) {
            e.preventDefault();
            let item = e.target.closest('.item');
            let data = JSON.parse(item.dataset.json);

            let contextMenuData = {
                item: item,
                data: data,
                x: e.pageX,
                y: e.pageY,
                actions: [],
                onlyThisActions: true,
            };

            if (!data.is_paid) {
                contextMenuData.actions.push({id: 'mark-paid', text: 'Mark Paid', onclick: `markThisPaid(${data.id})`})
                createContextMenu(contextMenuData);
            }
        }

        function markThisPaid(id) {
            $.ajax({
                url: `/utility-bills/${id}/mark-paid`,
                type: "PUT",
                data: {
                    _token: "{{ csrf_token() }}",
                    _method: "put",
                },
                success: function () {
                    location.reload();
                },
                error: function () {
                    alert("Failed to mark paid.");
                }
            });
        }
    </script>
@endsection
