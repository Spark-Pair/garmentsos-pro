@extends('app')
@section('title', 'Show Setups | ' . $client_company->name)
@section('content')
    @php
        $searchFields = [
            "Type" => [
                "id" => "type",
                "type" => "select",
                "options" => [
                    'supplier_category' => ['text' => 'Supplier Category'],
                    'bank_name' => ['text' => 'Bank Name'],
                    'city' => ['text' => 'City'],
                    'fabric' => ['text' => 'Fabric'],
                    'article_master_unit' => ['text' => 'Article Master Unit'],
                    'staff_type' => ['text' => 'Staff Type'],
                    'worker_type' => ['text' => 'Worker Type'],
                ],
                                "dataFilterPath" => "type",
            ],
            "Title" => [
                "id" => "title",
                "type" => "text",
                "placeholder" => "Enter title",
                                "dataFilterPath" => "title",
            ],
            "short_title" => [
                "id" => "short_title",
                "type" => "text",
                "placeholder" => "Enter short title / global key",
                                "dataFilterPath" => "short_title",
            ],
        ];
    @endphp
    <div class="w-[80%] mx-auto">
        <x-search-header heading="Setups" :search_fields=$searchFields/>
    </div>

    <!-- Main Content -->
    <section class="text-center mx-auto ">
        <div
            class="show-box mx-auto w-[80%] h-[70vh] bg-[var(--secondary-bg-color)] border border-[var(--glass-border-color)]/20 rounded-xl shadow pt-8.5 relative mt-3">
            <x-form-title-bar printBtn title="Show Setups" resetSortBtn />

            <div class="absolute bottom-3 right-3 flex items-center gap-2 w-fll z-50">
                <x-section-navigation-button link="{{ route('setups.create') }}" title="Add New Setup" icon="fa-plus" />
            </div>

            <div class="details h-full z-40">
                <div class="container-parent h-full">
                    <div class="card_container px-3 h-full flex flex-col">
                        <div id="table-head" class="grid grid-cols-3 bg-[var(--h-bg-color)] rounded-lg font-medium py-2 hidden mt-4">
                            <div class="cursor-pointer" onclick="sortByThis(this)">Type</div>
                            <div class="cursor-pointer" onclick="sortByThis(this)">Title</div>
                            <div class="cursor-pointer" onclick="sortByThis(this)">Short Title / Global Key</div>
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
<script defer src="{{ asset('js/pages/setups-index.js') }}"></script>
<script>
        window.__setupsIndex = {
            authLayout: @json($authLayout),
        };
    </script>
@endpush
