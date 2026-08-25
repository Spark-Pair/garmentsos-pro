function initArticlesCreate() {
    let titleDom = document.getElementById('title');
    let rateDom = document.getElementById('rate');
    let calcBottom = document.querySelector('#calc-bottom');
    let ratesArrayDom = document.getElementById('rates_array');
    let rateCount = 0;

    let totalRate = 0.00;
    let ratesArray = [];

    function addRate() {
        let title = titleDom.value;
        let rate = rateDom.value;

        if (title && rate && ratesArray.filter(rate => rate.title === title).length === 0) {
            let rateList = document.querySelector('#rate-list');

            if (rateCount === 0) {
                rateList.innerHTML = '';
            }

            rateCount++;
            let rateRow = document.createElement('div');
            rateRow.classList.add('flex', 'justify-between', 'items-center', 'bg-[var(--h-bg-color)]', 'rounded-lg', 'py-2', 'px-4');
            rateRow.innerHTML = `
                <div class="grow ml-5">${title}</div>
                <div class="w-1/4">${formatNumbersWithDigits(rate, 2, 2)}</div>
                <div class="w-[10%] text-center">
                    <button onclick="deleteRate(this)" type="button" class="text-[var(--danger-color)] text-xs px-2 py-1 rounded-lg hover:text-[var(--h-danger-color)] transition-all duration-300 ease-in-out cursor-pointer">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            `;
            rateList.insertBefore(rateRow, rateList.firstChild);

            titleDom.value = '';
            rateDom.value = '';
            titleDom.focus();

            totalRate += parseFloat(rate);

            ratesArray.push({
                title: title,
                rate: rate
            });

            updateRates();
        }
    }

    window.deleteRate = function(element) {
        element.parentElement.parentElement.remove();
        rateCount--;
        if (rateCount === 0) {
            let rateList = document.querySelector('#rate-list');
            rateList.innerHTML = `
                <div class="text-center bg-[var(--h-bg-color)] rounded-lg py-2 px-4">No Rates Added</div>
            `;
        }

        titleDom.focus();

        let rate = parseFloat(element.parentElement.previousElementSibling.innerText);
        totalRate -= rate;

        let title = element.parentElement.previousElementSibling.previousElementSibling.innerText;
        ratesArray = ratesArray.filter(rate => rate.title !== title);

        updateRates();
    }

    function updateRates() {
        calcBottom.innerHTML = `
            <div class="total flex justify-between items-center border border-gray-600 rounded-lg py-2 px-4 w-full cursor-not-allowed">
                <div>Total - Rs.</div>
                <div class="text-right">${formatNumbersWithDigits(totalRate, 2, 2)}</div>
            </div>
            <div class="final flex justify-between items-center bg-[var(--h-bg-color)] border border-gray-600 rounded-lg py-2 px-4 w-full">
                <label for="sales_rate" class="text-nowrap grow">Sales Rate - Rs.</label>
                <input type="text" required name="sales_rate" id="sales_rate" value="${totalRate.toFixed(2)}"
                    class="text-right bg-transparent outline-none border-none w-[50%]" />
            </div>
        `;

        ratesArrayDom.value = JSON.stringify(ratesArray);
    }

    rateDom.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            addRate();
        }
    });

    const articles = window.__articlesData || [];
    const articleNoDom = document.getElementById('article_no');
    const articleNoError = document.getElementById('article_no-error');
    const dateDom = document.getElementById('date');
    const dateError = document.getElementById('date-error');
    const sizeDom = document.getElementById('size');
    const sizeError = document.getElementById('size-error');
    const seasonDom = document.getElementById('season');
    const seasonError = document.getElementById('season-error');
    const quantityDom = document.getElementById('quantity');
    const quantityError = document.getElementById('quantity-error');
    const extraPcsDom = document.getElementById('extra_pcs');
    const extraPcsError = document.getElementById('extra_pcs-error');

    function validateArticleNo() {
        let articleNoValue = parseFloat(articleNoDom.value);
        let existingArticle = articles.some(a => {
            const numericPart = String(a.article_no || '').split('|').pop();
            return parseInt(numericPart, 10) === articleNoValue;
        });

        if (!articleNoValue) {
            setValidationError(articleNoDom, 'Article No field is required.');
            return false;
        } else if (existingArticle) {
            setValidationError(articleNoDom, 'Article No already exists.');
            return false;
        } else {
            setValidationError(articleNoDom, '');
            return true;
        }
    }

    function validateDate() {
        if (dateDom.value === '') {
            setValidationError(dateDom, 'Date field is required.');
            return false;
        } else {
            setValidationError(dateDom, '');
            return true;
        }
    }

    function validateSize() {
        if (sizeDom.value === '') {
            setValidationError(sizeDom, 'Size field is required.');
            return false;
        } else {
            setValidationError(sizeDom, '');
            return true;
        }
    }

    function validateSeason() {
        if (seasonDom.value === '') {
            setValidationError(seasonDom, 'Season field is required.');
            return false;
        } else {
            setValidationError(seasonDom, '');
            return true;
        }
    }

    function validateQuantity() {
        if (quantityDom.value === '') {
            setValidationError(quantityDom, 'Quantity field is required.');
            return false;
        } else if (quantityDom.value < 0) {
            setValidationError(quantityDom, 'Quantity cannot be less than 0.');
            return false;
        } else {
            setValidationError(quantityDom, '');
            return true;
        }
    }

    function validateExtraPcs() {
        if (extraPcsDom.value === '') {
            setValidationError(extraPcsDom, 'Extra Pcs field is required.');
            return false;
        } else {
            setValidationError(extraPcsDom, '');
            return true;
        }
    }

    articleNoDom.addEventListener('input', validateArticleNo);
    dateDom.addEventListener('change', validateDate);
    sizeDom.addEventListener('input', validateSize);
    seasonDom.addEventListener('input', validateSeason);
    quantityDom.addEventListener('input', validateQuantity);
    extraPcsDom.addEventListener('input', validateExtraPcs);

    window.validateForNextStep = function() {
        let isValidArticleNo = validateArticleNo();
        let isValidDate = validateDate();
        let isValidSize = validateSize();
        let isValidSeason = validateSeason();
        let isValidQuantity = validateQuantity();
        let isValidExtraPcs = validateExtraPcs();

        let isValid = isValidArticleNo && isValidDate && isValidSize && isValidSeason && isValidQuantity && isValidExtraPcs;

        if (!isValid) {
            if (typeof showMessageBox === 'function') {
                showMessageBox('error', 'Invalid details, please correct them.');
            }
        } else {
            isValid = true;
        }

        return isValid;
    }
}

function bootArticlesCreate() {
    if (window.__articlesData) {
        initArticlesCreate();
    }
}

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", bootArticlesCreate);
} else {
    bootArticlesCreate();
}
