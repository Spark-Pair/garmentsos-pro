/**
 * Global Filter Manager - Simple Filter & Append
 * Initially loads 15 records, filters fetch ALL matching records
 */

let rootAuthLayout = 'table';

const GlobalFilterManager = {
    config: {
        initialLoadCount: 15,
        debounceDelay: 500,
    },

    init() {
        if (!document.querySelector('.search_container')) return;

        this.restoreSavedFilters();
        this.bindFilterEvents();
        this.bindShortcutEvents();

        if (Object.keys(this.collectFilters()).length > 0) {
            this.applyFilters({ persist: false });
        } else {
            this.loadInitialData();
        }
    },

    storageKey(type) {
        return `garmentsos:${type}:${window.location.pathname}`;
    },

    readStorage(type) {
        try {
            return JSON.parse(localStorage.getItem(this.storageKey(type)) || '{}');
        } catch (error) {
            return {};
        }
    },

    writeStorage(type, value) {
        try {
            localStorage.setItem(this.storageKey(type), JSON.stringify(value));
        } catch (error) {
            console.warn(`Unable to persist ${type}:`, error);
        }
    },

    clearStorage(type) {
        try {
            localStorage.removeItem(this.storageKey(type));
        } catch (error) {
            console.warn(`Unable to clear ${type}:`, error);
        }
    },

    bindFilterEvents() {
        // Auto-filter on input (debounced)
        // document.querySelectorAll('[data-filter-path]').forEach(input => {
        //     const eventType = input.type === 'date' || input.classList.contains('dbInput')
        //         ? 'change'
        //         : 'input';

        //     input.addEventListener(eventType, this.debounce(() => {
        //         this.applyFilters();
        //     }, this.config.debounceDelay));
        // });

        // // Override global applyFilters
        window.applyFilters = () => this.applyFilters();

        // Override global clearAllSearchFields
        window.clearAllSearchFields = () => {
            document.querySelectorAll('[data-clearable]').forEach(field => {
                field.value = '';
                if (field.matches?.('[data-list-input]')) {
                    field.dataset.listInputValues = '';
                }
            });
            if (typeof window.refreshListInput === 'function') {
                window.refreshListInput();
            }
            this.clearSelectLabels();
            this.clearStorage('filters');
            this.loadInitialData();
        };
    },

    bindShortcutEvents() {
        document.addEventListener('keydown', (event) => {
            if (!document.querySelector('#search-form')) return;

            const activeElement = document.activeElement;
            const isTypingTarget = activeElement && (
                activeElement.tagName === 'INPUT' ||
                activeElement.tagName === 'TEXTAREA' ||
                activeElement.isContentEditable
            );

            if (event.key === '`' && !event.altKey && !event.ctrlKey && !event.metaKey && !isTypingTarget) {
                event.preventDefault();
                this.openFilterAndFocusFirstField();
                return;
            }

            if (!event.altKey || event.ctrlKey || event.metaKey) return;

            const shortcutKey = event.key.toLowerCase();

            if (shortcutKey === 'f') {
                event.preventDefault();
                this.toggleFilterPanel();
                return;
            }

            if (shortcutKey === 's') {
                event.preventDefault();
                this.applyFilters();
                return;
            }

            if (shortcutKey === 'c') {
                event.preventDefault();
                window.clearAllSearchFields();
            }
        });
    },

    async loadInitialData() {
        this.showLoading(true);

        try {
            const url = this.buildUrl({ limit: this.config.initialLoadCount });
            const data = await this.fetchData(url);

            rootAuthLayout = data.authLayout || window.__authLayout || window.authLayout || rootAuthLayout;

            this.renderData(data);

        } catch (error) {
            console.error('Error loading initial data:', error);
        } finally {
            this.showLoading(false);
        }
    },

    async applyFilters(options = {}) {
        const shouldPersist = options.persist !== false;
        const filters = this.collectFilters();

        if (shouldPersist) {
            if (Object.keys(filters).length > 0) {
                this.writeStorage('filters', filters);
            } else {
                this.clearStorage('filters');
            }
        }

        // If no filters, load initial data
        if (Object.keys(filters).length === 0) {
            this.loadInitialData();
            return;
        }

        this.showLoading(true);

        try {
            const url = this.buildUrl(filters);
            const data = await this.fetchData(url);

            this.renderData(data);

        } catch (error) {
            console.error('Error applying filters:', error);
            appAlert('Failed to apply filters. Please try again.');
        } finally {
            this.showLoading(false);
        }
    },

    getFilterTrigger() {
        return document.querySelector('#search-form .dropdown-trigger');
    },

    getFilterMenu() {
        return this.getFilterTrigger()?.nextElementSibling || null;
    },

    isFilterMenuOpen() {
        const menu = this.getFilterMenu();
        return !!menu && !menu.classList.contains('hidden');
    },

    toggleFilterPanel() {
        const trigger = this.getFilterTrigger();
        if (!trigger) return;
        trigger.click();
    },

    openFilterAndFocusFirstField() {
        const trigger = this.getFilterTrigger();
        if (!trigger) return;

        if (!this.isFilterMenuOpen()) {
            trigger.click();
        }

        window.setTimeout(() => {
            this.focusFirstFilterField();
        }, 60);
    },

    focusFirstFilterField() {
        const menu = this.getFilterMenu();
        if (!menu) return;

        const firstField = menu.querySelector('[data-filter-path]');
        if (!firstField) return;

        if (firstField.classList.contains('dbInput')) {
            const targetId = firstField.getAttribute('data-for') || firstField.id;
            const visibleInput = menu.querySelector(`#${CSS.escape(targetId)}`);
            if (visibleInput) {
                visibleInput.focus();
                if (typeof visibleInput.select === 'function') {
                    visibleInput.select();
                }
                return;
            }
        }

        firstField.focus();
        if (typeof firstField.select === 'function') {
            firstField.select();
        }
    },

    buildUrl(params) {
        const currentUrl = new URL(window.location.href);
        const searchParams = new URLSearchParams(params);
        return `${currentUrl.pathname}?${searchParams.toString()}`;
    },

    async fetchData(url) {
        const response = await fetch(url, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
            }
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        return await response.json();
    },

    collectFilters() {
        const filters = {};

        document.querySelectorAll('[data-filter-path]').forEach(input => {
            const value = input.matches?.('[data-list-input]') && typeof window.getListInputValue === 'function'
                ? window.getListInputValue(input).trim()
                : input.value?.trim();

            if (value && value !== '') {
                // Use input id as filter key
                const key = input.id || input.getAttribute('data-for');
                filters[key] = value;
            }
        });

        this.completeDateRangeFilters(filters);

        return filters;
    },

    completeDateRangeFilters(filters) {
        const rangeGroups = {};

        document.querySelectorAll('input[type="date"][data-filter-path]').forEach(input => {
            const path = input.getAttribute('data-filter-path');
            if (!path) return;

            rangeGroups[path] ??= [];
            rangeGroups[path].push(input);
        });

        Object.values(rangeGroups).forEach(inputs => {
            if (inputs.length < 2) return;

            const startInput = inputs.find(input => /(^|_)(start|from)$/i.test(input.id)) || inputs[0];
            const endInput = inputs.find(input => /(^|_)(end|to)$/i.test(input.id)) || inputs[1];
            if (!startInput || !endInput) return;

            const startKey = startInput.id;
            const endKey = endInput.id;
            const hasStart = !!filters[startKey];
            const hasEnd = !!filters[endKey];

            if (hasStart && !hasEnd) {
                filters[endKey] = '9999-12-31';
                endInput.value = filters[endKey];
            } else if (!hasStart && hasEnd) {
                filters[startKey] = '1900-01-01';
                startInput.value = filters[startKey];
            }
        });
    },

    restoreSavedFilters() {
        const savedFilters = this.readStorage('filters');
        Object.entries(savedFilters).forEach(([key, value]) => {
            const input = document.querySelector(`[data-for="${CSS.escape(key)}"].dbInput`)
                || document.getElementById(key)
                || document.querySelector(`[data-filter-path][id="${CSS.escape(key)}"]`);

            if (!input) return;

            input.value = value;

            if (input.classList.contains('dbInput')) {
                this.syncSelectLabel(input);
            }

            if (input.matches?.('[data-list-input]') && typeof window.refreshListInput === 'function') {
                input.dataset.listInputValues = value;
                input.value = '';
                window.refreshListInput(input);
            }
        });
    },

    syncSelectLabel(dbInput) {
        const forId = dbInput.getAttribute('data-for');
        if (!forId) return;

        const scope = dbInput.closest('form') || document;
        const visibleInput = scope.querySelector(`#${CSS.escape(forId)}`);
        const selectedOption = scope.querySelector(`.optionsDropdown li[data-for="${CSS.escape(forId)}"][data-value="${CSS.escape(dbInput.value)}"]`);

        if (visibleInput && selectedOption) {
            visibleInput.value = selectedOption.textContent.trim();
        }

        scope.querySelectorAll(`.optionsDropdown li[data-for="${CSS.escape(forId)}"]`).forEach(li => {
            li.classList.toggle('selected', li === selectedOption);
        });
    },

    clearSelectLabels() {
        document.querySelectorAll('.dbInput[data-for]').forEach(dbInput => {
            const forId = dbInput.getAttribute('data-for');
            if (!forId) return;

            const scope = dbInput.closest('form') || document;
            const visibleInput = scope.querySelector(`#${CSS.escape(forId)}`);
            const defaultOption = scope.querySelector(`.optionsDropdown li[data-for="${CSS.escape(forId)}"][data-value=""]`);

            if (visibleInput) {
                visibleInput.value = defaultOption ? defaultOption.textContent.trim() : '';
            }

            scope.querySelectorAll(`.optionsDropdown li[data-for="${CSS.escape(forId)}"]`).forEach(li => {
                li.classList.toggle('selected', li === defaultOption);
            });
        });
    },

    renderData(response) {
        const container = document.querySelector('.search_container');
        const noItemsError = document.getElementById('noItemsError');

        if (!container) return;

        // Get data array from response
        const items = response.data || response.items || response;
        window.allDataArray = Array.isArray(items) ? items : [];
        window.visibleData = window.allDataArray;

        rootAuthLayout = response.authLayout || window.__authLayout || window.authLayout || rootAuthLayout;
        const calculations = response.calculations || {};
        if (typeof window.renderCalculation === 'function') {
            window.renderCalculation(calculations);
        }

        // Use existing page-specific rendering functions
        if (typeof window.createCard === 'function' || typeof window.createRow === 'function') {
            this.renderWithExistingFunctions(items);
        } else {
            console.warn('No createCard or createRow function found');
        }

        if (typeof window.applyPersistedSort === 'function') {
            window.applyPersistedSort();
        }

        document.dispatchEvent(new CustomEvent('app:data:rendered', { detail: { items: window.allDataArray } }));

        // Show/hide no results
        if (noItemsError) {
            noItemsError.style.display = items.length === 0 ? 'block' : 'none';
        }
    },

    renderWithExistingFunctions(items) {
        const container = document.querySelector('.search_container');
        const tableHead = document.getElementById('table-head');

        const isGrid = typeof rootAuthLayout !== 'undefined' && rootAuthLayout === 'grid';

        if (isGrid) {
            if (tableHead) tableHead.classList.add('hidden');
            container.className = 'search_container grid grid-cols-1 sm:grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5 pt-4 px-2';

            if (typeof window.createCard === 'function') {
                container.innerHTML = items.map(item => window.createCard(item)).join('');
            }
        } else {
            if (tableHead) tableHead.classList.remove('hidden');
            container.className = 'search_container';

            if (typeof window.createRow === 'function') {
                container.innerHTML = items.map(item => window.createRow(item)).join('');
            }
        }
    },

    showLoading(show) {
        let loading = document.getElementById('global-loading');

        if (!loading) {
            const container = document.querySelector('.search_container');
            if (!container) return;

            loading = document.createElement('div');
            loading.id = 'global-loading';
            loading.className = 'text-center py-8 hidden';
            loading.innerHTML = `
                <i class="fas fa-spinner fa-spin text-2xl text-[var(--primary-color)]"></i>
                <p class="text-sm text-[var(--secondary-text)] mt-2">Loading...</p>
            `;
            container.parentElement.insertBefore(loading, container);
        }

        const container = document.querySelector('.search_container');

        if (show) {
            loading.classList.remove('hidden');
            if (container) container.classList.add('opacity-50', 'pointer-events-none');
        } else {
            loading.classList.add('hidden');
            if (container) container.classList.remove('opacity-50', 'pointer-events-none');
        }
    },

    debounce(func, wait) {
        let timeout;
        return function(...args) {
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(this, args), wait);
        };
    }
};

// Auto-initialize
document.addEventListener('DOMContentLoaded', () => {
    GlobalFilterManager.init();
});

window.GlobalFilterManager = GlobalFilterManager;
