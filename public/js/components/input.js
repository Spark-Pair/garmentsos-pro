(() => {
    const escapeFieldAttribute = value => String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/"/g, '&quot;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');

    const dynamicErrorMarkup = name => `
        <div class="errorIconWrap absolute right-3 top-1/2 z-20 -translate-y-1/2">
            <button type="button" tabindex="-1" aria-label="Validation error"
                class="errorIcon peer flex size-[20px] items-center justify-center rounded-full border border-[var(--border-error)] bg-[color-mix(in_srgb,var(--border-error)_10%,var(--secondary-bg-color))] text-[13px] font-bold leading-none text-[var(--border-error)] opacity-0 pointer-events-none transition-all duration-200">!</button>
            <div id="${escapeFieldAttribute(name)}-error" role="alert"
                class="field-error-msg hidden absolute bottom-[calc(100%+8px)] right-0 z-50 w-max min-w-[9rem] max-w-[230px] rounded-md border border-[color-mix(in_srgb,var(--border-error)_35%,transparent)] bg-[var(--secondary-bg-color)] px-3 py-2 text-xs font-medium leading-4 text-[var(--text-color)] shadow-[0_10px_30px_rgba(15,23,42,0.16)] opacity-0 pointer-events-none translate-y-1 transition-all duration-150 peer-hover:translate-y-0 peer-hover:opacity-100 peer-focus:translate-y-0 peer-focus:opacity-100"></div>
        </div>`;

    function dynamicFieldLabel({ label, id, name, required, readonly, disabled, addBtnLink = '' }) {
        if (!label) return '';
        return `
            <span class="mb-2 flex items-center justify-between">
                <label for="${escapeFieldAttribute(id || name)}" class="block font-medium text-[var(--secondary-text)]">
                    ${escapeFieldAttribute(label)}${!required && !readonly && !disabled ? ' (optional)' : ''}
                </label>
                ${addBtnLink ? `<a class="px-2 text-lg leading-none" href="${escapeFieldAttribute(addBtnLink)}">+</a>` : ''}
            </span>`;
    }

    function dynamicInputMarkup(options = {}) {
        const {
            label = '', name = '', id = '', type = 'text', placeholder = '', value = '',
            required = false, disabled = false, readonly = false, dataValidate = '', oninput = '',
            className = '', min = '', max = '', step = '',
        } = options;
        const attr = (key, val) => val !== '' && val !== null && typeof val !== 'undefined'
            ? `${key}="${escapeFieldAttribute(val)}"` : '';

        return `
            <div class="form-group relative ${escapeFieldAttribute(className)}">
                ${dynamicFieldLabel({ label, id, name, required, readonly, disabled })}
                <div class="field-control relative flex gap-4">
                    <input id="${escapeFieldAttribute(id)}" type="${escapeFieldAttribute(type)}" name="${escapeFieldAttribute(name)}"
                        ${attr('placeholder', placeholder)} ${attr('value', value)} ${required ? 'required aria-required="true"' : ''}
                        ${readonly ? 'readonly' : ''} ${disabled ? 'disabled' : ''} ${attr('data-validate', dataValidate)}
                        ${attr('oninput', oninput)} ${attr('min', min)} ${attr('max', max)} ${attr('step', step)}
                        aria-describedby="${escapeFieldAttribute(name)}-error"
                        class="w-full rounded-lg border border-gray-600 bg-[var(--h-bg-color)] px-3 ${type === 'date' ? 'py-[7px]' : 'py-2'} text-[var(--text-color)] transition-all duration-200 ease-out placeholder:capitalize disabled:bg-transparent disabled:opacity-70" />
                    ${dynamicErrorMarkup(name)}
                </div>
            </div>`;
    }

    function dynamicSelectMarkup(options = {}) {
        const {
            label = '', name = '', id = '', options: choices = [], showDefault = false,
            required = false, disabled = false, onchange = '', addBtnLink = '', className = '', value = '',
        } = options;
        const choiceMarkup = choices.map(choice => {
            const choiceValue = choice.value ?? choice.id ?? '';
            const choiceText = choice.text ?? choice.label ?? choice.name ?? choiceValue;
            const dataOption = choice.data_option
                ? `data-option="${escapeFieldAttribute(JSON.stringify(choice.data_option))}"`
                : '';
            return `<li data-for="${escapeFieldAttribute(id)}" data-value="${escapeFieldAttribute(choiceValue)}" ${dataOption}
                onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg transition hover:bg-[var(--h-bg-color)] text-nowrap overflow-x-auto scrollbar-hidden">${escapeFieldAttribute(choiceText)}</li>`;
        }).join('');

        return `
            <div class="form-group relative grow ${escapeFieldAttribute(className)}">
                ${dynamicFieldLabel({ label, id, name, required, disabled, addBtnLink })}
                <div class="selectParent field-control relative flex gap-4">
                    <input id="${escapeFieldAttribute(id)}" name="${escapeFieldAttribute(id)}_name" autocomplete="off"
                        ${disabled ? 'disabled' : ''} placeholder="-- Select ${escapeFieldAttribute(label)} --"
                        onfocus="selectClicked(this)" data-error-for="${escapeFieldAttribute(name)}"
                        aria-describedby="${escapeFieldAttribute(name)}-error"
                        class="w-full rounded-lg border border-gray-600 bg-[var(--h-bg-color)] px-3 py-2 text-[var(--text-color)] transition-all duration-200 ease-out placeholder:capitalize disabled:bg-transparent disabled:opacity-70" />
                    <input type="hidden" class="dbInput" data-for="${escapeFieldAttribute(id)}" name="${escapeFieldAttribute(name)}"
                        value="${escapeFieldAttribute(value)}" ${onchange ? `onchange="${escapeFieldAttribute(onchange)}"` : ''} ${required ? 'required' : ''}>
                    <div class="dropDownParent flex flex-col gap-2 fixed z-50 mt-2 w-full rounded-xl bg-[var(--secondary-bg-color)] border-gray-600 text-[var(--text-color)] p-1.5 border appearance-none max-h-[13rem]">
                        <input data-for="${escapeFieldAttribute(id)}" oninput="searchSelect(this)" onblur="validateSelectInput(this)"
                            autocomplete="off" placeholder="-- Select ${escapeFieldAttribute(label)} --" onkeydown="selectKeyDown(event, this)"
                            class="w-full rounded-lg border border-gray-600 bg-[var(--h-bg-color)] px-3 py-2 text-[var(--text-color)] transition-all duration-200 ease-out placeholder:capitalize" />
                        <ul class="optionsDropdown overflow-auto my-scrollbar-2 space-y-0.5 grow" data-for="${escapeFieldAttribute(id)}">
                            ${showDefault ? `<li data-for="${escapeFieldAttribute(id)}" data-value="" onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg transition hover:bg-[var(--h-bg-color)]">-- Select ${escapeFieldAttribute(label)} --</li>` : ''}
                            ${choiceMarkup}
                        </ul>
                    </div>
                    ${dynamicErrorMarkup(name)}
                </div>
            </div>`;
    }

    window.AppDynamicFields = Object.freeze({
        input: dynamicInputMarkup,
        select: dynamicSelectMarkup,
        error: dynamicErrorMarkup,
        hydrate: root => hydrateDynamicPageFields(root),
    });

    const dynamicPageFieldSelector = [
        'input:not([type="hidden"]):not([type="checkbox"]):not([type="radio"]):not([type="file"])',
        'select',
        'textarea',
    ].join(',');

    function isModalField(field) {
        return Boolean(field.closest(
            '.fixed[id$="-wrapper"], #context-menu, .dropDownParent, #printIframe, #preview-container, .preview-container'
        ));
    }

    function hydrateDynamicPageField(field) {
        if (!field?.matches?.(dynamicPageFieldSelector) || isModalField(field)) return;
        if (field.dataset.bladeInputBehavior === 'true') return;

        field.dataset.bladeInputBehavior = 'true';
        field.classList.add(
            'focus:outline-none',
            'focus:ring-2',
            'focus:ring-[var(--primary-color)]',
            'focus:border-transparent',
            'transition-all',
            'duration-200',
            'ease-out'
        );

        if (!field.hasAttribute('autocomplete') && field.matches('input:not([type="date"]):not([type="time"]):not([type="month"])')) {
            field.setAttribute('autocomplete', 'on');
        }

        if (field.required && !field.hasAttribute('aria-required')) {
            field.setAttribute('aria-required', 'true');
        }

        normalizeDynamicFieldError(field);

        document.dispatchEvent(new CustomEvent('app:dynamic-field-hydrated', {
            detail: { field },
        }));
    }

    function normalizeDynamicFieldError(field) {
        const selectValueField = field.closest('.selectParent')?.querySelector('input.dbInput[name]');
        const fieldName = field.dataset.errorFor || selectValueField?.name || field.name || field.id;
        const group = field.closest('.form-group');
        if (!fieldName || !group) return;

        if (selectValueField && !field.dataset.errorFor) {
            field.dataset.errorFor = fieldName;
        }

        const error = document.getElementById(`${fieldName}-error`);
        if (!error || !group.contains(error) || error.closest('.errorIconWrap')) return;

        const control = field.closest('.field-control') || field.parentElement;
        if (!control || !group.contains(control)) return;

        control.classList.add('field-control', 'relative');

        const wrap = document.createElement('div');
        wrap.className = 'errorIconWrap absolute right-3 top-1/2 z-20 -translate-y-1/2';

        const icon = document.createElement('button');
        icon.type = 'button';
        icon.tabIndex = -1;
        icon.setAttribute('aria-label', 'Validation error');
        icon.className = 'errorIcon peer flex size-[20px] items-center justify-center rounded-full border border-[var(--border-error)] bg-[color-mix(in_srgb,var(--border-error)_10%,var(--secondary-bg-color))] text-[13px] font-bold leading-none text-[var(--border-error)] opacity-0 pointer-events-none transition-all duration-200';
        icon.textContent = '!';

        error.setAttribute('role', 'alert');
        error.className = 'field-error-msg hidden absolute bottom-[calc(100%+8px)] right-0 z-50 w-max min-w-[9rem] max-w-[230px] rounded-md border border-[color-mix(in_srgb,var(--border-error)_35%,transparent)] bg-[var(--secondary-bg-color)] px-3 py-2 text-xs font-medium leading-4 text-[var(--text-color)] shadow-[0_10px_30px_rgba(15,23,42,0.16)] opacity-0 pointer-events-none translate-y-1 transition-all duration-150 peer-hover:translate-y-0 peer-hover:opacity-100 peer-focus:translate-y-0 peer-focus:opacity-100';
        field.setAttribute('aria-describedby', error.id);

        wrap.append(icon, error);
        control.appendChild(wrap);
    }

    function hydrateDynamicPageFields(root) {
        if (!root) return;
        if (root.matches?.(dynamicPageFieldSelector)) hydrateDynamicPageField(root);
        root.querySelectorAll?.(dynamicPageFieldSelector).forEach(hydrateDynamicPageField);
    }

    function initDynamicPageInputBehavior() {
        const observer = new MutationObserver(mutations => {
            mutations.forEach(mutation => {
                mutation.addedNodes.forEach(node => {
                    if (node.nodeType === Node.ELEMENT_NODE) {
                        hydrateDynamicPageFields(node);
                    }
                });
            });
        });

        observer.observe(document.body, { childList: true, subtree: true });
    }

    window.hydrateDynamicPageFields = hydrateDynamicPageFields;

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
        initDynamicPageInputBehavior();
    });
})();
