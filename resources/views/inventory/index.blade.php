@extends('app')
@section('title', 'Inventory | ' . $client_company->name)
@section('content')
    @php
        $searchFields = [
            'Item' => ['id' => 'name', 'type' => 'text', 'placeholder' => 'Enter item name', 'dataFilterPath' => 'name'],
            'Type' => ['id' => 'type', 'type' => 'text', 'placeholder' => 'Enter type', 'dataFilterPath' => 'type'],
            'Tag' => ['id' => 'tag', 'type' => 'text', 'placeholder' => 'Enter tag', 'dataFilterPath' => 'tag'],
        ];
    @endphp

    <div class="w-[80%] mx-auto">
        <x-search-header heading="Inventory" :search_fields="$searchFields" />
    </div>

    <section class="text-center mx-auto">
        <div class="show-box mx-auto w-[80%] h-[70vh] bg-[var(--secondary-bg-color)] border border-[var(--glass-border-color)]/20 rounded-xl shadow pt-8.5 relative">
            <x-form-title-bar printBtn layout="table" title="Inventory" resetSortBtn />

            <div class="absolute bottom-3 right-3 flex items-center gap-2 w-fll z-50">
                <x-section-navigation-button link="{{ route('inventory.create') }}" title="Add Inventory" icon="fa-plus" />
            </div>

            <div class="details h-full z-40">
                <div class="container-parent h-full">
                    <div class="card_container px-3 h-full flex flex-col">
                        <div id="table-head" class="grid grid-cols-7 bg-[var(--h-bg-color)] rounded-lg font-medium py-2 hidden mt-4">
                            <div onclick="sortByThis(this)" class="cursor-pointer">Item</div>
                            <div onclick="sortByThis(this)" class="cursor-pointer">Type</div>
                            <div onclick="sortByThis(this)" class="cursor-pointer">Fabric</div>
                            <div onclick="sortByThis(this)" class="cursor-pointer">Tag</div>
                            <div onclick="sortByThis(this)" class="cursor-pointer">Unit</div>
                            <div onclick="sortByThis(this)" class="cursor-pointer">Stock</div>
                            <div onclick="sortByThis(this)" class="cursor-pointer">Status</div>
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
    };
</script>
@endpush
