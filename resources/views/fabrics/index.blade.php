@extends('app')
@section('title', 'Show Fabrics | ' . $client_company->name)
@section('content')
    @php
        $searchFields = [
            'Supplier Name' => [
                'id' => 'supplier_name',
                'type' => 'text',
                'placeholder' => 'Enter supplier name',
                                'dataFilterPath' => 'supplier_name',
            ],
            'Worker Name' => [
                'id' => 'employee_name',
                'type' => 'text',
                'placeholder' => 'Enter worker name',
                                'dataFilterPath' => 'employee_name',
            ],
            'Fabric' => [
                'id' => 'fabric',
                'type' => 'select',
                'options' => $fabrics_options,
                                'dataFilterPath' => 'fabric',
            ],
            "Type" => [
                "id" => "type",
                "type" => "select",
                "options" => [
                            'Issued' => ['text' => 'Issued'],
                            'Received' => ['text' => 'Received'],
                            'Returned' => ['text' => 'Returned'],
                        ],
                                "dataFilterPath" => "type",
            ],
            'Tag' => [
                'id' => 'tag',
                'type' => 'text',
                'placeholder' => 'Enter tag',
                                'dataFilterPath' => 'tag',
            ],
            'Remarks' => [
                'id' => 'remarks',
                'type' => 'text',
                'placeholder' => 'Enter remarks',
                                'dataFilterPath' => 'remarks',
            ],
            'Date Range' => [
                'id' => 'date_range_start',
                'type' => 'date',
                'id2' => 'date_range_end',
                'type2' => 'date',
                                'dataFilterPath' => 'date',
            ],
        ];
    @endphp
    <div>
        <div class="w-[80%] mx-auto">
            <x-search-header heading="Fabrics" :search_fields=$searchFields />
        </div>

        <!-- Main Content -->
        <section class="text-center mx-auto">
            <div
                class="show-box mx-auto w-[80%] h-[70vh] bg-[var(--secondary-bg-color)] border border-[var(--glass-border-color)]/20 rounded-xl shadow pt-8.5 relative">
                <x-form-title-bar printBtn layout="table" title="Show Fabrics" resetSortBtn />

                <div class="absolute bottom-3 right-3 flex items-center gap-2 w-fll z-50">
                    <x-section-navigation-button link="{{ route('fabrics.create') }}" title="Add New Fabric"
                        icon="fa-plus" />
                </div>

                <div class="details h-full z-40">
                    <div class="container-parent h-full">
                        <div class="card_container px-3 h-full flex flex-col">
                            <div id="table-head" class="flex items-center bg-[var(--h-bg-color)] rounded-lg font-medium py-2 hidden mt-4">
                                <div class="cursor-pointer text-center w-[10%]" onclick="sortByThis(this)">Date</div>
                                <div class="cursor-pointer text-center w-[15%]" onclick="sortByThis(this)">Supplier / Worker</div>
                                <div class="cursor-pointer text-center w-[10%]" onclick="sortByThis(this)">Type</div>
                                <div class="cursor-pointer text-center w-[10%]" onclick="sortByThis(this)">Fabric</div>
                                <div class="cursor-pointer text-center w-[10%]" onclick="sortByThis(this)">Color</div>
                                <div class="cursor-pointer text-center w-[10%]" onclick="sortByThis(this)">Unit</div>
                                <div class="cursor-pointer text-center w-[10%]" onclick="sortByThis(this)">Quantity</div>
                                <div class="cursor-pointer text-center w-[20%]" onclick="sortByThis(this)">Tag</div>
                                <div class="cursor-pointer text-center w-[10%]" onclick="sortByThis(this)">Remarks</div>
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
    </div>

@endsection

@push('page-scripts')
<script defer src="{{ asset('js/pages/fabrics-index.js') }}"></script>
<script>
        window.__fabricsIndex = {
            currentUserRole: '{{ Auth::user()->role }}',
            authLayout: @json($authLayout),
            canUpdate: @json($canUpdate),
            canDelete: @json($canDelete),
        };
    </script>
@endpush
