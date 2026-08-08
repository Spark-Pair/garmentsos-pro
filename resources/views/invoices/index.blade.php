@extends('app')
@section('title', 'Show Invoices | ' . $client_company->name)
@section('content')
    @php
        $searchFields = [
            "Invoice No" => [
                "id" => "invoice_no",
                "type" => "text",
                "placeholder" => "Enter invoice no. or 1590-1600",
                "dataFilterPath" => "invoice_no",
                "listInput" => true,
            ],
            "Reff. No." => [
                "id" => "reff_no",
                "type" => "text",
                "placeholder" => "Enter reff. no.",
                "dataFilterPath" => "reff_no",
            ],
            "Customer Name" => [
                "id" => "customer_name",
                "type" => "text",
                "placeholder" => "Enter customer name",
                "dataFilterPath" => "customer_name",
            ],
            "City" => [
                "id" => "city",
                "type" => "text",
                "placeholder" => "Enter city",
                "dataFilterPath" => "city",
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
        <x-search-header heading="Invoices" :search_fields=$searchFields/>
    </div>

    <!-- Main Content -->
    <section class="text-center mx-auto ">
        <div
            class="show-box mx-auto w-[80%] h-[70vh] bg-[var(--secondary-bg-color)] border border-[var(--glass-border-color)]/20 rounded-xl shadow pt-8.5 relative">
            <x-form-title-bar printBtn title="Show Invoices" changeLayoutBtn layout="{{ $authLayout }}" resetSortBtn />

            <div class="absolute bottom-3 right-3 flex items-center gap-2 w-fll z-50">
                <x-section-navigation-button link="{{ route('invoices.create') }}" title="Add New Invoice" icon="fa-plus" />
            </div>

            <div class="details h-full z-40">
                <div class="container-parent h-full my-scrollbar-2">
                    <div class="card_container px-3 h-full flex flex-col">
                        <div id="table-head" class="grid grid-cols-5 bg-[var(--h-bg-color)] rounded-lg font-medium py-2 hidden mt-4">
                            <div class="text-center cursor-pointer" onclick="sortByThis(this)">Invoice No.</div>
                            <div class="text-center cursor-pointer" onclick="sortByThis(this)">Reff. No.</div>
                            <div class="text-center cursor-pointer" onclick="sortByThis(this)">Customer</div>
                            <div class="text-center cursor-pointer" onclick="sortByThis(this)">Date</div>
                            <div class="text-center cursor-pointer" onclick="sortByThis(this)">Amount</div>
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
<script defer src="{{ asset('js/pages/invoices-index.js') }}"></script>
<script>
        window.__invoicesIndex = {
            companyData: @json($client_company),
            authLayout: '{{ $authLayout }}',
        };
    </script>
@endpush
