(function() {
    const originalPrintPage = window.printPage;
    let printColumns = [];
    let draggedRow = null;
    let printSettings = {};
    let pendingPrintPayload = null;
    const WIDTH_WEIGHTS = { narrow: 0.7, normal: 1, wide: 1.45 };
    const WIDTH_LABELS = { narrow: 'Narrow', normal: 'Normal', wide: 'Wide' };

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function sanitizeFileName(value) {
        return String(value || 'export')
            .trim()
            .replace(/[\\/:*?"<>|]+/g, '-')
            .replace(/\s+/g, ' ')
            .slice(0, 120);
    }

    function flattenObject(value, prefix = '', output = {}) {
        if (value === null || value === undefined) {
            if (prefix) output[prefix] = '';
            return output;
        }

        if (Array.isArray(value)) {
            if (prefix) output[prefix] = JSON.stringify(value);
            return output;
        }

        if (typeof value === 'object') {
            Object.entries(value).forEach(([key, child]) => {
                const nextKey = prefix ? `${prefix}.${key}` : key;
                flattenObject(child, nextKey, output);
            });
            return output;
        }

        if (prefix) output[prefix] = value;
        return output;
    }

    function visibleRows(container) {
        return Array.from(container?.children || []).filter(row => {
            if (!(row instanceof HTMLElement)) return false;
            const style = window.getComputedStyle(row);
            return style.display !== 'none' && style.visibility !== 'hidden';
        });
    }

    function tableColumns() {
        const tableHead = document.querySelector('#table-head');
        if (!tableHead) return [];

        return Array.from(tableHead.children).map((col, index) => ({
            index,
            originalIndex: index,
            text: col.textContent.trim() || `Column ${index + 1}`,
            width: col.className.match(/w-\[(\d+)%\]/)?.[1] || '10',
            selected: true,
            mergeWith: null,
        }));
    }

    function formattedRows() {
        const searchContainer = document.querySelector('.search_container');
        return visibleRows(searchContainer)
            .map(row => Array.from(row.querySelectorAll('span')).map(cell => cell.textContent.trim()))
            .filter(row => row.some(Boolean));
    }

    function rawRows() {
        const searchContainer = document.querySelector('.search_container');
        return visibleRows(searchContainer)
            .map(row => {
                const raw = row.getAttribute('data-json');
                if (!raw) return null;

                try {
                    return flattenObject(JSON.parse(raw));
                } catch (error) {
                    console.warn('Could not parse row raw data for export.', error);
                    return null;
                }
            })
            .filter(Boolean);
    }

    function reportKey() {
        const path = window.location.pathname.replace(/[^a-z0-9]+/gi, '-').replace(/^-|-$/g, '');
        const title = document.getElementById('page-title')?.textContent?.trim() || 'report';
        return `${path || 'page'}:${sanitizeFileName(title).toLowerCase()}`;
    }

    function storageKey(suffix) {
        return `gos.report-layout.${reportKey()}.${suffix}`;
    }

    function storageGet(key, fallback = null) {
        try {
            const value = localStorage.getItem(key);
            return value ? JSON.parse(value) : fallback;
        } catch (error) {
            console.warn('Could not read report layout storage.', error);
            return fallback;
        }
    }

    function storageSet(key, value) {
        try {
            localStorage.setItem(key, JSON.stringify(value));
            return true;
        } catch (error) {
            console.warn('Could not save report layout storage.', error);
            return false;
        }
    }

    function normalizedColumnText(value) {
        return String(value || '').trim().toLowerCase();
    }

    function findColumnIndex(columns, names) {
        const normalizedNames = names.map(normalizedColumnText);
        const found = columns.find(col => normalizedNames.some(name => normalizedColumnText(col.text).includes(name)));
        return found ? String(found.originalIndex) : '';
    }

    function defaultSettings(columns = tableColumns()) {
        const pageTitle = document.getElementById('page-title')?.textContent?.trim() || 'Print';
        const lowerTitle = normalizedColumnText(pageTitle);
        const customerColumn = findColumnIndex(columns, ['customer']);
        const cityColumn = findColumnIndex(columns, ['city']);
        const dateColumn = findColumnIndex(columns, ['date']);

        return {
            title: pageTitle,
            orientation: 'landscape',
            paper: 'A4',
            fontSize: 10,
            density: 'normal',
            showCompany: true,
            showDate: true,
            showFooter: true,
            showRowCount: true,
            groupBy: lowerTitle.includes('payment') ? (customerColumn || cityColumn) : '',
            sortBy: dateColumn,
            sortDirection: 'asc',
            pattern: 'clean',
            showGrid: true,
            zebra: false,
            wrapText: false,
        };
    }

    function hydrateColumnsFromLayout(columns, layout = {}) {
        const safeLayout = layout || {};
        const savedColumns = safeLayout.columns || [];

        return columns.map(col => {
            const saved = savedColumns.find(item => Number(item.originalIndex) === Number(col.originalIndex));
            return {
                ...col,
                selected: saved?.selected ?? true,
                mergeWith: saved?.mergeWith ?? null,
                printWidth: saved?.printWidth || 'normal',
                totalMode: saved?.totalMode || 'none',
            };
        }).sort((a, b) => {
            const aOrder = savedColumns.findIndex(item => Number(item.originalIndex) === Number(a.originalIndex));
            const bOrder = savedColumns.findIndex(item => Number(item.originalIndex) === Number(b.originalIndex));
            return (aOrder === -1 ? Number.MAX_SAFE_INTEGER : aOrder) - (bOrder === -1 ? Number.MAX_SAFE_INTEGER : bOrder);
        });
    }

    function currentLayoutSnapshot() {
        return {
            settings: { ...printSettings },
            columns: printColumns.map(col => ({
                originalIndex: col.originalIndex,
                selected: col.selected,
                mergeWith: col.mergeWith,
                printWidth: col.printWidth || 'normal',
                totalMode: col.totalMode || 'none',
            })),
        };
    }

    function saveLastLayout() {
        storageSet(storageKey('last'), currentLayoutSnapshot());
    }

    function loadLastLayout() {
        return storageGet(storageKey('last'), null);
    }

    function loadPresets() {
        return storageGet(storageKey('presets'), {});
    }

    function savePresets(presets) {
        return storageSet(storageKey('presets'), presets);
    }

    function presetOptions() {
        return Object.keys(loadPresets()).sort().map(name => ({ value: name, text: name }));
    }

    function parseNumber(value) {
        const normalized = String(value ?? '').replace(/,/g, '').replace(/[^\d.-]/g, '');
        const number = Number(normalized);
        return Number.isFinite(number) ? number : 0;
    }

    function formatNumber(value) {
        return Number(value || 0).toLocaleString(undefined, {
            maximumFractionDigits: 2,
            minimumFractionDigits: Number.isInteger(value) ? 0 : 2,
        });
    }

    function appInputHtml({ id, name = id, label, value = '', type = 'text', min = '', max = '' }) {
        return `
            <div class="form-group relative">
                <span class="mb-2 flex items-center justify-between">
                    <label for="${id}" class="block font-medium text-[var(--secondary-text)]">${label} (optional)</label>
                </span>
                <div class="field-control relative flex gap-4">
                    <input
                        id="${id}"
                        type="${type}"
                        name="${name}"
                        value="${escapeHtml(value)}"
                        ${min !== '' ? `min="${escapeHtml(min)}"` : ''}
                        ${max !== '' ? `max="${escapeHtml(max)}"` : ''}
                        autocomplete="off"
                        class="w-full rounded-lg border border-gray-600 bg-[var(--h-bg-color)] px-3 ${type === 'date' ? 'py-[7px]' : 'py-2'} text-[var(--text-color)] transition-all duration-200 ease-out placeholder:capitalize disabled:bg-transparent disabled:opacity-70"
                    >
                </div>
            </div>
        `;
    }

    function appSelectHtml({ id, name = id, label, options, value = '', placeholder = null }) {
        const selected = options.find(option => String(option.value) === String(value));
        const displayText = selected?.text || '';
        const defaultText = placeholder || `-- Select ${label} --`;

        return `
            <div class="select-component form-group">
                <span class="flex items-center justify-between mb-2">
                    <label for="${id}" class="block font-medium text-[var(--secondary-text)]">${label} (optional)</label>
                </span>
                <div class="selectParent flex gap-4">
                    <div class="form-group relative grow">
                        <div class="field-control relative flex gap-4">
                            <input
                                id="${id}"
                                name="${id}_name"
                                value="${escapeHtml(displayText)}"
                                placeholder="${escapeHtml(defaultText)}"
                                autocomplete="off"
                                onfocus="selectClicked(this)"
                                class="w-full rounded-lg border border-gray-600 bg-[var(--h-bg-color)] px-3 py-2 text-[var(--text-color)] transition-all duration-200 ease-out placeholder:capitalize disabled:bg-transparent disabled:opacity-70"
                            >
                        </div>
                    </div>
                    <input type="hidden" class="dbInput" data-for="${id}" name="${name}" value="${escapeHtml(value)}">
                    <div class="dropDownParent flex flex-col gap-2 fixed z-50 mt-2 w-full rounded-xl bg-[var(--secondary-bg-color)] border-gray-600 text-[var(--text-color)] p-1.5 border appearance-none focus:ring-2 focus:ring-primary focus:border-transparent max-h-[13rem]">
                        <div class="form-group relative">
                            <div class="field-control relative flex gap-4">
                                <input
                                    data-for="${id}"
                                    oninput="searchSelect(this)"
                                    onblur="validateSelectInput(this)"
                                    autocomplete="off"
                                    value="${escapeHtml(displayText)}"
                                    placeholder="${escapeHtml(defaultText)}"
                                    onkeydown="selectKeyDown(event, this)"
                                    class="w-full rounded-lg border border-gray-600 bg-[var(--h-bg-color)] px-3 py-2 text-[var(--text-color)] transition-all duration-200 ease-out placeholder:capitalize disabled:bg-transparent disabled:opacity-70"
                                >
                            </div>
                        </div>
                        <ul class="optionsDropdown overflow-auto my-scrollbar-2 space-y-0.5 grow" data-for="${id}">
                            <li
                                data-for="${id}"
                                data-value=""
                                onmousedown="selectThisOption(this)"
                                class="py-2 px-3 cursor-pointer rounded-lg transition hover:bg-[var(--h-bg-color)] ${String(value) === '' ? 'selected' : ''}"
                            >${escapeHtml(defaultText)}</li>
                            ${options.map(option => `
                                <li
                                    data-for="${id}"
                                    data-value="${escapeHtml(option.value)}"
                                    onmousedown="selectThisOption(this)"
                                    class="py-2 px-3 cursor-pointer rounded-lg transition hover:bg-[var(--h-bg-color)] text-nowrap overflow-x-auto scrollbar-hidden ${String(option.value) === String(value) ? 'selected' : ''}"
                                >${escapeHtml(option.text)}</li>
                            `).join('')}
                        </ul>
                    </div>
                </div>
            </div>
        `;
    }

    window.TableExportTools = {
        sanitizeFileName,
        columns: tableColumns,
        formattedRows,
        rawRows,
        reportLayoutData() {
            const columns = tableColumns();
            const layout = loadLastLayout();
            printSettings = {
                ...defaultSettings(columns),
                ...(layout?.settings || {}),
            };
            printColumns = hydrateColumnsFromLayout(columns, layout);
            const selectedColumns = printColumns.filter(col => col.selected);
            return buildReportData(selectedColumns);
        },
    };

    window.openPrintColumnModal = function() {
        const tableHead = document.querySelector('#table-head');
        if (!tableHead) {
            appAlert('Table header not found');
            return;
        }

        const columns = tableColumns();
        const savedLayout = loadLastLayout();

        printSettings = {
            ...defaultSettings(columns),
            ...(savedLayout?.settings || {}),
        };
        printColumns = hydrateColumnsFromLayout(columns, savedLayout);
        const columnSelectOptions = printColumns.map(col => ({ value: col.originalIndex, text: col.text }));
        const presetSelectOptions = presetOptions();
        const paperOptions = [
            { value: 'A4', text: 'A4' },
            { value: 'A5', text: 'A5' },
        ];
        const orientationOptions = [
            { value: 'landscape', text: 'Landscape' },
            { value: 'portrait', text: 'Portrait' },
        ];
        const patternOptions = [
            { value: 'clean', text: 'Clean' },
            { value: 'ledger', text: 'Strong Lines' },
            { value: 'boxed', text: 'Boxed Cells' },
            { value: 'minimal', text: 'Plain' },
        ];
        const densityOptions = [
            { value: 'compact', text: 'Compact' },
            { value: 'normal', text: 'Normal' },
            { value: 'relaxed', text: 'Relaxed' },
        ];
        const sortDirectionOptions = [
            { value: 'asc', text: 'A to Z' },
            { value: 'desc', text: 'Z to A' },
        ];

        let modalData = {
            id: 'printColumnModal',
            name: 'Print Control',
            class: 'p-4 max-w-5xl h-[42rem]',
            fieldsGridCount: '1',
            fields: [
                {
                    category: 'explicitHtml',
                    full: true,
                    html: `
                        <div class="space-y-3 pb-4">
                            <div class="rounded-2xl border border-[var(--h-bg-color)] bg-[var(--h-bg-color)]/25 p-3 text-sm shadow-sm">
                                <div class="mb-3 flex items-start justify-between gap-4 border-b border-[var(--h-bg-color)] pb-3 text-left">
                                    <div class="flex min-w-0 items-start gap-3">
                                        <span class="mt-1 h-9 w-1.5 shrink-0 rounded-full bg-[var(--primary-color)]/80"></span>
                                        <div class="min-w-0">
                                            <h6 class="text-sm font-semibold uppercase tracking-wide text-[var(--text-color)]">Print Report Layout</h6>
                                            <p class="mt-1 text-xs text-[var(--secondary-text)]">Configure report columns, grouping, sorting and print page settings.</p>
                                        </div>
                                    </div>
                                    <span class="inline-flex shrink-0 items-center rounded-xl border border-[var(--h-bg-color)] bg-[var(--secondary-bg-color)] px-3 py-1.5 text-xs font-semibold text-[var(--secondary-text)]">
                                        ${printColumns.length} columns
                                    </span>
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-4 gap-2 text-xs">
                                    <div class="md:col-span-2">${appInputHtml({ id: 'print-setting-title', label: 'Report Title', value: printSettings.title })}</div>
                                    ${appSelectHtml({ id: 'print-setting-group-by', label: 'Group By', options: columnSelectOptions, placeholder: 'No Group' })}
                                    ${appSelectHtml({ id: 'print-setting-sort-by', label: 'Sort By', options: columnSelectOptions, placeholder: 'Default Order' })}
                                    ${appSelectHtml({ id: 'print-setting-sort-direction', label: 'Sort', options: sortDirectionOptions, value: printSettings.sortDirection })}
                                    ${appSelectHtml({ id: 'print-setting-pattern', label: 'Pattern', options: patternOptions, value: printSettings.pattern })}
                                    ${appSelectHtml({ id: 'print-setting-paper', label: 'Paper', options: paperOptions, value: printSettings.paper })}
                                    ${appSelectHtml({ id: 'print-setting-orientation', label: 'Orientation', options: orientationOptions, value: printSettings.orientation })}
                                    ${appInputHtml({ id: 'print-setting-font-size', label: 'Font Size', value: printSettings.fontSize, type: 'number', min: 7, max: 14 })}
                                    ${appSelectHtml({ id: 'print-setting-density', label: 'Density', options: densityOptions, value: printSettings.density })}
                                    <div class="md:col-span-2">${appInputHtml({ id: 'print-preset-name', label: 'Preset Name', value: '' })}</div>
                                    ${appSelectHtml({ id: 'print-preset-select', label: 'Load Preset', options: presetSelectOptions, placeholder: 'No Saved Preset' })}
                                    <div class="grid grid-cols-3 gap-2 self-end">
                                        <button type="button" onclick="savePrintPreset()" class="rounded-lg border border-gray-600 bg-[var(--h-bg-color)] px-3 py-2 text-xs font-semibold text-[var(--secondary-text)] transition hover:bg-[var(--secondary-bg-color)]">Save</button>
                                        <button type="button" onclick="applyPrintPreset()" class="rounded-lg border border-gray-600 bg-[var(--h-bg-color)] px-3 py-2 text-xs font-semibold text-[var(--secondary-text)] transition hover:bg-[var(--secondary-bg-color)]">Load</button>
                                        <button type="button" onclick="deletePrintPreset()" class="rounded-lg border border-gray-600 bg-[var(--h-bg-color)] px-3 py-2 text-xs font-semibold text-[var(--secondary-text)] transition hover:bg-[var(--secondary-bg-color)]">Delete</button>
                                    </div>
                                    <div class="col-span-full grid grid-cols-2 md:grid-cols-4 gap-2 border-t border-gray-600 pt-2">
                                        ${[
                                            ['show-company', 'Company'],
                                            ['show-date', 'Print Date'],
                                            ['show-footer', 'Footer'],
                                            ['show-row-count', 'Row Count'],
                                            ['show-grid', 'Grid Lines'],
                                            ['zebra', 'Zebra Rows'],
                                            ['wrap-text', 'Wrap Text'],
                                        ].map(([id, label]) => `
                                            <label class="flex items-center justify-between gap-3 rounded-lg border border-gray-600 bg-[var(--h-bg-color)] px-3 py-2">
                                                <span class="font-semibold text-[var(--secondary-text)]">${label}</span>
                                                <input id="print-setting-${id}" type="checkbox" ${['wrap-text', 'zebra'].includes(id) ? '' : 'checked'} class="row-checkbox shrink-0 w-3.5 h-3.5 appearance-none border border-gray-400 rounded-sm checked:bg-[var(--primary-color)] checked:border-transparent focus:outline-none transition duration-150 cursor-pointer">
                                            </label>
                                        `).join('')}
                                    </div>
                                </div>
                            </div>
                            <div class="rounded-2xl border border-[var(--h-bg-color)] bg-[var(--h-bg-color)]/25 p-3 text-sm shadow-sm">
                                <div class="mb-3 flex items-start justify-between gap-4 border-b border-[var(--h-bg-color)] pb-3 text-left">
                                    <div class="flex min-w-0 items-start gap-3">
                                        <span class="mt-1 h-7 w-1.5 shrink-0 rounded-full bg-[var(--primary-color)]/80"></span>
                                        <div class="min-w-0">
                                            <h6 class="text-sm font-semibold uppercase tracking-wide text-[var(--text-color)]">Columns</h6>
                                            <p class="mt-1 text-xs text-[var(--secondary-text)]">Select columns, drag order, and connect/merge related columns before print.</p>
                                        </div>
                                    </div>
                                </div>
                                <div id="table-head" class="flex justify-between items-center bg-[var(--h-bg-color)] rounded-lg py-2 px-3 mb-2 text-xs font-medium">
                                    <div class="w-[5%]"></div>
                                    <div class="w-[6%] text-center">Use</div>
                                    <div class="grow">Column</div>
                                    <div class="w-[15%]">Merge</div>
                                    <div class="w-[13%]">Width</div>
                                    <div class="w-[12%]">Total</div>
                                </div>
                                <div id="noItemsError" style="display: none" class="text-sm text-[var(--border-error)] mt-2 mb-1">No items found</div>
                                <div id="table-body" class="search_container max-h-[12rem] overflow-y-auto my-scrollbar-2 pr-1"></div>
                            </div>
                        </div>
                    `,
                },
            ],
            bottomActions: [
                {
                    id: 'select-all',
                    text: 'Select All',
                    type: 'button',
                    onclick: 'selectAllPrintColumns(true)'
                },
                {
                    id: 'deselect-all',
                    text: 'Deselect All',
                    type: 'button',
                    onclick: 'selectAllPrintColumns(false)'
                },
                {
                    id: 'reset-order',
                    text: 'Reset Order',
                    type: 'button',
                    onclick: 'resetColumnOrder()'
                },
                {
                    id: 'preview-print',
                    text: 'Preview',
                    type: 'button',
                    onclick: 'previewSelectedPrintColumns()'
                },
                {
                    id: 'print-selected',
                    text: 'Print',
                    type: 'button',
                    onclick: 'printWithSelectedColumns()'
                }
            ]
        };

        createModal(modalData);

        setTimeout(() => {
            updateTableBodyOnly();
        }, 150);
    };

    function updateTableBodyOnly() {
        const tableBody = document.querySelector('#printColumnModal #table-body');
        if (!tableBody) return;

        let bodyHTML = '';

        printColumns.forEach((col, displayIndex) => {
            const mergeOptions = printColumns
                .map((c, i) => i !== displayIndex ? `<option value="${i}" ${col.mergeWith === i ? 'selected' : ''}>→ ${c.text}</option>` : '')
                .join('');

            bodyHTML += `
                <div class="flex justify-between items-center border-t border-gray-600 py-1.5 px-3 cursor-pointer hover:bg-[var(--h-secondary-bg-color)] transition-all">
                    <div class="w-[5%] flex items-center justify-center cursor-move drag-handle" data-display-index="${displayIndex}">
                        <i class="fas fa-grip-vertical text-gray-400"></i>
                    </div>
                    <div class="w-[6%] flex items-center justify-center">
                        <input ${col.selected ? 'checked' : ''} type="checkbox" class="row-checkbox shrink-0 w-3.5 h-3.5 appearance-none border border-gray-400 rounded-sm checked:bg-[var(--primary-color)] checked:border-transparent focus:outline-none transition duration-150 cursor-pointer" />
                    </div>
                    <div class="grow font-medium truncate">${col.text}</div>
                    <div class="w-[15%] flex items-center gap-2">
                        <select class="merge-select text-xs px-2 py-1.5 rounded-lg bg-[var(--h-bg-color)] border border-gray-600 w-full" data-display-index="${displayIndex}">
                            <option value="">No Merge</option>
                            ${mergeOptions}
                        </select>
                    </div>
                    <div class="w-[13%] flex items-center gap-2">
                        <select class="width-select text-xs px-2 py-1.5 rounded-lg bg-[var(--h-bg-color)] border border-gray-600 w-full" data-display-index="${displayIndex}">
                            ${Object.entries(WIDTH_LABELS).map(([value, label]) => `<option value="${value}" ${col.printWidth === value ? 'selected' : ''}>${label}</option>`).join('')}
                        </select>
                    </div>
                    <div class="w-[12%] flex items-center gap-2">
                        <select class="total-select text-xs px-2 py-1.5 rounded-lg bg-[var(--h-bg-color)] border border-gray-600 w-full" data-display-index="${displayIndex}">
                            <option value="none" ${col.totalMode !== 'sum' ? 'selected' : ''}>None</option>
                            <option value="sum" ${col.totalMode === 'sum' ? 'selected' : ''}>Sum</option>
                        </select>
                    </div>
                </div>
            `;
        });

        tableBody.innerHTML = bodyHTML;

        setTimeout(() => {
            setupModalInteractions();
        }, 50);
    }

    function setupModalInteractions() {
        const tableBody = document.querySelector('#printColumnModal #table-body');
        if (!tableBody) {
            console.error('Table body not found');
            return;
        }

        const rows = Array.from(tableBody.children);

        rows.forEach((row, displayIndex) => {
            const checkbox = row.querySelector('.row-checkbox');
            if (checkbox) {
                row.addEventListener('click', function(e) {
                    if (e.target === checkbox ||
                        e.target.classList.contains('drag-handle') ||
                        e.target.closest('.drag-handle') ||
                        e.target.classList.contains('merge-select') ||
                        e.target.classList.contains('width-select') ||
                        e.target.classList.contains('total-select') ||
                        e.target.tagName === 'SELECT' ||
                        e.target.tagName === 'I') {
                        return;
                    }

                    checkbox.checked = !checkbox.checked;
                    printColumns[displayIndex].selected = checkbox.checked;
                });

                checkbox.addEventListener('change', function() {
                    printColumns[displayIndex].selected = this.checked;
                });
            }
        });

        setupDragAndDrop();
        setupMergeSelects();
        setupWidthSelects();
        setupTotalSelects();
    }

    function setupDragAndDrop() {
        const tableBody = document.querySelector('#printColumnModal #table-body');
        if (!tableBody) return;

        const rows = Array.from(tableBody.children);

        rows.forEach((row) => {
            const dragHandle = row.querySelector('.drag-handle');
            if (!dragHandle) return;

            dragHandle.setAttribute('draggable', 'true');

            dragHandle.addEventListener('dragstart', function(e) {
                draggedRow = row;
                row.style.opacity = '0.5';
                e.dataTransfer.effectAllowed = 'move';
            });

            dragHandle.addEventListener('dragend', function() {
                row.style.opacity = '1';
                draggedRow = null;
            });

            row.addEventListener('dragover', function(e) {
                if (draggedRow && draggedRow !== row) {
                    e.preventDefault();
                    e.dataTransfer.dropEffect = 'move';
                    this.style.borderTop = '2px solid var(--primary-color)';
                }
            });

            row.addEventListener('dragleave', function() {
                this.style.borderTop = '';
            });

            row.addEventListener('drop', function(e) {
                e.preventDefault();
                this.style.borderTop = '';

                if (!draggedRow || draggedRow === row) return;

                const fromIndex = Array.from(tableBody.children).indexOf(draggedRow);
                const toIndex = Array.from(tableBody.children).indexOf(row);

                const [movedColumn] = printColumns.splice(fromIndex, 1);
                printColumns.splice(toIndex, 0, movedColumn);

                updateTableBodyOnly();
            });
        });
    }

    function setupMergeSelects() {
        const mergeSelects = document.querySelectorAll('.merge-select');

        mergeSelects.forEach(select => {
            const displayIndex = parseInt(select.dataset.displayIndex);

            select.addEventListener('change', function() {
                const mergeWithIndex = this.value ? parseInt(this.value) : null;
                printColumns[displayIndex].mergeWith = mergeWithIndex;
            });
        });
    }

    function setupWidthSelects() {
        document.querySelectorAll('.width-select').forEach(select => {
            const displayIndex = parseInt(select.dataset.displayIndex);

            select.addEventListener('change', function() {
                printColumns[displayIndex].printWidth = this.value || 'normal';
            });
        });
    }

    function setupTotalSelects() {
        document.querySelectorAll('.total-select').forEach(select => {
            const displayIndex = parseInt(select.dataset.displayIndex);

            select.addEventListener('change', function() {
                printColumns[displayIndex].totalMode = this.value || 'none';
            });
        });
    }

    window.resetColumnOrder = function() {
        printColumns.sort((a, b) => a.originalIndex - b.originalIndex);

        printColumns.forEach(col => {
            col.mergeWith = null;
            col.selected = true;
            col.printWidth = 'normal';
            col.totalMode = 'none';
        });

        updateTableBodyOnly();
    };

    window.selectAllPrintColumns = function(select) {
        printColumns.forEach(col => {
            col.selected = select;
        });

        const checkboxes = document.querySelectorAll('#printColumnModal #table-body .row-checkbox');
        checkboxes.forEach(checkbox => {
            checkbox.checked = select;
        });
    };

    function selectedPresetName() {
        const visible = document.getElementById('print-preset-select')?.value;
        const hidden = document.querySelector('.dbInput[data-for="print-preset-select"]')?.value;
        return hidden || visible || '';
    }

    window.savePrintPreset = function() {
        readPrintSettings();
        const input = document.getElementById('print-preset-name');
        const name = input?.value?.trim();
        if (!name) {
            appAlert('Please enter a preset name.');
            return;
        }

        const presets = loadPresets();
        presets[name] = currentLayoutSnapshot();
        savePresets(presets);
        appAlert('Print preset saved.');
        closeModal('printColumnModal');
        window.openPrintColumnModal();
    };

    window.applyPrintPreset = function() {
        const name = selectedPresetName();
        const presets = loadPresets();
        const preset = presets[name];
        if (!name || !preset) {
            appAlert('Please select a saved preset.');
            return;
        }

        printSettings = {
            ...defaultSettings(tableColumns()),
            ...(preset.settings || {}),
        };
        printColumns = hydrateColumnsFromLayout(tableColumns(), preset);
        saveLastLayout();
        closeModal('printColumnModal');
        window.openPrintColumnModal();
    };

    window.deletePrintPreset = function() {
        const name = selectedPresetName();
        const presets = loadPresets();
        if (!name || !presets[name]) {
            appAlert('Please select a saved preset.');
            return;
        }

        delete presets[name];
        savePresets(presets);
        appAlert('Print preset deleted.');
        closeModal('printColumnModal');
        window.openPrintColumnModal();
    };

    window.printWithSelectedColumns = function() {
        const selectedColumns = printColumns.filter(col => col.selected);

        if (selectedColumns.length === 0) {
            appAlert('Please select at least one column');
            return;
        }

        readPrintSettings();
        closeModal('printColumnModal');
        executePrintWithColumns(selectedColumns);
    };

    window.previewSelectedPrintColumns = function() {
        const selectedColumns = printColumns.filter(col => col.selected);

        if (selectedColumns.length === 0) {
            appAlert('Please select at least one column');
            return;
        }

        readPrintSettings();
        saveLastLayout();
        const payload = buildPrintPayload(selectedColumns);
        if (!payload) {
            appAlert('Printable data not found.');
            return;
        }

        pendingPrintPayload = payload;
        createModal({
            id: 'printPreviewModal',
            name: 'Print Preview',
            class: 'p-4 max-w-5xl h-[42rem]',
            fieldsGridCount: '1',
            fields: [{
                category: 'explicitHtml',
                full: true,
                html: `
                    <div class="rounded-2xl border border-[var(--h-bg-color)] bg-white p-4 text-black shadow-sm">
                        <style>${payload.style}</style>
                        <div id="print-preview-surface" class="max-h-[32rem] overflow-y-auto my-scrollbar-2 pr-2">
                            ${payload.html}
                        </div>
                    </div>
                `,
            }],
            bottomActions: [{
                id: 'print-preview-confirm',
                text: 'Print',
                type: 'button',
                onclick: 'printCurrentPreviewPayload()'
            }],
        });
    };

    window.printCurrentPreviewPayload = function() {
        if (!pendingPrintPayload) {
            appAlert('Preview is not ready.');
            return;
        }

        closeModal('printPreviewModal');
        window.DocumentPrint.printHtml({
            title: pendingPrintPayload.title,
            html: pendingPrintPayload.html,
            style: pendingPrintPayload.style,
        });
    };

    function readPrintSettings() {
        const checked = id => document.getElementById(id)?.checked !== false;
        const selectValue = id => document.querySelector(`.dbInput[data-for="${id}"]`)?.value || '';
        const numberValue = (id, fallback) => {
            const value = Number(document.getElementById(id)?.value);
            return Number.isFinite(value) ? value : fallback;
        };

        printSettings = {
            title: document.getElementById('print-setting-title')?.value?.trim() || 'Print',
            paper: selectValue('print-setting-paper') || 'A4',
            orientation: selectValue('print-setting-orientation') || 'landscape',
            fontSize: Math.max(7, Math.min(14, numberValue('print-setting-font-size', 10))),
            density: selectValue('print-setting-density') || 'normal',
            showCompany: checked('print-setting-show-company'),
            showDate: checked('print-setting-show-date'),
            showFooter: checked('print-setting-show-footer'),
            showRowCount: checked('print-setting-show-row-count'),
            showGrid: checked('print-setting-show-grid'),
            zebra: checked('print-setting-zebra'),
            wrapText: checked('print-setting-wrap-text'),
            groupBy: selectValue('print-setting-group-by'),
            sortBy: selectValue('print-setting-sort-by'),
            sortDirection: selectValue('print-setting-sort-direction') || 'asc',
            pattern: selectValue('print-setting-pattern') || 'clean',
        };
    }

    function buildReportData(selectedColumns) {
        const preview = document.querySelector('.container-parent');
        if (!preview) {
            return null;
        }

        const clone = preview.cloneNode(true);
        clone.querySelector('#calc-bottom')?.remove();

        const header = clone.querySelector('#table-head');
        const body = clone.querySelector('.search_container');
        if (!header || !body) return null;

        const processedColumns = [];
        const mergedIndices = new Set();

        selectedColumns.forEach((col) => {
            if (mergedIndices.has(col.originalIndex)) return;

            if (col.mergeWith !== null) {
                const mergeTargetCol = printColumns[col.mergeWith];
                if (mergeTargetCol?.selected && !mergedIndices.has(mergeTargetCol.originalIndex)) {
                    processedColumns.push({
                        originalIndices: [col.originalIndex, mergeTargetCol.originalIndex],
                        text: `${col.text} / ${mergeTargetCol.text}`,
                        isMerged: true,
                        printWidth: col.printWidth || mergeTargetCol.printWidth || 'normal',
                        totalMode: col.totalMode === 'sum' || mergeTargetCol.totalMode === 'sum' ? 'sum' : 'none',
                    });
                    mergedIndices.add(col.originalIndex);
                    mergedIndices.add(mergeTargetCol.originalIndex);
                } else {
                    processedColumns.push({
                        originalIndices: [col.originalIndex],
                        text: col.text,
                        isMerged: false,
                        printWidth: col.printWidth || 'normal',
                        totalMode: col.totalMode || 'none',
                    });
                    mergedIndices.add(col.originalIndex);
                }
            } else {
                processedColumns.push({
                    originalIndices: [col.originalIndex],
                    text: col.text,
                    isMerged: false,
                    printWidth: col.printWidth || 'normal',
                    totalMode: col.totalMode || 'none',
                });
                mergedIndices.add(col.originalIndex);
            }
        });

        const totalWeight = processedColumns.reduce((total, col) => total + (WIDTH_WEIGHTS[col.printWidth] || 1), 0) || 1;
        const columnWidths = processedColumns.map(col => `${(((WIDTH_WEIGHTS[col.printWidth] || 1) / totalWeight) * 100).toFixed(2)}%`);

        const rowRecords = Array.from(body.children).map(row => {
            const sourceCells = Array.from(row.querySelectorAll('span')).map(cell => cell.textContent.trim());
            const cells = processedColumns.map(col => {
                if (col.isMerged) {
                    return col.originalIndices.map(idx => sourceCells[idx] || '').filter(Boolean).join(' / ');
                }

                return sourceCells[col.originalIndices[0]] || '';
            });

            return {
                sourceCells,
                cells,
                groupValue: printSettings.groupBy !== '' ? sourceCells[Number(printSettings.groupBy)] || '-' : '',
                sortValue: printSettings.sortBy !== '' ? sourceCells[Number(printSettings.sortBy)] || '' : '',
            };
        }).filter(record => record.cells.some(Boolean));

        if (printSettings.sortBy !== '' || printSettings.groupBy !== '') {
            rowRecords.sort((a, b) => {
                const groupResult = String(a.groupValue).localeCompare(String(b.groupValue), undefined, {
                    numeric: true,
                    sensitivity: 'base',
                });
                const sortResult = String(a.sortValue).localeCompare(String(b.sortValue), undefined, {
                    numeric: true,
                    sensitivity: 'base',
                });
                const result = printSettings.groupBy !== '' && groupResult !== 0 ? groupResult : sortResult;
                return printSettings.sortDirection === 'desc' && printSettings.sortBy !== '' ? -result : result;
            });
        }

        const totalValues = processedColumns.map((col, columnIndex) => {
            if (col.totalMode !== 'sum') return '';
            return rowRecords.reduce((sum, record) => sum + parseNumber(record.cells[columnIndex]), 0);
        });

        return {
            processedColumns,
            columnWidths,
            rowRecords,
            totalValues,
            hasTotals: totalValues.some(value => value !== ''),
        };
    }

    function buildPrintStyle() {
        const pattern = printSettings.pattern || 'clean';
        const rowPadding = printSettings.density === 'compact' ? '5px 0' : printSettings.density === 'relaxed' ? '11px 0' : '8px 0';
        const rowBorder = printSettings.showGrid ? (pattern === 'ledger' ? '1px solid #9ca3af' : '1px solid #d1d5db') : '0';
        const cellBorder = printSettings.showGrid && pattern === 'boxed' ? '1px solid #d1d5db' : '0';
        const rowBackground = '#ffffff';
        const zebraBackground = '#f9fafb';
        const whiteSpace = printSettings.wrapText ? 'normal' : 'nowrap';

        return `
            @page {
                size: ${printSettings.paper} ${printSettings.orientation};
                margin: 16px;
            }
            body {
                margin: 0;
                padding: 0;
                background: #fff;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .container-parent, .card_container {
                display: block !important;
                overflow: visible !important;
                height: auto !important;
            }
            * {
                page-break-inside: auto;
                box-sizing: border-box;
            }
            .row, .record, tr, .card {
                page-break-inside: avoid;
                break-inside: avoid;
            }
            thead { display: table-header-group; }
            .scrollbar-hidden { overflow: visible !important; }
            body #table-head, #print-preview-surface #table-head {
                color: white !important;
                background: var(--primary-color) !important;
                font-size: ${printSettings.fontSize}px !important;
                display: flex !important;
                border: ${printSettings.showGrid ? '1px solid transparent' : '0'} !important;
                border-radius: 8px !important;
            }
            body #table-head > div, #print-preview-surface #table-head > div {
                flex-shrink: 0;
                flex-grow: 0;
                text-align: center;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: ${whiteSpace};
                padding: 0 4px;
            }
            body .report-group-title, #print-preview-surface .report-group-title {
                margin: 6px 0 0;
                padding: 5px 9px;
                border-top: ${rowBorder};
                border-bottom: ${rowBorder};
                background: #ffffff;
                color: #111827 !important;
                text-align: left;
                font-size: ${Math.max(8, printSettings.fontSize - 1)}px !important;
                font-weight: 700;
            }
            body .row, #print-preview-surface .row {
                display: flex !important;
                border-bottom: ${rowBorder};
                padding: ${rowPadding};
                background: ${rowBackground};
            }
            body .row:nth-child(even), #print-preview-surface .row:nth-child(even) {
                background: ${printSettings.zebra ? zebraBackground : rowBackground};
            }
            body .row span, #print-preview-surface .row span {
                color: black !important;
                font-size: ${printSettings.fontSize}px !important;
                flex-shrink: 0;
                flex-grow: 0;
                text-align: center;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: ${whiteSpace};
                padding: 0 4px;
                border-right: ${cellBorder};
            }
            body .row span:last-child, #print-preview-surface .row span:last-child { border-right: 0; }
            body .report-total-row, #print-preview-surface .report-total-row {
                font-weight: 700;
                border-top: ${rowBorder};
                border-bottom: ${rowBorder};
                background: #f9fafb !important;
            }
        `;
    }

    function buildPrintPayload(selectedColumns) {
        const report = buildReportData(selectedColumns);
        if (!report) return null;

        const headerHTML = `<div id="table-head" class="flex items-center bg-[var(--h-bg-color)] rounded-lg font-medium py-2 text-center px-4">
            ${report.processedColumns.map((col, index) => `
                <div style="width:${report.columnWidths[index]};min-width:${report.columnWidths[index]};max-width:${report.columnWidths[index]};flex:0 0 ${report.columnWidths[index]};">${escapeHtml(col.text)}</div>
            `).join('')}
        </div>`;

        const rows = [];
        let lastGroupValue = null;

        report.rowRecords.forEach((record, recordIndex) => {
            if (printSettings.groupBy !== '' && record.groupValue !== lastGroupValue) {
                lastGroupValue = record.groupValue;
                rows.push({
                    type: 'group',
                    html: `<div class="report-group-title">${escapeHtml(record.groupValue || '-')}</div>`,
                });
            }

            rows.push({
                type: 'row',
                html: `<div class="row report-row" data-row-index="${recordIndex}">
                    ${record.cells.map((cell, index) => `
                        <span style="width:${report.columnWidths[index]};min-width:${report.columnWidths[index]};max-width:${report.columnWidths[index]};flex:0 0 ${report.columnWidths[index]};">${escapeHtml(cell)}</span>
                    `).join('')}
                </div>`,
            });
        });

        if (report.hasTotals) {
            rows.push({
                type: 'total',
                html: `<div class="row report-total-row">
                    ${report.totalValues.map((value, index) => `
                        <span style="width:${report.columnWidths[index]};min-width:${report.columnWidths[index]};max-width:${report.columnWidths[index]};flex:0 0 ${report.columnWidths[index]};">${value === '' ? (index === 0 ? 'Total' : '') : escapeHtml(formatNumber(value))}</span>
                    `).join('')}
                </div>`,
            });
        }

        if (!rows.length) {
            return null;
        }

        let html = '';
        let currentRows = [];
        let height = 0;
        const rowHeight = printSettings.density === 'compact' ? 28 : printSettings.density === 'relaxed' ? 48 : 38;
        const groupHeight = printSettings.density === 'compact' ? 24 : 30;
        const maxHeight = printSettings.orientation === 'portrait' ? 960 : 700;
        const estimatedHeight = rows.reduce((total, row) => total + (row.type === 'group' ? groupHeight : rowHeight), 0);
        const totalPages = Math.max(1, Math.ceil(estimatedHeight / maxHeight));
        const companyText = [document.getElementById('page-name')?.textContent || printSettings.title, window.__clientCompanyName || ''].filter(Boolean).join(' | ');
        let pageNo = 1;

        rows.forEach((r, i) => {
            currentRows.push(r.html);
            height += r.type === 'group' ? groupHeight : rowHeight;

            if (height >= maxHeight || i === rows.length - 1) {
                html += `
                    <div class="print-page flex flex-col min-h-[750px]">
                        ${(printSettings.showCompany || printSettings.showDate) ? `
                            <div class="print-topbar px-4 w-full flex justify-between text-[12px] font-medium tracking-wide leading-none mb-2">
                                <div class="capitalize">${printSettings.showCompany ? escapeHtml(companyText) : ''}</div>
                                <div>${printSettings.showDate ? `Printed on: ${formatDate(new Date())}` : ''}</div>
                            </div>
                        ` : ''}
                        <h1 class="print-title px-4 mb-2 text-center text-[15px] font-bold uppercase tracking-wide">${escapeHtml(printSettings.title)}</h1>
                        ${headerHTML}
                        <div class="rows px-4 text-center">
                            ${currentRows.join('')}
                        </div>
                        <div class="grow"></div>
                        ${printSettings.showFooter ? `
                            <div class="px-4 w-full grid grid-cols-3 text-[12px] tracking-wide leading-none mt-3">
                                <div class="text-left">${printSettings.showRowCount ? `Showing ${report.rowRecords.length} Records` : ''}</div>
                                <div class="text-center">Powered by: <strong>SparkPair</strong></div>
                                <div class="text-right">Page ${pageNo} of ${totalPages}</div>
                            </div>
                        ` : ''}
                    </div>
                `;
                if (i !== rows.length - 1)
                    html += `<div style="page-break-after:always"></div>`;

                currentRows = [];
                height = 0;
                pageNo += 1;
            }
        });

        return {
            title: printSettings.title,
            html,
            style: buildPrintStyle(),
            data: report,
        };
    }

    function executePrintWithColumns(selectedColumns) {
        const payload = buildPrintPayload(selectedColumns);
        if (!payload) {
            appAlert('Printable data not found.');
            return;
        }

        saveLastLayout();
        window.DocumentPrint.printHtml({
            title: payload.title,
            html: payload.html,
            style: payload.style,
        });
    }

    window.printPage = function() {
        window.openPrintColumnModal();
    };

    if (typeof originalPrintPage === 'function') {
        window.__originalPrintPage = originalPrintPage;
    }
})();
