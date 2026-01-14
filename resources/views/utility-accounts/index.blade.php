@extends('app')
@section('title', 'Show Utility Accounts | ' . $client_company->name)
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
        ];
    @endphp

    <div class="w-[80%] mx-auto">
        <x-search-header heading="Utility Accounts" :search_fields=$searchFields />
    </div>

    <!-- Main Content -->
    <section class="text-center mx-auto ">
        <div
            class="show-box mx-auto w-[80%] h-[70vh] bg-[var(--secondary-bg-color)] border border-[var(--glass-border-color)]/20 rounded-xl shadow pt-8.5 relative">
            <x-form-title-bar printBtn layout="table" title="Show Utility Accounts" resetSortBtn />

            @if (count($utilityAccounts) > 0)
                <div class="absolute bottom-3 right-3 flex items-center gap-2 w-fll z-50">
                    <x-section-navigation-button link="{{ route('utility-accounts.create') }}" title="Add Utility Account"
                        icon="fa-plus" />
                </div>

                <div class="details h-full z-40">
                    <div class="container-parent h-full">
                        <div class="card_container px-3 h-full flex flex-col text-center">
                            <div id="table-head" class="grid grid-cols-4 bg-[var(--h-bg-color)] rounded-lg font-medium py-2 hidden mt-4">
                                <div class="cursor-pointer" onclick="sortByThis(this)">Bill Type</div>
                                <div class="cursor-pointer" onclick="sortByThis(this)">Location</div>
                                <div class="cursor-pointer" onclick="sortByThis(this)">Account Title</div>
                                <div class="cursor-pointer" onclick="sortByThis(this)">Account No.</div>
                            </div>
                            <p id="noItemsError" style="display: none" class="text-sm text-[var(--border-error)] mt-3">No Record found</p>
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
                    <a href="{{ route('utility-accounts.create') }}"
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
                class="item row relative group grid grid-cols-4 border-b border-[var(--h-bg-color)] items-center py-2 cursor-pointer hover:bg-[var(--h-secondary-bg-color)] transition-all fade-in ease-in-out"
                data-json='${JSON.stringify(data)}'>

                <span class="capitalize">${data.bill_type}</span>
                <span class="capitalize">${data.location}</span>
                <span class="capitalize">${data.account_title}</span>
                <span class="capitalize">${data.account_no}</span>
            </div>`;
        }


        const fetchedData = @json($utilityAccounts);
        let allDataArray = fetchedData.map(item => {
            return {
                id: item.id,
                bill_type: item.bill_type.title,
                location: item.location.title,
                account_title: item.account_title,
                account_no: item.account_no,
                visible: true,
            };
        });
    </script>
@endsection
