function focusPreviousModalSearch() {
    requestAnimationFrame(() => {
        requestAnimationFrame(() => {
            setTimeout(() => {
                const wrappers = Array.from(
                    document.querySelectorAll('div[id$="-wrapper"]')
                );

                const activeWrapper = wrappers[wrappers.length - 1];

                if (!activeWrapper) return;

                const searchInput = activeWrapper.querySelector(
                    '#basicSearch input'
                );

                if (searchInput) {
                    searchInput.focus();
                    searchInput.select?.();
                }
            }, 50);
        });
    });
}

function closeModal(modalId, animate = 'animate') {
    const modal = document.getElementById(`${modalId}-wrapper`);
    if (!modal) return;

    const modalForm = modal.querySelector('form');

    const finishClose = () => {
        modal.remove();

        // Previous/active modal ke search input par focus
        focusPreviousModalSearch();
    };

    if (!modalForm) {
        finishClose();
        return;
    }

    if (animate === 'animate') {
        modalForm.classList.add('scale-out');

        modalForm.addEventListener('animationend', () => {
            modal.classList.add('fade-out');

            modal.addEventListener('animationend', () => {
                finishClose();
            }, { once: true });
        }, { once: true });
    } else {
        finishClose();
    }

    document.removeEventListener('mousedown', closeOnClickOutside);
    document.removeEventListener('keydown', escToClose);
    document.removeEventListener('keydown', enterToSubmit);
}