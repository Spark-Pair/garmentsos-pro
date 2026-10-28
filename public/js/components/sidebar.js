(() => {
    function initSidebar() {
        if (window.__sidebarInitialized) {
            if (typeof window.renderMenuShortcuts === 'function') {
                window.renderMenuShortcuts();
            }
            if (typeof window.renderMobileMenuShortcuts === 'function') {
                window.renderMobileMenuShortcuts();
            }
            return;
        }
        window.__sidebarInitialized = true;

        const config = window.__sidebar || {};
        const menuData = config.menuData || [];
        let menuCategories = Array.isArray(config.menuCategories)
            ? config.menuCategories.map(category => ({
                id: category.id,
                name: category.name,
            }))
            : [];
        const categoryByModuleId = new Map();
        const configuredCategoryAssignments = config.menuCategoryAssignments && typeof config.menuCategoryAssignments === 'object'
            ? config.menuCategoryAssignments
            : {};
        Object.entries(configuredCategoryAssignments).forEach(([moduleId, categoryId]) => {
            if (menuCategories.some(category => category.id === categoryId)) {
                categoryByModuleId.set(moduleId, categoryId);
            }
        });
        menuData.forEach(module => {
            module.category = categoryByModuleId.get(module.id) ?? null;
        });
        const defaultMenuActions = menuData.flatMap(module => (module.subMenu || []).map((action, index) => ({
            ...action,
            sourceModuleId: module.id,
            defaultIndex: index,
        })));
        let savedActionNavigation = config.menuActionNavigation || { assignments: {}, order: {} };
        let workingActionAssignments = { ...(savedActionNavigation.assignments || {}) };
        let workingActionOrder = Object.fromEntries(Object.entries(savedActionNavigation.order || {}).map(([id, hrefs]) => [id, [...hrefs]]));

        function applyMenuActionNavigation(assignments = workingActionAssignments, order = workingActionOrder) {
            const validModuleIds = new Set(menuData.map(module => module.id));
            menuData.forEach(module => { module.subMenu = []; });
            defaultMenuActions.forEach(action => {
                const destination = validModuleIds.has(assignments[action.href]) ? assignments[action.href] : action.sourceModuleId;
                menuData.find(module => module.id === destination)?.subMenu.push({ name: action.name, href: action.href });
            });
            menuData.forEach(module => {
                const positions = new Map((order[module.id] || []).map((href, index) => [href, index]));
                module.subMenu.sort((a, b) => {
                    const aPosition = positions.has(a.href) ? positions.get(a.href) : Number.MAX_SAFE_INTEGER;
                    const bPosition = positions.has(b.href) ? positions.get(b.href) : Number.MAX_SAFE_INTEGER;
                    if (aPosition !== bPosition) return aPosition - bPosition;
                    return defaultMenuActions.findIndex(action => action.href === a.href)
                        - defaultMenuActions.findIndex(action => action.href === b.href);
                });
            });
        }
        applyMenuActionNavigation();
        let availableMenuCategories = config.canManageMenuCategories === true
            ? menuCategories
            : menuCategories.filter(category => menuData.some(module => module.category === category.id));
        const pageName = window.location.href.toLowerCase().split('/')[3];
        const sidebarShell = document.getElementById('desktopSidebarShell');
        const sidebarToggle = document.getElementById('sidebarToggle');
        const sidebarStateUrl = config.updateStateUrl;
        const canManageMenuCategories = config.canManageMenuCategories === true;
        const updateMenuCategoriesUrl = config.updateMenuCategoriesUrl;
        let preferredSidebarExpanded = config.expanded === true;
        let sidebarSaveQueue = Promise.resolve();
        let sidebarCollapseTimer;

        function setSidebarExpanded(expanded) {
            const isDesktop = window.matchMedia('(min-width: 768px)').matches;
            const shouldExpand = isDesktop && expanded;
            const wasExpanded = sidebarShell?.classList.contains('is-expanded');

            window.clearTimeout(sidebarCollapseTimer);
            if (shouldExpand) {
                sidebarShell?.classList.remove('is-collapsing');
                sidebarShell?.classList.add('is-expanded');
            } else if (wasExpanded) {
                sidebarShell?.classList.add('is-collapsing');
                sidebarShell?.classList.remove('is-expanded');
                sidebarCollapseTimer = window.setTimeout(() => {
                    sidebarShell?.classList.remove('is-collapsing');
                }, 240);
            } else {
                sidebarShell?.classList.remove('is-expanded', 'is-collapsing');
            }

            sidebarToggle?.setAttribute('aria-expanded', shouldExpand ? 'true' : 'false');
            sidebarToggle?.setAttribute('aria-label', shouldExpand ? 'Collapse sidebar' : 'Expand sidebar');
            if (sidebarToggle) sidebarToggle.title = shouldExpand ? 'Collapse sidebar' : 'Expand sidebar';

            closeAllDropdowns();
        }

        function persistSidebarState(expanded, previousState) {
            sidebarSaveQueue = sidebarSaveQueue
                .catch(() => {})
                .then(async () => {
                    const response = await fetch(sidebarStateUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: JSON.stringify({ expanded }),
                    });

                    if (!response.ok) {
                        throw new Error('Sidebar preference could not be saved.');
                    }
                })
                .catch(error => {
                    if (preferredSidebarExpanded === expanded) {
                        preferredSidebarExpanded = previousState;
                        setSidebarExpanded(previousState);
                    }

                    if (typeof showMessageBox === 'function') {
                        showMessageBox('error', error.message || 'Sidebar preference could not be saved.');
                    }
                });
        }

        setSidebarExpanded(preferredSidebarExpanded);

        sidebarToggle?.addEventListener('click', event => {
            if (!window.matchMedia('(min-width: 768px)').matches) return;
            event.preventDefault();

            if (window.__appConfig?.readonlySession) {
                if (typeof showMessageBox === 'function') {
                    showMessageBox('warning', 'Read-only mode is enabled. You cannot update the sidebar preference.');
                }
                return;
            }

            const previousState = preferredSidebarExpanded;
            preferredSidebarExpanded = !sidebarShell?.classList.contains('is-expanded');
            setSidebarExpanded(preferredSidebarExpanded);
            persistSidebarState(preferredSidebarExpanded, previousState);
        });

        window.addEventListener('resize', () => {
            setSidebarExpanded(preferredSidebarExpanded);
        });

        function escapeHtml(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/'/g, '&#39;')
                .replace(/"/g, '&quot;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;');
        }

        function removeSidebarFromTabOrder() {
            document.querySelectorAll('aside a, aside button, aside [role="button"], aside [tabindex], #mobileMenu a, #mobileMenu button, #mobileMenu [role="button"], #mobileMenu [tabindex]')
                .forEach(element => {
                    element.setAttribute('tabindex', '-1');
                });
        }

        function getAppConfigShortcuts() {
            if (window.__appConfig?.menuShortcuts) {
                return window.__appConfig.menuShortcuts;
            }
            const raw = document.body?.dataset?.appConfig;
            if (!raw) return [];
            try {
                const parsed = JSON.parse(raw);
                return parsed?.menuShortcuts || [];
            } catch (_) {
                return [];
            }
        }

        function normalizeShortcuts(value) {
            let shortcuts = value;
            if (typeof shortcuts === 'string') {
                try {
                    shortcuts = JSON.parse(shortcuts);
                } catch (_) {
                    shortcuts = [];
                }
            }
            if (!Array.isArray(shortcuts)) {
                shortcuts = [];
            }
            return shortcuts;
        }

        function getMenuShortcuts() {
            if (typeof menu_shortcuts !== 'undefined') {
                return normalizeShortcuts(menu_shortcuts);
            }
            const appShortcuts = getAppConfigShortcuts();
            if (appShortcuts.length) {
                return normalizeShortcuts(appShortcuts);
            }
            return normalizeShortcuts(config.menuShortcuts || []);
        }

        function renderMenuShortcuts() {
            const customMenuShortcutsDom = document.getElementById('customMenuShortcuts');
            if (!customMenuShortcutsDom) return;
            if (config.sidebarMode === 'categories') {
                customMenuShortcutsDom.innerHTML = availableMenuCategories.map(category => {
                    const firstModule = menuData.find(module => module.category === category.id);
                    const icon = firstModule?.svgIcon || '<i class="fas fa-folder text-[var(--text-color)]"></i>';
                    return `<div class="relative group"><button type="button" onclick="openSidebarMenuCategory('${escapeHtml(category.id)}')" aria-label="${escapeHtml(category.name)}" class="nav-link text-[var(--text-color)] p-3 rounded-[41.5%] group-hover:bg-[var(--h-bg-color)] transition-all duration-300 ease-in-out w-10 h-10 flex items-center justify-center cursor-pointer relative">${icon}<span class="sidebar-nav-label absolute shadow-xl left-18 top-1/2 transform -translate-y-1/2 bg-[var(--h-secondary-bg-color)] border border-gray-600 text-[var(--text-color)] text-xs rounded-lg px-2 py-1 opacity-0 group-hover:opacity-100 transition-all duration-300 pointer-events-none text-nowrap">${escapeHtml(category.name)}</span></button></div>`;
                }).join('');
                renderMobileMenuShortcuts();
                return;
            }
            const shortcuts = getMenuShortcuts();
            window.menu_shortcuts = shortcuts;
            const filteredModules = menuData.filter(module => shortcuts.includes(module.id));

            let clutter = '';
            filteredModules.forEach(shortcut => {
                const isActive = pageName == shortcut.id.toLowerCase();
                clutter += `
                    <div class="relative group">
                        <button
                            type="button"
                            tabindex="-1"
                            onclick="openDropDown(event, this)"
                            onkeydown="handleSidebarDropdownKeydown(event, this)"
                            aria-haspopup="menu"
                            aria-expanded="false"
                            aria-label="${shortcut.name}"
                            class="nav-link ${shortcut.name.toLowerCase()} ${isActive && 'active'} dropdown-trigger text-[var(--text-color)] p-3 rounded-[41.5%] group-hover:bg-[var(--h-bg-color)] transition-all duration-300 ease-in-out w-10 h-10 flex items-center justify-center cursor-pointer relative"
                        >
                            ${shortcut.svgIcon}

                            <span
                                class="sidebar-nav-label absolute shadow-xl left-18 top-1/2 transform -translate-y-1/2 bg-[var(--h-secondary-bg-color)] border border-gray-600 text-[var(--text-color)] text-xs rounded-lg px-2 py-1 opacity-0 group-hover:opacity-100 transition-all duration-300 pointer-events-none text-nowrap"
                            >
                                ${shortcut.name}
                            </span>
                        </button>

                        <div
                            role="menu"
                            aria-label="${shortcut.name}"
                            class="dropdownMenu text-sm absolute top-0 left-16 border border-gray-600 w-48 bg-[var(--h-secondary-bg-color)] text-[var(--text-color)] shadow-lg rounded-2xl transform scale-95 transition-all duration-300 ease-in-out z-50 opacity-0 scale-out hidden"
                        >
                            <ul class="p-2">
                                ${shortcut.subMenu
                                    .map(
                                        item => `
                                    <li>
                                        <a
                                            href="${item.href}"
                                            tabindex="-1"
                                            role="menuitem"
                                            class="block px-4 py-2 hover:bg-[var(--h-bg-color)] rounded-lg transition-all duration-200 ease-in-out"
                                        >
                                            ${item.name}
                                        </a>
                                    </li>
                                `
                                    )
                                    .join('')}
                            </ul>
                        </div>
                    </div>
                `;
            });
            customMenuShortcutsDom.innerHTML = clutter;
            removeSidebarFromTabOrder();
            renderMobileMenuShortcuts();
        }
        window.renderMenuShortcuts = renderMenuShortcuts;

        function mobileShortcutCard(module) {
            const links = Array.isArray(module.subMenu) ? module.subMenu : [];
            const primary = links[0]?.href || '#';
            const actions = links
                .slice(0, 3)
                .map(item => `
                    <a href="${escapeHtml(item.href)}"
                        class="rounded-lg border border-gray-600/45 bg-[var(--bg-color)] px-3 py-2 text-center text-xs text-[var(--text-color)]">
                        ${escapeHtml(item.name)}
                    </a>
                `)
                .join('');

            return `
                <div class="rounded-xl border border-gray-600/45 bg-[var(--secondary-bg-color)] p-3 shadow-sm">
                    <div class="flex items-center justify-between gap-3">
                        <a href="${escapeHtml(primary)}" class="flex min-w-0 items-center gap-3">
                            <span class="flex size-10 shrink-0 items-center justify-center rounded-[41.5%] bg-[var(--h-bg-color)]">
                                ${module.svgIcon || '<i class="fas fa-circle text-[var(--primary-color)]"></i>'}
                            </span>
                            <span class="truncate font-semibold text-[var(--text-color)]">${escapeHtml(module.name)}</span>
                        </a>
                        <button type="button"
                            class="mobile-shortcut-toggle flex size-9 shrink-0 items-center justify-center rounded-lg border border-gray-600 text-[var(--secondary-text)]"
                            aria-expanded="false">
                            <i class="fas fa-chevron-down transition-transform duration-200"></i>
                        </button>
                    </div>
                    <div class="mobile-shortcut-actions hidden grid-cols-2 gap-2 pt-3">
                        ${actions || `<a href="${escapeHtml(primary)}" class="rounded-lg border border-gray-600/45 bg-[var(--bg-color)] px-3 py-2 text-center text-xs text-[var(--text-color)]">Open</a>`}
                    </div>
                </div>
            `;
        }

        function renderMobileMenuShortcuts() {
            const mobileShortcutsDom = document.getElementById('mobileMenuShortcuts');
            if (!mobileShortcutsDom) return;

            if (config.sidebarMode === 'categories') {
                mobileShortcutsDom.innerHTML = availableMenuCategories.map(category => `<button type="button" onclick="openSidebarMenuCategory('${escapeHtml(category.id)}'); window.closeMobileMenu && window.closeMobileMenu();" class="w-full rounded-xl border border-gray-600/45 bg-[var(--secondary-bg-color)] p-4 text-left font-semibold text-[var(--text-color)]"><i class="fas fa-folder mr-2 text-[var(--primary-color)]"></i>${escapeHtml(category.name)}</button>`).join('');
                return;
            }

            const shortcuts = getMenuShortcuts();
            const filteredModules = menuData.filter(module => shortcuts.includes(module.id));
            const modules = filteredModules.length ? filteredModules : menuData.slice(0, 4);

            mobileShortcutsDom.innerHTML = modules.length
                ? modules.map(mobileShortcutCard).join('')
                : `<button type="button" class="rounded-xl border border-gray-600 bg-[var(--secondary-bg-color)] px-4 py-3 text-left text-[var(--text-color)]" onclick="generateMenuModal(); window.closeMobileMenu && window.closeMobileMenu();">Open Menu</button>`;
        }
        window.renderMobileMenuShortcuts = renderMobileMenuShortcuts;
        window.openSidebarMenuCategory = function (categoryId) {
            window.generateMenuModal();
            window.filterMenuCategory(categoryId);
        };
        renderMenuShortcuts();

        let modalData = {
            id: 'menuModal',
            class: 'h-[82%] w-[96vw]',
            menuModal: true,
            cards: {
                name: 'Menu',
                count: 3,
                data: menuData,
                useMenuCard: true,
                groupByCategory: true,
                categories: availableMenuCategories,
                emptyMessage: 'No matching module found.',
            },
            menuCategories: availableMenuCategories,
            activeMenuCategory: 'all',
            canManageMenuCategories,
            basicSearch: true,
            onBasicSearch: 'menuBasicSearch(this.value)',
            info: `Enabled: ${getMenuShortcuts().length}/${typeof maxShortcutsLimit !== 'undefined' ? maxShortcutsLimit : 7}`,
            flex_col: true,
        };

        let activeMenuCategory = 'all';
        let menuSearchValue = '';
        let isManagingMenuCategories = false;
        let isManagingMenuActions = false;
        let isSavingMenuCategories = false;
        let savedCategoryAssignments = { ...configuredCategoryAssignments };
        menuData.forEach(item => {
            if (!Object.prototype.hasOwnProperty.call(savedCategoryAssignments, item.id)) {
                savedCategoryAssignments[item.id] = item.category;
            }
        });
        let workingCategoryAssignments = { ...savedCategoryAssignments };
        let isAddingMenuCategory = false;

        function syncMenuItemCategories(assignments) {
            menuData.forEach(item => {
                item.category = assignments[item.id] ?? null;
            });
        }

        function menuItemMatchesSearch(item, searchValue) {
            const normalizedSearch = searchValue.trim().toLowerCase();
            if (!normalizedSearch) return true;

            const description = item.details && typeof item.details === 'object'
                ? Object.values(item.details).join(' ')
                : '';
            const actions = Array.isArray(item.subMenu)
                ? item.subMenu.map(action => action.name).join(' ')
                : '';

            return `${item.name} ${description} ${actions}`.toLowerCase().includes(normalizedSearch);
        }

        function filteredMenuData() {
            if (isManagingMenuCategories || isManagingMenuActions) {
                return menuData;
            }

            return menuData.filter(item => {
                const inSelectedCategory = activeMenuCategory === 'all' || item.category === activeMenuCategory;
                return inSelectedCategory && menuItemMatchesSearch(item, menuSearchValue);
            });
        }

        function refreshMenuModalContent() {
            modalData.activeMenuCategory = activeMenuCategory;
            modalData.cards.data = filteredMenuData();
            modalData.cards.showEmptyCategories = isManagingMenuCategories;
            modalData.cards.categoryManagement = isManagingMenuCategories;
            modalData.cards.categories = availableMenuCategories;
            renderCardsInModal(modalData);

            renderMenuCategoryFilters();

            applyMenuCategoryManagementState();
        }

        function renderMenuCategoryFilters() {
            const filters = document.querySelector('#menuModal .menu-category-filters');
            if (!filters) return;

            filters.innerHTML = [
                { id: 'all', name: 'All' },
                ...availableMenuCategories,
            ].map(category => {
                const isActive = category.id === activeMenuCategory;
                return `
                    <button type="button" data-menu-category="${escapeHtml(category.id)}"
                        aria-pressed="${isActive ? 'true' : 'false'}"
                        onclick="filterMenuCategory('${htmlAttr(category.id)}')"
                        class="menu-category-filter ${isActive ? 'is-active' : ''}">
                        ${escapeHtml(category.name)}
                    </button>
                `;
            }).join('');
        }

        function menuCategoryManagerButton() {
            return `
                <button type="button" onclick="toggleMenuCategoryManagement()"
                    class="menu-category-manage-button">
                    <i class="fa-solid fa-grip"></i>
                    Manage Categories
                </button>
                <button type="button" onclick="toggleMenuActionManagement()"
                    class="menu-category-manage-button">
                    <i class="fa-solid fa-list-check"></i>
                    Manage Actions
                </button>
            `;
        }

        function menuCategorySaveButtons() {
            return `
                <button type="button" onclick="addMenuCategory()" class="menu-category-add-button">
                    <i class="fa-solid fa-plus"></i>
                    Add Category
                </button>
                <button type="button" onclick="cancelMenuCategoryManagement()"
                    class="menu-category-cancel-button" ${isSavingMenuCategories ? 'disabled' : ''}>
                    Cancel
                </button>
                <button type="button" onclick="saveMenuCategoryManagement()"
                    class="menu-category-save-button" ${isSavingMenuCategories ? 'disabled' : ''}>
                    <i class="fa-solid ${isSavingMenuCategories ? 'fa-spinner fa-spin' : 'fa-check'}"></i>
                    ${isSavingMenuCategories ? 'Saving...' : (isManagingMenuActions ? 'Save Actions' : 'Save Categories')}
                </button>
            `;
        }

        function menuCategoryAddForm() {
            return `
                <input id="newMenuCategoryName" type="text" maxlength="80" placeholder="Category name..."
                    class="menu-category-add-input" onkeydown="if (event.key === 'Enter') confirmAddMenuCategory()" />
                <button type="button" onclick="confirmAddMenuCategory()" class="menu-category-save-button">Add</button>
                <button type="button" onclick="cancelAddMenuCategory()" class="menu-category-cancel-button">Cancel</button>
            `;
        }

        function updateMenuCategoryManagerActions() {
            const actions = document.getElementById('menuCategoryManagerActions');
            if (!actions || !canManageMenuCategories) return;
            actions.innerHTML = isAddingMenuCategory
                ? menuCategoryAddForm()
                : (isManagingMenuCategories || isManagingMenuActions)
                    ? menuCategorySaveButtons()
                    : menuCategoryManagerButton();
            if (isManagingMenuActions) {
                const addButton = actions.querySelector('.menu-category-add-button');
                addButton?.remove();
            }

            if (isAddingMenuCategory) {
                document.getElementById('newMenuCategoryName')?.focus();
            }
        }

        function categorySelectHtml(moduleId) {
            const choices = [
                { id: '', name: 'Uncategorized' },
                ...availableMenuCategories,
            ];
            return `
                <label class="menu-card-category-select-label">
                    <span>Move to</span>
                    ${menuReusableSelect(`category_${moduleId}`, workingCategoryAssignments[moduleId] ?? '', `data-menu-category-select="${htmlAttr(moduleId)}"`, choices, 'Choose category')}
                </label>
            `;
        }

        let actionSelectCounter = 0;
        function menuReusableSelect(id, value, marker, choices, searchPlaceholder) {
            const selectId = `menu_navigation_${id}_${++actionSelectCounter}`;
            const selected = choices.find(option => option.id === value);
            return `
                <div class="form-group menu-action-select">
                    <div class="selectParent flex gap-1">
                        <input id="${selectId}" data-for="${selectId}" data-sort-direction="asc" autocomplete="off" value="${escapeHtml(selected?.name || '')}" onfocus="selectClicked(this); sortSelectOptions(this)" class="w-full min-w-0 rounded-lg border border-gray-600 bg-[var(--h-bg-color)] px-2 py-1 text-[var(--text-color)] text-xs" aria-label="${escapeHtml(searchPlaceholder)}">
                        <input type="hidden" class="dbInput" data-for="${selectId}" ${marker} value="${htmlAttr(value)}">
                        <div class="dropDownParent fixed z-[60] mt-2 w-52 rounded-xl border border-gray-600 bg-[var(--secondary-bg-color)] p-1.5 text-[var(--text-color)] shadow-xl">
                            <input data-for="${selectId}" autocomplete="off" placeholder="${escapeHtml(searchPlaceholder)}" oninput="searchSelect(this)" onblur="validateSelectInput(this)" onkeydown="selectKeyDown(event, this)" class="w-full rounded-lg border border-gray-600 bg-[var(--h-bg-color)] px-2 py-1 text-xs text-[var(--text-color)]">
                            <ul class="optionsDropdown my-scrollbar-2 max-h-40 space-y-0.5 overflow-auto" data-for="${selectId}">
                                ${choices.map(option => `<li data-for="${selectId}" data-value="${htmlAttr(option.id)}" onmousedown="selectThisOption(this)" class="cursor-pointer rounded-lg px-2 py-1.5 text-xs transition hover:bg-[var(--h-bg-color)] ${String(option.id) === String(value) ? 'selected' : ''}">${escapeHtml(option.name)}</li>`).join('')}
                            </ul>
                        </div>
                    </div>
                </div>`;
        }

        function menuActionDestinationSelect(action, currentModuleId) {
            return menuReusableSelect(`action_${actionSelectCounter}`, currentModuleId, `data-menu-action-select="${htmlAttr(action.href)}"`, menuData.map(module => ({ id: module.id, name: module.name })), 'Search modules...');
        }

        function applyMenuCategoryManagementState() {
            const modal = document.getElementById('menuModal');
            if (!modal) return;

            modal.classList.toggle('is-managing-menu-categories', isManagingMenuCategories);
            modal.classList.toggle('is-managing-menu-actions', isManagingMenuActions);
            modal.querySelectorAll('[data-menu-category]').forEach(button => {
                button.disabled = isManagingMenuCategories || isManagingMenuActions;
            });

            const searchInput = modal.querySelector('#basicSearch input');
            if (searchInput) searchInput.disabled = isManagingMenuCategories || isManagingMenuActions;

            modal.querySelectorAll('.menu-modal-card').forEach(card => {
                card.draggable = isManagingMenuCategories;
                card.classList.toggle('is-category-draggable', isManagingMenuCategories);
                card.classList.toggle('is-action-editing', isManagingMenuActions);

                if (isManagingMenuCategories && !card.querySelector('.menu-card-drag-controls')) {
                    card.removeAttribute('onclick');
                    card.removeAttribute('oncontextmenu');
                    card.insertAdjacentHTML('afterbegin', `
                        <div class="menu-card-drag-controls">
                            <span><i class="fa-solid fa-grip-vertical"></i> Drag module</span>
                            ${categorySelectHtml(card.id)}
                        </div>
                    `);
                }
                if (isManagingMenuActions && !card.querySelector('.menu-card-action-controls')) {
                    card.removeAttribute('onclick');
                    card.removeAttribute('oncontextmenu');
                    const actions = menuData.find(module => module.id === card.id)?.subMenu || [];
                    card.insertAdjacentHTML('afterbegin', `<div class="menu-card-action-controls"><strong>Actions</strong>${actions.map((action, index) => `
                        <div class="menu-card-action-row">
                            <span>${escapeHtml(action.name)}</span>
                            ${menuActionDestinationSelect(action, card.id)}
                            <button type="button" onclick="moveMenuAction('${htmlAttr(action.href)}', -1, '${htmlAttr(card.id)}')" aria-label="Move ${escapeHtml(action.name)} up" ${index === 0 ? 'disabled' : ''}>&#8593;</button>
                            <button type="button" onclick="moveMenuAction('${htmlAttr(action.href)}', 1, '${htmlAttr(card.id)}')" aria-label="Move ${escapeHtml(action.name)} down" ${index === actions.length - 1 ? 'disabled' : ''}>&#8595;</button>
                        </div>`).join('')}</div>`);
                }
            });

            updateMenuCategoryManagerActions();
        }

        function bindMenuCategoryManagementEvents() {
            const modal = document.getElementById('menuModal');
            if (!modal || modal.dataset.categoryManagerBound === 'true') return;
            modal.dataset.categoryManagerBound = 'true';

            modal.addEventListener('dragstart', event => {
                const card = event.target.closest('.menu-modal-card');
                if (!isManagingMenuCategories || !card) {
                    event.preventDefault();
                    return;
                }

                event.dataTransfer.effectAllowed = 'move';
                event.dataTransfer.setData('text/plain', card.id);
                card.classList.add('is-dragging');
            });

            modal.addEventListener('dragend', event => {
                event.target.closest('.menu-modal-card')?.classList.remove('is-dragging');
                modal.querySelectorAll('.menu-category-section').forEach(section => {
                    section.classList.remove('is-menu-drop-target');
                });
            });

            modal.addEventListener('dragover', event => {
                if (!isManagingMenuCategories) return;
                const section = event.target.closest('.menu-category-section');
                if (!section) return;
                event.preventDefault();
                event.dataTransfer.dropEffect = 'move';
                section.classList.add('is-menu-drop-target');
            });

            modal.addEventListener('dragleave', event => {
                const section = event.target.closest('.menu-category-section');
                if (section && !section.contains(event.relatedTarget)) {
                    section.classList.remove('is-menu-drop-target');
                }
            });

            modal.addEventListener('drop', event => {
                if (!isManagingMenuCategories) return;
                const section = event.target.closest('.menu-category-section');
                const moduleId = event.dataTransfer.getData('text/plain');
                const categoryId = section?.dataset.menuCategorySection;
                const isUncategorized = categoryId === '__uncategorized__';
                if (!section || !moduleId || (!isUncategorized && !availableMenuCategories.some(category => category.id === categoryId))) return;

                event.preventDefault();
                workingCategoryAssignments[moduleId] = isUncategorized ? null : categoryId;
                syncMenuItemCategories(workingCategoryAssignments);
                refreshMenuModalContent();
            });

            modal.addEventListener('change', event => {
                const select = event.target.closest('[data-menu-category-select]');
                if (!isManagingMenuCategories || !select) return;

                workingCategoryAssignments[select.dataset.menuCategorySelect] = select.value;
                syncMenuItemCategories(workingCategoryAssignments);
                refreshMenuModalContent();
            });

            modal.addEventListener('change', event => {
                const select = event.target.closest('.dbInput[data-menu-action-select]');
                if (!isManagingMenuActions || !select) return;
                event.stopPropagation();
                workingActionAssignments[select.dataset.menuActionSelect] = select.value;
                applyMenuActionNavigation();
                refreshMenuModalContent();
            });

        }

        window.generateMenuModal = function generateMenuModal() {
            const shortcuts = getMenuShortcuts();
            activeMenuCategory = 'all';
            menuSearchValue = '';
            isManagingMenuCategories = false;
            isManagingMenuActions = false;
            isSavingMenuCategories = false;
            isAddingMenuCategory = false;
            workingCategoryAssignments = { ...savedCategoryAssignments };
            workingActionAssignments = { ...(savedActionNavigation.assignments || {}) };
            workingActionOrder = Object.fromEntries(Object.entries(savedActionNavigation.order || {}).map(([id, hrefs]) => [id, [...hrefs]]));
            syncMenuItemCategories(savedCategoryAssignments);
            applyMenuActionNavigation();
            menuData.forEach(item => {
                if (!item.switchBtn) {
                    item.switchBtn = { active: false };
                }
                item.switchBtn.active = shortcuts.includes(item.id);
            });
            modalData.cards.data = menuData;
            modalData.activeMenuCategory = activeMenuCategory;
            modalData.info = `Enabled: ${shortcuts.length}/${typeof maxShortcutsLimit !== 'undefined' ? maxShortcutsLimit : 7}`;
            createModal(modalData);
            bindMenuCategoryManagementEvents();
            applyMenuCategoryManagementState();
        };

        window.addMenuCategory = function addMenuCategory() {
            if (!isManagingMenuCategories || isSavingMenuCategories) return;
            isAddingMenuCategory = true;
            updateMenuCategoryManagerActions();
        };

        window.cancelAddMenuCategory = function cancelAddMenuCategory() {
            isAddingMenuCategory = false;
            updateMenuCategoryManagerActions();
        };

        window.confirmAddMenuCategory = function confirmAddMenuCategory() {
            const input = document.getElementById('newMenuCategoryName');
            const name = input?.value.trim() || '';
            if (!name) return;

            const baseId = name.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '') || 'category';
            let id = baseId;
            let suffix = 2;
            while (menuCategories.some(category => category.id === id)) {
                id = `${baseId}-${suffix++}`;
            }

            menuCategories.push({ id, name });
            availableMenuCategories = menuCategories;
            isAddingMenuCategory = false;
            activeMenuCategory = 'all';
            refreshMenuModalContent();
        };

        window.renameMenuCategory = function renameMenuCategory(categoryId, name) {
            const category = menuCategories.find(item => item.id === categoryId);
            const nextName = String(name || '').trim();
            if (category && nextName) {
                category.name = nextName;
                renderMenuCategoryFilters();
            }
        };

        window.deleteMenuCategory = function deleteMenuCategory(categoryId) {
            if (!isManagingMenuCategories || isSavingMenuCategories) return;
            menuCategories = menuCategories.filter(category => category.id !== categoryId);
            menuData.forEach(item => {
                if (workingCategoryAssignments[item.id] === categoryId) {
                    workingCategoryAssignments[item.id] = null;
                }
            });
            availableMenuCategories = menuCategories;
            activeMenuCategory = 'all';
            refreshMenuModalContent();
        };

        window.moveMenuCategory = function moveMenuCategory(categoryId, direction) {
            const index = menuCategories.findIndex(category => category.id === categoryId);
            const nextIndex = index + direction;
            if (index < 0 || nextIndex < 0 || nextIndex >= menuCategories.length) return;
            [menuCategories[index], menuCategories[nextIndex]] = [menuCategories[nextIndex], menuCategories[index]];
            availableMenuCategories = menuCategories;
            refreshMenuModalContent();
        };

        window.toggleMenuCategoryManagement = function toggleMenuCategoryManagement() {
            if (!canManageMenuCategories || isManagingMenuCategories) return;
            if (window.__appConfig?.readonlySession) {
                if (typeof showMessageBox === 'function') {
                    showMessageBox('warning', 'Read-only mode is enabled. Menu categories cannot be updated.');
                }
                return;
            }

            activeMenuCategory = 'all';
            menuSearchValue = '';
            isManagingMenuCategories = true;
            isManagingMenuActions = false;
            workingCategoryAssignments = { ...savedCategoryAssignments };
            syncMenuItemCategories(workingCategoryAssignments);

            const searchInput = document.querySelector('#menuModal #basicSearch input');
            if (searchInput) searchInput.value = '';
            refreshMenuModalContent();
        };

        window.toggleMenuActionManagement = function toggleMenuActionManagement() {
            if (!canManageMenuCategories || isManagingMenuCategories || isManagingMenuActions) return;
            if (window.__appConfig?.readonlySession) {
                if (typeof showMessageBox === 'function') showMessageBox('warning', 'Read-only mode is enabled. Menu actions cannot be updated.');
                return;
            }

            activeMenuCategory = 'all';
            menuSearchValue = '';
            isManagingMenuActions = true;
            workingActionAssignments = { ...(savedActionNavigation.assignments || {}) };
            workingActionOrder = Object.fromEntries(Object.entries(savedActionNavigation.order || {}).map(([id, hrefs]) => [id, [...hrefs]]));
            applyMenuActionNavigation();
            const searchInput = document.querySelector('#menuModal #basicSearch input');
            if (searchInput) searchInput.value = '';
            refreshMenuModalContent();
        };

        window.moveMenuAction = function (href, direction, moduleId) {
            if (!isManagingMenuActions) return;
            const module = menuData.find(item => item.id === moduleId);
            if (!module) return;
            const actions = module.subMenu || [];
            const index = actions.findIndex(action => action.href === href);
            const nextIndex = index + direction;
            if (index < 0 || nextIndex < 0 || nextIndex >= actions.length) return;
            const hrefs = actions.map(action => action.href);
            [hrefs[index], hrefs[nextIndex]] = [hrefs[nextIndex], hrefs[index]];
            workingActionOrder[moduleId] = hrefs;
            applyMenuActionNavigation();
            refreshMenuModalContent();
        };

        window.cancelMenuCategoryManagement = function cancelMenuCategoryManagement() {
            if ((!isManagingMenuCategories && !isManagingMenuActions) || isSavingMenuCategories) return;
            workingCategoryAssignments = { ...savedCategoryAssignments };
            syncMenuItemCategories(savedCategoryAssignments);
            workingActionAssignments = { ...(savedActionNavigation.assignments || {}) };
            workingActionOrder = Object.fromEntries(Object.entries(savedActionNavigation.order || {}).map(([id, hrefs]) => [id, [...hrefs]]));
            isManagingMenuCategories = false;
            isManagingMenuActions = false;
            applyMenuActionNavigation();
            refreshMenuModalContent();
        };

        window.saveMenuCategoryManagement = async function saveMenuCategoryManagement() {
            if (!canManageMenuCategories || (!isManagingMenuCategories && !isManagingMenuActions) || isSavingMenuCategories) return;
            isSavingMenuCategories = true;
            updateMenuCategoryManagerActions();

            try {
                const response = await fetch(updateMenuCategoriesUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({
                        categories: menuCategories,
                        assignments: workingCategoryAssignments,
                        ...(isManagingMenuActions ? { action_assignments: workingActionAssignments, action_order: Object.fromEntries(menuData.map(module => [module.id, (module.subMenu || []).map(action => action.href)])) } : {}),
                    }),
                });
                const payload = await response.json().catch(() => ({}));
                if (!response.ok || payload.saved !== true) {
                    throw new Error(payload.message || 'Menu categories could not be saved.');
                }

                savedCategoryAssignments = { ...payload.assignments };
                workingCategoryAssignments = { ...savedCategoryAssignments };
                savedActionNavigation = payload.actionNavigation || savedActionNavigation;
                workingActionAssignments = { ...(savedActionNavigation.assignments || {}) };
                workingActionOrder = Object.fromEntries(Object.entries(savedActionNavigation.order || {}).map(([id, hrefs]) => [id, [...hrefs]]));
                menuCategories = payload.categories.map(category => ({ id: category.id, name: category.name }));
                availableMenuCategories = menuCategories;
                modalData.menuCategories = availableMenuCategories;
                Object.entries(savedCategoryAssignments).forEach(([moduleId, categoryId]) => {
                    categoryByModuleId.set(moduleId, categoryId);
                });
                syncMenuItemCategories(savedCategoryAssignments);
                isManagingMenuCategories = false;
                isManagingMenuActions = false;
                applyMenuActionNavigation();
                if (typeof showMessageBox === 'function') {
                    showMessageBox('success', payload.message || 'Menu categories updated successfully.');
                }
            } catch (error) {
                if (typeof showMessageBox === 'function') {
                    showMessageBox('error', error.message || 'Menu categories could not be saved.');
                }
            } finally {
                isSavingMenuCategories = false;
                refreshMenuModalContent();
            }
        };

        window.filterMenuCategory = function filterMenuCategory(categoryId) {
            if (isManagingMenuCategories || isManagingMenuActions) return;
            const isAvailableCategory = categoryId === 'all'
                || availableMenuCategories.some(category => category.id === categoryId);
            if (!isAvailableCategory) return;

            activeMenuCategory = categoryId;
            refreshMenuModalContent();
        };

        document.addEventListener('app:config:ready', () => {
            if (typeof window.renderMenuShortcuts === 'function') {
                window.renderMenuShortcuts();
            }
            removeSidebarFromTabOrder();
        });

        document.addEventListener('keydown', function (event) {
            if (event.ctrlKey && event.key === ' ') {
                event.preventDefault();
                const existingModal = document.getElementById(modalData.id);
                if (!existingModal) {
                    generateMenuModal();
                }
            }
        });

        window.menuBasicSearch = function menuBasicSearch(searchValue) {
            if (isManagingMenuCategories || isManagingMenuActions) return;
            menuSearchValue = searchValue || '';
            refreshMenuModalContent();
        };

        function getDropdownItems(menu) {
            if (!menu) return [];
            return Array.from(menu.querySelectorAll('a[href], button:not([disabled]), [tabindex]:not([tabindex="-1"])'))
                .filter(item => item.offsetParent !== null);
        }

        function focusDropdownItem(trigger, position = 'first') {
            const menu = trigger?.nextElementSibling;
            const items = getDropdownItems(menu);
            if (!items.length) return;
            const target = position === 'last' ? items[items.length - 1] : items[0];
            target.focus();
        }

        window.handleSidebarDropdownKeydown = function handleSidebarDropdownKeydown(event, trigger) {
            if (event.key === 'Enter' || event.key === ' ' || event.key === 'ArrowDown') {
                event.preventDefault();
                const menu = trigger.nextElementSibling;
                const shouldOpen = menu?.classList.contains('hidden');
                if (shouldOpen) {
                    openDropDown(event, trigger);
                }
                setTimeout(() => focusDropdownItem(trigger), 20);
            }

            if (event.key === 'ArrowUp') {
                event.preventDefault();
                const menu = trigger.nextElementSibling;
                if (menu?.classList.contains('hidden')) {
                    openDropDown(event, trigger);
                }
                setTimeout(() => focusDropdownItem(trigger, 'last'), 20);
            }

            if (event.key === 'Escape') {
                event.preventDefault();
                closeAllDropdowns();
                trigger.focus();
            }
        };

        document.addEventListener('keydown', event => {
            const menu = event.target.closest?.('.dropdownMenu');
            if (!menu) return;

            const trigger = menu.previousElementSibling;
            const items = getDropdownItems(menu);
            const currentIndex = items.indexOf(event.target);

            if (event.key === 'Escape') {
                event.preventDefault();
                closeAllDropdowns();
                trigger?.focus();
                return;
            }

            if (!items.length) return;

            if (event.key === 'ArrowDown') {
                event.preventDefault();
                items[(currentIndex + 1) % items.length].focus();
            }

            if (event.key === 'ArrowUp') {
                event.preventDefault();
                items[(currentIndex - 1 + items.length) % items.length].focus();
            }

            if (event.key === 'Home') {
                event.preventDefault();
                items[0].focus();
            }

            if (event.key === 'End') {
                event.preventDefault();
                items[items.length - 1].focus();
            }
        });

        document.querySelectorAll('.dropdown-toggle').forEach(button => {
            button.addEventListener('click', () => {
                document.querySelectorAll('.dropdown-menu').forEach(menu => {
                    if (menu !== button.nextElementSibling) {
                        menu.classList.add('hidden');
                        menu.previousElementSibling?.setAttribute('aria-expanded', 'false');
                        menu.previousElementSibling.querySelector('i').classList.remove('rotate-180');
                    }
                });

                const dropdownMenu = button.nextElementSibling;
                dropdownMenu.classList.toggle('hidden');
                button.setAttribute('aria-expanded', dropdownMenu.classList.contains('hidden') ? 'false' : 'true');
                button.querySelector('i').classList.toggle('rotate-180');
            });
        });

        removeSidebarFromTabOrder();

        function closeAllMobileMenuDropdowns() {
            document.querySelectorAll('.dropdown-menu').forEach(menu => {
                menu.classList.add('hidden');
                menu.previousElementSibling?.setAttribute('aria-expanded', 'false');
                menu.previousElementSibling.querySelector('i').classList.remove('rotate-180');
            });
        }

        const menuToggle = document.getElementById('menuToggle');
        const menuToggleIcon = document.querySelector('#menuToggle i');
        const mobileMenuOverlay = document.getElementById('mobileMenuOverlay');
        const mobileMenu = document.getElementById('mobileMenu');

        function setMobileMenuOpen(open) {
            if (!mobileMenuOverlay || !mobileMenu) return;
            closeAllMobileMenuDropdowns();
            menuToggleIcon?.classList.toggle('fa-bars', !open);
            menuToggleIcon?.classList.toggle('fa-xmark', open);
            mobileMenu.classList.toggle('-translate-y-full', !open);
            mobileMenu.classList.toggle('is-open', open);
            mobileMenuOverlay.classList.toggle('opacity-zero', !open);
            mobileMenuOverlay.classList.toggle('pointer-events-none', !open);
        }

        function toggleMobileMenu() {
            setMobileMenuOpen(!mobileMenu?.classList.contains('is-open'));
        }

        window.openMobileMenu = () => setMobileMenuOpen(true);
        window.closeMobileMenu = () => setMobileMenuOpen(false);

        menuToggle?.addEventListener('click', () => {
            toggleMobileMenu();
        });

        document.querySelectorAll('.mobile-menu-close').forEach(button => {
            button.addEventListener('click', () => setMobileMenuOpen(false));
        });

        document.addEventListener('click', event => {
            const toggle = event.target.closest?.('.mobile-shortcut-toggle');
            if (!toggle) return;

            const actions = toggle.closest('.rounded-xl')?.querySelector('.mobile-shortcut-actions');
            if (!actions) return;

            const isOpen = !actions.classList.contains('hidden');
            actions.classList.toggle('hidden', isOpen);
            actions.classList.toggle('grid', !isOpen);
            toggle.setAttribute('aria-expanded', isOpen ? 'false' : 'true');
            toggle.querySelector('i')?.classList.toggle('rotate-180', !isOpen);
        });

        mobileMenuOverlay?.addEventListener('mousedown', e => {
            if (e.target.classList.contains('mobileMenuOverlay')) {
                setMobileMenuOpen(false);
            }
        });

        document.getElementById('logoutModal')?.addEventListener('click', e => {
            if (e.target.id === 'logoutModal') {
                closeLogoutModal();
            }
        });

        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') {
                closeLogoutModal();
            }
        });

        document.addEventListener('mousedown', function (e) {
            if (!e.target.closest('.dropdown-trigger') && !e.target.closest('.dropdownMenu')) {
                closeAllDropdowns();
            }
        });

        window.openLogoutModal = function openLogoutModal() {
            document.getElementById('logoutModal')?.classList.remove('hidden');
            closeAllDropdowns();
        };

        window.closeLogoutModal = function closeLogoutModal() {
            const logoutModal = document.getElementById('logoutModal');
            if (!logoutModal) return;
            logoutModal.classList.add('fade-out');

            logoutModal.addEventListener(
                'animationend',
                () => {
                    logoutModal.classList.add('hidden');
                    logoutModal.classList.remove('fade-out');
                },
                { once: true }
            );
        };
    }

    window.initSidebar = initSidebar;

    function boot() {
        if (window.__sidebar) {
            initSidebar();
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
    document.addEventListener('app:config:ready', boot);
    document.addEventListener('sidebar:config:ready', boot);
})();
