function getSelectScope(elem) {
    return elem.closest('form') || document;
}

function selectThisOption(optionLiElem, options = {}) {
    const shouldValidate = options.validate !== false;
    const forId = optionLiElem.dataset.for;
    const scope = getSelectScope(optionLiElem);
    const selectSearch = scope.querySelector(`#${forId}`);
    const dropdownSearch = optionLiElem.closest('.selectParent')?.querySelector('.dropDownParent input');
    const dbInput = scope.querySelector(`.dbInput[data-for="${forId}"]`);

    if (!selectSearch || !dbInput) return;

    selectSearch.value = optionLiElem.textContent.trim();
    if (dropdownSearch) {
        dropdownSearch.value = selectSearch.value;
    }
    dbInput.value = optionLiElem.dataset.value;

    const allOptions = scope.querySelectorAll(`.optionsDropdown li[data-for="${forId}"]`);
    allOptions.forEach(li => li.classList.remove('selected'));
    optionLiElem.classList.add('selected');

    const changeEvent = new Event('change', { bubbles: true });

    const onchangeAttr = dbInput.getAttribute('onchange') || '';
    const handlerName = onchangeAttr.split('(')[0].trim();

    function dispatchChangeWithRetry(retries = 6) {
        if (!handlerName || typeof window[handlerName] === 'function') {
            dbInput.dispatchEvent(changeEvent);
            return;
        }
        if (retries <= 0) {
            return;
        }
        setTimeout(() => dispatchChangeWithRetry(retries - 1), 0);
    }

    dispatchChangeWithRetry();

    if (!shouldValidate) {
        return;
    }

    selectSearch.dispatchEvent(new Event('input', { bubbles: true }));
    selectSearch.dispatchEvent(new Event('change', { bubbles: true }));
    if (
        typeof validateInput === 'function'
        && (typeof shouldShowRealtimeValidation !== 'function' || shouldShowRealtimeValidation(selectSearch))
    ) {
        validateInput(selectSearch);
    }
}

function searchSelect(selectSearchInput) {
    const inputValue = selectSearchInput.value.toLowerCase().trim();
    const forId = selectSearchInput.dataset.for;
    const scope = getSelectScope(selectSearchInput);
    const allOptions = scope.querySelectorAll(`.optionsDropdown li[data-for="${forId}"]`);

    const isDefaultSelection = inputValue.startsWith('-- select');

    allOptions.forEach((li) => {
        const optionText = li.textContent.toLowerCase().trim();

        if (optionText.startsWith('-- select')) {
            li.classList.remove('hidden');
            li.innerHTML = li.textContent;
            return;
        }

        if (isDefaultSelection) {
            li.classList.remove('hidden');
            li.innerHTML = li.textContent;
            return;
        }

        if (optionText.includes(inputValue) && inputValue.length > 0) {
            li.classList.remove('hidden');
            const originalText = li.textContent;
            const regex = new RegExp(`(${inputValue})`, 'ig');
            li.innerHTML = originalText.replace(regex, '<mark class="bg-yellow-200 text-black rounded">$1</mark>');
        } else if (optionText.includes(inputValue)) {
            li.classList.remove('hidden');
            li.innerHTML = li.textContent;
        } else {
            li.classList.add('hidden');
            li.innerHTML = li.textContent;
        }
    });

    const visibleOptions = Array.from(allOptions).filter(li => !li.classList.contains('hidden'));
    const bestMatch = findBestSelectSearchMatch(visibleOptions, inputValue);

    allOptions.forEach(li => li.classList.remove('selected'));
    if (bestMatch) {
        bestMatch.classList.add('selected');
        if (typeof bestMatch.scrollIntoView === 'function') {
            bestMatch.scrollIntoView({ block: 'nearest', inline: 'nearest' });
        }
    }
}

function findBestSelectSearchMatch(options, query) {
    if (!options.length) return null;

    const realOptions = options.filter(li => !li.textContent.trim().toLowerCase().startsWith('-- select'));
    if (!query) {
        return realOptions[0] || options[0];
    }

    const normalizedQuery = query.toLowerCase().trim();
    const scored = (realOptions.length ? realOptions : options)
        .map((li, index) => {
            const text = li.textContent.toLowerCase().trim();
            const startsAt = text.indexOf(normalizedQuery);
            const tokens = text.split(/[\s|,\-_/]+/).filter(Boolean);
            const tokenStarts = tokens.some(token => token.startsWith(normalizedQuery));

            let score = 1000 + index;
            if (text === normalizedQuery) {
                score = index;
            } else if (text.startsWith(normalizedQuery)) {
                score = 50 + index;
            } else if (tokenStarts) {
                score = 100 + index;
            } else if (startsAt >= 0) {
                score = 200 + startsAt + index;
            }

            return { li, score };
        })
        .sort((left, right) => left.score - right.score);

    return scored[0]?.li || null;
}

function validateSelectInput(selectSearchInput) {
    const inputValue = selectSearchInput.value.toLowerCase().trim();
    const forId = selectSearchInput.id;
    const scope = getSelectScope(selectSearchInput);
    const allOptions = scope.querySelectorAll(`.optionsDropdown li[data-for="${forId}"]`);

    let isValid = false;
    allOptions.forEach((li) => {
        const optionText = li.textContent.toLowerCase().trim();
        if (optionText === inputValue) {
            isValid = true;
        }
    });

    if (!isValid) {
        selectFirstOption(forId, scope);
    }
}

function selectFirstOption(forId, scope = document, options = {}) {
    const dbInput = scope.querySelector(`.dbInput[data-for="${forId}"]`);
    const currentValue = dbInput ? String(dbInput.value || '') : '';
    if (currentValue) {
        const matched = scope.querySelector(`.optionsDropdown li[data-for="${forId}"][data-value="${CSS.escape(currentValue)}"]`);
        if (matched) {
            selectThisOption(matched, options);
            return;
        }
    }

    const firstOption = scope.querySelector(`.optionsDropdown li[data-for="${forId}"]:not(.hidden)`);
    if (firstOption) {
        selectThisOption(firstOption, options);
    }
}

function selectClicked(input) {
    const searchInput = input.closest('.selectParent').querySelector('.dropDownParent input');
    searchInput.focus();
    searchInput.value = '';
    searchSelect(searchInput);

    const inputRect = input.getBoundingClientRect();
    const dropdown = input.closest(".selectParent").querySelector(".dropDownParent");

    dropdown.style.width = inputRect.width + "px";
    dropdown.style.top = (inputRect.top + inputRect.height) + "px";
    dropdown.style.left = inputRect.left + "px";
}

function selectKeyDown(event, input) {
    const dropdown = input.closest(".selectParent").querySelector(".optionsDropdown");
    const allOptions = dropdown.querySelectorAll("li");
    const options = Array.from(allOptions).filter(li => !li.classList.contains("hidden"));

    function scrollIntoViewIfNeeded(element) {
        if (element && typeof element.scrollIntoView === "function") {
            element.scrollIntoView({ block: "nearest", inline: "nearest" });
        }
    }

    if (event.key === "ArrowDown") {
        event.preventDefault();
        const selected = dropdown.querySelector("li.selected:not(.hidden)");
        let next = selected ? options[options.indexOf(selected) + 1] : options[0];
        if (next) {
            options.forEach(li => li.classList.remove("selected"));
            next.classList.add("selected");
            input.value = next.textContent.trim();
            scrollIntoViewIfNeeded(next);
        }
    } else if (event.key === "ArrowUp") {
        event.preventDefault();
        const selected = dropdown.querySelector("li.selected:not(.hidden)");
        let prev = selected ? options[options.indexOf(selected) - 1] : options[options.length - 1];
        if (prev) {
            options.forEach(li => li.classList.remove("selected"));
            prev.classList.add("selected");
            input.value = prev.textContent.trim();
            scrollIntoViewIfNeeded(prev);
        }
    } else if (event.key === "Enter") {
        event.preventDefault();
        const selected = dropdown.querySelector("li.selected:not(.hidden)");
        if (selected) {
            selectThisOption(selected);
            input.blur();
            if (typeof window.focusNextFormField === 'function') {
                window.focusNextFormField(input);
            }
        }
    } else if (event.key === "Escape") {
        input.blur();
    }
}

function bootSelectDefaults() {
    document.querySelectorAll(".selectParent .dbInput")
        .forEach(dbInput => selectFirstOption(dbInput.dataset.for, getSelectScope(dbInput), { validate: false }));
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootSelectDefaults);
} else {
    bootSelectDefaults();
}
