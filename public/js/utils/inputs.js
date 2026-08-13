function checkMax(input) {
    input.value = input.value.replace(/\D/g, '');

    let errorElem = document.getElementById(input.id + '-error');

    const max = parseInt(input.max, 10);
    if (parseInt(input.value, 10) > max) {
        errorElem.textContent = `Value cannot exceed ${max}.`;
        if (errorElem.classList.contains('hidden')) {
            errorElem.classList.remove('hidden');
        }

        input.value = max;
    } else {
        errorElem.textContent = '';
        if (!errorElem.classList.contains('hidden')) {
            errorElem.classList.add('hidden');
        }
    }
}

function setQuantityPairError(input, message = '') {
    if (!input) return;

    const errorElem = document.getElementById(`${input.id}-error`);
    if (message) {
        input.classList.add('border-[var(--border-error)]');
        if (errorElem) {
            errorElem.textContent = message;
            errorElem.classList.remove('hidden');
        }
        return;
    }

    input.classList.remove('border-[var(--border-error)]');
    if (errorElem) {
        errorElem.textContent = '';
        errorElem.classList.add('hidden');
    }
}

function integerInputValue(input) {
    const raw = String(input?.value ?? '').trim();
    if (raw === '') {
        return { empty: true, valid: true, value: 0 };
    }

    if (!/^\d+$/.test(raw)) {
        return { empty: false, valid: false, value: 0 };
    }

    return { empty: false, valid: true, value: parseInt(raw, 10) };
}

function syncArticleQuantityPair(source, pcsPerPacket, maxPcs = 0) {
    const pcsInput = document.getElementById('quantity');
    const packetsInput = document.getElementById('quantity_packets');
    const setButton = document.getElementById('setQuantityBtn-in-modal');
    if (!pcsInput || !packetsInput) return true;

    const unit = parseInt(pcsPerPacket || 0, 10);
    const max = parseInt(maxPcs || pcsInput.max || 0, 10);
    let valid = true;

    setQuantityPairError(pcsInput);
    setQuantityPairError(packetsInput);

    if (source === 'packets') {
        const packets = integerInputValue(packetsInput);
        if (!packets.valid) {
            setQuantityPairError(packetsInput, 'Packets must be a whole number.');
            valid = false;
        } else if (unit <= 0 && !packets.empty) {
            setQuantityPairError(packetsInput, 'Pcs per packet is missing for this article.');
            valid = false;
        } else if (packets.empty) {
            pcsInput.value = '';
        } else {
            const pcs = packets.value * unit;
            pcsInput.value = pcs || '';
            if (max > 0 && pcs > max) {
                setQuantityPairError(pcsInput, `Quantity cannot exceed ${max} pcs.`);
                valid = false;
            }
        }
    } else {
        const pcs = integerInputValue(pcsInput);
        if (!pcs.valid) {
            setQuantityPairError(pcsInput, 'Quantity must be a whole number.');
            valid = false;
        } else if (pcs.empty) {
            packetsInput.value = '';
        } else if (max > 0 && pcs.value > max) {
            setQuantityPairError(pcsInput, `Quantity cannot exceed ${max} pcs.`);
            valid = false;
        } else if (unit <= 0) {
            packetsInput.value = '';
        } else if (pcs.value % unit !== 0) {
            packetsInput.value = '';
            setQuantityPairError(pcsInput, `Quantity must make whole packets of ${unit} pcs.`);
            valid = false;
        } else {
            packetsInput.value = pcs.value / unit;
        }
    }

    if (setButton) {
        setButton.disabled = !valid;
        setButton.classList.toggle('opacity-50', !valid);
        setButton.classList.toggle('cursor-not-allowed', !valid);
    }

    return valid;
}

function initializeArticleQuantityPair(pcsPerPacket, maxPcs = 0, pcsValue = '') {
    const pcsInput = document.getElementById('quantity');
    const packetsInput = document.getElementById('quantity_packets');
    if (!pcsInput || !packetsInput) return;

    pcsInput.value = pcsValue ? parseInt(pcsValue, 10) : '';
    syncArticleQuantityPair('pcs', pcsPerPacket, maxPcs);
}

function isFooterActionVisible(btn) {
    if (!btn || !btn.isConnected) return false;
    if (btn.closest('[hidden], .hidden')) return false;
    if (btn.disabled || btn.readOnly) return false;

    const style = window.getComputedStyle(btn);
    if (style.display === 'none' || style.visibility === 'hidden') return false;

    const rect = btn.getBoundingClientRect();
    return rect.width > 0 && rect.height > 0;
}

function getNextFooterActionButton() {
    const ids = ['nextBtn', 'saveBtn', 'printAndSaveBtn', 'printBtn'];
    for (const id of ids) {
        const btn = document.getElementById(id);
        if (isFooterActionVisible(btn)) {
            return btn;
        }
    }
    return null;
}

(function initEnterToNextField() {
    const fieldSelector = [
        'input:not([type="hidden"])',
        'select',
        'textarea',
        'button',
        'a[href]',
        '[role="button"]',
    ].join(',');

    function isRenderedField(field) {
        if (!field) return false;

        if (field.closest('.dropDownParent')) {
            return false;
        }

        if (field.closest('[hidden], .hidden')) {
            return false;
        }

        const style = window.getComputedStyle(field);

        if (
            style.display === 'none' ||
            style.visibility === 'hidden'
        ) {
            return false;
        }

        const rect = field.getBoundingClientRect();

        return rect.width > 0 && rect.height > 0;
    }

    function isVisibleField(field) {
        if (!isRenderedField(field) || field.disabled || field.readOnly) return false;
        if (field.matches?.('button[type="submit"], input[type="submit"]')) return false;
        if (field.matches?.('[tabindex="-1"]')) return false;
        if (field.classList.contains('select-add-btn')) return false;

        return true;
    }

    function fieldAnchor(field) {
        if (!field) return field;

        // Dropdown search input ko uske actual select field
        // ke saath associate karo.
        if (field.closest('.dropDownParent')) {
            const selectParent = field.closest('.selectParent');

            if (!selectParent) {
                return field;
            }

            return selectParent.querySelector(
                ':scope > .form-group input:not([type="hidden"])'
            ) || field;
        }

        return field;
    }

    function getFieldScope(field) {
        return field.closest('form')
            || field.closest('div[id$="-wrapper"]')
            || field.closest('.main-child')
            || document;
    }

    function getFocusableFields(scope) {
        return Array.from(scope.querySelectorAll(fieldSelector))
            .filter(field => !field.classList.contains('dbInput'))
            .filter(field => !field.classList.contains('errorIcon'))
            .filter(isVisibleField)
            .map(fieldAnchor)
            .filter((field, index, fields) => fields.indexOf(field) === index);
    }

    function getRenderedFields(scope) {
        return Array.from(scope.querySelectorAll(fieldSelector))
            .filter(field => !field.classList.contains('dbInput'))
            .filter(field => !field.classList.contains('errorIcon'))
            .filter(isRenderedField)
            .map(fieldAnchor)
            .filter((field, index, fields) => fields.indexOf(field) === index);
    }

    function isPickerField(field) {
        return ['date', 'time', 'datetime-local', 'month', 'week'].includes(field?.type);
    }

    function openNativePicker(field) {
        if (!isPickerField(field) || typeof field.showPicker !== 'function') {
            return;
        }

        window.setTimeout(() => {
            if (document.activeElement !== field || field.disabled || field.readOnly) {
                return;
            }

            try {
                field.showPicker();
            } catch (_) {
                // Browsers may block native pickers when focus was not caused by a direct user action.
            }
        }, 80);
    }

    function focusField(field, options = {}) {
        if (!field) return;

        const fromKeyboard = options.fromKeyboard ?? false;

        isKeyboardNavigationFocus = fromKeyboard;

        field.focus();

        isKeyboardNavigationFocus = false;

        if (fromKeyboard) {
            return;
        }

        if (
            field.matches?.('input, textarea') &&
            !isPickerField(field) &&
            !field.closest('.selectParent')
        ) {
            field.select?.();
        }

        if (isPickerField(field)) {
            setTimeout(() => {
                if (document.activeElement === field) {
                    try {
                        field.showPicker?.();
                    } catch (_) { }
                }
            }, 50);
        }
    }

    function waitForFieldAndFocus(field, options = {}) {
        const timeout = options.timeout ?? 1600;
        const startedAt = Date.now();

        const tryFocus = () => {
            if (!field?.isConnected || !isRenderedField(field)) {
                return false;
            }

            if (!field.disabled && !field.readOnly) {
                focusField(field);
                return true;
            }

            if (Date.now() - startedAt >= timeout) {
                return false;
            }

            window.setTimeout(tryFocus, 50);
            return true;
        };

        return tryFocus();
    }

    function isActionField(field) {
        return field?.matches?.('button, a[href], [role="button"], input[type="button"]');
    }

    function nextRenderedField(currentField) {
        const current = fieldAnchor(currentField);
        const renderedFields = getRenderedFields(getFieldScope(current));
        const renderedIndex = renderedFields.indexOf(current);

        if (renderedIndex === -1 || renderedIndex >= renderedFields.length - 1) {
            return null;
        }

        return renderedFields[renderedIndex + 1];
    }

    function watchImmediateNextAction(currentField) {
        const current = fieldAnchor(currentField);
        const next = nextRenderedField(current);

        if (!isActionField(next) || (!next.disabled && !next.readOnly)) {
            return false;
        }

        const startedAt = Date.now();
        const timeout = 1600;

        const tryFocus = () => {
            if (!current.isConnected || !next.isConnected || document.activeElement !== current) {
                return;
            }

            if (!next.disabled && !next.readOnly && isVisibleField(next)) {
                focusField(next);
                return;
            }

            if (Date.now() - startedAt < timeout) {
                window.setTimeout(tryFocus, 40);
            }
        };

        window.setTimeout(tryFocus, 0);
        return true;
    }

    function deferUntilInterfaceReady(callback, delay = 80) {
        const run = () => {
            window.requestAnimationFrame(() => {
                window.requestAnimationFrame(() => {
                    window.setTimeout(callback, delay);
                });
            });
        };

        if (document.readyState === 'complete') {
            run();
            return;
        }

        window.addEventListener('load', run, { once: true });
    }

    window.focusNextFormField = function (currentField) {
        const current = fieldAnchor(currentField);
        const scope = getFieldScope(current);

        const start = Date.now();
        const timeout = 1500;

        function findNext() {
            const fields = getRenderedFields(scope);
            const currentIndex = fields.indexOf(current);

            if (currentIndex === -1) {
                const firstField = getFocusableFields(scope)[0];

                if (firstField) {
                    focusField(firstField);
                    return true;
                }

                return false;
            }

            for (let i = currentIndex + 1; i < fields.length; i++) {
                const field = fields[i];

                if (!field?.isConnected) {
                    continue;
                }

                if (isVisibleField(field)) {
                    focusField(field, {
                        fromKeyboard: true
                    });

                    return true;
                }
            }

            const footerBtn = getNextFooterActionButton();
            if (footerBtn) {
                focusField(footerBtn);
                return true;
            }

            if (Date.now() - start < timeout) {
                setTimeout(findNext, 50);
                return true;
            }

            return false;
        }

        return findNext();
    };

    window.focusFirstFormField = function focusFirstFormField(scope = document, options = {}) {
        const form = scope.matches?.('form') ? scope : scope.querySelector?.('form');
        if (!form || form.dataset.autoFocusApplied === 'true') return false;

        if (options.onlyWhenIdle !== false) {
            const active = document.activeElement;
            const canMoveFocus = !active || active === document.body || active === document.documentElement;
            if (!canMoveFocus) return false;
        }

        const first = getFocusableFields(form)[0];
        if (!first) return false;

        form.dataset.autoFocusApplied = 'true';
        deferUntilInterfaceReady(() => {
            focusField(first, false);
        }, options.delay ?? 80);
        return true;
    };

    window.focusPreviousFormField = function (currentField) {
        const current = fieldAnchor(currentField);
        const scope = getFieldScope(current);

        const fields = getRenderedFields(scope);
        const currentIndex = fields.indexOf(current);

        if (currentIndex <= 0) {
            return false;
        }

        for (let i = currentIndex - 1; i >= 0; i--) {
            const field = fields[i];

            if (!isVisibleField(field)) {
                continue;
            }

            keyboardFieldNavigation = true;

            // Select ko focus karte waqt uska normal auto-open
            // behavior temporarily suppress hoga.
            field.focus();

            setTimeout(() => {
                keyboardFieldNavigation = false;
            }, 0);

            return true;
        }

        return false;
    };

    document.addEventListener("keydown", event => {
        if (event.defaultPrevented) return;

        let target = event.target;

        /*
        * Custom select dropdown search input:
        *
        * Actual focus dropdown ke andar wale search input par hota hai,
        * lekin form navigation ke liye usko visible select input maana jayega.
        */
        if (target?.closest?.('.dropDownParent')) {
            const selectParent = target.closest('.selectParent');

            if (selectParent) {
                const selectInput =
                    selectParent.querySelector(
                        ':scope > .form-group input:not([type="hidden"])'
                    ) ||
                    selectParent.querySelector(
                        'input:not([type="hidden"]):not(.dbInput)'
                    );

                if (selectInput) {
                    /*
                    * Shift + Enter
                    * dropdown search se previous FORM field
                    */
                    if (event.key === 'Enter' && event.shiftKey) {
                        event.preventDefault();
                        event.stopImmediatePropagation();

                        window.focusPreviousFormField(selectInput);
                        return;
                    }

                    /*
                    * Enter
                    * dropdown ka normal selectKeyDown handle karega.
                    */
                    return;
                }
            }
        }

        if (!target?.matches?.(fieldSelector)) return;
        if (!isVisibleField(target)) return;

        /*
        * Shift + Enter = Previous field
        */
        if (event.key === "Enter" && event.shiftKey) {
            event.preventDefault();
            event.stopImmediatePropagation();

            window.focusPreviousFormField(target);
            return;
        }

        if (event.key !== "Enter") return;

        event.preventDefault();

        /*
        * Date / time picker
        */
        if (isPickerField(target)) {
            if (!target.value) {
                try {
                    target.showPicker?.();
                } catch (_) { }

                return;
            }

            window.focusNextFormField(target);
            return;
        }

        /*
        * Footer/action button
        */
        if (isActionField(target)) {
            target.click();
            return;
        }

        /*
        * Normal field
        */
        window.focusNextFormField(target);
    });

    document.addEventListener("change", event => {
        const target = event.target;

        if (!isPickerField(target)) {
            return;
        }

        setTimeout(() => {
            window.focusNextFormField(target);
        }, 50);
    });

    document.addEventListener('input', event => {
        const target = event.target;
        if (!target?.matches?.('input:not([type="hidden"]), textarea')) return;
        watchImmediateNextAction(target);
    });

    document.addEventListener('change', event => {
        const target = event.target;
        if (!target?.matches?.(fieldSelector)) return;
        watchImmediateNextAction(target);
    });

    function focusInitialPageForm() {
        document.querySelectorAll('form').forEach(form => {
            window.focusFirstFormField(form);
        });
    }

    const scheduleInitialPageFocus = () => deferUntilInterfaceReady(focusInitialPageForm, 120);

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', scheduleInitialPageFocus);
    } else {
        scheduleInitialPageFocus();
    }

    document.addEventListener('app:config:ready', scheduleInitialPageFocus, { once: true });

    const observer = new MutationObserver(mutations => {
        mutations.forEach(mutation => {
            mutation.addedNodes.forEach(node => {
                if (!(node instanceof HTMLElement)) return;

                if (node.matches('form')) {
                    window.focusFirstFormField(node, { delay: 80 });
                    return;
                }

                node.querySelectorAll?.('form').forEach(form => {
                    window.focusFirstFormField(form, { delay: 80 });
                });
            });
        });
    });

    observer.observe(document.body, { childList: true, subtree: true });

    document.addEventListener('wizard:step-changed', event => {
        const step = event.detail.step;

        setTimeout(() => {
            const container = document.querySelector(`.step${step}`);

            if (!container) {
                return;
            }

            const firstField = getFocusableFields(container)[0];

            if (firstField) {
                focusField(firstField);
            }
        }, 50);
    });
})();
