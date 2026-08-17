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
        initMobileDocumentPreviewFitting();
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

    function initMobileDocumentPreviewFitting() {
        if (window.__mobileDocumentPreviewFittingReady) return;
        window.__mobileDocumentPreviewFittingReady = true;

        let resizeTimer = null;
        const scheduleFit = () => {
            window.clearTimeout(resizeTimer);
            resizeTimer = window.setTimeout(() => {
                window.fitDocumentPreviewsToMobile?.();
            }, 80);
        };

        const observer = new MutationObserver(scheduleFit);
        observer.observe(document.body, {
            attributes: true,
            attributeFilter: ['class'],
            childList: true,
            subtree: true,
        });

        window.addEventListener('resize', scheduleFit);
        window.addEventListener('orientationchange', scheduleFit);
        scheduleFit();
    }

    window.fitDocumentPreviewsToMobile = function fitDocumentPreviewsToMobile(root = document) {
        const isMobile = window.matchMedia('(max-width: 768px)').matches;
        const containers = Array.from(root.querySelectorAll?.('#preview-container, .preview-container') || []);

        containers.forEach(container => {
            if (container.closest('#printIframe')) return;

            const previewPages = Array.from(container.querySelectorAll('.preview-page'));
            const pages = previewPages.length
                ? previewPages
                : Array.from(container.querySelectorAll('.preview'));
            if (!pages.length) return;

            if (!isMobile) {
                container.previousElementSibling
                    ?.matches?.('[data-mobile-preview-toolbar="auto"]') &&
                    container.previousElementSibling.remove();
                delete container.dataset.mobilePreviewFit;
                delete container.dataset.mobilePreviewBaseScale;
                delete container.dataset.mobilePreviewZoomSteps;
                container.style.removeProperty('--mobile-preview-scale');
                container.style.removeProperty('--mobile-preview-width');
                container.style.removeProperty('--mobile-preview-height');
                container.style.removeProperty('width');
                container.style.removeProperty('min-width');
                container.style.removeProperty('max-width');
                container.style.removeProperty('transform');
                container.style.removeProperty('transform-origin');
                pages.forEach(page => {
                    page.style.removeProperty('zoom');
                    page.style.removeProperty('transform');
                    page.style.removeProperty('transform-origin');
                    page.style.removeProperty('margin-left');
                    page.style.removeProperty('margin-right');
                    page.style.removeProperty('margin-bottom');
                });
                return;
            }

            // ensureMobilePreviewToolbar(container);

            const scrollParent = container.closest('.step2, .modal-body, .details, main') || container.parentElement;
            const availableWidth = Math.max(260, (scrollParent?.clientWidth || window.innerWidth) - 24);
            const firstPage = pages[0];
            const previousScale = Number(firstPage.dataset.mobilePreviewAppliedScale || 1) || 1;
            const measuredRect = firstPage.getBoundingClientRect();
            const measuredWidth = measuredRect.width / previousScale;
            const measuredHeight = measuredRect.height / previousScale;

            if (!measuredWidth || !measuredHeight) return;

            const baseScale = Math.min(1, Math.max(0.32, availableWidth / measuredWidth));
            const zoomSteps = Number(container.dataset.mobilePreviewZoomSteps || 0);
            const scale = Math.min(1.8, Math.max(0.32, baseScale + (zoomSteps * 0.1)));
            const scaledWidth = measuredWidth * scale;
            const scaledHeight = measuredHeight * scale;

            container.dataset.mobilePreviewFit = '1';
            container.dataset.mobilePreviewBaseScale = String(baseScale);
            container.dataset.mobilePreviewScale = String(scale);
            container.style.setProperty('--mobile-preview-scale', String(scale));
            container.style.width = `${Math.ceil(scaledWidth)}px`;
            container.style.minWidth = `${Math.ceil(scaledWidth)}px`;
            container.style.maxWidth = '100%';

            pages.forEach(page => {
                page.dataset.mobilePreviewAppliedScale = String(scale);
                page.style.removeProperty('zoom');
                page.style.transform = `scale(${scale})`;
                page.style.transformOrigin = 'top left';
                page.style.marginLeft = '0';
                page.style.marginRight = `${Math.ceil(scaledWidth - measuredWidth)}px`;
                page.style.marginBottom = `${Math.ceil(scaledHeight - measuredHeight)}px`;
            });

            const resetButton = previewToolbarFor(container)?.querySelector('[data-statement-zoom="reset"], [data-preview-zoom="reset"]');
            if (resetButton && container.id === 'preview-container') {
                resetButton.textContent = `${Math.round(scale * 100)}%`;
            }
        });
    };

    function previewToolbarFor(container) {
        const previous = container.previousElementSibling;
        if (previous?.matches?.('.statement-preview-toolbar, [data-mobile-preview-toolbar]')) {
            return previous;
        }

        return null;
    }

    function ensureMobilePreviewToolbar(container) {
        if (previewToolbarFor(container)) return;

        const toolbar = document.createElement('div');
        toolbar.dataset.mobilePreviewToolbar = 'auto';
        toolbar.className = 'sticky top-0 z-20 mb-2 flex justify-end gap-2 bg-white/95 p-2 text-black shadow-sm';
        toolbar.innerHTML = `
            <button type="button" class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-semibold" data-preview-zoom="out">-</button>
            <button type="button" class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-semibold" data-preview-zoom="reset">100%</button>
            <button type="button" class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-semibold" data-preview-zoom="in">+</button>
        `;

        toolbar.querySelectorAll('[data-preview-zoom]').forEach(button => {
            button.addEventListener('click', () => {
                let zoomSteps = Number(container.dataset.mobilePreviewZoomSteps || 0);
                const action = button.dataset.previewZoom;

                if (action === 'in') zoomSteps = Math.min(10, zoomSteps + 1);
                if (action === 'out') zoomSteps = Math.max(-3, zoomSteps - 1);
                if (action === 'reset') zoomSteps = 0;

                container.dataset.mobilePreviewZoomSteps = String(zoomSteps);
                window.fitDocumentPreviewsToMobile?.();
            });
        });

        container.insertAdjacentElement('beforebegin', toolbar);
    }

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
