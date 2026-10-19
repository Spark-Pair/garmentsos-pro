@extends('app')
@section('title', 'Show Employees | ' . $client_company->name)
@section('content')
    @php
        $searchFields = [
            "Employee Name" => [
                "id" => "employee_name",
                "type" => "text",
                "placeholder" => "Enter employee name",
                                "dataFilterPath" => "employee_name",
            ],
            "Phone" => [
                "id" => "phone_number",
                "type" => "text",
                "placeholder" => "Enter phone number",
                                "dataFilterPath" => "phone_number",
            ],
            "Category" => [
                "id" => "category",
                "type" => "select",
                "options" => [
                            'staff' => ['text' => 'Staff'],
                            'worker' => ['text' => 'Worker'],
                        ],
                                "dataFilterPath" => "category",
            ],
            "Type" => [
                "id" => "type",
                "type" => "select",
                "options" => $all_types,
                                "dataFilterPath" => "type",
            ]
        ];
    @endphp
    <div>
        <div class="w-[90%] mx-auto">
            <x-search-header heading="Employees" :search_fields=$searchFields/>
        </div>

        <!-- Main Content -->
        <section class="text-center mx-auto">
            <div class="show-box mx-auto w-[90%] h-[70vh] bg-[var(--secondary-bg-color)] rounded-xl shadow pt-8.5 pr-2 relative">
                <x-form-title-bar printBtn title="Show Employees" changeLayoutBtn layout="{{ $authLayout }}" resetSortBtn />

                <div class="absolute bottom-3 right-3 flex items-center gap-2 w-fll z-50">
                    <x-section-navigation-button link="{{ route('employees.create') }}" title="Add New Employee" icon="fa-plus" />
                </div>

                <div class="details h-full z-40">
                    <div class="container-parent h-full">
                        <div class="card_container px-3 h-full flex flex-col">
                            <div id="table-head"class="grid grid-cols-6 bg-[var(--h-bg-color)] rounded-lg font-medium py-2 mt-4">
                                <div onclick="sortByThis(this)" class="cursor-pointer text-left pl-5">Employee Name</div>
                                <div onclick="sortByThis(this)" class="cursor-pointer text-left pl-5">Urdu Title</div>
                                <div onclick="sortByThis(this)" class="cursor-pointer text-left pl-5">Category</div>
                                <div onclick="sortByThis(this)" class="cursor-pointer text-center">Type</div>
                                <div onclick="sortByThis(this)" class="cursor-pointer text-center">Balance</div>
                                <div onclick="sortByThis(this)" class="cursor-pointer text-right pr-5">Status</div>
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
<script defer src="{{ asset('js/pages/employees-index.js') }}"></script>
<script>
        window.__employeesIndex = {
            currentUserRole: @json(Auth::user()->role),
            authLayout: @json($authLayout),
            updateStatusUrl: @json(route('update-employee-status')),
            companyData: @json($client_company),
        };
    </script>
@endpush
