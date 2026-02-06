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

        this.loadInitialData();
        this.bindFilterEvents();
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
            });
            this.loadInitialData();
        };
    },

    async loadInitialData() {
        this.showLoading(true);

        try {
            const url = this.buildUrl({ limit: this.config.initialLoadCount });
            const data = await this.fetchData(url);

            rootAuthLayout = data.authLayout;

            this.renderData(data);

        } catch (error) {
            console.error('Error loading initial data:', error);
        } finally {
            this.showLoading(false);
        }
    },

    async applyFilters() {
        const filters = this.collectFilters();

        console.log(filters);


        // If no filters, load initial data
        if (Object.keys(filters).length === 0) {
            this.loadInitialData();
            return;
        }

        this.showLoading(true);

        try {
            const url = this.buildUrl(filters);
            const data = await this.fetchData(url);
console.log(data);

            this.renderData(data);

        } catch (error) {
            console.error('Error applying filters:', error);
            alert('Failed to apply filters. Please try again.');
        } finally {
            this.showLoading(false);
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
            const value = input.value?.trim();

            if (value && value !== '') {
            console.log(input);

                // Use input id as filter key
                const key = input.id || input.getAttribute('data-for');
                filters[key] = value;
            }
        });

        return filters;
    },

    renderData(response) {
        const container = document.querySelector('.search_container');
        const noItemsError = document.getElementById('noItemsError');

        if (!container) return;

        // Get data array from response
        const items = response.data || response.items || response;

        calculations = response.calculations;
        if (typeof window.renderCalculation === 'function') {
            window.renderCalculation(calculations);
        }

        // Use existing page-specific rendering functions
        if (typeof window.createCard === 'function' || typeof window.createRow === 'function') {
            this.renderWithExistingFunctions(items);
        } else {
            console.warn('No createCard or createRow function found');
        }

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
