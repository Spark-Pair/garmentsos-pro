@extends('app')
@section('title', 'Inventory | ' . $client_company->name)
@section('content')
    @php
        $searchFields = [
            'Supplier Name' => ['id' => 'supplier_name', 'type' => 'text', 'placeholder' => 'Enter supplier name', 'dataFilterPath' => 'supplier_name'],
            'Item' => ['id' => 'name', 'type' => 'text', 'placeholder' => 'Enter item name', 'dataFilterPath' => 'name'],
            'Item Type' => ['id' => 'type', 'type' => 'select', 'options' => $typeOptions, 'dataFilterPath' => 'item_type'],
            'Fabric' => ['id' => 'fabric_id', 'type' => 'select', 'options' => $fabricOptions, 'dataFilterPath' => 'fabric'],
            'Tag' => ['id' => 'tag', 'type' => 'text', 'placeholder' => 'Enter tag', 'dataFilterPath' => 'tag'],
            'Date Range' => ['id' => 'date_range_start', 'type' => 'date', 'id2' => 'date_range_end', 'type2' => 'date', 'dataFilterPath' => 'filter_date'],
        ];
    @endphp

    <div class="w-[80%] mx-auto">
        <x-search-header heading="Inventory" :search_fields="$searchFields" />
    </div>

    <section class="text-center mx-auto">
        <div class="show-box mx-auto w-[80%] h-[70vh] bg-[var(--secondary-bg-color)] border border-[var(--glass-border-color)]/20 rounded-xl shadow pt-8.5 relative">
            <x-form-title-bar printBtn layout="table" title="Show Inventory" resetSortBtn />

            <div class="absolute bottom-3 right-3 flex items-center gap-2 w-full z-50">
                <x-section-navigation-button link="{{ route('inventory.create') }}" title="Add New Inventory" icon="fa-plus" />
            </div>

            <div class="details h-full z-40">
                <div class="container-parent h-full">
                    <div class="card_container px-3 h-full flex flex-col">
                        <div id="table-head" class="flex items-center bg-[var(--h-bg-color)] rounded-lg font-medium py-2 hidden mt-4">
                            <div class="cursor-pointer text-center w-[9%]" onclick="sortByThis(this)">Date</div>
                            <div class="cursor-pointer text-center w-[15%]" onclick="sortByThis(this)">Item</div>
                            <div class="cursor-pointer text-center w-[8%]" onclick="sortByThis(this)">Type</div>
                            <div class="cursor-pointer text-center w-[10%]" onclick="sortByThis(this)">Fabric</div>
                            <div class="cursor-pointer text-center w-[15%]" onclick="sortByThis(this)">Supplier</div>
                            <div class="cursor-pointer text-center w-[10%]" onclick="sortByThis(this)">Tag</div>
                            <div class="cursor-pointer text-center w-[7%]" onclick="sortByThis(this)">Unit</div>
                            <div class="cursor-pointer text-center w-[8%]" onclick="sortByThis(this)">Available</div>
                            <div class="cursor-pointer text-center w-[8%]" onclick="sortByThis(this)">Rate</div>
                            <div class="cursor-pointer text-center w-[10%]" onclick="sortByThis(this)">Amount</div>
                        </div>
                        <p id="noItemsError" style="display: none" class="text-sm text-[var(--border-error)] mt-3">No items found</p>
                        <div class="overflow-y-auto grow my-scrollbar-2">
                            <div class="search_container grid grid-cols-1 sm:grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5 grow"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection

@push('page-scripts')
<script defer src="{{ asset('js/pages/inventory-index.js') }}"></script>
<script>
    window.__inventoryIndex = {
        authLayout: @json($authLayout),
        currentUserRole: @json(Auth::user()->role),
        csrfToken: @json(csrf_token()),
        supplierOptions: @json($supplierOptions),
    };
</script>
@endpush
