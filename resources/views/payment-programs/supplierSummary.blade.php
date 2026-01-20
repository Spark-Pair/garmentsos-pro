@extends('app')
@section('title', 'Program Summary (supplier) | ' . $client_company->name)
@section('content')
    @php
        $searchFields = [
            'Name' => [
                'id' => 'supplier_name',
                'type' => 'text',
                'placeholder' => 'Enter supplier name',
                'oninput' => 'runDynamicFilter()',
                'dataFilterPath' => 'supplier_name',
            ],
        ];
    @endphp
    <div class="w-[80%] mx-auto">
        <x-search-header heading="Program Summary (supplier)" :search_fields=$searchFields />
    </div>

    <!-- Main Content -->
    <section class="text-center mx-auto">
        <div
            class="show-box mx-auto w-[80%] h-[70vh] bg-[var(--secondary-bg-color)] border border-[var(--glass-border-color)]/20 rounded-xl shadow pt-8.5 relative">
            <x-form-title-bar printBtn layout="table" title="Program Summary (supplier)" resetSortBtn />

            <div class="absolute bottom-0 right-0 flex items-center justify-between gap-2 w-fll z-50 p-3 w-full pointer-events-none">
                <x-section-navigation-button direction="right" id="info" icon="fa-info" />
                <x-section-navigation-button link="{{ route('payment-programs.create') }}" title="Add New Program"
                    icon="fa-plus" />
            </div>

            <div class="details h-full z-40">
                <div class="container-parent h-full">
                    <div class="card_container px-3 h-full flex flex-col">
                        <div id="table-head" class="grid grid-cols-4 bg-[var(--h-bg-color)] rounded-lg font-medium py-2 mt-4">
                            <div class="cursor-pointer" onclick="sortByThis(this)">Name</div>
                            <div class="cursor-pointer" onclick="sortByThis(this)">Total Amount</div>
                            <div class="cursor-pointer" onclick="sortByThis(this)">Total Payment</div>
                            <div class="cursor-pointer" onclick="sortByThis(this)">Total Balance</div>
                        </div>
                        <p id="noItemsError" style="display: none" class="text-sm text-[var(--border-error)] mt-3 cursor-pointer" onclick="sortByThis(this)">No items found</p>
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
            let total_amount = data.data.payment_programs.reduce((sum, p) => sum + Number(p.amount || 0), 0);
            let total_payment = data.data.payment_programs.reduce((sum, p) => sum + Number(p.payment || 0), 0);
            let total_balance = data.data.payment_programs.reduce((sum, p) => sum + Number(p.balance || 0), 0);

            if (total_balance !== 0) {
                return `
                <div id="${data.id}" class="item row relative group grid grid-cols-4 border-b border-[var(--h-bg-color)] items-center py-2 cursor-pointer hover:bg-[var(--h-secondary-bg-color)] transition-all fade-in ease-in-out">
                    <span>${(data.name)}</span>
                    <span>${total_amount}</span>
                    <span>${total_payment}</span>
                    <span>${total_balance}</span>
                </div>`;
            }
        }

        // const fetchedData = [];
        // let allDataArray = fetchedData.map((item, index) => {
        //     return {
        //         id: index + 1,
        //         name: item.name,
        //         total_amount: item.total_amount > 0 ? formatNumbersWithDigits(item.total_amount, 1, 1) : '-',
        //         total_payment: item.total_payment > 0 ? formatNumbersWithDigits(item.total_payment, 1, 1) : '-',
        //         balance: item.balance > 0 ? formatNumbersWithDigits(item.balance, 1, 1) : '-',
        //         visible: true,
        //     };
        // });
    </script>
@endsection
