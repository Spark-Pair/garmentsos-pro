(() => {
    function initSetupsIndex() {
        const config = window.__setupsIndex || {};
        window.authLayout = config.authLayout || "table";
        const canUpdate = Boolean(config.canUpdate);
        const canDelete = Boolean(config.canDelete);

        window.createRow = function createRow(data) {
            const shortTitle = data.short_title
                ? `<div class="flex items-center justify-center gap-2">
                        <span class="uppercase font-semibold">${data.short_title}</span>
                        <span class="text-[10px] uppercase tracking-wide px-2 py-0.5 rounded-full border border-[var(--border-warning)] text-[var(--text-warning)] bg-[var(--bg-warning)]">
                            Global Key
                        </span>
                   </div>`
                : `<span>-</span>`;

            return `
            <div id="${data.id}" oncontextmenu="generateContextMenu(event)" onclick="generateModal(this)"
                class="item row relative group grid grid-cols-3 text-center border-b border-[var(--h-bg-color)] items-center py-2 cursor-pointer hover:bg-[var(--h-secondary-bg-color)] transition-all fade-in ease-in-out"
                data-json='${jsonAttr(data)}'>

                <span class="capitalize">${data.type.replace(/_/g, " ")}</span>
                <span class="capitalize">${data.title.replace(/_/g, " ")}</span>
                ${shortTitle}
            </div>`;
        };

        window.generateContextMenu = function generateContextMenu(e) {
            e.preventDefault();
            const item = e.target.closest('.item');
            if (!item) return;

            const data = JSON.parse(item.dataset.json);
            createContextMenu({
                item,
                data,
                x: e.pageX,
                y: e.pageY,
                actions: setupActions(data),
            });
        };

        window.generateModal = function generateModal(item) {
            const data = JSON.parse(item.dataset.json);
            createModal({
                id: 'modalForm',
                name: data.title,
                details: {
                    Type: String(data.type || '-').replace(/_/g, ' '),
                    Title: data.title || '-',
                    'Short Title / Global Key': data.short_title || '-',
                },
                bottomActions: setupActions(data),
            });
        };

        function setupActions(data) {
            const actions = [];

            if (canUpdate) {
                actions.push({
                    id: 'edit-setup',
                    text: 'Edit',
                    link: `/setups/${data.id}/edit`,
                });
            }

            if (canDelete) {
                actions.push({
                    id: 'delete-setup',
                    text: 'Delete',
                    onclick: `submitResourceDelete('/setups/${data.id}')`,
                });
            }

            return actions;
        }
    }

    window.initSetupsIndex = initSetupsIndex;

    function boot() {
        if (window.__setupsIndex) {
            initSetupsIndex();
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
