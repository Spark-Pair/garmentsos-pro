(() => {
    window.formatUsername = function formatUsername(input) {
        input.value = input.value.toLowerCase().replace(/[^a-z0-9]/g, '');
    };

    window.validateUsername = function validateUsername() {
        const username = document.getElementById('username')?.value || '';
        if (username.length < 6) {
            if (typeof showToast === 'function') {
                showToast('error', 'Username must be at least 6 characters long.');
            } else if (typeof showMessageBox === 'function') {
                showMessageBox('error', 'Username must be at least 6 characters long.');
            }
            return false;
        }
        return true;
    };

    function parseListInputValue(value) {
        return String(value || '')
            .split(/[,\n]+/)
            .map(item => item.trim())
            .filter(Boolean)
            .filter((item, index, list) => (
                list.findIndex(existing => existing.toLowerCase() === item.toLowerCase()) === index
            ));
    }

    function listInputItems(input) {
        return parseListInputValue(input?.dataset?.listInputValues || '');
    }

    function setListInputItems(input, items) {
        if (!input) return;
        input.dataset.listInputValues = parseListInputValue(items.join(',')).join(', ');
    }

    function listInputItemLabel(item) {
        const range = String(item || '').match(/^(\d+)\s*(?:-|to|se)\s*(\d+)$/i);
        if (!range) return item;

        return `${range[1]} - ${range[2]}`;
    }

    function isListInputRange(item) {
        return /^(\d+)\s*(?:-|to|se)\s*(\d+)$/i.test(String(item || ''));
    }

    function showListInputDropdown(input) {
        if (!input?.id) return;

        const wrap = document.querySelector(`[data-list-input-items-for="${CSS.escape(input.id)}"]`);
        if (!wrap) return;

        wrap.classList.remove('hidden');
        wrap.classList.add('flex');
        const toggleBtn = document.querySelector(`[data-list-input-toggle="${CSS.escape(input.id)}"]`);
        if (toggleBtn) {
            toggleBtn.title = 'Hide list';
        }
    }

    function renderListInput(input) {
        if (!input?.id) return;

        const wrap = document.querySelector(`[data-list-input-items-for="${CSS.escape(input.id)}"]`);
        if (!wrap) return;

        const typedItems = parseListInputValue(input.value);
        if (!input.dataset.listInputValues && typedItems.length > 1) {
            setListInputItems(input, typedItems);
            input.value = '';
        }

        const items = listInputItems(input);
        const toggleBtn = document.querySelector(`[data-list-input-toggle="${CSS.escape(input.id)}"]`);
        if (toggleBtn) {
            toggleBtn.title = items.length ? `Show list (${items.length})` : 'Show list';
            toggleBtn.classList.toggle('text-[var(--primary-color)]', items.length > 0);
            toggleBtn.dataset.count = String(items.length);
        }
        const countBadge = document.querySelector(`[data-list-input-count-for="${CSS.escape(input.id)}"]`);
        if (countBadge) {
            countBadge.textContent = String(items.length);
            countBadge.classList.toggle('hidden', items.length === 0);
        }

        wrap.innerHTML = `
            <ul class="optionsDropdown overflow-auto my-scrollbar-2 space-y-1 grow">
                ${items.map(item => `
                    <li class="group flex w-full cursor-default items-center justify-between gap-2 rounded-lg border border-gray-600/25 bg-[var(--h-bg-color)]/35 px-2 py-1 transition hover:border-gray-600/60 hover:bg-[var(--h-bg-color)]">
                        <div class="min-w-0 flex items-center gap-2">
                            <span class="flex size-5 shrink-0 items-center justify-center rounded-sm border border-gray-600 bg-[var(--bg-color)] text-[10px] text-[var(--primary-color)]">
                                <i class="fas fa-check"></i>
                            </span>
                            <span class="truncate text-nowrap overflow-x-auto scrollbar-hidden font-medium">${escapeListInputHtml(listInputItemLabel(item))}</span>
                        </div>
                        <div class="flex shrink-0 items-center gap-2 pl-2">
                            <span class="min-w-[2.6rem] text-right text-[10px] uppercase tracking-wide text-[var(--secondary-text)] opacity-70">${isListInputRange(item) ? 'Range' : 'Value'}</span>
                            <button type="button" class="rounded-md px-2 py-1 text-[var(--border-error)] opacity-70 transition hover:bg-[var(--border-error)]/10 hover:opacity-100" data-list-input-remove="${escapeListInputHtml(item)}" title="Remove">
                                <i class="fas fa-xmark"></i>
                            </button>
                        </div>
                    </li>
                `).join('')}
            </ul>
        `;

        if (items.length === 0) {
            wrap.innerHTML = `
                <ul class="optionsDropdown overflow-auto my-scrollbar-2 space-y-0.5 grow">
                    <li class="rounded-lg px-3 py-2 text-[var(--secondary-text)] opacity-70">-- No values added --</li>
                </ul>
            `;
        }
    }

    function escapeListInputHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function addListInputValue(input) {
        if (!input) return;

        const items = [
            ...listInputItems(input),
            ...parseListInputValue(input.value),
        ];
        setListInputItems(input, items);
        input.value = '';
        renderListInput(input);
        showListInputDropdown(input);
        input.dispatchEvent(new Event('change', { bubbles: true }));
    }

    window.getListInputValue = function getListInputValue(inputOrId) {
        const input = typeof inputOrId === 'string' ? document.getElementById(inputOrId) : inputOrId;
        if (!input) return '';
        return parseListInputValue([
            ...listInputItems(input),
            ...parseListInputValue(input.value),
        ].join(',')).join(',');
    };

    window.clearListInput = function clearListInput(inputOrId) {
        const input = typeof inputOrId === 'string' ? document.getElementById(inputOrId) : inputOrId;
        if (!input) return;

        input.value = '';
        input.dataset.listInputValues = '';
        renderListInput(input);
    };

    window.refreshListInput = function refreshListInput(inputOrId = null) {
        if (typeof inputOrId === 'string') {
            renderListInput(document.getElementById(inputOrId));
            return;
        }

        if (inputOrId) {
            renderListInput(inputOrId);
            return;
        }

        document.querySelectorAll('[data-list-input]').forEach(renderListInput);
    };

    document.addEventListener('click', event => {
        const addBtn = event.target.closest('[data-list-input-target]');
        if (addBtn) {
            const input = document.getElementById(addBtn.getAttribute('data-list-input-target'));
            addListInputValue(input);
            input?.focus();
            return;
        }

        const toggleBtn = event.target.closest('[data-list-input-toggle]');
        if (toggleBtn) {
            const inputId = toggleBtn.getAttribute('data-list-input-toggle');
            const wrap = document.querySelector(`[data-list-input-items-for="${CSS.escape(inputId)}"]`);
            if (wrap) {
                wrap.classList.toggle('hidden');
                wrap.classList.toggle('flex');
                const isVisible = !wrap.classList.contains('hidden');
                toggleBtn.title = isVisible ? 'Hide list' : toggleBtn.title.replace(/^Hide/, 'Show');
            }
            return;
        }

        if (!event.target.closest('[data-list-input]')
            && !event.target.closest('[data-list-input-toggle]')
            && !event.target.closest('[data-list-input-items-for]')) {
            document.querySelectorAll('[data-list-input-items-for]').forEach(wrap => {
                wrap.classList.add('hidden');
                wrap.classList.remove('flex');
            });
        }

        const removeBtn = event.target.closest('[data-list-input-remove]');
        if (!removeBtn) return;

        const wrap = removeBtn.closest('[data-list-input-items-for]');
        const input = document.getElementById(wrap?.getAttribute('data-list-input-items-for') || '');
        if (!input) return;

        const removeValue = removeBtn.getAttribute('data-list-input-remove') || '';
        const items = listInputItems(input)
            .filter(item => item.toLowerCase() !== removeValue.toLowerCase());
        setListInputItems(input, items);
        renderListInput(input);
        input.dispatchEvent(new Event('change', { bubbles: true }));
    });

    document.addEventListener('keydown', event => {
        const input = event.target.closest?.('[data-list-input]');
        if (!input) return;

        if (event.key === 'Enter') {
            if (parseListInputValue(input.value).length === 0) {
                return;
            }
            event.preventDefault();
            addListInputValue(input);
            window.setTimeout(() => input.focus(), 0);
        }
    });

    document.addEventListener('blur', event => {
        const input = event.target.closest?.('[data-list-input]');
        if (input) {
            addListInputValue(input);
        }
    }, true);

    document.addEventListener('DOMContentLoaded', () => {
        window.refreshListInput();
    });
})();
