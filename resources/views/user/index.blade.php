@extends('app')
@section('title', 'Show Users | ' . $client_company->name)
@section('content')
    @php
        $searchFields = [
            "Name" => [
                "id" => "name",
                "type" => "text",
                "placeholder" => "Enter name",
                "dataFilterPath" => "name",
            ],
            "Username" => [
                "id" => "username",
                "type" => "text",
                "placeholder" => "Enter username",
                "dataFilterPath" => "details.Username",
            ],
            'Role' => [
                'id' => 'role',
                'type' => 'select',
                'options' => [
                    'owner' => ['text' => 'Owner'],
                    'admin' => ['text' => 'Admin'],
                    'accountant' => ['text' => 'Accountant'],
                    'store_keeper' => ['text' => 'Store Keeper '],
                    'guest' => ['text' => 'Guest'],
                ],
                'dataFilterPath' => 'details.Role',
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
    <!-- Main Content -->
    <div>

        <div class="w-[80%] mx-auto">
            <x-search-header heading="Users" :search_fields=$searchFields/>
        </div>

        <section class="text-center mx-auto ">
            <div
                class="show-box mx-auto w-[80%] h-[70vh] bg-[var(--secondary-bg-color)] border border-[var(--glass-border-color)]/20 rounded-xl shadow pt-8.5 relative">
                <x-form-title-bar title="Show Users" />

                <div class="absolute bottom-0 right-0 flex items-center justify-between gap-2 w-fll z-50 p-3 w-full pointer-events-none">
                    <x-section-navigation-button link="{{ route('users.create') }}" title="Add New User" icon="fa-plus" />
                </div>

                <div class="details h-full z-40">
                    <div class="container-parent h-full">
                        <div class="card_container px-3 h-full flex flex-col">
                            <div id="table-head" class="grid grid-cols-5 bg-[var(--h-bg-color)] rounded-lg font-medium py-2 hidden mt-4">
                                <div class="cursor-pointer text-left pl-5 col-span-2" onclick="sortByThis(this)">Name</div>
                                <div class="cursor-pointer text-left pl-5" onclick="sortByThis(this)">Username</div>
                                <div class="cursor-pointer text-center" onclick="sortByThis(this)">Role</div>
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
<script defer src="{{ asset('js/pages/users-index.js') }}"></script>
<script>
        window.__usersIndex = {
            currentUserRole: @json(Auth::user()->role),
            currentUserId: @json(Auth::id()),
            authLayout: @json($authLayout),
            updateUserStatusUrl: @json(route('update-user-status')),
            resetPasswordUrl: @json(route('users.reset-password')),
            csrfToken: @json(csrf_token()),
        };
    </script>
@endpush
