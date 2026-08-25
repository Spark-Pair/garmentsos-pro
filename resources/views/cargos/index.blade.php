@extends('app')
@section('title', 'Show Cargo Lists | ' . $client_company->name)
@section('content')
    @php
        $searchFields = [
            "Cargo No" => [
                "id" => "cargo_no",
                "type" => "text",
                "placeholder" => "Enter cargo no",
                                "dataFilterPath" => "cargo_no",
            ],
            "Cargo Name" => [
                "id" => "cargo_name",
                "type" => "text",
                "placeholder" => "Enter cargo name",
                                "dataFilterPath" => "cargo_name",
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
        <x-search-header heading="Cargo Lists" :search_fields=$searchFields />
    </div>

    <!-- Main Content -->
    <section class="text-center mx-auto ">
        <div
            class="show-box mx-auto w-[80%] h-[70vh] bg-[var(--secondary-bg-color)] border border-[var(--glass-border-color)]/20 rounded-xl shadow pt-8.5 relative">
            <x-form-title-bar printBtn title="Show Cargo Lists" changeLayoutBtn layout="{{ $authLayout }}" resetSortBtn />

            <div class="absolute bottom-3 right-3 flex items-center gap-2 w-fll z-50">
                <x-section-navigation-button link="{{ route('cargos.create') }}" title="Add New Cargo" icon="fa-plus" />
            </div>

            <div class="details h-full z-40">
                <div class="container-parent h-full">
                    <div class="card_container px-3 h-full flex flex-col">
                        <div id="table-head" class="grid grid-cols-3 bg-[var(--h-bg-color)] rounded-lg font-medium py-2 hidden mt-4">
                            <div class="text-center cursor-pointer" onclick="sortByThis(this)">Cargo No.</div>
                            <div class="text-center cursor-pointer" onclick="sortByThis(this)">Cargo Name</div>
                            <div class="text-center cursor-pointer" onclick="sortByThis(this)">Date</div>
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
<script defer src="{{ asset('js/pages/cargos-index.js') }}"></script>
<script>
        window.__cargosIndex = {
            companyData: @json($client_company),
            authLayout: '{{ $authLayout }}',
            openRecordId: @json(request()->integer('open_cargo') ?: null),
            printOpenRecord: @json(request()->boolean('print_cargo')),
        };
    </script>
@endpush
