@extends('app')
@section('title', 'Show Bank Accounts | ' . $client_company->name)
@section('content')
    @php
        $searchFields = [
            "Account Title" => [
                "id" => "account_title",
                "type" => "text",
                "placeholder" => "Enter account title",
                                "dataFilterPath" => "account_title",
            ],
            "Category" => [
                "id" => "category",
                "type" => "select",
                "options" => [
                            'self' => ['text' => 'Self'],
                            'customer' => ['text' => 'Customer'],
                            'supplier' => ['text' => 'Supplier'],
                        ],
                                "dataFilterPath" => "category",
            ],
            "Name" => [
                "id" => "name",
                "type" => "text",
                "placeholder" => "Enter name",
                                "dataFilterPath" => "name",
            ],
            "Account No" => [
                "id" => "account_no",
                "type" => "text",
                "placeholder" => "Enter account no",
                                "dataFilterPath" => "account_no",
            ],
            "Bank" => [
                "id" => "bank",
                "type" => "select",
                "options" => $bank_options,
                                "dataFilterPath" => "bank",
            ],
            'Status' => [
                'id' => 'status',
                'type' => 'select',
                'options' => [
                    'active' => ['text' => 'Active'],
                    'in_active' => ['text' => 'In Active'],
                ],
                'dataFilterPath' => 'status',
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
    <div>
        <div class="w-[90%] mx-auto">
            <x-search-header heading="Bank Accounts" :search_fields=$searchFields/>
        </div>

        <!-- Main Content -->
        <section class="text-center mx-auto">
            <div
                class="show-box mx-auto w-[90%] h-[70vh] bg-[var(--secondary-bg-color)] border border-[var(--glass-border-color)]/20 rounded-xl shadow pt-8.5 relative">
                <x-form-title-bar printBtn title="Show Bank Accounts" changeLayoutBtn layout="{{ $authLayout }}" resetSortBtn />

                <div class="absolute bottom-0 right-0 flex items-center justify-between gap-2 w-fll z-50 p-3 w-full pointer-events-none">
                    <x-section-navigation-button link="{{ route('bank-accounts.create') }}" title="Add New Account" icon="fa-plus" />
                </div>

                <div class="details h-full z-40">
                    <div class="container-parent h-full">
                        <div class="card_container px-3 h-full flex flex-col">
                            <div id="table-head" class="grid grid-cols-9 bg-[var(--h-bg-color)] rounded-lg text-center font-medium py-2 hidden mt-4">
                                <div class="cursor-pointer" onclick="sortByThis(this)">Date</div>
                                <div class="cursor-pointer col-span-2" onclick="sortByThis(this)">Account Title</div>
                                <div class="cursor-pointer col-span-2" onclick="sortByThis(this)">Name</div>
                                <div class="cursor-pointer" onclick="sortByThis(this)">Bank</div>
                                <div class="cursor-pointer" onclick="sortByThis(this)">Category</div>
                                <div class="cursor-pointer" onclick="sortByThis(this)">Balance</div>
                                <div class="cursor-pointer" onclick="sortByThis(this)">Status</div>
                            </div>
                            <p id="noItemsError" style="display: none" class="text-sm text-[var(--border-error)] mt-3">No items found</p>
                            <div class="overflow-y-auto grow my-scrollbar-2">
                                <div class="search_container grid grid-cols-1 sm:grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>

@endsection

@push('page-scripts')
<script defer src="{{ asset('js/pages/bank-accounts-index.js') }}"></script>
<script>
        window.__bankAccountsIndex = {
            currentUserRole: @json(Auth::user()->role),
            authLayout: @json($authLayout),
            bankAccountStatusUrl: @json(route('update-bank-account-status')),
            bankAccountsUpdateSerialBase: @json(url('bank-accounts')),
        };
    </script>
@endpush
