@extends('app')
@section('title', 'Show Customers | ' . $client_company->name)
@section('content')
    @php
        $searchFields = [
            'Customer Name' => [
                'id' => 'customer_name',
                'type' => 'text',
                'placeholder' => 'Enter customer name',
                'dataFilterPath' => 'customer_name',
            ],
            'Urdu Title' => [
                'id' => 'urdu_title',
                'type' => 'text',
                'placeholder' => 'Enter urdu title',
                'dataFilterPath' => 'urdu_title',
            ],
            'Username' => [
                'id' => 'username',
                'type' => 'text',
                'placeholder' => 'Enter username',
                'dataFilterPath' => 'username',
            ],
            'Phone' => [
                'id' => 'phone_number',
                'type' => 'text',
                'placeholder' => 'Enter phone number',
                'dataFilterPath' => 'phone_number',
            ],
            'Category' => [
                'id' => 'category',
                'type' => 'select',
                'options' => [
                    'cash' => ['text' => 'Cash'],
                    'regular' => ['text' => 'Regular'],
                    'site' => ['text' => 'Site'],
                    'other' => ['text' => 'Others'],
                ],
                'dataFilterPath' => 'category',
            ],
            'City' => [
                'id' => 'city',
                'type' => 'select',
                'options' => $cities_options,
                'dataFilterPath' => 'city',
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
        <div class="w-[80%] mx-auto">
            <x-search-header heading="Customers" :search_fields=$searchFields />
        </div>

        <!-- Main Content -->
        <section class="text-center mx-auto">
            <div
                class="show-box mx-auto w-[80%] h-[70vh] bg-[var(--secondary-bg-color)] border border-[var(--glass-border-color)]/20 rounded-xl shadow pt-8.5 relative">
                <x-form-title-bar printBtn title="Show Customers" changeLayoutBtn layout="{{ $authLayout }}" resetSortBtn />

                <div class="absolute bottom-0 right-0 flex items-center justify-between gap-2 w-fll z-50 p-3 w-full pointer-events-none">
                    <x-section-navigation-button direction="right" id="info" icon="fa-info" />
                    <x-section-navigation-button link="{{ route('customers.create') }}" title="Add New Customer"
                        icon="fa-plus" />
                </div>

                <div class="details h-full z-40">
                    <div class="container-parent h-full">
                        <div class="card_container px-3 h-full flex flex-col">
                            <div id="table-head" class="grid grid-cols-8 bg-[var(--h-bg-color)] rounded-lg font-medium py-2 hidden mt-4">
                                <div class="cursor-pointer text-left pl-5 col-span-2" onclick="sortByThis(this)">Customer</div>
                                <div class="cursor-pointer text-left pl-5" onclick="sortByThis(this)">Urdu Title</div>
                                <div class="cursor-pointer text-center" onclick="sortByThis(this)">Category</div>
                                <div class="cursor-pointer text-center" onclick="sortByThis(this)">City</div>
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

    <script>
        let currentUserRole = '{{ Auth::user()->role }}';
        let authLayout = '{{ $authLayout }}';

        function createRow(data) {
            return `
            <div id="${data.id}" oncontextmenu='${data.oncontextmenu || ""}' onclick='${data.onclick || ""}'
                class="item row relative group grid text- grid-cols-8 border-b border-[var(--h-bg-color)] items-center py-2 cursor-pointer hover:bg-[var(--h-secondary-bg-color)] transition-all fade-in ease-in-out"
                data-json='${JSON.stringify(data)}'>

                <span class="text-left pl-5 col-span-2">${data.name}</span>
                <span class="text-left pl-5">${data.details["Urdu Title"]}</span>
                <span class="text-center capitalize">${data.details["Category"]}</span>
                <span class="text-center capitalize">${data.city}</span>
                <span class="text-center">${data.phone_number}</span>
                <span class="text-right">${Number(data.details["Balance"]).toFixed(1)}</span>
                <span class="text-right pr-5 capitalize ${data.user.status === 'active' ? 'text-[var(--border-success)]' : 'text-[var(--border-error)]'}">
                    ${data.user.status}
                </span>
            </div>`;
        }

        // const fetchedData = [];
        // let allDataArray = fetchedData.map(item => {
        //     return {
        //         id: item.id,
        //         image: item.user.profile_picture == 'default_avatar.png' ? '/images/default_avatar.png' : `/storage/uploads/images/${item.user.profile_picture}`,
        //         name: item.customer_name,
        //         details: {
        //             'Urdu Title': item.urdu_title,
        //             'Category': item.category,
        //             'Balance': formatNumbersWithDigits(item.balance, 1, 1),
        //         },
        //         person_name: item.person_name,
        //         phone_number: item.phone_number,
        //         user: {
        //             id: item.user.id,
        //             username: item.user.username,
        //             status: item.user.status,
        //         },
        //         city: item.city.title,
        //         oncontextmenu: "generateContextMenu(event)",
        //         onclick: "generateModal(this)",
        //         date: item.date,
        //         profile: true,
        //         visible: true,
        //     };
        // });

        function generateContextMenu(e) {
            e.preventDefault();
            let item = e.target.closest('.item');
            let data = JSON.parse(item.dataset.json);

            let contextMenuData = {
                item: item,
                data: data,
                x: e.pageX,
                y: e.pageY,
                action: "{{ route('update-user-status') }}",
                actions: [
                    {id: 'edit', text: 'Edit Customer'}
                ],
            };

            if ((currentUserRole == 'admin' || currentUserRole == 'developer' || currentUserRole == 'owner') && currentUserRole != data.details['Role']) {
                contextMenuData.actions.push(
                    {id: 'reset-password', text: 'Reset Password', onclick: `generateResetPasswordModel(${JSON.stringify(data.user)})`},
                );
            }

            createContextMenu(contextMenuData);
        }

        function generateModal(item) {
            let data = JSON.parse(item.dataset.json);

            let modalData = {
                id: 'modalForm',
                method: "POST",
                action: "{{ route('update-user-status') }}",
                image: data.image,
                name: data.name,
                details: {
                    'Urud Title': data.details['Urdu Title'],
                    'Person Name': data.person_name,
                    'Username': data.user.username,
                    'Phone Number': data.phone_number,
                    'Balance': data.details['Balance'],
                    'Category': data.details['Category'],
                    'City': data.city,
                },
                user: data.user,
                profile: true,
                bottomActions: [
                    {id: 'edit', text: 'Edit Customer', dataId: data.id}
                ],
            }

            if (currentUserRole == 'admin' || currentUserRole == 'developer' || currentUserRole == 'owner') {
                modalData.bottomActions.push(
                    {id: 'reset-password', text: 'Reset Password', onclick: `generateResetPasswordModel(${JSON.stringify(data.user)})`},
                );
            }

            createModal(modalData);
        }

        function generateResetPasswordModel(data) {
            let modalData = {
                id: 'resetPasswordModalForm',
                class: 'h-auto',
                method: 'POST',
                action: '{{ route("users.reset-password") }}',
                name: 'Reset Password',
                fields: [
                    {
                        category: 'input',
                        label: 'Username',
                        value: data.username,
                        disabled: true,
                    },
                    {
                        category: 'input',
                        type: 'hidden',
                        name: 'user_id',
                        value: data.id,
                    },
                    {
                        category: 'input',
                        label: 'Password',
                        name: 'password',
                        id: 'password',
                        type: 'password',
                        placeholder: 'Enter new password',
                        data_validate: 'required|min:4|alphanumeric|lowercase',
                        required: true,
                    },
                ],
                fieldsGridCount: '2',
                bottomActions: [
                    {id: 'reset-password-btn', text: 'Reset Password', type: 'submit'}
                ]
            }

            createModal(modalData);
        }
    </script>
@endsection
