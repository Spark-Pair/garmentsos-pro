(function () {
    function setAuthLayout(data) {
        if (data?.authLayout) {
            window.authLayout = data.authLayout;
        }
        if (data?.companyData) {
            window.companyData = data.companyData;
        }
    }

    window.createRow = function createRow(data) {
        return `
                <div id="${data.id}" oncontextmenu='${htmlAttr(data.oncontextmenu || "")}' onclick='${htmlAttr(data.onclick || "")}'
                    class="item row relative group grid text- grid-cols-3 border-b border-[var(--h-bg-color)] items-center py-2 cursor-pointer hover:bg-[var(--h-secondary-bg-color)] transition-all fade-in ease-in-out"
                    data-json='${jsonAttr(data)}'>

                    <span class="text-center">${data.name}</span>
                    <span class="text-center">${data.details["Cargo Name"]}</span>
                    <span class="text-center">${data.details['Date']}</span>
                </div>
            `;
    };

    window.printCargoList = function printCargoList(elem) {
        closeAllDropdowns();

        const openedFromContextMenu = elem.parentElement.tagName.toLowerCase() === 'li';
        if (openedFromContextMenu) {
            elem.parentElement.parentElement.querySelector('#show-details').click();
            document.getElementById('modalForm')?.parentElement.classList.add('hidden');
        }

        const preview = document.getElementById('preview-container');
        if (!preview) return;

        window.DocumentPrint.printPreview({
            title: 'Print Cargo List',
            preview,
            delay: 1000,
            beforePrint: printDocument => {
                const listCopy = printDocument.querySelector('#preview-container .preview-copy');
                if (listCopy) listCopy.textContent = 'Cargo List Copy: Office';
            },
            afterPrint: () => {
                if (openedFromContextMenu) document.getElementById('modalForm')?.parentElement.remove();
            },
        });
    };

    window.generateContextMenu = function generateContextMenu(e) {
        e.preventDefault();
        const item = e.target.closest('.item');
        if (!item) return;
        const data = JSON.parse(item.dataset.json);

        const contextMenuData = {
            item: item,
            data: data,
            x: e.pageX,
            y: e.pageY,
            actions: [
                { id: 'edit-cargo', text: 'Edit', link: `/cargos/${data.id}/edit` },
                { id: 'print', text: 'Print Cargo List', onclick: 'printCargoList(this)' },
            ],
        };

        if (isDeveloperUser()) {
            contextMenuData.actions.push({
                id: 'delete-cargo',
                text: 'Delete',
                onclick: `submitResourceDelete('/cargos/${data.id}')`,
            });
        }

        createContextMenu(contextMenuData);
    };

    window.generateModal = function generateModal(item) {
        const data = JSON.parse(item.dataset.json);

        const modalData = {
            id: 'modalForm',
            preview: { type: 'cargo_list', data: data.data, document: 'Cargo List' },
            bottomActions: [
                { id: 'edit-cargo', text: 'Edit', link: `/cargos/${data.id}/edit` },
                { id: 'print', text: 'Print Cargo List', onclick: 'printCargoList(this)' },
            ],
        };

        if (isDeveloperUser()) {
            modalData.bottomActions.push({
                id: 'delete-cargo',
                text: 'Delete',
                onclick: `submitResourceDelete('/cargos/${data.id}')`,
            });
        }

        createModal(modalData);
    };

    function initCargosIndex(data) {
        setAuthLayout(data);
    }

    window.initCargosIndex = initCargosIndex;

    function boot() {
        if (window.__cargosIndex) {
            initCargosIndex(window.__cargosIndex);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
