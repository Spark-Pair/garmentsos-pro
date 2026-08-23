@extends('app')
@section('title', 'Daily Ledger Summary | ' . $client_company->name)
@section('content')
@php
    $searchFields = [
        "Type" => [
            "id" => "type",
            "type" => "select",
            "options" => [
                        'deposit' => ['text' => 'Deposit'],
                        'use' => ['text' => 'Use'],
                    ],
                        "dataFilterPath" => "type",
        ],
        "Date Range" => [
            "id" => "date_range_start",
            "type" => "date",
            "id2" => "date_range_end",
            "type2" => "date",
                        "dataFilterPath" => "date",
        ]
    ];
@endphp
    <div class="w-[80%] mx-auto">
        <x-search-header heading="Daily Ledger Summary" :search_fields=$searchFields/>
    </div>

    <!-- Main Content -->
    <section class="text-center mx-auto ">
        <div
            class="show-box mx-auto w-[80%] h-[70vh] bg-[var(--secondary-bg-color)] border border-[var(--glass-border-color)]/20 rounded-xl shadow pt-8.5 relative">
            <x-form-title-bar printBtn layout="table" title="Daily Ledger Summary" resetSortBtn />

            <div class="absolute bottom-14 right-0 flex items-center justify-between gap-2 w-fll z-50 p-3 w-full pointer-events-none">
                <x-section-navigation-button link="{{ route('daily-ledger.create') }}" title="New Deposit | use" icon="fa-plus" />
            </div>

            <div class="details h-full z-40">
                <div class="container-parent h-full">
                    <div class="card_container px-3 pb-3 h-full flex flex-col">
                        <div id="table-head" class="grid grid-cols-4 text-center bg-[var(--h-bg-color)] rounded-lg font-medium py-2 hidden mt-4">
                            <div class="cursor-pointer" onclick="sortByThis(this)">Date</div>
                            <div class="cursor-pointer" onclick="sortByThis(this)">Deposit</div>
                            <div class="cursor-pointer" onclick="sortByThis(this)">Use</div>
                            <div class="cursor-pointer" onclick="sortByThis(this)">Balance</div>
                        </div>
                        <p id="noItemsError" style="display: none" class="text-sm text-[var(--border-error)] mt-3">No items found</p>
                        <div class="overflow-y-auto grow my-scrollbar-2">
                            <div class="search_container grid grid-cols-1 sm:grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5 grow">
                            </div>
                        </div>
                        <div id="calc-bottom" class="flex w-full gap-4 text-sm bg-[var(--secondary-bg-color)] pt-2 rounded-lg">
                            <div
                                class="opening-balance flex justify-between items-center border border-gray-600 rounded-lg py-2 px-4 w-full cursor-not-allowed">
                                <div>Opening Balance - Rs.</div>
                                <div class="text-right">0.0</div>
                            </div>
                            <div
                                class="total-Deposit flex justify-between items-center border border-gray-600 rounded-lg py-2 px-4 w-full cursor-not-allowed">
                                <div>Total Deposit - Rs.</div>
                                <div class="text-right">0.0</div>
                            </div>
                            <div
                                class="total-Payment flex justify-between items-center border border-gray-600 rounded-lg py-2 px-4 w-full cursor-not-allowed">
                                <div>Total Use - Rs.</div>
                                <div class="text-right">0.0</div>
                            </div>
                            <div
                                class="balance flex justify-between items-center border border-gray-600 rounded-lg py-2 px-4 w-full cursor-not-allowed">
                                <div>Total Balance - Rs.</div>
                                <div class="text-right">0.0</div>
                            </div>
                            <div
                                class="closing-balance flex justify-between items-center border border-gray-600 rounded-lg py-2 px-4 w-full cursor-not-allowed">
                                <div>Closing Balance - Rs.</div>
                                <div class="text-right">0.0</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

@endsection

@push('page-scripts')
<script defer src="{{ asset('js/pages/daily-ledger-summary.js') }}"></script>
<script>
    window.__dailyLedgerIndex = {
        csrfToken: @json(csrf_token()),
        currentUserRole: @json(Auth::user()->role),
    };
</script>
@endpush
