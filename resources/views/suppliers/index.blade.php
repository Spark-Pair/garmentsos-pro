@extends('app')
@section('title', 'Show Suppliers | ' . $client_company->name)
@section('content')
    @php
        $searchFields = [
            "Supplier Name" => [
                "id" => "supplier_name",
                "type" => "text",
                "placeholder" => "Enter supplier name",
                "dataFilterPath" => "name",
            ],
            "Urdu Title" => [
                "id" => "urdu_title",
                "type" => "text",
                "placeholder" => "Enter urdu title",
                "dataFilterPath" => "details.Urdu Title",
            ],
            "Username" => [
                "id" => "username",
                "type" => "text",
                "placeholder" => "Enter username",
                "dataFilterPath" => "user.username",
            ],
            "Phone" => [
                "id" => "phone",
                "type" => "text",
                "placeholder" => "Enter phone number",
                "dataFilterPath" => "details.Phone",
            ],
            'Category' => [
                'id' => 'category',
                'type' => 'select',
                'options' => $categories_options,
                'dataFilterPath' => 'categories[].short_title',
            ],
            'Status' => [
                'id' => 'status',
                'type' => 'select',
                'options' => [
                    'active' => ['text' => 'Active'],
                    'in_active' => ['text' => 'In Active'],
                ],
                'dataFilterPath' => 'user.status',
            ]
        ];
    @endphp
    <div>
        <div class="w-[80%] mx-auto">
            <x-search-header heading="Suppliers" :search_fields=$searchFields/>
        </div>

        <section class="text-center mx-auto ">
            <div
                class="show-box mx-auto w-[80%] h-[70vh] bg-[var(--secondary-bg-color)] border border-[var(--glass-border-color)]/20 rounded-xl shadow pt-8.5 relative">
                <x-form-title-bar printBtn title="Show Suppliers" changeLayoutBtn layout="{{ $authLayout }}" resetSortBtn />

                @if (count($suppliers) > 0)
                    <div class="absolute bottom-0 right-0 flex items-center justify-between gap-2 w-fll z-50 p-3 w-full pointer-events-none">
                        <x-section-navigation-button direction="right" id="info" icon="fa-info" />
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
                @else
                    <div class="no-records-message w-full h-full flex flex-col items-center justify-center gap-2">
                        <h1 class="text-md text-[var(--secondary-text)] capitalize">No Suppliers yet</h1>
                        <a href="{{ route('suppliers.create') }}"
                        class="text-sm bg-[var(--primary-color)] text-[var(--text-color)] px-4 py-2 rounded-md hover:bg-[var(--h-primary-color)] hover:scale-105 hover:mb-2 transition-all duration-300 ease-in-out font-semibold">Add
                            New</a>
                    </div>
                @endif
            </div>
        </section>
    </div>

    <script>
        let currentUserRole = '{{ Auth::user()->role }}';
        let authLayout = '{{ $authLayout }}';

        function createRow(data) {
            return `
            <div id="${data.id}" oncontextmenu='${data.oncontextmenu || ""}' onclick='${data.onclick || ""}'
                class="item row relative group grid text- grid-cols-5 border-b border-[var(--h-bg-color)] items-center py-2 cursor-pointer hover:bg-[var(--h-secondary-bg-color)] transition-all fade-in ease-in-out"
                data-json='${JSON.stringify(data)}'>

                <span class="text-left pl-5 capitalize">${data.name}</span>
                <span class="text-left pl-5">${data.details["Urdu Title"]}</span>
                <span class="text-center capitalize">${data.details["Phone"]}</span>
                <span class="text-right">${data.details["Balance"]}</span>
                <span class="text-right pr-5 capitalize ${data.user.status === 'active' ? 'text-[var(--border-success)]' : 'text-[var(--border-error)]'}">
                    ${data.user.status}
                </span>
            </div>`;
        }

        // const fetchedData = [];
        // let allDataArray = fetchedData.map(item => {
        //     return {
        //         id: item.id,
        //         image: item.user.profile_picture === 'default_avatar.png'
        //             ? '/images/default_avatar.png'
        //             : `/storage/uploads/images/${item.user.profile_picture}`,
        //         name: item.supplier_name,
        //         details: {
        //             'Urdu Title': item.urdu_title,
        //             'Phone': item.phone_number,
        //             'Balance': formatNumbersWithDigits(item.balance, 1, 1),
        //         },
        //         user: {
        //             id: item.user.id,
        //             username: item.user.username,
        //             status: item.user.status,
        //         },
        //         oncontextmenu: "generateContextMenu(event)",
        //         onclick: "generateModal(this)",
        //         date: item.date,
        //         data: item,
        //         categories: item.categories?.map(cat => ({
        //             ...cat,
        //             short_title: cat.short_title?.toLowerCase() || ""
        //         })) || [],
        //         profile: true,
        //         visible: true,
        //     };
        // });

        // const activeSuppliers = allDataArray.filter(supplier => supplier.user.status === 'active');

        // let infoDom = document.getElementById('info').querySelector('span');
        // infoDom.textContent = `Total Supplier: ${allDataArray.length} | Active: ${activeSuppliers.length}`;

        function generateContextMenu(e) {
            let item = e.target.closest('.item');
            let data = JSON.parse(item.dataset.json);

            let contextMenuData = {
                item: item,
                data: data,
                x: e.pageX,
                y: e.pageY,
                action: "{{ route('update-user-status') }}",
                actions: [
                    {id: 'edit', text: 'Edit Supplier'},
                    {id: 'manage-category', text: 'Manage Category', onclick: `generateManageCategoryModal(${JSON.stringify(data)})`},
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
                    'Username': data.user.username,
                    'Phone Number': data.details['Phone'],
                    'Balance': data.details['Balance'],
                },
                chips: data.categories,
                user: data.user,
                profile: true,
                bottomActions: [
                    {id: 'edit', text: 'Edit Supplier', dataId: data.id},
                    {id: 'manage-category', text: 'Manage Category', onclick: `generateManageCategoryModal(${JSON.stringify(data)})`},
                ],
            }

            if (currentUserRole == 'admin' || currentUserRole == 'developer' || currentUserRole == 'owner') {
                modalData.bottomActions.push(
                    {id: 'reset-password', text: 'Reset Password', onclick: `generateResetPasswordModel(${JSON.stringify(data.user)})`},
                );
            }

            createModal(modalData);
        }

        function trackCategoryState(elem) {
            let addCategoryBtn = elem.parentElement.querySelector('button');

            if (elem.value != '') {
                addCategoryBtn.disabled = false;
            } else {
                addCategoryBtn.disabled = true;
            }

            const chipsContainer = elem.parentElement.closest('form').querySelector('#chipsContainer');
            addCategoryBtn.addEventListener('click', () => {
                let selectedCategory = elem.options[elem.selectedIndex];
                const dataIds = Array.from(chipsContainer.children).map(child => child.getAttribute('data-id'));

                if (dataIds.includes(elem.value)) {
                    chipsContainer.querySelector('.bg-\\[var\\(--bg-error\\)\\]')?.classList.remove('bg-[var(--bg-error)]');
                    let existingChip = Array.from(chipsContainer.children).find(chip =>
                        chip.getAttribute('data-id') === elem.value
                    );

                    if (existingChip) {
                        messageBox.innerHTML = `
                            <x-alert type="error" :messages="'This category is already exists.'" />
                        `;
                        messageBoxAnimation();
                        existingChip.classList.add('bg-[var(--bg-error)]', 'transition', 'duration-300');
                        setTimeout(() => {
                            existingChip.classList.remove('bg-[var(--bg-error)]');
                        }, 5000);
                        elem.value = '';
                        addCategoryBtn.disabled = true;
                        elem.focus();
                    }

                    return;
                }

                if (elem.value != '') {
                    chipsContainer.querySelector('.bg-\\[var\\(--bg-error\\)\\]')?.classList.remove('bg-[var(--bg-error)]');
                    chipsContainer.innerHTML += `
                        <div data-id="${elem.value}" class="chip border border-gray-600 text-xs rounded-xl py-2 px-4 inline-flex items-center gap-2 transition-all 0.3s ease-in-out">
                            <div class="text tracking-wide">${selectedCategory.textContent}</div>
                            <button class="delete cursor-pointer" type="button">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"
                                class="size-3 stroke-gray-400">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    `;

                    elem.value = '';
                    addCategoryBtn.disabled = true;
                    elem.focus();

                    const allChips = chipsContainer.querySelectorAll('.chip');
                    allChips.forEach((chip) => {
                        let deleteBtn = chip.querySelector('.delete')
                        if (deleteBtn.classList.contains('hidden')) {
                            deleteBtn.classList.remove('hidden')
                        }
                    })
                }
            })
        }

        function trackAddBtnState(elem, data) {
            const formDom = elem.closest('form');
            const chipsContainer = formDom.querySelector('#chipsContainer');
            const dataIds = Array.from(chipsContainer.children).map(child => child.getAttribute('data-id'));

            let categoriesInp = formDom.querySelector('input[name="categories_array"]');
            categoriesInp.value = JSON.stringify(dataIds);
            formDom.submit();
        }

        function generateManageCategoryModal(item) {
            let modalData = {
                id: 'manageCategoryModalForm',
                method: "POST",
                action: "{{ route('update-supplier-category') }}",
                name: 'Manage Category',
                chips: item.categories,
                editableChips: true,
                fields: [
                    {
                        category: 'input',
                        label: 'Supplier Name',
                        value: item.name,
                        disabled: true,
                    },
                    {
                        category: 'input',
                        type: 'hidden',
                        name: 'supplier_id',
                        value: item.id,
                    },
                    {
                        category: 'input',
                        type: 'hidden',
                        name: 'categories_array',
                    },
                    {
                        category: 'select',
                        label: 'Category',
                        id: 'category',
                        options: [@json($categories_options)],
                        showDefault: true,
                        class: 'grow',
                        onchange: 'trackCategoryState(this)',
                        btnId: 'addCategoryBtn',
                    }
                ],
                fieldsGridCount: '2',
                bottomActions: [
                    {id: 'add', text: 'Update', onclick: 'trackAddBtnState(this)'},
                ],
            }

            createModal(modalData);

            const chipsContainer = document.getElementById('manageCategoryModalForm').querySelector('#chipsContainer');

            chipsContainer.addEventListener('click', (e) => {

                const deleteButton = e.target.closest('.delete');

                if (deleteButton) {
                    const clickedChip = deleteButton.parentElement;
                    clickedChipId = clickedChip.dataset.id;

                    clickedChip.classList.add('fade-out');

                    setTimeout(() => {
                        clickedChip.remove();

                        // Update allChips after deletion
                        const updatedChips = chipsContainer.querySelectorAll('.chip');

                        // Agar sirf 1 chip bachi hai toh uska delete button hide karo
                        if (updatedChips.length === 1) {
                            const lastChipDeleteBtn = updatedChips[0].querySelector('.delete');
                            if (lastChipDeleteBtn) {
                                lastChipDeleteBtn.classList.add('hidden');
                            }
                        }
                    }, 300);
                }
            })
            return;
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
