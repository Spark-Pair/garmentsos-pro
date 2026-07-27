(() => {
    function initSidebar() {
        const config = window.__sidebar || {};
        const menuData = config.menuData || [];
        const pageName = window.location.href.toLowerCase().split('/')[3];

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
                                class="absolute shadow-xl left-18 top-1/2 transform -translate-y-1/2 bg-[var(--h-secondary-bg-color)] border border-gray-600 text-[var(--text-color)] text-xs rounded-lg px-2 py-1 opacity-0 group-hover:opacity-100 transition-all duration-300 pointer-events-none text-nowrap"
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
        }
        window.renderMenuShortcuts = renderMenuShortcuts;
        renderMenuShortcuts();

        let modalData = {
            id: 'menuModal',
            class: 'h-[82%] w-full',
            menuModal: true,
            cards: { name: 'Menu', count: 3, data: menuData, useMenuCard: true },
            basicSearch: true,
            onBasicSearch: 'menuBasicSearch(this.value)',
            info: `Enabled: ${getMenuShortcuts().length}/${typeof maxShortcutsLimit !== 'undefined' ? maxShortcutsLimit : 7}`,
            flex_col: true,
        };

        window.generateMenuModal = function generateMenuModal() {
            const shortcuts = getMenuShortcuts();
            menuData.forEach(item => {
                if (!item.switchBtn) {
                    item.switchBtn = { active: false };
                }
                item.switchBtn.active = shortcuts.includes(item.id);
            });
            modalData.cards.data = menuData;
            createModal(modalData);
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
            modalData.cards.data = menuData.filter(item => item.name.toLowerCase().includes(searchValue.toLowerCase()));
            renderCardsInModal(modalData);
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

        function toggleMobileMenu() {
            if (!mobileMenuOverlay || !mobileMenu) return;
            closeAllMobileMenuDropdowns();
            menuToggleIcon?.classList.toggle('fa-bars');
            menuToggleIcon?.classList.toggle('fa-xmark');
            mobileMenu.classList.toggle('-translate-y-full');
            mobileMenu.classList.toggle('is-open');
            mobileMenuOverlay.classList.toggle('opacity-zero');
            mobileMenuOverlay.classList.toggle('pointer-events-none');
        }

        menuToggle?.addEventListener('click', () => {
            toggleMobileMenu();
        });

        mobileMenuOverlay?.addEventListener('mousedown', e => {
            if (e.target.classList.contains('mobileMenuOverlay')) {
                toggleMobileMenu();
            }
        });

        const html = document.documentElement;
        const themeIcon = document.querySelector('#themeToggle i');
        const themeToggle = document.getElementById('themeToggle');
        const themeToggleMobile = document.getElementById('themeToggleMobile');

        themeToggle?.addEventListener('click', () => {
            themefunction();
        });
        themeToggleMobile?.addEventListener('click', () => {
            themefunction();
        });

        function changeTheme() {
            const currentTheme = html.getAttribute('data-theme');
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            html.setAttribute('data-theme', newTheme);

            themeIcon?.classList.toggle('fa-sun');
            themeIcon?.classList.toggle('fa-moon');
        }

        function themefunction() {
            changeTheme();
            const currentTheme = $('html').attr('data-theme');

            $.ajax({
                url: '/update-theme',
                type: 'POST',
                data: {
                    theme: currentTheme,
                    _token: $('meta[name="csrf-token"]').attr('content'),
                },
                success: function (response) {
                    if (typeof messageBox !== 'undefined') {
                        if (response.success) {
                            messageBox.innerHTML =
                                (config.themeSuccessTemplate || '').replace('__MESSAGE__', response.message || '');
                            messageBoxAnimation();
                        } else {
                            messageBox.innerHTML = config.themeFailureTemplate || '';
                            messageBoxAnimation();
                        }
                    }
                },
                error: function (xhr, status, error) {
                    console.error('AJAX Error:', error);
                    if (typeof messageBox !== 'undefined') {
                        messageBox.innerHTML = config.themeErrorTemplate || '';
                        messageBoxAnimation();
                    }
                },
            });
        }

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
})();
