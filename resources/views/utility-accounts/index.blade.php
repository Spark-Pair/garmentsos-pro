@extends('app')
@section('title', 'Show Utility Accounts | ' . $client_company->name)
@section('content')
    @php
        $searchFields = [
            "Bill Type" => [
                "id" => "bill_type",
                "type" => "text",
                "placeholder" => "Enter bill type",
                                "dataFilterPath" => "bill_type",
            ],
            "Location" => [
                "id" => "location",
                "type" => "text",
                "placeholder" => "Enter location",
                                "dataFilterPath" => "location",
            ],
            "Account Title" => [
                "id" => "account_title",
                "type" => "text",
                "placeholder" => "Enter account title",
                                "dataFilterPath" => "account_title",
            ],
            "Account No." => [
                "id" => "account_no",
                "type" => "text",
                "placeholder" => "Enter account no.",
                                "dataFilterPath" => "account_no",
            ],
        ];
    @endphp

    <div class="w-[90%] mx-auto">
        <x-search-header heading="Utility Accounts" :search_fields=$searchFields />
    </div>

    <!-- Main Content -->
    <section class="text-center mx-auto ">
        <div
            class="show-box mx-auto w-[90%] h-[70vh] bg-[var(--secondary-bg-color)] border border-[var(--glass-border-color)]/20 rounded-xl shadow pt-8.5 relative">
            <x-form-title-bar printBtn layout="table" title="Show Utility Accounts" resetSortBtn />

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
        </div>
    </section>

@endsection

@push('page-scripts')
<script defer src="{{ asset('js/pages/utility-accounts-index.js') }}"></script>
@endpush
