@extends('app')
@section('title', 'Show Productions | ' . $client_company->name)
@section('content')
    @php
        $searchFields = [
            "Article No." => [
                "id" => "article_no",
                "type" => "text",
                "placeholder" => "Enter article no.",
                                "dataFilterPath" => "article_no",
            ],
            "Worker Name" => [
                "id" => "worker_name",
                "type" => "text",
                "placeholder" => "Enter worker name",
                                "dataFilterPath" => "worker_name",
            ],
            "Ticket" => [
                "id" => "ticket",
                "type" => "text",
                "placeholder" => "Enter ticket",
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
                        <div id="table-head" class="flex bg-[var(--h-bg-color)] rounded-lg font-medium py-2 hidden mt-4">
                            <div class="w-1/8 cursor-pointer" onclick="sortByThis(this)">Article No.</div>
                            <div class="w-1/8 cursor-pointer" onclick="sortByThis(this)">Issue Date</div>
                            <div class="w-1/8 cursor-pointer" onclick="sortByThis(this)">Receive Date</div>
                            <div class="w-1/8 cursor-pointer" onclick="sortByThis(this)">Ticket</div>
                            <div class="w-1/7 cursor-pointer" onclick="sortByThis(this)">Worker Name</div>
                            <div class="w-1/8 cursor-pointer" onclick="sortByThis(this)">Quantity</div>
                            <div class="w-1/8 cursor-pointer" onclick="sortByThis(this)">Rate</div>
                            <div class="w-1/8 cursor-pointer" onclick="sortByThis(this)">Amount</div>
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

@endsection


@push('page-scripts')
<script defer src="{{ asset('js/pages/production-ticket-print.js') }}"></script>
<script defer src="{{ asset('js/pages/productions-index.js') }}"></script>
<script>
        window.__productionTicketPrint = {
            company: @json(app(\App\Services\Branches\ModuleBranchService::class)->documentBranding('productions')),
        };
        window.__productionsIndex = {
            authLayout: @json($authLayout),
        };
    </script>
@endpush
