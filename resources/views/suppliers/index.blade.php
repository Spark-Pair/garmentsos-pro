@extends('app')
@section('title', 'Show Suppliers | ' . $client_company->name)
@section('content')
    @php
        $searchFields = [
            "Supplier Name" => [
                "id" => "supplier_name",
                "type" => "text",
                "placeholder" => "Enter supplier name",
                "dataFilterPath" => "supplier_name",
            ],
            "Urdu Title" => [
                "id" => "urdu_title",
                "type" => "text",
                "placeholder" => "Enter urdu title",
                "dataFilterPath" => "urdu_title",
            ],
            "Username" => [
                "id" => "username",
                "type" => "text",
                "placeholder" => "Enter username",
                "dataFilterPath" => "username",
            ],
            "Phone" => [
                "id" => "phone_number",
                "type" => "text",
                "placeholder" => "Enter phone number",
                "dataFilterPath" => "phone_number",
            ],
            'Category' => [
                'id' => 'category',
                'type' => 'select',
                'options' => $categories_options,
                'dataFilterPath' => 'category',
            ],
            'Status' => [
                'id' => 'status',
                'type' => 'select',
                'options' => [
                    'active' => ['text' => 'Active'],
                    'in_active' => ['text' => 'In Active'],
                ],
                'dataFilterPath' => 'status',
            ]
        ];
    @endphp
    <div>
        <div class="w-[90%] mx-auto">
            <x-search-header heading="Suppliers" :search_fields=$searchFields/>
        </div>

        <section class="text-center mx-auto ">
            <div
                class="show-box mx-auto w-[90%] h-[70vh] bg-[var(--secondary-bg-color)] border border-[var(--glass-border-color)]/20 rounded-xl shadow pt-8.5 relative">
                <x-form-title-bar printBtn title="Show Suppliers" changeLayoutBtn layout="{{ $authLayout }}" resetSortBtn />

                <div class="absolute bottom-0 right-0 flex items-center justify-between gap-2 w-fll z-50 p-3 w-full pointer-events-none">
                    <x-section-navigation-button link="{{ route('suppliers.create') }}" title="Add New Supplier" icon="fa-plus" />
                </div>

                <div class="details h-full z-40">
                    <div class="container-parent h-full">
                        <div class="card_container px-3 h-full flex flex-col">
                            <div id="table-head" class="grid grid-cols-5 bg-[var(--h-bg-color)] rounded-lg font-medium py-2 hidden mt-4">
                                <div class="cursor-pointer text-left pl-5" onclick="sortByThis(this)">Supplier</div>
                                <div class="cursor-pointer text-center" onclick="sortByThis(this)">Urdu Title</div>
                                <div class="cursor-pointer text-center" onclick="sortByThis(this)">Phone</div>
                                <div class="cursor-pointer text-right" onclick="sortByThis(this)">Balance</div>
                                <div class="cursor-pointer text-right pr-5" onclick="sortByThis(this)">Status</div>
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
<script defer src="{{ asset('js/pages/suppliers-index.js') }}"></script>
<script>
        window.__suppliersIndex = {
            currentUserRole: @json(Auth::user()->role),
            authLayout: @json($authLayout),
            updateUserStatusUrl: @json(route('update-user-status')),
            updateSupplierCategoryUrl: @json(route('update-supplier-category')),
            resetPasswordUrl: @json(route('users.reset-password')),
            categoriesOptions: @json($categories_options),
        };
    </script>
@endpush
