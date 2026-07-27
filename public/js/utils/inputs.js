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

(function initEnterToNextField() {
    const fieldSelector = [
        'input:not([type="hidden"])',
        'select',
        'textarea',
    ].join(',');

    function isVisibleField(field) {
        if (!field || field.disabled || field.readOnly) return false;
        if (field.closest('[hidden], .hidden')) return false;
        if (field.closest('.dropDownParent')) return false;

        const style = window.getComputedStyle(field);
        if (style.display === 'none' || style.visibility === 'hidden') return false;

        const rect = field.getBoundingClientRect();
        return rect.width > 0 && rect.height > 0;
    }

    function fieldAnchor(field) {
        const selectParent = field.closest?.('.selectParent');
        if (!selectParent) return field;

        const visibleSelectInput = selectParent.querySelector(':scope > .form-group input:not([type="hidden"])')
            || selectParent.querySelector('input:not([type="hidden"]):not(.dbInput)');

        return visibleSelectInput || field;
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
            .filter(isVisibleField)
            .map(fieldAnchor)
            .filter((field, index, fields) => fields.indexOf(field) === index);
    }

    function focusField(field) {
        field.focus();
        field.select?.();

        if (
            ['date', 'time', 'datetime-local', 'month', 'week'].includes(field.type)
            && typeof field.showPicker === 'function'
        ) {
            try {
                field.showPicker();
            } catch (_) {
                // Some browsers only allow showPicker during direct user activation.
            }
        }
    }

    window.focusNextFormField = function focusNextFormField(currentField) {
        const current = fieldAnchor(currentField);
        const fields = getFocusableFields(getFieldScope(current));
        if (!fields.length) return false;

        const currentIndex = fields.indexOf(current);
        if (currentIndex === -1 || currentIndex >= fields.length - 1) return false;

        const next = fields[currentIndex + 1];
        focusField(next);
        return true;
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
        requestAnimationFrame(() => {
            focusField(first);
        });
        return true;
    };

    document.addEventListener('keydown', event => {
        if (event.defaultPrevented) return;
        if (event.key !== 'Enter') return;
        if (event.ctrlKey || event.altKey || event.metaKey || event.shiftKey) return;

        const target = event.target;
        if (!target?.matches?.(fieldSelector)) return;
        if (target.matches('textarea')) return;
        if (target.closest('.dropDownParent')) return;
        if (!isVisibleField(target)) return;

        if (window.focusNextFormField(target)) {
            event.preventDefault();
        }
    });

    function focusInitialPageForm() {
        document.querySelectorAll('form').forEach(form => {
            window.focusFirstFormField(form);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', focusInitialPageForm);
    } else {
        focusInitialPageForm();
    }

    const observer = new MutationObserver(mutations => {
        mutations.forEach(mutation => {
            mutation.addedNodes.forEach(node => {
                if (!(node instanceof HTMLElement)) return;

                if (node.matches('form')) {
                    window.focusFirstFormField(node, { onlyWhenIdle: false });
                    return;
                }

                node.querySelectorAll?.('form').forEach(form => {
                    window.focusFirstFormField(form, { onlyWhenIdle: false });
                });
            });
        });
    });

    observer.observe(document.body, { childList: true, subtree: true });
})();
