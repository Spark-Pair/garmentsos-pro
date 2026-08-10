function initGlobalLoader() {
    let activeLoads = 0;
    let hideTimer = null;
    let loaderShownAt = 0;
    const minVisibleMs = 180;

    function shouldSkipUrl(url) {
        const text = String(url || '');

        return text.includes('/notifications') ||
            text.includes('/update-last-activity') ||
            text.includes('/developer/updater/status') ||
            text.includes('/update-lock') ||
            text.includes('/license/status/current');
    }

    function beginLoad() {
        window.clearTimeout(hideTimer);
        activeLoads += 1;

        if (activeLoads === 1) {
            loaderShownAt = Date.now();
            showLoader();
        }
    }

    function endLoad(force = false) {
        activeLoads = force ? 0 : Math.max(0, activeLoads - 1);
        if (activeLoads > 0) return;

        const elapsed = Date.now() - loaderShownAt;
        const delay = Math.max(0, minVisibleMs - elapsed);

        window.clearTimeout(hideTimer);
        hideTimer = window.setTimeout(function () {
            if (activeLoads === 0) {
                hideLoader();
            }
        }, delay);
    }

    function isDownloadLikeLink(link) {
        const href = link.getAttribute('href') || '';

        return link.hasAttribute('download') ||
            link.dataset.noLoader === 'true' ||
            link.dataset.downloadLink === 'true' ||
            href.includes('download=1') ||
            /\/download(?:[/?#]|$)/i.test(href);
    }

    function suppressLoaderForDownload() {
        window.__skipNextGlobalLoader = true;

        window.setTimeout(function () {
            hideLoader();
            window.__skipNextGlobalLoader = false;
        }, 1200);
    }

    function bindPageHandlers() {
        document.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', function () {
                const href = this.getAttribute('href');
                const target = this.getAttribute('target');

                if (isDownloadLikeLink(this)) {
                    suppressLoaderForDownload();
                    return;
                }

                if (
                    href &&
                    !href.startsWith('#') &&
                    !href.startsWith('javascript:') &&
                    !target
                ) {
                    beginLoad();
                }
            });
        });

        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function () {
                beginLoad();
            });
        });
    }

    window.addEventListener('beforeunload', function () {
        if (window.__skipNextGlobalLoader) {
            return;
        }

        beginLoad();
    });

    window.addEventListener('focus', function () {
        if (window.__skipNextGlobalLoader) {
            endLoad(true);
            window.__skipNextGlobalLoader = false;
        }
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bindPageHandlers);
    } else {
        bindPageHandlers();
    }

    window.addEventListener('load', function () {
        endLoad(true);
    });

    window.addEventListener('pageshow', function () {
        endLoad(true);
    });

    document.addEventListener('app:data:rendered', function () {
        endLoad(true);
    });

    if (typeof axios !== 'undefined') {
        axios.interceptors.request.use(config => {
            beginLoad();
            return config;
        }, error => {
            endLoad();
            return Promise.reject(error);
        });

        axios.interceptors.response.use(response => {
            endLoad();
            return response;
        }, error => {
            endLoad();
            return Promise.reject(error);
        });
    }

    if (typeof $ !== 'undefined') {
        $(document).ajaxStart(function () {
            beginLoad();
        }).ajaxStop(function () {
            if (!doHide) {
                endLoad(true);
            }
            doHide = false;
        });
    }

    if (typeof window.fetch === 'function' && !window.fetch.__garmentsosLoaderWrapped) {
        const nativeFetch = window.fetch.bind(window);

        window.fetch = function loaderFetch(input, init = {}) {
            const url = typeof input === 'string' ? input : input?.url;
            const method = String(init?.method || input?.method || 'GET').toUpperCase();
            const headers = init?.headers || input?.headers || {};
            const requestedWith = typeof headers.get === 'function'
                ? headers.get('X-Requested-With')
                : (headers['X-Requested-With'] || headers['x-requested-with']);
            const isPageDataRequest = method === 'GET' && requestedWith === 'XMLHttpRequest';
            const shouldTrack = !isPageDataRequest && !shouldSkipUrl(url) && (method !== 'GET' || String(url || '').startsWith('/'));

            if (shouldTrack) {
                beginLoad();
            }

            return nativeFetch(input, init).finally(function () {
                if (shouldTrack) {
                    endLoad();
                }
            });
        };

        window.fetch.__garmentsosLoaderWrapped = true;
    }
}
