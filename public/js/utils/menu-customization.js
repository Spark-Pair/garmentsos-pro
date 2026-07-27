function switchBtnTogggle(switchBtn, event) {
    event?.preventDefault?.();
    event?.stopPropagation?.();

    window.menu_shortcuts = normalizeMenuShortcuts(window.menu_shortcuts);
    if (typeof window.maxShortcutsLimit === 'undefined') {
        window.maxShortcutsLimit = 7;
    }

    if (window.__appConfig?.readonlySession) {
        if (typeof showMessageBox === 'function') {
            showMessageBox('warning', 'Read-only mode is enabled. You cannot update shortcuts.');
        }
        return;
    }

    const moduleName = switchBtn.dataset.for;
    const shouldEnable = !switchBtn.classList.contains('active');

    if (shouldEnable && window.menu_shortcuts.length >= window.maxShortcutsLimit) {
        if (typeof showMessageBox === 'function') {
            showMessageBox('error', `You have reached the maximum limit of ${window.maxShortcutsLimit} shortcuts.`);
        }
        return null;
    }

    if (switchBtn.dataset.saving === 'true') {
        return;
    }

    updateMenuCustomization(moduleName, shouldEnable ? 'active' : 'not-active', switchBtn);
}

function normalizeMenuShortcuts(value) {
    let shortcuts = value;
    if (typeof shortcuts === 'string') {
        try {
            shortcuts = JSON.parse(shortcuts);
        } catch (_) {
            shortcuts = [];
        }
    }

    if (!Array.isArray(shortcuts)) {
        return [];
    }

    return shortcuts.filter(item => typeof item === 'string' && item !== '');
}

function updateMenuCustomization(moduleName, newState, switchBtn = null) {
    window.menu_shortcuts = normalizeMenuShortcuts(window.menu_shortcuts);
    const nextShortcuts = [...window.menu_shortcuts];

    if (newState == 'active' && !nextShortcuts.includes(moduleName)) {
        nextShortcuts.push(moduleName);
    } else {
        const removeIndex = nextShortcuts.indexOf(moduleName);
        if (removeIndex !== -1) {
            nextShortcuts.splice(removeIndex, 1);
        }
    }

    if (switchBtn) {
        switchBtn.dataset.saving = 'true';
        switchBtn.classList.add('pointer-events-none', 'opacity-60');
    }

    $.ajax({
        url: '/update-menu-shortcuts',
        type: 'POST',
        data: JSON.stringify({
            menu_shortcuts: nextShortcuts
        }),
        contentType: 'application/json',
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
            'X-Requested-With': 'XMLHttpRequest',
        },
        success: function(response) {
            if (response.status === 'success' && response.saved === true) {
                window.menu_shortcuts = normalizeMenuShortcuts(response.menu_shortcuts);
                if (window.__appConfig) {
                    window.__appConfig.menuShortcuts = window.menu_shortcuts;
                }
                if (switchBtn) {
                    switchBtn.classList.toggle('active', window.menu_shortcuts.includes(moduleName));
                }
                if (typeof window.renderMenuShortcuts === 'function') {
                    window.renderMenuShortcuts();
                }
                reRenderInfoInModal('.menuModalInfo', `Enabled: ${window.menu_shortcuts.length}/${window.maxShortcutsLimit}`);
                return;
            }

            if (typeof showMessageBox === 'function') {
                showMessageBox('error', 'Menu shortcuts could not be saved.');
            }
        },
        error: function(xhr, status, error) {
            console.error('Menu shortcuts not updated', error);
            if (typeof showMessageBox === 'function') {
                const message = xhr?.responseJSON?.message || 'Menu shortcuts could not be saved.';
                showMessageBox('error', message);
            }
        },
        complete: function() {
            if (switchBtn) {
                switchBtn.dataset.saving = 'false';
                switchBtn.classList.remove('pointer-events-none', 'opacity-60');
            }
        }
    });
}
