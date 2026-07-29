(() => {
function initSuppliersIndex() {
    const config = window.__suppliersIndex || {};
    const currentUserRole = config.currentUserRole;
    const authLayout = config.authLayout;
    const updateUserStatusUrl = config.updateUserStatusUrl;
    const updateSupplierCategoryUrl = config.updateSupplierCategoryUrl;
    const resetPasswordUrl = config.resetPasswordUrl;
    const categoriesOptions = config.categoriesOptions || {};

    window.createRow = function(data) {
        return `
        <div id="${data.id}" oncontextmenu='${htmlAttr(data.oncontextmenu || "")}' onclick='${htmlAttr(data.onclick || "")}'
            class="item row relative group grid text- grid-cols-5 border-b border-[var(--h-bg-color)] items-center py-2 cursor-pointer hover:bg-[var(--h-secondary-bg-color)] transition-all fade-in ease-in-out"
            data-json='${jsonAttr(data)}'>

            <span class="text-left pl-5 capitalize">${data.name}</span>
            <span class="text-left pl-5">${data.details["Urdu Title"]}</span>
            <span class="text-center capitalize">${data.details["Phone"]}</span>
            <span class="text-right">${data.details["Balance"]}</span>
            <span class="text-right pr-5 capitalize ${data.user.status === 'active' ? 'text-[var(--border-success)]' : 'text-[var(--border-error)]'}">
                ${data.user.status}
            </span>
        </div>`;
    }

    window.generateContextMenu = function(e) {
        let item = e.target.closest('.item');
        let data = JSON.parse(item.dataset.json);

        let contextMenuData = {
            item: item,
            data: data,
            x: e.pageX,
            y: e.pageY,
            action: updateUserStatusUrl,
            actions: [
                {id: 'edit', text: 'Edit'},
                {id: 'manage-category', text: 'Manage Category', onclick: `generateManageCategoryModal(${JSON.stringify(data)})`},
            ],
        };

        if (isDeveloperUser()) {
            contextMenuData.actions.push({
                id: 'delete-supplier',
                text: 'Delete',
                onclick: `submitResourceDelete('/suppliers/${data.id}')`,
            });
        }

        if ((currentUserRole == 'admin' || currentUserRole == 'developer' || currentUserRole == 'owner') && currentUserRole != data.details['Role']) {
            contextMenuData.actions.push(
                {id: 'reset-password', text: 'Reset Password', onclick: `generateResetPasswordModel(${JSON.stringify(data.user)})`},
            );
        }

        createContextMenu(contextMenuData);
    }

    window.generateModal = function(item) {
        let data = JSON.parse(item.dataset.json);

        let modalData = {
            id: 'modalForm',
            method: "POST",
            action: updateUserStatusUrl,
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
                {id: 'edit', text: 'Edit', dataId: data.id},
                {id: 'manage-category', text: 'Manage Category', onclick: `generateManageCategoryModal(${JSON.stringify(data)})`},
            ],
        }

        if (isDeveloperUser()) {
            modalData.bottomActions.push({
                id: 'delete-supplier',
                text: 'Delete',
                onclick: `submitResourceDelete('/suppliers/${data.id}')`,
            });
        }

        if (currentUserRole == 'admin' || currentUserRole == 'developer' || currentUserRole == 'owner') {
            modalData.bottomActions.push(
                {id: 'reset-password', text: 'Reset Password', onclick: `generateResetPasswordModel(${JSON.stringify(data.user)})`},
            );
        }

        createModal(modalData);
    }

    window.trackCategoryState = function(elem) {
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
                    if (typeof showMessageBox === 'function') {
                        showMessageBox('error', 'This category is already exists.');
                    }
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

    window.trackAddBtnState = function(elem, data) {
        const formDom = elem.closest('form');
        const chipsContainer = formDom.querySelector('#chipsContainer');
        const dataIds = Array.from(chipsContainer.children).map(child => child.getAttribute('data-id'));

        let categoriesInp = formDom.querySelector('input[name="categories_array"]');
        categoriesInp.value = JSON.stringify(dataIds);
        formDom.submit();
    }

    window.generateManageCategoryModal = function(item) {
        let modalData = {
            id: 'manageCategoryModalForm',
            method: "POST",
            action: updateSupplierCategoryUrl,
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
                    options: [categoriesOptions],
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

                    const updatedChips = chipsContainer.querySelectorAll('.chip');

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

    window.generateResetPasswordModel = function(data) {
        let modalData = {
            id: 'resetPasswordModalForm',
            class: 'h-auto',
            method: 'POST',
            action: resetPasswordUrl,
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
}

window.initSuppliersIndex = initSuppliersIndex;

function boot() {
    if (window.__suppliersIndex) initSuppliersIndex();
}

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", boot);
} else {
    boot();
}
})();
