@extends('app')
@section('title', 'Show Daily Ledger | ' . $client_company->name)
@section('content')
@php
    $searchFields = [
        "Description" => [
            "id" => "description",
            "type" => "text",
            "placeholder" => "Enter description",
            "oninput" => "runDynamicFilter()",
            "dataFilterPath" => "description",
        ],
        "Type" => [
            "id" => "type",
            "type" => "select",
            "options" => [
                        'deposit' => ['text' => 'Deposit'],
                        'use' => ['text' => 'Use'],
                    ],
            "onchange" => "runDynamicFilter()",
            "dataFilterPath" => "type",
        ],
        "Date Range" => [
            "id" => "date_range_start",
            "type" => "date",
            "value" => \Carbon\Carbon::now()->startOfWeek()->toDateString(),
            "id2" => "date_range_end",
            "type2" => "date",
            "oninput" => "runDynamicFilter()",
            "dataFilterPath" => "date",
        ]
    ];
@endphp
    <div class="w-[80%] mx-auto">
        <x-search-header heading="Daily Ledger" :search_fields=$searchFields/>
    </div>

    <!-- Main Content -->
    <section class="text-center mx-auto ">
        <div
            class="show-box mx-auto w-[80%] h-[70vh] bg-[var(--secondary-bg-color)] border border-[var(--glass-border-color)]/20 rounded-xl shadow pt-8.5 relative">
            <x-form-title-bar printBtn layout="table" title="Show Daily Ledger" resetSortBtn />

            <div class="absolute bottom-14 right-0 flex items-center justify-between gap-2 w-fll z-50 p-3 w-full pointer-events-none">
                <x-section-navigation-button direction="right" id="info" icon="fa-info" />
                <x-section-navigation-button link="{{ route('daily-ledger.create') }}" title="New Deposit | use" icon="fa-plus" />
            </div>

            <div class="details h-full z-40">
                <div class="container-parent h-full">
                    <div class="card_container px-3 pb-3 h-full flex flex-col">
                        <div id="table-head" class="grid grid-cols-5 text-center bg-[var(--h-bg-color)] rounded-lg font-medium py-2 hidden mt-4">
                            <div class="cursor-pointer" onclick="sortByThis(this)">Date</div>
                            <div class="cursor-pointer" onclick="sortByThis(this)">Description</div>
                            <div class="cursor-pointer" onclick="sortByThis(this)">Deposit</div>
                            <div class="cursor-pointer" onclick="sortByThis(this)">Use</div>
                            <div class="cursor-pointer" onclick="sortByThis(this)">Balance</div>
                        </div>
                        <p id="noItemsError" style="display: none" class="text-sm text-[var(--border-error)] mt-3">No items found</p>
                        <div class="overflow-y-auto grow my-scrollbar-2">
                            <div class="search_container grid grid-cols-1 sm:grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5 grow">
                            </div>
                        </div>
                        <div id="calc-bottom" class="flex w-full gap-4 text-sm bg-[var(--secondary-bg-color)] py-2 rounded-lg">
                            <div
                                class="opening-balance flex justify-between items-center border border-gray-600 rounded-lg py-2 px-4 w-full cursor-not-allowed">
                                <div>Opening Balance - Rs.</div>
                                <div class="text-right">0.00</div>
                            </div>
                            <div
                                class="total-Deposit flex justify-between items-center border border-gray-600 rounded-lg py-2 px-4 w-full cursor-not-allowed">
                                <div>Total Deposit - Rs.</div>
                                <div class="text-right">0.00</div>
                            </div>
                            <div
                                class="total-Payment flex justify-between items-center border border-gray-600 rounded-lg py-2 px-4 w-full cursor-not-allowed">
                                <div>Total Use - Rs.</div>
                                <div class="text-right">0.00</div>
                            </div>
                            <div
                                class="balance flex justify-between items-center border border-gray-600 rounded-lg py-2 px-4 w-full cursor-not-allowed">
                                <div>Total Balance - Rs.</div>
                                <div class="text-right">0.00</div>
                            </div>
                            <div
                                class="closing-balance flex justify-between items-center border border-gray-600 rounded-lg py-2 px-4 w-full cursor-not-allowed">
                                <div>Closing Balance - Rs.</div>
                                <div class="text-right">0.00</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <script>
        let totalDepositAmount = 0;
        let totalUseAmount = 0;
        let authLayout = 'table';

        function createRow(data) {
            return `
                <div id="${data.id}" oncontextmenu='${data.oncontextmenu || ""}' onclick='${data.onclick || ""}'
                    class="item row relative group grid grid-cols-5 text-center border-b border-[var(--h-bg-color)] items-center py-2 cursor-pointer hover:bg-[var(--h-secondary-bg-color)] transition-all fade-in ease-in-out"
                    data-json='${JSON.stringify(data)}'>

                    <span>${data.date}</span>
                    <span>${data.description}</span>
                    <span>${formatNumbersWithDigits(data.deposit, 1, 1)}</span>
                    <span>${formatNumbersWithDigits(data.use, 1, 1)}</span>
                    <span>${formatNumbersWithDigits(data.balance, 1, 1)}</span>
                </div>
            `;
        }

        let allDataArray = []; // Will be populated from AJAX response
        let openingBalanceDom = document.querySelector('#calc-bottom >.opening-balance .text-right');
        let totalDepositDom = document.querySelector('#calc-bottom >.total-Deposit .text-right');
        let totalUseDom = document.querySelector('#calc-bottom >.total-Payment .text-right');
        let balanceDom = document.querySelector('#calc-bottom >.balance .text-right');
        let closingBalanceDom = document.querySelector('#calc-bottom >.closing-balance .text-right');
        let infoDom = document.getElementById('info').querySelector('span');

        function renderCalculation(data) {
            openingBalanceDom.innerText = formatNumbersWithDigits(data.opening_balance, 1, 1);
            totalDepositDom.innerText = formatNumbersWithDigits(data.total_deposit, 1, 1);
            totalUseDom.innerText = formatNumbersWithDigits(data.total_use, 1, 1);
            balanceDom.innerText = formatNumbersWithDigits(data.total_deposit - data.total_use, 1, 1);
            closingBalanceDom.innerText = formatNumbersWithDigits(data.closing_balance, 1, 1);
        }

        // const fetchedData = [];
        // let allDataArray = fetchedData.map(item => {
        //     balance += item.deposit;
        //     balance -= item.use;
        //     totalDepositAmount += parseFloat(item.deposit ?? 0);
        //     totalUseAmount += parseFloat(item.use ?? 0);
        //     return {
        //         id: item.id,
        //         date: item.date = new Date(item.date).toLocaleDateString('sv'),
        //         description: item.description ?? '-',
        //         deposit: item.deposit,
        //         use: item.use,
        //         type: item.deposit > 0 ? 'deposit' : 'use',
        //         balance: balance,
        //         visible: true,
        //     };
        // });

        function onFilter() {
            // =========== CASE 1: NO VISIBLE RECORDS ===========
            if (visibleData.length === 0) {
                infoDom.textContent = `Showing 0 of ${allDataArray.length} records.`;

                if (allDataArray.length > 0) {
                    // Full history balance as opening
                    let fullDeposit = allDataArray.reduce((sum, d) => sum + parseFloat(d.deposit || 0), 0);
                    let fullUse = allDataArray.reduce((sum, d) => sum + parseFloat(d.use || 0), 0);
                    let fullBalance = fullDeposit - fullUse;

                    openingBalanceDom.innerText = formatNumbersWithDigits(fullBalance, 1, 1);
                    totalDepositDom.innerText = "0.00";
                    totalUseDom.innerText = "0.00";
                    balanceDom.innerText = "0.00";
                    closingBalanceDom.innerText = formatNumbersWithDigits(fullBalance, 1, 1);
                } else {
                    // Everything zero
                    openingBalanceDom.innerText = "0.00";
                    totalDepositDom.innerText = "0.00";
                    totalUseDom.innerText = "0.00";
                    balanceDom.innerText = "0.00";
                    closingBalanceDom.innerText = "0.00";
                }
                return;
            }

            // =========== CASE 2: VISIBLE RECORDS EXIST ===========

            // Sort visible data by date + created_at to maintain chronological order
            let sortedVisibleData = [...visibleData].sort((a, b) => {
                let dateCompare = new Date(a.date) - new Date(b.date);
                if (dateCompare !== 0) return dateCompare;
                return new Date(a.created_at) - new Date(b.created_at);
            });

            // Get oldest visible date + created_at
            let oldestVisible = sortedVisibleData[0];

            // Records BEFORE oldest visible (for opening balance)
            let beforeRecords = allDataArray.filter(d => {
                let dDate = new Date(d.date);
                let oldestDate = new Date(oldestVisible.date);

                if (dDate < oldestDate) return true;
                if (dDate.getTime() === oldestDate.getTime()) {
                    return new Date(d.created_at) < new Date(oldestVisible.created_at);
                }
                return false;
            });

            // Calculate opening balance
            let openingDeposit = beforeRecords.reduce((sum, d) => sum + parseFloat(d.deposit || 0), 0);
            let openingUse = beforeRecords.reduce((sum, d) => sum + parseFloat(d.use || 0), 0);
            let openingBalance = openingDeposit - openingUse;

            // Calculate visible totals
            let visibleDeposit = sortedVisibleData.reduce((sum, d) => sum + parseFloat(d.deposit || 0), 0);
            let visibleUse = sortedVisibleData.reduce((sum, d) => sum + parseFloat(d.use || 0), 0);

            // Calculate running balance for each visible row
            let runningBalance = openingBalance;
            sortedVisibleData.forEach(row => {
                runningBalance += parseFloat(row.deposit || 0);
                runningBalance -= parseFloat(row.use || 0);
                row.balance = runningBalance; // Update balance in the row
            });

            // Closing balance = last visible row's balance
            let closingBalance = runningBalance;

            // Update UI
            infoDom.textContent = `Showing ${visibleData.length} of ${allDataArray.length} records.`;
            openingBalanceDom.innerText = formatNumbersWithDigits(openingBalance, 1, 1);
            totalDepositDom.innerText = formatNumbersWithDigits(visibleDeposit, 1, 1);
            totalUseDom.innerText = formatNumbersWithDigits(visibleUse, 1, 1);
            balanceDom.innerText = formatNumbersWithDigits(visibleDeposit - visibleUse, 1, 1);
            closingBalanceDom.innerText = formatNumbersWithDigits(closingBalance, 1, 1);
        }
    </script>
@endsection
