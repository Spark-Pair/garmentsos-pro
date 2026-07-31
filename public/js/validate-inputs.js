(() => {
    'use strict';

    /*
     * Validation behavior:
     *
     * 1. Focus karte hi error show nahi hoga.
     * 2. Field se focus hatne ke baad validation hogi.
     * 3. Error aane ke baad typing/selecting par live revalidation hogi.
     * 4. Custom select ki value hidden .dbInput se read hogi.
     * 5. Select component ka dropdown/selection logic change nahi hoga.
     */

    const touchedValidationFields = new WeakSet();
    const pendingSelectBlurTimers = new WeakMap();

    function escapeCss(value) {
        const stringValue = String(value ?? '');

        if (
            window.CSS &&
            typeof window.CSS.escape === 'function'
        ) {
            return window.CSS.escape(stringValue);
        }

        return stringValue.replace(/["\\]/g, '\\$&');
    }

    function validationLabel(input) {
        const fieldName =
            input.dataset.errorFor ||
            input.name ||
            input.id ||
            'This field';

        /*
         * Custom select visible input nested x-input component mein hota hai.
         * Select ka actual label outer form-group mein hota hai.
         */
        const selectGroup = input.dataset.errorFor
            ? input
                .closest('.selectParent')
                ?.closest('.form-group')
            : null;

        const group =
            selectGroup ||
            input.closest('.form-group');

        const localLabel = group?.querySelector(
            ':scope > span label, :scope > label'
        );

        const externalLabel = input.id
            ? document.querySelector(
                `label[for="${escapeCss(input.id)}"]`
            )
            : null;

        const label =
            localLabel?.textContent ||
            externalLabel?.textContent ||
            fieldName.replace(/[_-]/g, ' ');

        return String(label)
            .replace(/\s*\(optional\)\s*/i, '')
            .replace(/\s*\*\s*$/, '')
            .trim() || 'This field';
    }

    function validationFieldName(input) {
        return (
            input.dataset.errorFor ||
            input.name ||
            input.id ||
            ''
        );
    }

    function validationRootGroup(input) {
        /*
         * Custom select ke error state ko outer select form-group par lagao.
         * Normal input ke liye nearest form-group use hoga.
         */
        if (input.dataset.errorFor) {
            return (
                input
                    .closest('.selectParent')
                    ?.closest('.form-group') ||
                input.closest('.form-group')
            );
        }

        return input.closest('.form-group');
    }

    function validationErrorElement(input) {
        const name = validationFieldName(input);

        if (!name) {
            return null;
        }

        /*
         * Input/select Blade component mein already available
         * error message element ko use karo.
         */
        const existingError = document.getElementById(
            `${name}-error`
        );

        if (existingError) {
            return existingError;
        }

        /*
         * Fallback: purane components mein error element na mile
         * to basic hidden message create kar do.
         */
        const group = validationRootGroup(input);

        if (!group) {
            return null;
        }

        const fallbackError =
            document.createElement('div');

        fallbackError.id = `${name}-error`;

        fallbackError.className =
            'field-error-msg text-[var(--border-error)] text-xs mt-1 hidden';

        fallbackError.setAttribute(
            'role',
            'alert'
        );

        group.appendChild(fallbackError);

        return fallbackError;
    }

    function validationErrorIcon(input) {
        const errorElement =
            validationErrorElement(input);

        if (!errorElement) {
            return null;
        }

        return errorElement
            .closest('.errorIconWrap')
            ?.querySelector('.errorIcon') || null;
    }

    function setValidationError(input, error) {
        const errorElement =
            validationErrorElement(input);

        const errorIcon =
            validationErrorIcon(input);

        const group =
            validationRootGroup(input);

        const hasError = Boolean(error);

        group?.classList.toggle(
            'has-field-error',
            hasError
        );

        input.classList.toggle(
            'border-[var(--border-error)]',
            hasError
        );

        if (hasError) {
            input.setAttribute(
                'aria-invalid',
                'true'
            );

            if (errorElement) {
                errorElement.textContent = error;

                errorElement.classList.remove(
                    'hidden'
                );

                input.setAttribute(
                    'aria-describedby',
                    errorElement.id
                );
            }

            if (errorIcon) {
                errorIcon.tabIndex = 0;

                errorIcon.setAttribute(
                    'aria-label',
                    error
                );
            }

            return false;
        }

        input.removeAttribute(
            'aria-invalid'
        );

        if (errorElement) {
            errorElement.classList.add(
                'hidden'
            );

            errorElement.textContent = '';

            if (
                input.getAttribute(
                    'aria-describedby'
                ) === errorElement.id
            ) {
                input.removeAttribute(
                    'aria-describedby'
                );
            }
        }

        if (errorIcon) {
            errorIcon.tabIndex = -1;

            errorIcon.setAttribute(
                'aria-label',
                'Show field error'
            );
        }

        return true;
    }

    function customSelectHiddenInput(input) {
        if (!input.dataset.errorFor) {
            return null;
        }

        const selectParent =
            input.closest('.selectParent');

        const errorFor =
            input.dataset.errorFor;

        const inputId =
            input.id || '';

        /*
         * Primary selector: hidden input name.
         */
        const hiddenByName =
            selectParent?.querySelector(
                `input.dbInput[name="${escapeCss(errorFor)}"]`
            );

        if (hiddenByName) {
            return hiddenByName;
        }

        /*
         * Secondary selector: data-for matching visible input id.
         */
        if (inputId) {
            const hiddenById =
                selectParent?.querySelector(
                    `input.dbInput[data-for="${escapeCss(inputId)}"]`
                );

            if (hiddenById) {
                return hiddenById;
            }
        }

        /*
         * Final fallback inside current form.
         */
        const hiddenInputs = Array.from(
            input
                .closest('form')
                ?.querySelectorAll(
                    'input[type="hidden"]'
                ) ||
            document.querySelectorAll(
                'input[type="hidden"]'
            )
        );

        return (
            hiddenInputs.find(
                field =>
                    field.name === errorFor
            ) ||
            null
        );
    }

    function validationValue(input) {
        const hiddenInput =
            customSelectHiddenInput(input);

        return hiddenInput
            ? hiddenInput.value
            : input.value;
    }

    function normalizeValidationRules(input) {
        const rules = String(
            input.dataset.validate || ''
        )
            .split('|')
            .map(rule => rule.trim())
            .filter(Boolean);

        if (
            input.required &&
            !rules.includes('required')
        ) {
            rules.unshift('required');
        }

        if (
            input.type === 'email' &&
            !rules.includes('email')
        ) {
            rules.push('email');
        }

        if (
            input.type === 'number' &&
            !rules.includes('numeric')
        ) {
            rules.push('numeric');
        }

        return rules;
    }

    function validateInput(input) {
        if (
            !input ||
            input.disabled ||
            input.readOnly ||
            input.type === 'hidden'
        ) {
            return true;
        }

        const rules =
            normalizeValidationRules(input);

        let value =
            String(input.value ?? '');

        let error = '';

        const label =
            validationLabel(input);

        const hasRequiredRule =
            rules.includes('required');

        const rawValidationValue =
            String(
                validationValue(input) ?? ''
            ).trim();

        /*
         * Optional empty field valid hai.
         */
        if (
            !hasRequiredRule &&
            rawValidationValue === '' &&
            value.trim() === ''
        ) {
            return setValidationError(
                input,
                ''
            );
        }

        for (const rule of rules) {
            if (error) {
                break;
            }

            /*
             * Required
             */
            if (
                rule === 'required' &&
                String(
                    validationValue(input) ?? ''
                ).trim() === ''
            ) {
                error =
                    `${label} is required.`;

                continue;
            }

            /*
             * Lowercase
             */
            if (rule === 'lowercase') {
                value = value.toLowerCase();
                continue;
            }

            /*
             * Alphanumeric
             */
            if (rule === 'alphanumeric') {
                if (/[^a-z0-9]/gi.test(value)) {
                    error =
                        `${label} can only contain letters and numbers.`;
                }

                value = value.replace(
                    /[^a-z0-9]/gi,
                    ''
                );

                continue;
            }

            /*
             * Letters and spaces
             */
            if (rule === 'letters') {
                value = value.replace(
                    /[^a-zA-Z ]/g,
                    ''
                );

                continue;
            }

            /*
             * Numeric
             */
            if (rule === 'numeric') {
                const allowNegative =
                    input.dataset
                        .allowNegativeAmount ===
                    'true';

                const allowedPattern =
                    allowNegative
                        ? /[^0-9.-]/g
                        : /[^0-9.]/g;

                const multipleMinusSigns =
                    allowNegative &&
                    (
                        value.match(/-/g) || []
                    ).length > 1;

                if (
                    allowedPattern.test(value) ||
                    multipleMinusSigns
                ) {
                    error =
                        `${label} must be a number.`;
                }

                value = value.replace(
                    allowedPattern,
                    ''
                );

                if (
                    allowNegative &&
                    value.includes('-')
                ) {
                    value =
                        (
                            value
                                .trim()
                                .startsWith('-')
                                ? '-'
                                : ''
                        ) +
                        value.replace(/-/g, '');
                }

                continue;
            }

            /*
             * Friendly:
             * letters, numbers, spaces, dot, dash and pipe.
             */
            if (rule === 'friendly') {
                if (
                    /[^a-zA-Z0-9 .\-|]/g.test(
                        value
                    )
                ) {
                    error =
                        `${label} can only contain letters, numbers, spaces, dots, dashes, and pipe.`;
                }

                value = value.replace(
                    /[^a-zA-Z0-9 .\-|]/g,
                    ''
                );

                continue;
            }

            /*
             * Phone:
             * one or more phone numbers separated by commas.
             */
            if (rule === 'phone') {
                value = value.replace(
                    /[^\d+,\-()\s]/g,
                    ''
                );

                value = value
                    .split(',')
                    .map(part =>
                        part
                            .replace(/\s+/g, ' ')
                            .trim()
                    )
                    .filter(
                        (
                            part,
                            index,
                            parts
                        ) =>
                            part !== '' ||
                            index ===
                                parts.length - 1
                    )
                    .join(', ');

                const phoneParts = value
                    .split(',')
                    .map(part => part.trim())
                    .filter(Boolean);

                const isValid =
                    phoneParts.length > 0 &&
                    phoneParts.every(part => {
                        const digits =
                            part.replace(/\D/g, '');

                        return (
                            digits.length >= 7 &&
                            /^\+?[0-9][0-9\s\-()]*$/.test(
                                part
                            )
                        );
                    });

                if (!isValid) {
                    error =
                        'Enter valid phone number(s), separated by commas.';
                }

                continue;
            }

            /*
             * Urdu
             */
            if (rule === 'urdu') {
                value = value.replace(
                    /[^\u0600-\u06FF\u06F0-\u06F90-9\s،۔!?؟]/g,
                    ''
                );

                if (
                    !/[\u0600-\u06FF\u06F0-\u06F90-9]/.test(
                        value
                    )
                ) {
                    error =
                        'Please enter in Urdu only.';
                }

                continue;
            }

            /*
             * Amount formatting
             */
            if (rule === 'amount') {
                const allowNegative =
                    input.dataset
                        .allowNegativeAmount ===
                    'true';

                const isNegative =
                    allowNegative &&
                    value
                        .trim()
                        .startsWith('-');

                value = value.replace(
                    /[^0-9.]/g,
                    ''
                );

                if (value) {
                    /*
                     * Only first decimal point allowed.
                     */
                    const firstDotIndex =
                        value.indexOf('.');

                    if (firstDotIndex !== -1) {
                        value =
                            value.slice(
                                0,
                                firstDotIndex + 1
                            ) +
                            value
                                .slice(
                                    firstDotIndex + 1
                                )
                                .replace(/\./g, '');
                    }

                    const parts =
                        value.split('.');

                    /*
                     * Integer formatting.
                     */
                    parts[0] = parts[0]
                        .replace(/,/g, '')
                        .replace(
                            /\B(?=(\d{3})+(?!\d))/g,
                            ','
                        );

                    /*
                     * Two decimal digits.
                     */
                    if (parts.length > 1) {
                        parts[1] =
                            parts[1].slice(0, 2);
                    }

                    value = parts.join('.');
                }

                if (isNegative && value) {
                    value = `-${value}`;
                }

                continue;
            }

            /*
             * Minimum string length
             */
            if (rule.startsWith('min:')) {
                const min =
                    Number.parseInt(
                        rule.split(':')[1],
                        10
                    );

                if (
                    Number.isFinite(min) &&
                    value.length < min
                ) {
                    error =
                        `${label} must be at least ${min} characters.`;
                }

                continue;
            }

            /*
             * Maximum numeric value
             */
            if (rule.startsWith('max:')) {
                const max =
                    Number.parseFloat(
                        rule.split(':')[1]
                    );

                const numericValue =
                    Number.parseFloat(
                        value.replace(/,/g, '')
                    );

                if (
                    Number.isFinite(max) &&
                    Number.isFinite(
                        numericValue
                    ) &&
                    numericValue > max
                ) {
                    error =
                        `${label} cannot be more than ${max}.`;

                    value = String(max);
                }

                continue;
            }

            /*
             * Email
             */
            if (
                rule === 'email' &&
                value.trim() !== '' &&
                !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(
                    value.trim()
                )
            ) {
                error =
                    'Enter a valid email address.';

                continue;
            }

            /*
             * Unique
             */
            if (rule.startsWith('unique:')) {
                const field =
                    rule.split(':')[1];

                const dataset =
                    window[`${field}s`];

                if (
                    Array.isArray(dataset) &&
                    dataset.includes(value)
                ) {
                    error =
                        `${field.replace(/[_-]/g, ' ')} already exists.`;
                }
            }
        }

        input.value = value;

        return setValidationError(
            input,
            error
        );
    }

    function shouldRealtimeValidate(input) {
        return Boolean(
            input &&
            input.matches?.(
                'input, select, textarea'
            ) &&
            input.type !== 'hidden' &&
            (
                input.required ||
                input.dataset.validate
            )
        );
    }

    function markValidationFieldTouched(input) {
        if (input) {
            touchedValidationFields.add(
                input
            );
        }
    }

    function shouldShowRealtimeValidation(input) {
        return (
            touchedValidationFields.has(
                input
            ) ||
            input?.getAttribute(
                'aria-invalid'
            ) === 'true'
        );
    }

    function shouldFormatAmountOnInput(input) {
        if (!input?.matches?.('input')) {
            return false;
        }

        return (
            String(
                input.dataset.validate || ''
            )
                .split('|')
                .includes('amount') ||
            input.getAttribute('type') ===
                'amount'
        );
    }

    function visibleValidationFieldForHiddenInput(
        input
    ) {
        if (
            !input?.classList?.contains(
                'dbInput'
            )
        ) {
            return null;
        }

        const scope =
            input.closest('.selectParent') ||
            input.closest('form') ||
            document;

        const forId =
            input.dataset.for || '';

        const name =
            input.name || '';

        if (name) {
            const byName =
                scope.querySelector(
                    `[data-error-for="${escapeCss(name)}"]`
                );

            if (byName) {
                return byName;
            }
        }

        if (forId) {
            return scope.querySelector(
                `#${escapeCss(forId)}`
            );
        }

        return null;
    }

    function isMovingInsideSameSelect(
        input,
        relatedTarget
    ) {
        const selectParent =
            input.closest('.selectParent');

        if (
            !selectParent ||
            !relatedTarget
        ) {
            return false;
        }

        return selectParent.contains(
            relatedTarget
        );
    }

    function scheduleCustomSelectValidation(input) {
        const oldTimer =
            pendingSelectBlurTimers.get(input);

        if (oldTimer) {
            window.clearTimeout(oldTimer);
        }

        const timer = window.setTimeout(
            () => {
                pendingSelectBlurTimers.delete(input);

                markValidationFieldTouched(input);
                validateInput(input);
            },
            120
        );

        pendingSelectBlurTimers.set(
            input,
            timer
        );
    }

    /*
     * Input event:
     * Error sirf touched/invalid field par revalidate hoga.
     */
    document.addEventListener(
        'input',
        event => {
            const input =
                event.target;

            /*
             * Amount formatting input ke waqt
             * chalti rahegi.
             */
            if (
                shouldFormatAmountOnInput(
                    input
                )
            ) {
                const wasTouched =
                    shouldShowRealtimeValidation(
                        input
                    );

                validateInput(input);

                /*
                 * Untouched amount field par
                 * formatting ke waqt error na dikhao.
                 */
                if (
                    !wasTouched &&
                    input.getAttribute(
                        'aria-invalid'
                    ) === 'true'
                ) {
                    setValidationError(
                        input,
                        ''
                    );
                }

                return;
            }

            if (
                shouldRealtimeValidate(input) &&
                shouldShowRealtimeValidation(
                    input
                )
            ) {
                validateInput(input);
            }
        }
    );

    /*
     * Change event:
     * Native fields aur custom select hidden dbInput.
     */
    document.addEventListener(
        'change',
        event => {
            const input =
                event.target;

            if (
                shouldRealtimeValidate(input) &&
                shouldShowRealtimeValidation(
                    input
                )
            ) {
                validateInput(input);
                return;
            }

            const visibleField =
                visibleValidationFieldForHiddenInput(input);

            if (
                !visibleField ||
                !shouldRealtimeValidate(visibleField)
            ) {
                return;
            }

            /*
            * Actual option selected hai to error clear.
            */
            if (
                String(input.value ?? '').trim() !== ''
            ) {
                markValidationFieldTouched(visibleField);

                setValidationError(
                    visibleField,
                    ''
                );

                return;
            }

            /*
            * Placeholder/default option ki value empty hai,
            * isliye required error show hoga.
            */
            markValidationFieldTouched(visibleField);
            validateInput(visibleField);

            /*
             * Valid option select hote hi
             * error clear ho jayega.
             */
            if (
                String(
                    input.value ?? ''
                ).trim() !== ''
            ) {
                setValidationError(
                    visibleField,
                    ''
                );

                return;
            }

            /*
             * Empty selection par error sirf
             * already touched select ko milega.
             */
            if (
                shouldShowRealtimeValidation(
                    visibleField
                )
            ) {
                validateInput(
                    visibleField
                );
            }
        }
    );

    /*
     * Focusout:
     * User field se bahar jaye tab validation.
     */
    document.addEventListener(
        'focusout',
        event => {
            const target = event.target;
            const relatedTarget = event.relatedTarget;

            /*
            * Check whether this focusout occurred anywhere inside
            * a custom select component.
            */
            const selectParent = target.closest?.('.selectParent');

            if (selectParent) {
                /*
                * Focus abhi isi select ke visible input, search input,
                * dropdown options ya kisi aur inner element par gaya hai.
                * Is situation mein validation mat chalao.
                */
                if (
                    relatedTarget &&
                    selectParent.contains(relatedTarget)
                ) {
                    return;
                }

                /*
                * Find the primary visible select input.
                * Dropdown search input par data-error-for nahi hota.
                */
                const visibleSelectInput =
                    selectParent.querySelector(
                        'input[data-error-for]'
                    );

                if (
                    visibleSelectInput &&
                    shouldRealtimeValidate(
                        visibleSelectInput
                    )
                ) {
                    /*
                    * Option selection onmousedown par hidden .dbInput
                    * update hota hai, isliye thora delay zaroori hai.
                    */
                    scheduleCustomSelectValidation(
                        visibleSelectInput
                    );
                }

                return;
            }

            /*
            * Normal input, textarea or native select.
            */
            if (!shouldRealtimeValidate(target)) {
                return;
            }

            markValidationFieldTouched(target);
            validateInput(target);
        },
        true
    );

    /*
     * Focus dobara field mein aaye to pending
     * custom select blur validation cancel karo.
     */
    document.addEventListener(
        'focusin',
        event => {
            const input =
                event.target;

            const pendingTimer =
                pendingSelectBlurTimers.get(
                    input
                );

            if (pendingTimer) {
                window.clearTimeout(
                    pendingTimer
                );

                pendingSelectBlurTimers.delete(
                    input
                );
            }
        }
    );

    /*
     * Complete form validation.
     */
    function validateAllInputs(
        root = document
    ) {
        let valid = true;
        let firstInvalid = null;

        root
            .querySelectorAll(
                'input, select, textarea'
            )
            .forEach(input => {
                if (
                    !shouldRealtimeValidate(
                        input
                    )
                ) {
                    return;
                }

                markValidationFieldTouched(
                    input
                );

                if (!validateInput(input)) {
                    valid = false;

                    firstInvalid ||=
                        input;
                }
            });

        if (!valid) {
            firstInvalid?.focus();
        }

        return valid;
    }

    /*
     * Existing application files ke liye
     * functions globally available rakho.
     */
    window.validationLabel =
        validationLabel;

    window.validationErrorElement =
        validationErrorElement;

    window.setValidationError =
        setValidationError;

    window.validationValue =
        validationValue;

    window.normalizeValidationRules =
        normalizeValidationRules;

    window.validateInput =
        validateInput;

    window.validateAllInputs =
        validateAllInputs;
})();