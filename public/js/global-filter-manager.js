/**
 * Global Filter Manager - Simple Filter & Append
 *
 * Behaviour:
 * - Initially loads 15 records
 * - Filters fetch ALL matching records
 * - Undefined layout ALWAYS defaults to table
 * - Grid is used only when explicitly defined as "grid"
 * - Safe JSON response handling
 * - Prevents stale AJAX responses from overwriting newer results
 */

let rootAuthLayout = 'table';

const GlobalFilterManager = {

    config: {
        initialLoadCount: 15,
        debounceDelay: 500,
    },

    // Used to prevent old requests from rendering after a newer request
    requestSequence: 0,

    init() {
        if (!document.querySelector('.search_container')) {
            return;
        }

        /*
         * IMPORTANT:
         * If no layout is explicitly defined anywhere,
         * currentLayout() MUST return table.
         */
        rootAuthLayout = this.resolveLayout();

        this.restoreSavedFilters();
        this.bindFilterEvents();
        this.bindShortcutEvents();

        if (Object.keys(this.collectFilters()).length > 0) {
            this.applyFilters({ persist: false });
        } else {
            this.loadInitialData();
        }
    },

    /* =========================================================
     * STORAGE
     * ========================================================= */

    storageKey(type) {
        return `garmentsos:${type}:${window.location.pathname}`;
    },

    readStorage(type) {
        try {
            return JSON.parse(
                localStorage.getItem(this.storageKey(type)) || '{}'
            );
        } catch (error) {
            console.warn(`Unable to read ${type} storage:`, error);
            return {};
        }
    },

    writeStorage(type, value) {
        try {
            localStorage.setItem(
                this.storageKey(type),
                JSON.stringify(value)
            );
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

    /* =========================================================
     * FILTER EVENTS
     * ========================================================= */

    bindFilterEvents() {

        /*
         * Auto-filter intentionally disabled for now.
         *
         * If you want it later, uncomment:
         *
         * document.querySelectorAll('[data-filter-path]').forEach(input => {
         *
         *     const eventType =
         *         input.type === 'date' ||
         *         input.classList.contains('dbInput')
         *             ? 'change'
         *             : 'input';
         *
         *     input.addEventListener(
         *         eventType,
         *         this.debounce(() => {
         *             this.applyFilters();
         *         }, this.config.debounceDelay)
         *     );
         * });
         */

        window.applyFilters = () => {
            this.applyFilters();
        };

        window.clearAllSearchFields = () => {

            document.querySelectorAll('[data-clearable]').forEach(field => {

                field.value = '';

                if (field.matches?.('[data-list-input]')) {
                    field.dataset.listInputValues = '';
                }
            });

            if (typeof closeAllDropdowns === 'function') {
                closeAllDropdowns();
            }

            if (typeof window.refreshListInput === 'function') {
                document
                    .querySelectorAll('[data-list-input]')
                    .forEach(input => {
                        window.refreshListInput(input);
                    });
            }

            this.clearSelectLabels();
            this.clearStorage('filters');

            this.loadInitialData();
        };
    },

    bindShortcutEvents() {

        document.addEventListener('keydown', (event) => {

            if (!document.querySelector('#search-form')) {
                return;
            }

            const activeElement = document.activeElement;

            const isTypingTarget =
                activeElement &&
                (
                    activeElement.tagName === 'INPUT' ||
                    activeElement.tagName === 'TEXTAREA' ||
                    activeElement.isContentEditable
                );

            /*
             * ` = open filter + focus first field
             */
            if (
                event.key === '`' &&
                !event.altKey &&
                !event.ctrlKey &&
                !event.metaKey &&
                !isTypingTarget
            ) {
                event.preventDefault();

                this.openFilterAndFocusFirstField();

                return;
            }

            if (
                !event.altKey ||
                event.ctrlKey ||
                event.metaKey
            ) {
                return;
            }

            const shortcutKey = event.key.toLowerCase();

            /*
             * Alt + F
             */
            if (shortcutKey === 'f') {
                event.preventDefault();
                this.toggleFilterPanel();
                return;
            }

            /*
             * Alt + S
             */
            if (shortcutKey === 's') {
                event.preventDefault();
                this.applyFilters();
                return;
            }

            /*
             * Alt + C
             */
            if (shortcutKey === 'c') {
                event.preventDefault();

                if (typeof window.clearAllSearchFields === 'function') {
                    window.clearAllSearchFields();
                }
            }
        });
    },

    /* =========================================================
     * INITIAL DATA
     * ========================================================= */

    async loadInitialData() {

        /*
         * IMPORTANT:
         * Resolve layout BEFORE rendering skeleton.
         */
        rootAuthLayout = this.resolveLayout();

        this.showLoading(true);
        this.emitLoading();

        const requestId = ++this.requestSequence;

        try {

            const url = this.buildUrl({
                limit: this.config.initialLoadCount
            });

            const data = await this.fetchData(url);

            /*
             * Ignore old request if a newer request already started.
             */
            if (requestId !== this.requestSequence) {
                return;
            }

            /*
             * Server response gets priority if explicitly defined.
             * Otherwise preserve current resolved layout.
             */
            rootAuthLayout = this.normalizeLayout(
                data?.authLayout
            ) || this.resolveLayout();

            this.syncGlobalLayout();

            this.renderData(data);

        } catch (error) {

            /*
             * Ignore errors from obsolete requests.
             */
            if (requestId !== this.requestSequence) {
                return;
            }

            console.error(
                '[GlobalFilterManager] Error loading initial data:',
                error
            );

            this.showRequestError(error);

        } finally {

            if (requestId === this.requestSequence) {
                this.showLoading(false);
                this.emitRendered();
            }
        }
    },

    /* =========================================================
     * APPLY FILTERS
     * ========================================================= */

    async applyFilters(options = {}) {

        const shouldPersist = options.persist !== false;

        const filters = this.collectFilters();

        if (typeof closeAllDropdowns === 'function') {
            closeAllDropdowns();
        }

        if (shouldPersist) {

            if (Object.keys(filters).length > 0) {

                this.writeStorage(
                    'filters',
                    filters
                );

            } else {

                this.clearStorage('filters');
            }
        }

        /*
         * No filters:
         * Return to initial 15-record load.
         */
        if (Object.keys(filters).length === 0) {

            await this.loadInitialData();

            return;
        }

        rootAuthLayout = this.resolveLayout();

        this.showLoading(true);
        this.emitLoading();

        const requestId = ++this.requestSequence;

        try {

            const url = this.buildUrl(filters);

            const data = await this.fetchData(url);

            /*
             * Ignore stale response.
             */
            if (requestId !== this.requestSequence) {
                return;
            }

            rootAuthLayout = this.normalizeLayout(
                data?.authLayout
            ) || this.resolveLayout();

            this.syncGlobalLayout();

            this.renderData(data);

        } catch (error) {

            if (requestId !== this.requestSequence) {
                return;
            }

            console.error(
                '[GlobalFilterManager] Error applying filters:',
                error
            );

            this.showRequestError(error);

        } finally {

            if (requestId === this.requestSequence) {
                this.showLoading(false);
                this.emitRendered();
            }
        }
    },

    /* =========================================================
     * FILTER PANEL
     * ========================================================= */

    getFilterTrigger() {
        return document.querySelector(
            '#search-form .dropdown-trigger'
        );
    },

    getFilterMenu() {
        return this.getFilterTrigger()?.nextElementSibling || null;
    },

    isFilterMenuOpen() {

        const menu = this.getFilterMenu();

        return !!menu &&
            !menu.classList.contains('hidden');
    },

    toggleFilterPanel() {

        const trigger = this.getFilterTrigger();

        if (!trigger) {
            return;
        }

        trigger.click();
    },

    openFilterAndFocusFirstField() {

        const trigger = this.getFilterTrigger();

        if (!trigger) {
            return;
        }

        if (!this.isFilterMenuOpen()) {
            trigger.click();
        }

        window.setTimeout(() => {
            this.focusFirstFilterField();
        }, 60);
    },

    focusFirstFilterField() {

        const menu = this.getFilterMenu();

        if (!menu) {
            return;
        }

        const firstField = menu.querySelector(
            '[data-filter-path]'
        );

        if (!firstField) {
            return;
        }

        if (firstField.classList.contains('dbInput')) {

            const targetId =
                firstField.getAttribute('data-for') ||
                firstField.id;

            if (!targetId) {
                return;
            }

            const visibleInput =
                menu.querySelector(
                    `#${CSS.escape(targetId)}`
                );

            if (visibleInput) {

                visibleInput.focus();

                if (
                    typeof visibleInput.select === 'function'
                ) {
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

    /* =========================================================
     * URL / FETCH
     * ========================================================= */

    buildUrl(params = {}) {

        const currentUrl =
            new URL(window.location.href);

        const searchParams =
            new URLSearchParams(params);

        /*
         * Preserve the current origin/path.
         */
        return `${currentUrl.pathname}?${searchParams.toString()}`;
    },

    async fetchData(url) {

        const response = await fetch(url, {
            method: 'GET',

            credentials: 'same-origin',

            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json, text/plain, */*',

                'X-CSRF-TOKEN':
                    document.querySelector(
                        'meta[name="csrf-token"]'
                    )?.content || ''
            }
        });

        /*
         * Read response as text first.
         *
         * This is IMPORTANT because:
         *
         * JSON.parse("<!DOCTYPE html>")
         *
         * gives:
         * Unexpected token '<'
         */
        const contentType =
            response.headers.get('content-type') || '';

        const responseText =
            await response.text();

        if (!response.ok) {

            const preview =
                responseText
                    .replace(/\s+/g, ' ')
                    .trim()
                    .slice(0, 500);

            throw new Error(
                `HTTP ${response.status} ${response.statusText}` +
                ` | URL: ${url}` +
                ` | Response: ${preview}`
            );
        }

        /*
         * If Laravel redirected to login or returned Blade HTML,
         * content-type will normally be text/html.
         */
        if (
            contentType.includes('text/html') ||
            /^\s*<!doctype html/i.test(responseText) ||
            /^\s*<html/i.test(responseText)
        ) {

            const preview =
                responseText
                    .replace(/\s+/g, ' ')
                    .trim()
                    .slice(0, 700);

            const isLoginPage =
                /<input[^>]+type=["']password["']/i.test(
                    responseText
                ) ||
                /login/i.test(
                    response.url || ''
                );

            throw new Error(
                isLoginPage
                    ? `Server returned the login HTML instead of JSON. ` +
                      `The request may have been redirected to login. ` +
                      `URL: ${response.url || url}`
                    : `Server returned HTML instead of JSON. ` +
                      `URL: ${response.url || url}` +
                      ` | Response: ${preview}`
            );
        }

        /*
         * Empty response is not valid JSON.
         */
        if (!responseText.trim()) {

            throw new Error(
                `Server returned an empty response. URL: ${url}`
            );
        }

        try {

            return JSON.parse(responseText);

        } catch (jsonError) {

            console.error(
                '[GlobalFilterManager] Invalid JSON response:',
                {
                    url,
                    status: response.status,
                    contentType,
                    responsePreview:
                        responseText
                            .replace(/\s+/g, ' ')
                            .trim()
                            .slice(0, 1000)
                }
            );

            throw new Error(
                `Invalid JSON response from server. ` +
                `Content-Type: ${contentType || 'unknown'}`
            );
        }
    },

    showRequestError(error) {

        console.error(
            '[GlobalFilterManager]',
            error?.message || error
        );

        /*
         * Don't show a duplicate alert if appAlert isn't available.
         */
        if (typeof window.appAlert === 'function') {

            window.appAlert(
                error?.message?.includes(
                    'HTML instead of JSON'
                )
                    ? 'Server returned an unexpected HTML response. Please check the route or session.'
                    : 'Failed to load data. Please try again.'
            );
        }
    },

    /* =========================================================
     * FILTER COLLECTION
     * ========================================================= */

    collectFilters() {

        const filters = {};

        document
            .querySelectorAll('[data-filter-path]')
            .forEach(input => {

                const value =
                    input.matches?.('[data-list-input]') &&
                    typeof window.getListInputValue === 'function'
                        ? window.getListInputValue(input).trim()
                        : input.value?.trim();

                if (!value) {
                    return;
                }

                /*
                 * Use input id as filter key.
                 */
                const key =
                    input.id ||
                    input.getAttribute('data-for');

                if (!key) {
                    return;
                }

                filters[key] = value;
            });

        this.completeDateRangeFilters(filters);

        return filters;
    },

    completeDateRangeFilters(filters) {

        const rangeGroups = {};

        document
            .querySelectorAll(
                'input[type="date"][data-filter-path]'
            )
            .forEach(input => {

                const path =
                    input.getAttribute(
                        'data-filter-path'
                    );

                if (!path) {
                    return;
                }

                rangeGroups[path] ??= [];

                rangeGroups[path].push(input);
            });

        Object.values(rangeGroups).forEach(inputs => {

            if (inputs.length < 2) {
                return;
            }

            const startInput =
                inputs.find(input =>
                    /(^|_)(start|from)$/i.test(input.id)
                ) || inputs[0];

            const endInput =
                inputs.find(input =>
                    /(^|_)(end|to)$/i.test(input.id)
                ) || inputs[1];

            if (!startInput || !endInput) {
                return;
            }

            const startKey = startInput.id;
            const endKey = endInput.id;

            const hasStart =
                !!filters[startKey];

            const hasEnd =
                !!filters[endKey];

            if (hasStart && !hasEnd) {

                filters[endKey] = '9999-12-31';

                endInput.value =
                    filters[endKey];

            } else if (!hasStart && hasEnd) {

                filters[startKey] = '1900-01-01';

                startInput.value =
                    filters[startKey];
            }
        });
    },

    /* =========================================================
     * RESTORE FILTERS
     * ========================================================= */

    restoreSavedFilters() {

        const savedFilters =
            this.readStorage('filters');

        Object.entries(savedFilters)
            .forEach(([key, value]) => {

                const escapedKey =
                    CSS.escape(key);

                const input =
                    document.querySelector(
                        `[data-for="${escapedKey}"].dbInput`
                    ) ||
                    document.getElementById(key) ||
                    document.querySelector(
                        `[data-filter-path][id="${escapedKey}"]`
                    );

                if (!input) {
                    return;
                }

                input.value = value;

                if (
                    input.classList.contains('dbInput')
                ) {
                    this.syncSelectLabel(input);
                }

                if (
                    input.matches?.('[data-list-input]') &&
                    typeof window.refreshListInput === 'function'
                ) {

                    input.dataset.listInputValues =
                        value;

                    input.value = '';

                    window.refreshListInput(input);
                }
            });
    },

    syncSelectLabel(dbInput) {

        const forId =
            dbInput.getAttribute('data-for');

        if (!forId) {
            return;
        }

        const scope =
            dbInput.closest('form') ||
            document;

        const escapedForId =
            CSS.escape(forId);

        const visibleInput =
            scope.querySelector(
                `#${escapedForId}`
            );

        const selectedOption =
            scope.querySelector(
                `.optionsDropdown li[data-for="${escapedForId}"][data-value="${CSS.escape(dbInput.value)}"]`
            );

        if (
            visibleInput &&
            selectedOption
        ) {
            visibleInput.value =
                selectedOption.textContent.trim();
        }

        scope
            .querySelectorAll(
                `.optionsDropdown li[data-for="${escapedForId}"]`
            )
            .forEach(li => {

                li.classList.toggle(
                    'selected',
                    li === selectedOption
                );
            });
    },

    clearSelectLabels() {

        document
            .querySelectorAll('.dbInput[data-for]')
            .forEach(dbInput => {

                const forId =
                    dbInput.getAttribute('data-for');

                if (!forId) {
                    return;
                }

                const scope =
                    dbInput.closest('form') ||
                    document;

                const escapedForId =
                    CSS.escape(forId);

                const visibleInput =
                    scope.querySelector(
                        `#${escapedForId}`
                    );

                const defaultOption =
                    scope.querySelector(
                        `.optionsDropdown li[data-for="${escapedForId}"][data-value=""]`
                    );

                if (visibleInput) {

                    visibleInput.value =
                        defaultOption
                            ? defaultOption.textContent.trim()
                            : '';
                }

                scope
                    .querySelectorAll(
                        `.optionsDropdown li[data-for="${escapedForId}"]`
                    )
                    .forEach(li => {

                        li.classList.toggle(
                            'selected',
                            li === defaultOption
                        );
                    });
            });
    },

    /* =========================================================
     * LAYOUT
     * ========================================================= */

    normalizeLayout(layout) {
        if (typeof layout !== 'string') {
            return null;
        }

        const normalized = layout.trim().toLowerCase();

        if (normalized === 'grid') {
            return 'grid';
        }

        if (normalized === 'table') {
            return 'table';
        }

        return null;
    },

    resolveLayout() {
        /*
        * IMPORTANT:
        * GlobalFilterManager must NOT inherit layout from:
        *
        * window.authLayout
        * window.__authLayout
        * previous page
        *
        * Only use an explicitly declared layout on THIS page.
        */

        const layoutButton = document.getElementById('changeLayoutBtn');

        const explicitButtonLayout = this.normalizeLayout(
            layoutButton?.dataset?.layout
        );

        if (explicitButtonLayout) {
            return explicitButtonLayout;
        }

        /*
        * Page-specific explicit declaration.
        *
        * If your Blade defines:
        *
        * window.__pendingAuthLayout = 'grid'
        *
        * then grid is allowed.
        */
        const explicitPageLayout = this.normalizeLayout(
            window.__pendingAuthLayout
        );

        if (explicitPageLayout) {
            return explicitPageLayout;
        }

        /*
        * EVERYTHING ELSE = TABLE
        */
        return 'table';
    },

    currentLayout() {
        /*
        * Only these two values are ever allowed.
        * Undefined = table.
        */
        return rootAuthLayout === 'grid'
            ? 'grid'
            : 'table';
    },

    syncGlobalLayout() {
        rootAuthLayout =
            rootAuthLayout === 'grid'
                ? 'grid'
                : 'table';

        /*
        * Do NOT write back to window.authLayout.
        *
        * That can leak this page's layout into another page.
        */
    },

    /* =========================================================
     * RENDER DATA
     * ========================================================= */

    renderData(response) {

        const container =
            document.querySelector(
                '.search_container'
            );

        const noItemsError =
            document.getElementById(
                'noItemsError'
            );

        if (!container) {
            return;
        }

        /*
         * Support:
         * { data: [...] }
         * { items: [...] }
         * [...]
         */
        const items =
            response?.data ||
            response?.items ||
            response ||
            [];

        window.allDataArray =
            Array.isArray(items)
                ? items
                : [];

        window.visibleData =
            window.allDataArray;

        /*
         * Server layout only if explicitly supplied.
         */
        const serverLayout =
            this.normalizeLayout(
                response?.authLayout
            );

        if (serverLayout) {
            rootAuthLayout =
                serverLayout;
        } else {
            /*
             * IMPORTANT:
             * Never accidentally switch to grid.
             */
            rootAuthLayout =
                rootAuthLayout === 'grid'
                    ? 'grid'
                    : 'table';
        }

        this.syncGlobalLayout();

        const calculations =
            response?.calculations || {};

        if (
            typeof window.renderCalculation ===
            'function'
        ) {
            window.renderCalculation(
                calculations
            );
        }

        /*
         * Use existing page rendering functions.
         */
        if (
            typeof window.createCard === 'function' ||
            typeof window.createRow === 'function'
        ) {

            this.renderWithExistingFunctions(
                window.allDataArray
            );

            this.scrollResultsToTop(
                container
            );

        } else {

            console.warn(
                '[GlobalFilterManager] No createCard or createRow function found.'
            );
        }

        if (
            typeof window.applyPersistedSort ===
            'function'
        ) {
            window.applyPersistedSort();
        }

        document.dispatchEvent(
            new CustomEvent(
                'app:data:rendered',
                {
                    detail: {
                        items:
                            window.allDataArray
                    }
                }
            )
        );

        if (noItemsError) {

            noItemsError.style.display =
                window.allDataArray.length === 0
                    ? 'block'
                    : 'none';
        }
    },

    scrollResultsToTop(container) {

        const scrollParent =
            container?.closest(
                '.overflow-y-auto, .my-scrollbar-2'
            );

        if (scrollParent) {

            scrollParent.scrollTop = 0;

            return;
        }

        container?.scrollIntoView?.({
            block: 'start',
            behavior: 'instant'
        });
    },

    emitLoading() {

        document.dispatchEvent(
            new CustomEvent(
                'app:data:loading'
            )
        );
    },

    emitRendered() {

        document.dispatchEvent(
            new CustomEvent(
                'app:data:rendered',
                {
                    detail: {
                        items:
                            window.allDataArray || []
                    }
                }
            )
        );
    },

    renderWithExistingFunctions(items) {

        const container =
            document.querySelector(
                '.search_container'
            );

        const tableHead =
            document.getElementById(
                'table-head'
            );

        if (!container) {
            return;
        }

        /*
         * IMPORTANT:
         * Layout comes ONLY from rootAuthLayout.
         */
        const isGrid =
            rootAuthLayout === 'grid';

        if (isGrid) {

            if (tableHead) {
                tableHead.classList.add('hidden');
            }

            container.className =
                'search_container grid grid-cols-1 sm:grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5 pt-4 px-2';

            if (
                typeof window.createCard ===
                'function'
            ) {

                container.innerHTML =
                    items
                        .map(item =>
                            window.createCard(item)
                        )
                        .join('');
            }

        } else {

            /*
             * TABLE MODE
             */
            if (tableHead) {
                tableHead.classList.remove(
                    'hidden'
                );
            }

            container.className =
                'search_container';

            if (
                typeof window.createRow ===
                'function'
            ) {

                container.innerHTML =
                    items
                        .map(item =>
                            window.createRow(item)
                        )
                        .join('');

            } else {

                /*
                 * If this is a table-only page
                 * but createRow is missing, do NOT
                 * try createCard.
                 */
                console.warn(
                    '[GlobalFilterManager] Table layout selected but createRow() is not available.'
                );
            }
        }
    },

    /* =========================================================
     * LOADING SKELETON
     * ========================================================= */

    showLoading(show) {

        const container =
            document.querySelector(
                '.search_container'
            );

        const tableHead =
            document.getElementById(
                'table-head'
            );

        if (!container) {
            return;
        }

        if (show) {

            container.dataset.loadingSkeleton =
                'true';

            container.classList.add(
                'pointer-events-none'
            );

            this.renderSkeleton(
                container,
                tableHead
            );

        } else {

            delete container.dataset.loadingSkeleton;

            container.classList.remove(
                'pointer-events-none'
            );
        }
    },

    renderSkeleton(
        container,
        tableHead
    ) {

        /*
         * IMPORTANT FIX:
         *
         * DO NOT call currentLayout()
         * here.
         *
         * currentLayout() could potentially
         * resolve a stale/global grid value.
         *
         * Skeleton uses the already-resolved
         * rootAuthLayout.
         */
        const isGrid =
            rootAuthLayout === 'grid';

        this.ensureSkeletonStyles();

        /*
         * =====================================================
         * GRID SKELETON
         * =====================================================
         */
        if (isGrid) {

            if (tableHead) {
                tableHead.classList.add(
                    'hidden'
                );
            }

            container.className =
                'search_container grid grid-cols-1 sm:grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5 pt-4 px-2 pointer-events-none';

            container.innerHTML =
                Array.from({
                    length: 6
                })
                    .map(() => `
                        <div class="gos-skeleton-card rounded-xl border border-[var(--glass-border-color)]/10 bg-[var(--secondary-bg-color)] p-4 shadow-sm">

                            <div class="flex items-center gap-3">

                                <div class="gos-skeleton-block size-11 rounded-xl"></div>

                                <div class="grow space-y-2">

                                    <div
                                        class="gos-skeleton-block h-3 rounded"
                                        style="width: 42%"
                                    ></div>

                                    <div
                                        class="gos-skeleton-block h-4 rounded"
                                        style="width: 64%"
                                    ></div>

                                </div>

                            </div>

                            <div class="mt-4 grid grid-cols-2 gap-3">

                                <div class="gos-skeleton-block h-8 rounded-lg"></div>
                                <div class="gos-skeleton-block h-8 rounded-lg"></div>
                                <div class="gos-skeleton-block h-8 rounded-lg"></div>
                                <div class="gos-skeleton-block h-8 rounded-lg"></div>

                            </div>

                        </div>
                    `)
                    .join('');

            return;
        }

        /*
         * =====================================================
         * TABLE SKELETON
         * =====================================================
         */

        if (tableHead) {
            tableHead.classList.remove(
                'hidden'
            );
        }

        container.className =
            'search_container pointer-events-none';

        const headerCells =
            tableHead
                ? Array.from(
                    tableHead.children
                )
                : [];

        /*
         * If table header exists,
         * create skeleton based on actual columns.
         */
        if (headerCells.length) {

            const rowLayoutClass =
                tableHead.className
                    .replace(
                        /\bhidden\b/g,
                        ''
                    )
                    .replace(
                        /\bbg-\[[^\]]+\]/g,
                        ''
                    )
                    .replace(
                        /\brounded-\S+/g,
                        ''
                    )
                    .replace(
                        /\bmt-\S+/g,
                        ''
                    )
                    .replace(
                        /\bfont-\S+/g,
                        ''
                    )
                    .replace(
                        /\bpy-\S+/g,
                        'py-2'
                    )
                    .replace(
                        /\s+/g,
                        ' '
                    )
                    .trim();

            container.innerHTML =
                Array.from({
                    length: 12
                })
                    .map(
                        (_, rowIndex) => `
                            <div class="${rowLayoutClass} border-b border-[var(--h-bg-color)] text-xs">

                                ${headerCells
                                    .map(
                                        (
                                            header,
                                            columnIndex
                                        ) => {

                                            const alignClass =
                                                header.className.includes(
                                                    'text-right'
                                                )
                                                    ? 'justify-end'
                                                    : (
                                                        header.className.includes(
                                                            'text-center'
                                                        )
                                                            ? 'justify-center'
                                                            : 'justify-start'
                                                    );

                                            const widths = [
                                                72,
                                                48,
                                                61,
                                                82,
                                                36
                                            ];

                                            const widthPercent =
                                                widths[
                                                    (
                                                        rowIndex +
                                                        columnIndex
                                                    ) %
                                                    widths.length
                                                ];

                                            return `
                                                <div class="${header.className} flex ${alignClass} px-2 cursor-default">

                                                    <span
                                                        class="gos-skeleton-block block h-3 rounded"
                                                        style="width: ${widthPercent}%"
                                                    ></span>

                                                </div>
                                            `;
                                        }
                                    )
                                    .join('')}

                            </div>
                        `
                    )
                    .join('');

            return;
        }

        /*
         * Fallback table skeleton
         */
        container.innerHTML =
            Array.from({
                length: 12
            })
                .map(() => `
                    <div class="flex items-center border-b border-[var(--h-bg-color)] py-2 text-xs">

                        <div
                            class="gos-skeleton-block mx-2 h-3 rounded"
                            style="width: 10%"
                        ></div>

                        <div
                            class="gos-skeleton-block mx-2 h-3 rounded"
                            style="width: 16%"
                        ></div>

                        <div
                            class="gos-skeleton-block mx-2 h-3 rounded"
                            style="width: 7%"
                        ></div>

                        <div
                            class="gos-skeleton-block mx-2 h-3 rounded"
                            style="width: 13%"
                        ></div>

                        <div
                            class="gos-skeleton-block mx-2 h-3 rounded"
                            style="width: 9%"
                        ></div>

                        <div
                            class="gos-skeleton-block mx-2 h-3 rounded"
                            style="width: 18%"
                        ></div>

                        <div
                            class="gos-skeleton-block mx-2 h-3 grow rounded"
                        ></div>

                    </div>
                `)
                .join('');
    },

    ensureSkeletonStyles() {

        if (
            document.getElementById(
                'gos-skeleton-styles'
            )
        ) {
            return;
        }

        const style =
            document.createElement('style');

        style.id =
            'gos-skeleton-styles';

        style.textContent = `

            .gos-skeleton-block {
                min-width: 1.75rem;
                background-color: var(--h-bg-color);
                background-image:
                    linear-gradient(
                        90deg,
                        transparent,
                        rgba(255, 255, 255, 0.34),
                        transparent
                    );
                background-size: 220% 100%;
                animation:
                    gos-skeleton-shimmer
                    1.15s
                    ease-in-out
                    infinite;
                box-shadow:
                    inset
                    0
                    0
                    0
                    1px
                    rgba(255, 255, 255, 0.04);
                opacity: 0.92;
            }

            .gos-skeleton-card {
                animation:
                    gos-skeleton-soft
                    1.25s
                    ease-in-out
                    infinite;
            }

            @keyframes gos-skeleton-shimmer {

                0% {
                    background-position: 120% 0;
                }

                100% {
                    background-position: -120% 0;
                }

            }

            @keyframes gos-skeleton-soft {

                0%,
                100% {
                    opacity: 0.78;
                }

                50% {
                    opacity: 1;
                }

            }

        `;

        document.head.appendChild(style);
    },

    /* =========================================================
     * UTILITY
     * ========================================================= */

    debounce(func, wait) {

        let timeout;

        return function (...args) {

            clearTimeout(timeout);

            timeout = setTimeout(() => {

                func.apply(
                    this,
                    args
                );

            }, wait);
        };
    }
};


/* =============================================================
 * AUTO INITIALIZE
 * ============================================================= */

document.addEventListener(
    'DOMContentLoaded',
    () => {
        GlobalFilterManager.init();
    }
);


/* =============================================================
 * GLOBAL ACCESS
 * ============================================================= */

window.GlobalFilterManager =
    GlobalFilterManager;