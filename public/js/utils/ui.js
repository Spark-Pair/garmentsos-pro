function messageBoxAnimation() {
    setTimeout(function() {
        const messages = document.querySelectorAll('.alert-message');
        messages.forEach((message) => {
            if (message) {
                message.classList.add('fade-out');
                message.addEventListener('animationend', () => {
                    message.style.display = 'none';
                });
            }
        });
    }, 5000);
}

function renderAlert(type = 'info', message = '') {
    const config = {
        info: {
            bg: 'bg-[var(--secondary-bg-color)]',
            text: 'text-[var(--secondary-text)]',
            icon: 'fa-circle-info',
        },
        success: {
            bg: 'bg-[var(--bg-success)]',
            text: 'text-[var(--text-success)]',
            icon: 'fa-circle-check',
        },
        warning: {
            bg: 'bg-[var(--bg-warning)]',
            text: 'text-[var(--text-warning)]',
            icon: 'fa-triangle-exclamation',
        },
        error: {
            bg: 'bg-[var(--bg-error)]',
            text: 'text-[var(--text-error)]',
            icon: 'fa-circle-exclamation',
        },
    };

    const cfg = config[type] || config.info;

    const wrapper = document.createElement('div');
    wrapper.className = `alert-message ${cfg.bg} ${cfg.text} ps-2 pe-5 py-2 rounded-2xl flex items-center gap-2 fade-in leading-none tracking-wide`;

    const icon = document.createElement('i');
    icon.className = `fas ${cfg.icon} text-lg mr-1`;

    const text = document.createElement('p');
    text.textContent = message;

    wrapper.appendChild(icon);
    wrapper.appendChild(text);

    return wrapper;
}

function showMessageBox(type = 'info', message = '') {
    if (typeof messageBox === 'undefined' || !messageBox) return;
    messageBox.innerHTML = '';
    messageBox.appendChild(renderAlert(type, message));
    messageBoxAnimation();
}

function showToast(type = 'info', message = '') {
    showMessageBox(type, message);
}

function appAlert(message = '', type = 'error') {
    if (typeof showMessageBox === 'function') {
        showMessageBox(type, message);
        return;
    }

    console.error(message);
}

function hideNotification(notificationElem) {
    notificationElem.classList.add('fade-out');
    notificationElem.addEventListener('animationend', () => {
        notificationElem.style.display = 'none';
        notificationElem.remove();
    });
}

function openDropDown(e, trigger) {
    e.stopPropagation();
    const relatedDropDownMenu = trigger.nextElementSibling;

    if (relatedDropDownMenu.classList.contains('hidden')) {
        closeAllDropdowns(relatedDropDownMenu);
        trigger?.setAttribute('aria-expanded', 'true');
        relatedDropDownMenu.classList.remove('hidden');
        setTimeout(() => {
            relatedDropDownMenu.classList.add('opacity-100', 'scale-in');
            relatedDropDownMenu.classList.remove('opacity-0', 'scale-out');
            focusFirstDropdownField(relatedDropDownMenu);
        }, 10);
    } else {
        trigger?.setAttribute('aria-expanded', 'false');
        relatedDropDownMenu.classList.remove('opacity-100', 'scale-in');
        relatedDropDownMenu.classList.add('opacity-0', 'scale-out');
        setTimeout(() => {
            relatedDropDownMenu.classList.add('hidden');
        }, 300);
    }
}

function closeAllDropdowns(skipElement = null) {
    const dropdownMenus = document.querySelectorAll('.dropdownMenu');
    dropdownMenus.forEach(menu => {
        if (menu === skipElement) return;
        const trigger = menu.previousElementSibling;
        trigger?.setAttribute?.('aria-expanded', 'false');
        menu.classList.remove('opacity-100', 'scale-in');
        menu.classList.add('opacity-0', 'scale-out');
        setTimeout(() => {
            menu.classList.add('hidden');
        }, 300);
    });
}

function focusFirstDropdownField(dropdownMenu) {
    if (!dropdownMenu) return;

    const firstField = dropdownMenu.querySelector('[data-filter-path]');
    if (!firstField) return;

    if (firstField.classList.contains('dbInput')) {
        const targetId = firstField.getAttribute('data-for') || firstField.id;
        const visibleInput = dropdownMenu.querySelector(`#${CSS.escape(targetId)}`);

        if (visibleInput) {
            visibleInput.focus();
            if (typeof visibleInput.select === 'function') {
                visibleInput.select();
            }
        }

        return;
    }

    firstField.focus();
    if (typeof firstField.select === 'function') {
        firstField.select();
    }
}

function showLoader() {
    const loader = document.getElementById('page-loader');
    if (!loader) return;
    loader.classList.remove('hidden');
    loader.classList.remove('fade-out');
    loader.classList.add('fade-in');
}

function hideLoader() {
    const loader = document.getElementById('page-loader');
    if (!loader) return;
    loader.classList.add('hidden');
    loader.classList.add('fade-out');
    loader.classList.remove('fade-in');
}

const previewImage = (event) => {
    const file = event.target.files[0];
    const placeholderIcon = document.querySelector('.placeholder_icon');
    const uploadText = document.querySelector('.upload_text');

    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            if (placeholderIcon) {
                placeholderIcon.src = e.target.result;
                placeholderIcon.classList.add('rounded-md', 'w-full', 'h-auto');
            }
            if (uploadText) {
                uploadText.textContent = 'Preview';
            }
        };
        reader.readAsDataURL(file);
    }
};

const previewFileName = (event) => {
    const file = event.target.files[0];
    const uploadText = document.querySelector('.upload_text');

    if (file && uploadText) {
        uploadText.textContent = `Selected: ${file.name}`;
    }
};

function initGlobalUI() {
    function isNativeInteractive(element) {
        return ['A', 'BUTTON', 'INPUT', 'SELECT', 'TEXTAREA', 'SUMMARY'].includes(element.tagName);
    }

    function isTypingTarget(element) {
        return element.closest('input, textarea, select, [contenteditable="true"]');
    }

    function hydrateKeyboardActions(root = document) {
        root.querySelectorAll?.('[data-keyboard-action="true"], [role="button"], #table-head > .cursor-pointer').forEach(element => {
            if (isNativeInteractive(element)) return;
            if (!element.hasAttribute('tabindex')) {
                element.setAttribute('tabindex', '0');
            }
            if (!element.hasAttribute('role')) {
                element.setAttribute('role', 'button');
            }
        });
    }

    hydrateKeyboardActions();

    const observer = new MutationObserver(mutations => {
        mutations.forEach(mutation => {
            mutation.addedNodes.forEach(node => {
                if (node.nodeType === Node.ELEMENT_NODE) {
                    hydrateKeyboardActions(node);
                }
            });
        });
    });
    observer.observe(document.body, { childList: true, subtree: true });

    document.addEventListener('focus', function(event) {
        if (!event.isTrusted) return;
        if (event.target.matches('input[type=\"date\"]')) {
            try { event.target.showPicker(); } catch (_) {}
        } else if (event.target.matches('input[type=\"month\"]')) {
            try { event.target.showPicker(); } catch (_) {}
        }
    }, true);

    document.addEventListener('keydown', function(event) {
        if (isTypingTarget(event.target)) return;

        if ((event.key === 'Enter' || event.key === ' ') && event.target.matches('[role="button"]')) {
            event.preventDefault();
            event.target.click();
        }
    });

    document.addEventListener('contextmenu', e => e.preventDefault());
}

function initNegativeValueHighlighter() {
    const negativeNumberPattern = /(?:^|[\s(:|])(?:Rs\.?\s*)?-\s*\d[\d,]*(?:\.\d+)?\b/i;
    const dateRangePattern = /\b\d{1,2}[-/](?:[A-Za-z]{3,9}|\d{1,2})[-/]\d{2,4}\s*-\s*\d{1,2}[-/](?:[A-Za-z]{3,9}|\d{1,2})[-/]\d{2,4}\b/i;
    const textTargets = 'span, div, p, td, th, label, strong, b, small, li';
    const inputTargets = 'input, textarea';
    const skippedTextParents = 'script, style, noscript, option, select, svg';
    const skippedInputs = new Set(['date', 'month', 'time', 'datetime-local', 'checkbox', 'radio', 'file', 'password']);

    function hasNegativeValue(value) {
        const text = String(value || '').trim();
        if (!text) return false;
        if (dateRangePattern.test(text)) return false;

        return negativeNumberPattern.test(text);
    }

    function isLeafTextElement(element) {
        return !Array.from(element.children).some(child => {
            const tag = child.tagName?.toLowerCase();
            return tag && !['i', 'svg', 'path'].includes(tag);
        });
    }

    function markTextElement(element) {
        if (element.closest(skippedTextParents)) return;
        if (!isLeafTextElement(element)) return;

        const hasNegative = hasNegativeValue(element.textContent);
        element.classList.toggle('negative-value', hasNegative);
    }

    function markInput(element) {
        if (skippedInputs.has((element.type || '').toLowerCase())) return;

        const hasNegative = hasNegativeValue(element.value);
        element.classList.toggle('negative-value', hasNegative);
    }

    function highlightNegativeValues(root = document.body) {
        if (!root) return;

        if (root.matches?.(textTargets)) markTextElement(root);
        if (root.matches?.(inputTargets)) markInput(root);

        root.querySelectorAll?.(textTargets).forEach(markTextElement);
        root.querySelectorAll?.(inputTargets).forEach(markInput);
    }

    function scheduleHighlight(root = document.body) {
        window.requestAnimationFrame(() => highlightNegativeValues(root));
    }

    scheduleHighlight();

    document.addEventListener('input', event => {
        if (event.target.matches?.(inputTargets)) {
            markInput(event.target);
        }
    }, true);

    document.addEventListener('app:data:rendered', () => scheduleHighlight());

    const observer = new MutationObserver(mutations => {
        const roots = new Set();

        mutations.forEach(mutation => {
            if (mutation.type === 'characterData') {
                const parent = mutation.target.parentElement;
                if (parent) roots.add(parent);
                return;
            }

            if (mutation.type === 'attributes') {
                roots.add(mutation.target);
                return;
            }

            mutation.addedNodes.forEach(node => {
                if (node.nodeType === Node.ELEMENT_NODE) {
                    roots.add(node);
                } else if (node.nodeType === Node.TEXT_NODE && node.parentElement) {
                    roots.add(node.parentElement);
                }
            });
        });

        if (!roots.size) return;
        window.requestAnimationFrame(() => roots.forEach(root => highlightNegativeValues(root)));
    });

    observer.observe(document.body, {
        childList: true,
        subtree: true,
        characterData: true,
        attributes: true,
        attributeFilter: ['value'],
    });

    window.highlightNegativeValues = highlightNegativeValues;
}

function initPreviewTextFitting() {
    const previewSelector = '#preview-container, .preview, .preview-document';
    const cellSelector = '.td, .th';
    let scheduled = false;

    function getPreviewRoots(root = document.body) {
        const roots = new Set();

        if (root.matches?.(previewSelector)) roots.add(root);
        root.querySelectorAll?.(previewSelector).forEach(preview => roots.add(preview));

        return Array.from(roots);
    }

    function getCells(root) {
        const cells = [];

        if (root.matches?.(cellSelector)) cells.push(root);
        root.querySelectorAll?.(cellSelector).forEach(cell => cells.push(cell));

        return cells;
    }

    function fitCell(cell) {
        if (!cell || !cell.isConnected || !cell.textContent.trim()) return;
        if (cell.clientWidth <= 0) return;

        cell.style.fontSize = '';
        const computed = window.getComputedStyle(cell);
        const baseSize = parseFloat(computed.fontSize) || 12;
        const minSize = cell.classList.contains('th') ? 8.5 : 7;

        if (cell.scrollWidth <= cell.clientWidth + 1) return;

        let size = baseSize;
        while (size > minSize && cell.scrollWidth > cell.clientWidth + 1) {
            size -= 0.25;
            cell.style.fontSize = `${size}px`;
        }

        cell.title = cell.textContent.trim();
    }

    function fitPreviewText(root = document.body) {
        getPreviewRoots(root).forEach(preview => {
            getCells(preview).forEach(fitCell);
        });
    }

    function scheduleFit(root = document.body) {
        if (scheduled) return;
        scheduled = true;

        window.requestAnimationFrame(() => {
            scheduled = false;
            fitPreviewText(root);
        });
    }

    scheduleFit();

    document.addEventListener('app:data:rendered', () => scheduleFit());

    const observer = new MutationObserver(mutations => {
        for (const mutation of mutations) {
            if (mutation.target?.closest?.(previewSelector)) {
                scheduleFit(mutation.target.closest(previewSelector));
                return;
            }

            for (const node of mutation.addedNodes) {
                if (node.nodeType !== Node.ELEMENT_NODE) continue;
                if (node.matches?.(previewSelector) || node.querySelector?.(previewSelector)) {
                    scheduleFit(node);
                    return;
                }
            }
        }
    });

    observer.observe(document.body, {
        childList: true,
        subtree: true,
        characterData: true,
    });

    window.fitPreviewText = fitPreviewText;
}
