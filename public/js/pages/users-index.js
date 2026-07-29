(() => {
    function initUsersIndex(config) {
        const currentUserRole = config?.currentUserRole;
        const currentUserId = Number(config?.currentUserId || 0);
        const authLayout = config?.authLayout;
        const updateUserStatusUrl = config?.updateUserStatusUrl;
        const resetPasswordUrl = config?.resetPasswordUrl;
        const csrfToken = config?.csrfToken;

        if (authLayout) {
            window.authLayout = authLayout;
        }

        window.createRow = function(data) {
            return `
            <div id="${data.id}" oncontextmenu='${htmlAttr(data.oncontextmenu || "")}' onclick='${htmlAttr(data.onclick || "")}'
                class="item row relative group grid grid-cols-5 border-b border-[var(--h-bg-color)] items-center py-2 cursor-pointer hover:bg-[var(--h-secondary-bg-color)] transition-all fade-in ease-in-out"
                data-json='${jsonAttr(data)}'>

                <span class="text-left pl-5 col-span-2">${data.name}</span>
                <span class="text-left pl-5">${data.username}</span>
                <span class="text-center capitalize">${data.role}</span>
                <span class="text-right pr-5 capitalize ${data.status === 'active' ? 'text-[var(--border-success)]' : 'text-[var(--border-error)]'}">${data.status}</span>
            </div>`;
        }

        function canChangeUserStatus(data) {
            const targetRole = data?.details?.Role;
            const targetUserId = Number(data?.id || 0);

            if (!targetRole || !targetUserId) return false;

            // Nobody can deactivate self
            if (targetUserId === currentUserId) return false;

            if (currentUserRole === 'admin') {
                return targetRole !== 'owner' && targetRole !== 'developer';
            }

            if (currentUserRole === 'owner') {
                return targetRole !== 'developer';
            }

            // Preserve existing behavior for other roles
            return currentUserRole !== targetRole;
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
            };

            if (canChangeUserStatus(data)) {
                contextMenuData.forceStatusBtn = true;
            }

            if ((currentUserRole == 'admin' || currentUserRole == 'developer' || currentUserRole == 'owner') && currentUserRole != data.details['Role']) {
                contextMenuData.actions = [
                    {id: 'reset-password', text: 'Reset Password', onclick: `generateResetPasswordModel(${JSON.stringify(data)})`},
                ];
            }

            if (currentUserRole === 'developer') {
                contextMenuData.actions = contextMenuData.actions || [];
                contextMenuData.actions.unshift({ id: 'edit', text: 'Edit' });
                if (Number(data.id) !== currentUserId) {
                    contextMenuData.actions.push({ id: 'delete-user', text: 'Delete', onclick: `submitUserDelete(${data.id})` });
                }
            }

            createContextMenu(contextMenuData);
        }

        window.generateModal = function(item) {
            let data = JSON.parse(item.dataset.json);

            let modalData = {
                id: 'modalForm',
                uId: data.id,
                status: data.status,
                method: "POST",
                action: updateUserStatusUrl,
                image: data.image,
                name: data.name,
                details: {
                    'Username': data.details['Username'],
                    'Role': data.details['Role'],
                },
                profile: true,
            }

            if (canChangeUserStatus(data)) {
                modalData.forceStatusBtn = true;
            }

            if ((currentUserRole == 'admin' || currentUserRole == 'developer' || currentUserRole == 'owner') && currentUserRole != data.details['Role']) {
                modalData.bottomActions = [
                    {id: 'reset-password', text: 'Reset Password', onclick: `generateResetPasswordModel(${JSON.stringify(data)})`},
                ];
            }

            if (currentUserRole === 'developer') {
                modalData.bottomActions = modalData.bottomActions || [];
                modalData.bottomActions.unshift({ id: 'edit', text: 'Edit', dataId: data.id });
                if (Number(data.id) !== currentUserId) {
                    modalData.bottomActions.push({ id: 'delete-user', text: 'Delete', onclick: `submitUserDelete(${data.id})` });
                }
            }

            createModal(modalData);
        }

        window.submitUserDelete = function submitUserDelete(id) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = `/users/${id}`;
            form.innerHTML = `
                <input type="hidden" name="_token" value="${csrfToken || document.querySelector('meta[name="csrf-token"]')?.content || ''}">
                <input type="hidden" name="_method" value="DELETE">
            `;
            document.body.appendChild(form);
            form.submit();
        };

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
                        value: data.details['Username'],
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

    window.initUsersIndex = initUsersIndex;

    function boot() {
        if (window.__usersIndex) {
            initUsersIndex(window.__usersIndex);
        }
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", boot);
    } else {
        boot();
    }
})();
