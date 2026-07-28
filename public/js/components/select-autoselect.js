(() => {
    function autoSelectOptions() {
        document.querySelectorAll('li[data-auto-select="true"]').forEach(li => {
            selectThisOption(li);
        });
    }

    function scheduleAutoSelectOptions() {
        const run = () => {
            window.requestAnimationFrame(() => {
                window.requestAnimationFrame(() => {
                    window.setTimeout(autoSelectOptions, 60);
                });
            });
        };

        if (document.readyState === 'complete') {
            run();
            return;
        }

        window.addEventListener('load', run, { once: true });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', scheduleAutoSelectOptions);
    } else {
        scheduleAutoSelectOptions();
    }

    document.addEventListener('app:config:ready', scheduleAutoSelectOptions, { once: true });
})();
