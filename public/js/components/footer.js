(() => {
    function initFooter() {
        const config = window.__footer || {};

        function removeFooterFromTabOrder() {
            document.querySelectorAll('footer a, footer button, footer [role="button"], footer [tabindex]')
                .forEach(element => {
                    element.setAttribute('tabindex', '-1');
                });
        }

        removeFooterFromTabOrder();

        if (config.wizardEnabled) {
            const yearDom = document.getElementById('year');
            if (yearDom) yearDom.textContent = new Date().getFullYear();

            let currentStep = 1;
            const progressIndicators = document.querySelector('.progress-indicators');
            const noOfSteps = progressIndicators ? progressIndicators.children.length : 1;

            function nextStep(step) {
                if (typeof validateForNextStep === 'function' && !validateForNextStep()) {
                    return false;
                }

                const step1Doms = document.querySelectorAll(`.step${currentStep}`);
                const step2Doms = document.querySelectorAll(`.step${step + 1}`);

                if (currentStep === noOfSteps) {
                    return;
                }

                step1Doms.forEach(dom => dom.classList.add('hidden'));
                step2Doms.forEach(dom => dom.classList.remove('hidden'));

                document.getElementById(`step${step + 1}-indicator`).classList.remove('bg-[var(--h-bg-color)]');
                document
                    .getElementById(`step${step + 1}-indicator`)
                    .classList.remove('hover:bg-[var(--secondary-bg-color)]');
                document.getElementById(`step${step + 1}-indicator`).classList.add('bg-[var(--primary-color)]');
                document
                    .getElementById(`step${step + 1}-indicator`)
                    .classList.add('hover:bg-[var(--h-primary-color)]');
                if (currentStep <= step) {
                    document.getElementById(`step${currentStep}-indicator`).classList.remove('bg-[var(--primary-color)]');
                    document
                        .getElementById(`step${currentStep}-indicator`)
                        .classList.remove('hover:bg-[var(--h-primary-color)]');
                    document.getElementById(`step${currentStep}-indicator`).classList.add('bg-[var(--h-bg-color)]');
                    document
                        .getElementById(`step${currentStep}-indicator`)
                        .classList.add('hover:bg-[var(--secondary-bg-color)]');
                }
                document.getElementById('progress-bar').style.width = `${(step + 1) * (100 / noOfSteps)}%`;

                currentStep = step + 1;
                updateButtons();

                document.dispatchEvent(
                    new CustomEvent('wizard:step-changed', {
                        detail: {
                            step: currentStep
                        }
                    })
                );
            }

            function prevStep(step) {
                const step1Doms = document.querySelectorAll(`.step${step - 1}`);
                const step2Doms = document.querySelectorAll(`.step${currentStep}`);

                if (step <= 1) {
                    return;
                }

                step1Doms.forEach(dom => dom.classList.remove('hidden'));
                step2Doms.forEach(dom => dom.classList.add('hidden'));

                document.getElementById(`step${step - 1}-indicator`).classList.add('bg-[var(--primary-color)]');
                document
                    .getElementById(`step${step - 1}-indicator`)
                    .classList.add('hover:bg-[var(--h-primary-color)]');
                document.getElementById(`step${step - 1}-indicator`).classList.remove('bg-[var(--h-bg-color)]');
                document
                    .getElementById(`step${step - 1}-indicator`)
                    .classList.remove('hover:bg-[var(--secondary-bg-color)]');
                document.getElementById(`step${currentStep}-indicator`).classList.remove('bg-[var(--primary-color)]');
                document
                    .getElementById(`step${currentStep}-indicator`)
                    .classList.remove('hover:bg-[var(--h-primary-color)]');
                document.getElementById(`step${currentStep}-indicator`).classList.add('bg-[var(--h-bg-color)]');
                document
                    .getElementById(`step${currentStep}-indicator`)
                    .classList.add('hover:bg-[var(--secondary-bg-color)]');
                document.getElementById('progress-bar').style.width = `${(step - 1) * (100 / noOfSteps)}%`;
                currentStep = step - 1;
                updateButtons();

                document.dispatchEvent(
                    new CustomEvent('wizard:step-changed', {
                        detail: {
                            step: currentStep
                        }
                    })
                );
            }

            window.gotoStep = function gotoStep(step) {
                if (currentStep <= step) {
                    nextStep(step - 1);
                } else if (currentStep > step) {
                    prevStep(step + 1);
                }
            };

            function updateButtons() {
                const prevBtn = document.getElementById('prevBtn');
                if (prevBtn) prevBtn.disabled = currentStep === 1;
                document.getElementById('nextBtn')?.classList.toggle('hidden', currentStep === noOfSteps);
                document.getElementById('saveBtn')?.classList.toggle('hidden', currentStep !== noOfSteps);
                document.getElementById('printAndSaveBtn')?.classList.toggle('hidden', currentStep !== noOfSteps);
                document.getElementById('printBtn')?.classList.toggle('hidden', currentStep !== noOfSteps);
            }

            document.getElementById('nextBtn')?.addEventListener('click', () => nextStep(currentStep));
            document.getElementById('prevBtn')?.addEventListener('click', () => prevStep(currentStep));
            document.getElementById('saveBtn')?.addEventListener('click', e => {
                const form = document.getElementById('form');
                if (!form) return;

                if (typeof onSubmitFunction === 'function') {
                    const result = onSubmitFunction();

                    if (result instanceof Promise) {
                        e.preventDefault();
                        result.then(res => {
                            if (res === false) {
                                return;
                            }
                            form.requestSubmit();
                        });
                        return;
                    }

                    if (result === false) {
                        e.preventDefault();
                        return;
                    }
                }
                form.requestSubmit();
            });

            const saveBtn = document.getElementById('saveBtn');
            document.addEventListener('keydown', e => {
                if (e.ctrlKey && e.key === 'ArrowRight') {
                    nextStep(currentStep);
                } else if (e.ctrlKey && e.key === 'ArrowLeft') {
                    prevStep(currentStep);
                } else if (e.ctrlKey && e.key === 'Enter') {
                    if (saveBtn && !saveBtn.classList.contains('hidden')) {
                        saveBtn.click();
                    }
                }
            });
        }

        if (config.enableEscapeClose) {
            document.addEventListener('keydown', e => {
                if (e.key == 'Escape') {
                    closeAllDropdowns();
                }
            });
        }

        if (config.isLogin) {
            const html = document.documentElement;
            const themeIcon = document.querySelector('#themeToggle i');
            window.changeTheme = function changeTheme() {
                const currentTheme = html.getAttribute('data-theme');
                const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
                html.setAttribute('data-theme', newTheme);

                themeIcon?.classList.toggle('fa-sun');
                themeIcon?.classList.toggle('fa-moon');
            };

            function setTheme(theme) {
                document.documentElement.setAttribute('data-theme', theme);
                document.cookie = `theme=${theme} path=/; max-age=31536000`;
            }

            const userTheme =
                localStorage.getItem('theme') ||
                (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
            setTheme(userTheme);
        }
    }

    window.initFooter = initFooter;

    function boot() {
        if (window.__footer) {
            initFooter();
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
