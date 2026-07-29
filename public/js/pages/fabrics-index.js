(function () {
    window.createRow = function createRow(data) {
        return `
            <div id="${data.id}" oncontextmenu='${htmlAttr(data.oncontextmenu || "")}' onclick='${htmlAttr(data.onclick || "")}'
                class="item row relative group flex border-b border-[var(--h-bg-color)] items-center py-2 cursor-pointer hover:bg-[var(--h-secondary-bg-color)] transition-all fade-in ease-in-out"
                data-json='${jsonAttr(data)}'>

                <span class="text-center w-[10%]">${data.date}</span>
                <span class="text-center w-[15%] capitalize">${data.supplier_name ?? data.employee_name}</span>
                <span class="text-center w-[10%]">${data.type ?? "-"}</span>
                <span class="text-center w-[10%] capitalize">${data.fabric ?? "-"}</span>
                <span class="text-center w-[10%] capitalize">${data.color ?? "-"}</span>
                <span class="text-center w-[10%] capitalize">${data.unit ?? "-"}</span>
                <span class="text-center w-[10%]">${data.quantity ?? "-"}</span>
                <span class="text-center w-[20%]">${data.tag ?? "-"}</span>
                <span class="text-center w-[10%] capitalize">${data.remarks ?? "-"}</span>
            </div>`;
    };

    window.generateContextMenu = function generateContextMenu(e) {
        e.preventDefault();
        const item = e.target.closest('.item');
        if (!item) return;
        const data = JSON.parse(item.dataset.json);

        const actions = [];
        const resource = fabricResourceFor(data);
        if (isDeveloperUser() && resource) {
            actions.push({
                id: 'edit-fabric',
                text: 'Edit',
                link: resource.edit,
            });
            actions.push({
                id: 'delete-fabric',
                text: 'Delete',
                onclick: `submitResourceDelete('${resource.destroy}')`,
            });
        }

        createContextMenu({
            item,
            data,
            x: e.pageX,
            y: e.pageY,
            actions,
        });
    };

    window.generateModal = function generateModal(item) {
        const data = JSON.parse(item.dataset.json);

        const modalData = {
            id: 'modalForm',
            name: data.fabric ?? data.tag ?? 'Fabric',
            details: {
                Date: data.date,
                'Supplier / Worker': data.supplier_name ?? data.employee_name ?? '-',
                Type: data.type ?? '-',
                Fabric: data.fabric ?? '-',
                Color: data.color ?? '-',
                Unit: data.unit ?? '-',
                Quantity: data.quantity ?? '-',
                Tag: data.tag ?? '-',
                Remarks: data.remarks ?? '-',
            },
            bottomActions: [],
        };

        const resource = fabricResourceFor(data);
        if (isDeveloperUser() && resource) {
            modalData.bottomActions.push({
                id: 'edit-fabric',
                text: 'Edit',
                link: resource.edit,
            });
            modalData.bottomActions.push({
                id: 'delete-fabric',
                text: 'Delete',
                onclick: `submitResourceDelete('${resource.destroy}')`,
            });
        }

        createModal(modalData);
    };

    function initFabricsIndex(data) {
        if (data?.authLayout) {
            window.authLayout = data.authLayout;
        }
        if (data?.currentUserRole) {
            window.__currentUserRole = data.currentUserRole;
        }

        const listContainer = document.querySelector('.search_container');
        if (listContainer) {
            listContainer.addEventListener('click', (e) => {
                const row = e.target.closest('.item');
                if (!row || !row.dataset.json) return;
                window.generateModal(row);
            });

            listContainer.addEventListener('contextmenu', (e) => {
                const row = e.target.closest('.item');
                if (!row || !row.dataset.json) return;
                e.preventDefault();
                window.generateContextMenu(e);
            });
        }
    }

    window.initFabricsIndex = initFabricsIndex;

    function fabricResourceFor(data) {
        const id = Number(data.id);
        if (!Number.isInteger(id)) {
            return null;
        }

        if (data.type === 'Received') {
            return {
                edit: `/fabrics/${id}/edit`,
                destroy: `/fabrics/${id}`,
            };
        }

        if (data.type === 'Issued') {
            return {
                edit: `/fabrics/issued/${id}/edit`,
                destroy: `/fabrics/issued/${id}`,
            };
        }

        if (data.type === 'Returned') {
            return {
                edit: `/fabrics/returned/${id}/edit`,
                destroy: `/fabrics/returned/${id}`,
            };
        }

        return null;
    }

    function boot() {
        if (window.__fabricsIndex) {
            initFabricsIndex(window.__fabricsIndex);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
