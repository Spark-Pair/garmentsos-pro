(() => {
    const selector = '.my-scrollbar-2';
    const minThumbHeight = 32;
    const thumbWidth = 6;
    const inset = 3;
    const verticalInset = 6;

    let activeElement = null;
    let dragging = false;
    let dragStartY = 0;
    let dragStartScrollTop = 0;
    let dragTrackHeight = 0;
    let dragThumbHeight = 0;
    let frameId = 0;
    let activeObserver = null;
    let observedElement = null;

    function getThumb() {
        let thumb = document.getElementById('gos-overlay-scrollbar-thumb');
        if (thumb) return thumb;

        thumb = document.createElement('div');
        thumb.id = 'gos-overlay-scrollbar-thumb';
        thumb.className = 'gos-overlay-scrollbar-thumb';
        document.body.appendChild(thumb);
        bindThumbDrag(thumb);

        return thumb;
    }

    function canUseOverlay(element) {
        return element
            && element.classList?.contains('my-scrollbar-2')
            && isVisible(element)
            && hasScrollableOverflow(element)
            && element.scrollHeight > element.clientHeight + 1
            && element.clientHeight > 0;
    }

    function hasScrollableOverflow(element) {
        const overflowY = window.getComputedStyle(element).overflowY;
        return ['auto', 'scroll', 'overlay'].includes(overflowY);
    }

    function isVisible(element) {
        if (!element?.isConnected) return false;
        if (!element.getClientRects().length) return false;

        let node = element;
        while (node && node.nodeType === Node.ELEMENT_NODE) {
            const styles = window.getComputedStyle(node);
            if (
                styles.display === 'none'
                || styles.visibility === 'hidden'
                || Number(styles.opacity) === 0
                || styles.pointerEvents === 'none'
                || node.classList.contains('hidden')
            ) {
                return false;
            }

            if (node === document.body) break;
            node = node.parentElement;
        }

        return true;
    }

    function findScrollableElement(target) {
        return target?.closest?.(selector) || null;
    }

    function hideThumb() {
        if (dragging) return;
        getThumb().classList.remove('is-visible');
        stopActiveObserver();
    }

    function validateActiveThumb() {
        if (!activeElement) return;

        if (!canUseOverlay(activeElement) || (!activeElement.matches(':hover') && !dragging)) {
            hideThumb();
        }
    }

    function scheduleValidate() {
        if (frameId) return;

        frameId = window.requestAnimationFrame(() => {
            frameId = 0;
            validateActiveThumb();
        });
    }

    function scheduleShow(element) {
        activeElement = element;
        if (frameId) return;

        frameId = window.requestAnimationFrame(() => {
            frameId = 0;
            showForElement(activeElement);
        });
    }

    function startActiveObserver(element) {
        if (activeObserver && observedElement === element) return;
        stopActiveObserver();

        observedElement = element;
        activeObserver = new MutationObserver(validateActiveThumb);

        const observedNodes = new Set();
        let node = element;

        while (node && node.nodeType === Node.ELEMENT_NODE) {
            if (!observedNodes.has(node)) {
                activeObserver.observe(node, {
                    attributes: true,
                    attributeFilter: ['class', 'style', 'hidden'],
                });
                observedNodes.add(node);
            }

            if (node.parentElement && !observedNodes.has(node.parentElement)) {
                activeObserver.observe(node.parentElement, {
                    childList: true,
                });
                observedNodes.add(node.parentElement);
            }

            if (node === document.body) break;
            node = node.parentElement;
        }
    }

    function stopActiveObserver() {
        activeObserver?.disconnect();
        activeObserver = null;
        observedElement = null;
    }

    function updateThumb(element = activeElement) {
        const thumb = getThumb();

        if (!canUseOverlay(element) || !element.isConnected) {
            thumb.classList.remove('is-visible');
            return;
        }

        activeElement = element;

        const rect = element.getBoundingClientRect();
        const trackHeight = Math.max(1, rect.height - (verticalInset * 2));
        const thumbHeight = Math.max(minThumbHeight, Math.round((element.clientHeight / element.scrollHeight) * trackHeight));
        const maxThumbTop = Math.max(0, trackHeight - thumbHeight);
        const maxScrollTop = Math.max(1, element.scrollHeight - element.clientHeight);
        const thumbTop = rect.top + verticalInset + (element.scrollTop / maxScrollTop) * maxThumbTop;
        const thumbLeft = rect.right - thumbWidth - inset;

        thumb.style.height = `${thumbHeight}px`;
        thumb.style.left = `${thumbLeft}px`;
        thumb.style.top = `${thumbTop}px`;
        thumb.classList.add('is-visible');
        startActiveObserver(element);
    }

    function showForElement(element) {
        if (!canUseOverlay(element)) return;

        updateThumb(element);
    }

    function bindThumbDrag(thumb) {
        thumb.addEventListener('pointerdown', event => {
            if (!canUseOverlay(activeElement)) return;

            event.preventDefault();
            dragging = true;
            dragStartY = event.clientY;
            dragStartScrollTop = activeElement.scrollTop;
            dragTrackHeight = Math.max(1, activeElement.getBoundingClientRect().height - (verticalInset * 2));
            dragThumbHeight = thumb.getBoundingClientRect().height;
            thumb.classList.add('is-dragging', 'is-visible');
            thumb.setPointerCapture?.(event.pointerId);
        });

        thumb.addEventListener('pointermove', event => {
            if (!dragging || !canUseOverlay(activeElement)) return;

            const maxThumbMove = Math.max(1, dragTrackHeight - dragThumbHeight);
            const maxScrollTop = activeElement.scrollHeight - activeElement.clientHeight;
            const delta = event.clientY - dragStartY;

            activeElement.scrollTop = dragStartScrollTop + (delta / maxThumbMove) * maxScrollTop;
            updateThumb(activeElement);
        });

        function stopDragging(event) {
            if (!dragging) return;

            dragging = false;
            thumb.classList.remove('is-dragging');
            thumb.releasePointerCapture?.(event.pointerId);
            if (!activeElement?.matches(':hover')) {
                hideThumb();
            }
        }

        thumb.addEventListener('pointerup', stopDragging);
        thumb.addEventListener('pointercancel', stopDragging);
    }

    document.addEventListener('scroll', event => {
        const element = event.target?.classList?.contains('my-scrollbar-2') ? event.target : null;
        if (element && (element.matches(':hover') || dragging)) {
            scheduleShow(element);
        }
    }, true);

    document.addEventListener('pointermove', event => {
        const element = findScrollableElement(event.target);
        if (element) {
            scheduleShow(element);
        } else {
            scheduleValidate();
        }
    }, true);

    document.addEventListener('pointerout', event => {
        const element = findScrollableElement(event.target);
        if (!element || dragging) return;

        const relatedTarget = event.relatedTarget;
        const thumb = getThumb();
        if (!element.contains(relatedTarget) && !thumb.contains(relatedTarget)) {
            hideThumb();
        }
    }, true);

    getThumb().addEventListener('pointerout', event => {
        if (dragging) return;

        const relatedTarget = event.relatedTarget;
        if (!activeElement?.contains(relatedTarget) && !getThumb().contains(relatedTarget)) {
            hideThumb();
        }
    });

    window.addEventListener('resize', () => scheduleShow(activeElement), { passive: true });
    document.addEventListener('click', () => window.setTimeout(validateActiveThumb, 0), true);
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape') {
            hideThumb();
            activeElement = null;
        }
    }, true);

    window.initOverlayScrollbars = function initOverlayScrollbars() {
        document.querySelectorAll(selector).forEach(element => {
            if (canUseOverlay(element) && element.matches(':hover')) {
                scheduleShow(element);
            }
        });
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', window.initOverlayScrollbars);
    } else {
        window.initOverlayScrollbars();
    }
})();
