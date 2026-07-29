(() => {
function initCustomersIndex() {
    const config = window.__customersIndex || {};
    const currentUserRole = config.currentUserRole;
    const authLayout = config.authLayout;
    const updateUserStatusUrl = config.updateUserStatusUrl;
    const resetPasswordUrl = config.resetPasswordUrl;

    window.createRow = function(data) {
        return `
        <div id="${data.id}" oncontextmenu='${htmlAttr(data.oncontextmenu || "")}' onclick='${htmlAttr(data.onclick || "")}'
            class="item row relative group grid text- grid-cols-8 border-b border-[var(--h-bg-color)] items-center py-2 cursor-pointer hover:bg-[var(--h-secondary-bg-color)] transition-all fade-in ease-in-out"
            data-json='${jsonAttr(data)}'>

            <span class="text-left pl-5 col-span-2">${data.name}</span>
            <span class="text-left pl-5">${data.details["Urdu Title"]}</span>
            <span class="text-center capitalize">${data.details["Category"]}</span>
            <span class="text-center capitalize">${data.city}</span>
            <span class="text-center">${data.phone_number}</span>
            <span class="text-right">${formatMoney(data.details["Balance"])}</span>
            <span class="text-right pr-5 capitalize ${data.user.status === 'active' ? 'text-[var(--border-success)]' : 'text-[var(--border-error)]'}">
                ${data.user.status}
            </span>
        </div>`;
    }

    window.generateContextMenu = function(e) {
        e.preventDefault();
        let item = e.target.closest('.item');
        let data = JSON.parse(item.dataset.json);

        let contextMenuData = {
            item: item,
            data: data,
            x: e.pageX,
            y: e.pageY,
            action: updateUserStatusUrl,
            actions: [
                {id: 'edit', text: 'Edit'}
            ],
        };

        if (isDeveloperUser()) {
            contextMenuData.actions.push({
                id: 'delete-customer',
                text: 'Delete',
                onclick: `submitResourceDelete('/customers/${data.id}')`,
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
                {id: 'edit', text: 'Edit', dataId: data.id}
            ],
        }

        if (isDeveloperUser()) {
            modalData.bottomActions.push({
                id: 'delete-customer',
                text: 'Delete',
                onclick: `submitResourceDelete('/customers/${data.id}')`,
            });
        }

        if (currentUserRole == 'admin' || currentUserRole == 'developer' || currentUserRole == 'owner') {
            modalData.bottomActions.push(
                {id: 'reset-password', text: 'Reset Password', onclick: `generateResetPasswordModel(${JSON.stringify(data.user)})`},
            );
        }

        createModal(modalData);
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

window.initCustomersIndex = initCustomersIndex;

function boot() {
    if (window.__customersIndex) initCustomersIndex();
}

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", boot);
} else {
    boot();
}
})();
