(() => {
    window.htmlAttr = function htmlAttr(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/'/g, '&#39;')
            .replace(/"/g, '&quot;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    };

    window.jsonAttr = function jsonAttr(value) {
        return htmlAttr(JSON.stringify(value));
    };

    function initAppCommon() {
        const config = window.__appConfig || {};

        window.messageBox = document.getElementById('messageBox');
        window.notificationBox = document.getElementById('notificationBox');

        if ('serviceWorker' in navigator) {
            navigator.serviceWorker
                .register('/service-worker.js')
                .then(() => {})
                .catch(err => console.warn('Service Worker registration failed ❌', err));
        }

        window.closeOnClickOutside = undefined;
        window.escToClose = undefined;
        window.enterToSubmit = undefined;

        if (typeof config.menuShortcuts !== 'undefined') {
            let shortcuts = config.menuShortcuts;
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
            window.menu_shortcuts = shortcuts;
        }
        if (typeof config.maxShortcutsLimit === 'number') {
            window.maxShortcutsLimit = config.maxShortcutsLimit;
        }

        if (config.authenticated) {
            window.url = window.location.href;
        }

        window.calculations = {};
        window.allDataArray = window.allDataArray || [];
        window.visibleData = window.visibleData || window.allDataArray;
        if (config.homeUrl) {
            window.__homeUrl = config.homeUrl;
        }
        if (config.notificationsUrl) {
            window.__notificationsUrl = config.notificationsUrl;
        }
        if (config.routeName) {
            window.__routeName = config.routeName;
        }
        if (config.companyLogoBase) {
            window.companyLogoBase = config.companyLogoBase;
        }
        if (typeof initNavButtons === 'function') initNavButtons();
        if (typeof initHomeShortcut === 'function' && config.homeUrl) initHomeShortcut();
        if (typeof messageBoxAnimation === 'function') messageBoxAnimation();
        if (typeof initGlobalUI === 'function') initGlobalUI();
        if (typeof initNegativeValueHighlighter === 'function') initNegativeValueHighlighter();
        if (typeof initPreviewTextFitting === 'function') initPreviewTextFitting();
        if (config.authenticated && typeof initActivityPing === 'function') initActivityPing();

        if (config.pusherEnabled) {
            window.__pusherKey = config.pusherKey;
            window.__pusherCluster = config.pusherCluster;
            window.__authUserId = config.authUserId;
            window.__authUserRole = config.authUserRole;
            window.__routeIsLogin = config.routeIsLogin;
            window.__routeIsSubscriptionExpired = config.routeIsSubscriptionExpired;
            window.__routeIsOrdersCreate = config.routeIsOrdersCreate;
            if (typeof initPusherNotifications === 'function') initPusherNotifications();
        }
        if (config.authenticated && typeof initNotificationPolling === 'function') {
            initNotificationPolling();
        }

        window.doHide = false;
        if (typeof initGlobalLoader === 'function') initGlobalLoader();

        const layoutBtn = document.getElementById('changeLayoutBtn');
        if (config.changeLayoutUrl) {
            window.__changeLayoutUrl = config.changeLayoutUrl;
        } else if (layoutBtn?.dataset?.changeLayoutUrl) {
            window.__changeLayoutUrl = layoutBtn.dataset.changeLayoutUrl;
        }

        if (layoutBtn?.dataset?.layout) {
            window.__authLayout = layoutBtn.dataset.layout;
        } else if (typeof window.authLayout !== 'undefined') {
            window.__authLayout = window.authLayout;
        } else {
            window.__authLayout = window.__authLayout || 'grid';
        }

        if (config.readonlySession && typeof initReadOnlyLock === 'function') {
            initReadOnlyLock();
        }

        if (typeof initAmountInputs === 'function') initAmountInputs();
        if (typeof initGlobalFormValidation === 'function') initGlobalFormValidation();

        if (typeof window.trackTypeState !== 'function') window.trackTypeState = () => {};
        if (typeof window.trackDateState !== 'function') window.trackDateState = () => {};
        if (typeof window.trackStateOfCategoryBtn !== 'function') window.trackStateOfCategoryBtn = () => {};
        if (typeof window.generateModal !== 'function') window.generateModal = () => {};
        if (typeof window.renderMenuShortcuts !== 'function') window.renderMenuShortcuts = () => {};

        const themeButtons = [
            document.getElementById('themeToggle'),
            document.getElementById('themeToggleMobile'),
        ].filter(Boolean);
        if (themeButtons.length) {
            const html = document.documentElement;
            const themeIcons = document.querySelectorAll('#themeToggle i, #themeToggleMobile i');
            const updateIcons = () => {
                themeIcons.forEach(icon => {
                    icon.classList.toggle('fa-sun');
                    icon.classList.toggle('fa-moon');
                });
            };
            const persistTheme = (theme) => {
                if (typeof $ !== 'undefined') {
                    $.ajax({
                        url: '/update-theme',
                        type: 'POST',
                        data: {
                            theme,
                            _token: $('meta[name="csrf-token"]').attr('content'),
                        },
                    });
                } else {
                    fetch('/update-theme', {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `theme=${encodeURIComponent(theme)}`,
                    }).catch(() => {});
                }
            };
            themeButtons.forEach(btn => {
                btn.addEventListener('click', () => {
                    const current = html.getAttribute('data-theme');
                    const next = current === 'dark' ? 'light' : 'dark';
                    html.setAttribute('data-theme', next);
                    updateIcons();
                    persistTheme(next);
                });
            });
        }
    }

    function hydrateConfigFromBody() {
        const raw = document.body?.dataset?.appConfig;
        if (!raw) return {};
        try {
            return JSON.parse(raw);
        } catch (e) {
            console.warn('Failed to parse data-app-config', e);
            return {};
        }
    }

    window.initAppCommon = initAppCommon;

    document.addEventListener('DOMContentLoaded', () => {
        const bodyConfig = hydrateConfigFromBody();
        window.__appConfig = Object.assign({}, bodyConfig, window.__appConfig || {});
        initAppCommon();
        document.dispatchEvent(new CustomEvent('app:config:ready'));
        document.addEventListener('app:data:rendered', (event) => {
            window.visibleData = event.detail?.items || window.allDataArray || [];
        });
    });

    window.formatPcsAndPackets = function formatPcsAndPackets(quantity, pcsPerPacket, packets = getPacketsFromPcs(quantity, pcsPerPacket)) {
        return `${quantity} pcs | ${packets} pkts`;
    }

    window.getPacketsFromPcs = function getPacketsFromPcs(quantity, pcsPerPacket) {
        const packetSize = Number(pcsPerPacket || 0);
        if (!packetSize) return 0;

        return Number(quantity || 0) / packetSize;
    }
})();
