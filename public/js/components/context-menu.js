function createContextMenu(data) {
    const statusColor = {
        active: ['[var(--bg-success)]', '[var(--text-success)]', '[var(--border-success)]'],
        transparent: ['transparent', 'transparent', 'transparent'],
        no_Image: ['[var(--bg-warning)]', '[var(--text-warning)]', '[var(--border-warning)]'],
        inactive: ['[var(--bg-error)]', '[var(--text-error)]', '[var(--border-error)]'],
    };

    // Remove old menu if exists
    const old = document.getElementById('context-menu');
    if (old) {
        old.classList.add('fade-out');
        old.addEventListener('animationend', () => {
            old.remove()
        }, { once: true })
    };

    const contextMenu = document.createElement('div');
    contextMenu.id = 'context-menu';
    contextMenu.className = 'context-menu absolute text-sm z-50 fade-in';
    contextMenu.style.top = `${data.y}px`;
    contextMenu.style.left = `${data.x}px`;

    let clutter = `
        <div class="border border-gray-600 w-48 bg-[var(--secondary-bg-color)] text-[var(--text-color)] shadow-lg rounded-xl transition-all duration-300 ease-in-out">
            <ul class="p-2">
    `;

    if (!data.onlyThisActions) {
        clutter += `
            <li>
                <button id="show-details" type="button"
                    class="flex items-center w-full px-4 py-2 text-left hover:bg-[var(--h-bg-color)] rounded-md transition-all duration-300 ease-in-out cursor-pointer">
                    Show Details
                </button>
            </li>
        `;
    }

    const validRecordId = (value) => value !== null
        && typeof value !== 'undefined'
        && String(value).trim() !== ''
        && String(value) !== 'undefined';
    const usableLink = (link) => typeof link === 'string'
        && link.trim() !== ''
        && !/\/(?:undefined|null|NaN)(?:\/|$)/.test(link);

    const editActionHref = (action) => {
        const recordId = action.dataId ?? data.data?.id ?? data.item?.id;
        if (!validRecordId(recordId)) return null;

        const basePath = window.location.pathname.replace(/\/+$/, '');
        return `${basePath}/${encodeURIComponent(recordId)}/edit`;
    };

    if (Array.isArray(data.actions)) {
        data.actions.forEach(action => {
            if (action.link) {
                if (!usableLink(action.link)) return;

                clutter += `
                    <li>
                        <a id="${action.id}-in-context" href="${action.link}"
                            class="flex items-center w-full px-4 py-2 text-left hover:bg-[var(--h-bg-color)] rounded-md transition-all duration-300 ease-in-out cursor-pointer">
                            ${action.text}
                        </a>
                    </li>
                `;
            } else if (action.onclick) {
                clutter += `
                    <li>
                        <button id="${action.id}-in-context" type="button" onclick='${htmlAttr(action.onclick)}'
                            class="flex items-center w-full px-4 py-2 text-left hover:bg-[var(--h-bg-color)] rounded-md transition-all duration-300 ease-in-out cursor-pointer">
                            ${action.text}
                        </button>
                    </li>
                `;
            } else if (action.id.includes('edit')) {
                const href = editActionHref(action);
                if (!href) return;

                clutter += `
                    <li>
                        <a id="${action.id}-in-context" href="${href}"
                            class="flex items-center w-full px-4 py-2 text-left hover:bg-[var(--h-bg-color)] rounded-md transition-all duration-300 ease-in-out cursor-pointer">
                            ${action.text}
                        </a>
                    </li>
                `;
            } else {
                clutter += `
                    <li>
                        <button id="${action.id}-in-context" type="button" onclick='${htmlAttr(action.onclick)}'
                            class="flex items-center w-full px-4 py-2 text-left hover:bg-[var(--h-bg-color)] rounded-md transition-all duration-300 ease-in-out cursor-pointer">
                            ${action.text}
                        </button>
                    </li>
                `;
            }
        });
    }

    if ((data.data.details && data.data.details['Balance'] == 0.0) || data.forceStatusBtn) {
        if (data.data.user?.status || data.data.status) {
            let status = data.data.user?.status ?? data.data.status;
            const [bgColor, textColor, borderColor] = statusColor[status === 'active' ? status = 'in_active' : status = 'active'] || statusColor.inactive;
            
            clutter += `
                <li id="ac_in_context">
                    <form method="POST" action="${data.action}">
                        <input type="hidden" name="_token" value="${document.querySelector('meta[name="csrf-token"]')?.content}">
                        <input type="hidden" name="user_id" value="${data.data.user?.id ?? data.data.uId}">
                        <input type="hidden" name="status" value="${data.data.user?.status ?? data.data.status}">
                        <button type="submit"
                            class="flex w-full items-center text-left px-4 py-2 font-medium rounded-md transition-all duration-300 ease-in-out cursor-pointer capitalize text-${borderColor} hover:bg-${bgColor} hover:text-${textColor}">
                            ${status.replace('_', ' ')}
                        </button>
                    </form>
                </li>
            `;
        }
    }

    clutter += `
            </ul>
        </div>
    `;

    contextMenu.innerHTML = clutter;
    document.body.appendChild(contextMenu);

    contextMenu.querySelector('#show-details')?.addEventListener('click', function () {
        generateModal(data.item);
    });

    // Auto-close on outside click
    const closeMenu = (e) => {
        if (!contextMenu.contains(e.target)) {
            contextMenu.classList.add('fade-out');
            contextMenu.addEventListener('animationend', () => {
                contextMenu.remove();
            }, { once: true })
            document.removeEventListener('mousedown', closeMenu);
            document.removeEventListener('keydown', escClose);
        }
    };

    const escClose = (e) => {
        if (e.key === 'Escape') {
            contextMenu.remove();
            document.removeEventListener('mousedown', closeMenu);
            document.removeEventListener('keydown', escClose);
        }
    };

    document.addEventListener('mousedown', closeMenu);
    document.addEventListener('keydown', escClose);
}
